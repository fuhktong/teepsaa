<?php
// POST /api/v1/product-action.php   {"id": 12, "action": "hide"}
//
// The four things products/index.php lets a vendor do to a product it already
// has: show, hide, archive, unarchive, delete. One file rather than four
// because all five share the same ownership check and differ by one statement —
// the same reason notifications-read.php handles both of its cases.
//
// Everything the website's toggle.php / archive.php / unarchive.php /
// delete.php check is repeated here, because there is no browser and no form in
// front of this:
//
//   ownership   business_id must be in this vendor's SELLABLE businesses —
//               approved and not suspended. A token for the wrong vendor, or a
//               suspended one, changes nothing and is told not_found.
//   archived    show/hide carry `AND archived = 0`, in the UPDATE itself. The
//               website has the same guard, and the reason is that an archived
//               row that is also active would be for sale while hidden from
//               its owner.
//   delete      the order_items / cart_items / photo-file sequence below is
//               copied from delete.php, including the order of operations.
//
// CSRF has no equivalent here on purpose — there is no cookie to ride on. The
// reasoning is in config/api.php.
//
// show/hide take an explicit target instead of the website's `active = 1 -
// active` flip. A flip needs the caller and the row to agree on the current
// state, and two taps arriving together would cancel each other out and report
// success twice. Saying which state is wanted cannot do that.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/upload.php';   // image_delete_derivatives()

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body      = api_body();
$productId = (int)($body['id'] ?? 0);
$action    = (string)($body['action'] ?? '');

if ($productId <= 0) api_json(['error' => 'missing_id'], 400);

$allowed = ['show', 'hide', 'archive', 'unarchive', 'delete'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

// Sellable businesses only — see the ownership note above.
$bizStmt = $pdo->prepare('
    SELECT id FROM businesses
     WHERE user_id = ? AND approved = 1 AND suspended = 0
');
$bizStmt->execute([$userId]);
$bizIds = array_map('intval', $bizStmt->fetchAll(PDO::FETCH_COLUMN));

if (empty($bizIds)) api_json(['error' => 'not_found'], 404);

$ph = implode(',', array_fill(0, count($bizIds), '?'));

// Read first so "no such product" can be told apart from "already in that
// state". The first is worth showing; the second is a vendor tapping twice.
$look = $pdo->prepare("
    SELECT id, active, archived FROM products
     WHERE id = ? AND business_id IN ($ph)
");
$look->execute(array_merge([$productId], $bizIds));
$product = $look->fetch();

if (!$product) api_json(['error' => 'not_found'], 404);

if ($action === 'delete') {
    // Read the filenames before the rows go, but only unlink once the database
    // side has committed — a failed DELETE would otherwise leave a live product
    // pointing at photos that no longer exist on disk. Same order as
    // products/delete.php.
    $photos = $pdo->prepare('SELECT filename FROM product_photos WHERE product_id = ?');
    $photos->execute([$productId]);
    $filenames = $photos->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        // Nulled, not deleted: an order's history must survive the product
        // being removed, or past orders lose what was bought.
        $pdo->prepare('UPDATE order_items SET product_id = NULL WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('DELETE FROM cart_items WHERE product_id = ?')->execute([$productId]);
        // product_photos rows go with it by cascade.
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Past the commit the product is gone, so a failure here must not be
    // reported as a failed delete — it would send the vendor back to a row that
    // no longer exists. Orphaned files are a tidiness problem, not a data one.
    try {
        foreach ($filenames as $filename) {
            $path = __DIR__ . '/../../uploads/' . $filename;
            if (file_exists($path)) @unlink($path);
            image_delete_derivatives($filename);
        }
    } catch (Throwable $e) {
        error_log('product-action: photo cleanup failed for product ' . $productId . ': ' . $e->getMessage());
    }

    api_json(['ok' => true, 'deleted' => true]);
}

// The remaining four are one UPDATE each. The WHERE clause carries the guard
// rather than an if above it, so two requests arriving together cannot both
// win — matching how order-dispatch.php puts the status check in the write.
if ($action === 'show' || $action === 'hide') {
    // An archived product has no active state to change — unarchive it first.
    // Checked here as well as in the WHERE below, so the reply says which of
    // the two reasons stopped it.
    if ((int)$product['archived'] === 1) {
        api_json(['error' => 'archived', 'archived' => true], 409);
    }

    $active = $action === 'show' ? 1 : 0;
    $upd = $pdo->prepare("
        UPDATE products SET active = $active
         WHERE id = ? AND archived = 0 AND business_id IN ($ph)
    ");
    $upd->execute(array_merge([$productId], $bizIds));

    // MySQL reports zero changed rows both for a product that was already in
    // this state and for one archived between the read above and this write.
    // The first is a vendor tapping twice and is fine; the second is not, so
    // only the state the caller did NOT already have is treated as a failure.
    if ($upd->rowCount() === 0 && (int)$product['active'] !== $active) {
        api_json(['error' => 'archived', 'archived' => true], 409);
    }
    $newActive   = $active === 1;
    $newArchived = false;
} elseif ($action === 'archive') {
    // active = 0 alongside it: archiving something that is still for sale must
    // take it off sale in the same statement, never in a second one that might
    // not run.
    $pdo->prepare("UPDATE products SET archived = 1, active = 0
                    WHERE id = ? AND business_id IN ($ph)")
        ->execute(array_merge([$productId], $bizIds));
    $newActive   = false;
    $newArchived = true;
} else {
    // Unarchiving deliberately leaves it inactive — the website does the same.
    // Coming back out of the archive should not put a product on sale before
    // its owner has looked at it.
    $pdo->prepare("UPDATE products SET archived = 0, active = 0
                    WHERE id = ? AND business_id IN ($ph)")
        ->execute(array_merge([$productId], $bizIds));
    $newActive   = false;
    $newArchived = false;
}

// The new state is returned so the app can redraw the row without a second
// call, and so it can never disagree with the database about what just changed.
api_json([
    'ok'       => true,
    'id'       => $productId,
    'active'   => $newActive,
    'archived' => $newArchived,
]);
