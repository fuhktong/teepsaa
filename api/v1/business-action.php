<?php
// Everything the app's Business screen can change: the shop's details, its
// categories, the storefront layout, the address, the bank account name, and
// sending a rejected shop back for review.
//
// The website splits these across four action files that each redirect back to
// the page with a sentence in the session. An app has no page to redirect to,
// so this answers with JSON and, when something is wrong, says which field.
//
// Five deliberate differences from the website:
//
//  1. Errors come back keyed by field, so the app can put the message under the
//     box that caused it instead of one line at the top.
//  2. An address saved from the phone leaves the map pin alone. The website's
//     form always posts lat/lng, so a save there with an empty map wipes the
//     pin. The app has no map, so if it behaved the same way every address
//     edit from a phone would quietly delete a pin dropped on the website.
//  3. Khan and sangkat are checked against config/phnom-penh-locations.php, and
//     a sangkat has to belong to the khan next to it. The website saves
//     whatever string arrives.
//  4. The joined category list is measured against the 100 characters the
//     column actually holds, and refused if it is over. The website lets MySQL
//     truncate it, which loses the last category without saying so.
//  5. Changing the bank account name to the same name it already was does
//     nothing at all — no email, no audit row, and no 24-hour payout hold. The
//     website fires all three on every save of that form.
//
// Photos are not here. Uploading a banner or a QR image from the phone is a
// later chapter; both can still be looked at, and the account name beside the
// QR can still be changed.
require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/notify.php';
// audit.php pulls in admin-auth.php, whose only include-time action is guarded
// on an active session inside the admin area. There is neither here, so it is
// inert — and audit_log() below is handed an explicit actor, so it never looks
// for an admin id.
require __DIR__ . '/../../config/audit.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body   = api_body();
$action = trim((string)($body['action'] ?? ''));

$allowed = ['details', 'categories', 'address', 'storefront', 'bank_name', 'resubmit'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

// Every action works on the one shop this vendor owns. Looked up the same way
// business.php looks it up, so the app can never edit a shop it was not shown.
$stmt = $pdo->prepare('
    SELECT id, approved, suspended
      FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC
     LIMIT 1
');
$stmt->execute([$userId]);
$business = $stmt->fetch();

if (!$business) {
    api_json(['error' => 'no_business'], 404);
}
$businessId = (int)$business['id'];

$fields = [];

/** Length in characters, not bytes — Khmer would fail a strlen check. */
function biz_len(string $s): int {
    return mb_strlen($s);
}

// ── Details ──────────────────────────────────────────────────────────
if ($action === 'details') {
    $name   = trim((string)($body['name'] ?? ''));
    $nameKm = trim((string)($body['name_km'] ?? ''));
    $desc   = trim((string)($body['description'] ?? ''));
    $descKm = trim((string)($body['description_km'] ?? ''));

    if ($name === '') {
        $fields['name'] = 'Enter a business name.';
    } elseif (biz_len($name) > 255) {
        $fields['name'] = 'That name is too long. Keep it under 255 characters.';
    }
    if (biz_len($nameKm) > 255) {
        $fields['name_km'] = 'That name is too long. Keep it under 255 characters.';
    }
    // Refused rather than cut short. The website trims at 160 without saying
    // so, and the vendor only finds out when the end of their sentence is
    // missing from the storefront.
    if (biz_len($desc) > 160) {
        $fields['description'] = 'That is ' . biz_len($desc) . ' characters. The limit is 160.';
    }
    if (biz_len($descKm) > 160) {
        $fields['description_km'] = 'That is ' . biz_len($descKm) . ' characters. The limit is 160.';
    }

    if ($fields) {
        api_json(['error' => 'invalid', 'fields' => $fields], 422);
    }

    $stmt = $pdo->prepare('
        UPDATE businesses
           SET name = ?, name_km = ?, description = ?, description_km = ?
         WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$name, $nameKm ?: null, $desc, $descKm ?: null, $businessId, $userId]);

    api_json(['ok' => true]);
}

// ── Categories ───────────────────────────────────────────────────────
if ($action === 'categories') {
    // The app sends a list; a comma-separated string is accepted too so the
    // shape the column stores is never a surprise.
    $raw = $body['categories'] ?? [];
    if (is_string($raw)) $raw = explode(',', $raw);
    if (!is_array($raw)) $raw = [];
    $raw = array_filter(array_map(fn($c) => trim((string)$c), $raw));

    $known = $pdo->query('SELECT name FROM categories')->fetchAll(PDO::FETCH_COLUMN);
    $safe  = array_values(array_unique(array_filter($raw, fn($c) => in_array($c, $known, true))));

    $joined = implode(', ', $safe);
    if (biz_len($joined) > 100) {
        $fields['categories'] = 'That is too many categories to store. Remove one and save again.';
        api_json(['error' => 'invalid', 'fields' => $fields], 422);
    }

    $pdo->prepare('UPDATE businesses SET category = ? WHERE id = ? AND user_id = ?')
        ->execute([$joined, $businessId, $userId]);

    api_json(['ok' => true, 'saved' => $safe]);
}

// ── Address ──────────────────────────────────────────────────────────
if ($action === 'address') {
    $locations = require __DIR__ . '/../../config/phnom-penh-locations.php';
    $cities    = require __DIR__ . '/../../config/cities.php';

    $house   = trim((string)($body['house_number'] ?? ''));
    $street  = trim((string)($body['street'] ?? ''));
    $notes   = trim((string)($body['notes'] ?? ''));
    $khan    = trim((string)($body['khan'] ?? ''));
    $sangkat = trim((string)($body['sangkat'] ?? ''));
    $city    = trim((string)($body['city'] ?? ''));

    if ($khan !== '' && !isset($locations[$khan])) {
        $fields['khan'] = 'Pick a khan from the list.';
    }
    if ($sangkat !== '') {
        if ($khan === '') {
            $fields['sangkat'] = 'Pick the khan first.';
        } elseif (isset($locations[$khan]) && !in_array($sangkat, $locations[$khan], true)) {
            // The website would save this pair happily and the delivery
            // calculator would then be working from an address that does not
            // exist.
            $fields['sangkat'] = 'That sangkat is not in ' . $khan . '.';
        }
    }
    if (biz_len($street) > 255)  $fields['street'] = 'That is too long for one line.';
    if (biz_len($house) > 255)   $fields['house_number'] = 'That is too long for one line.';
    if (biz_len($notes) > 255)   $fields['notes'] = 'That is too long for one line.';

    if ($fields) {
        api_json(['error' => 'invalid', 'fields' => $fields], 422);
    }

    // Anything but a city teepsaa delivers in becomes the one it does.
    $city = in_array($city, $cities, true) ? $city : ($cities[0] ?? null);

    // lat and lng are not in this UPDATE on purpose — see note 2 at the top.
    $stmt = $pdo->prepare('
        UPDATE businesses
           SET house_number = ?, address = ?, address_notes = ?,
               khan = ?, sangkat = ?, city = ?
         WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([
        $house   ?: null,
        $street  ?: null,
        $notes   ?: null,
        $khan    ?: null,
        $sangkat ?: null,
        $city,
        $businessId,
        $userId,
    ]);

    api_json(['ok' => true]);
}

// ── Storefront layout ────────────────────────────────────────────────
if ($action === 'storefront') {
    $stmt = $pdo->prepare('SELECT id FROM products WHERE business_id = ? AND active = 1 AND archived = 0');
    $stmt->execute([$businessId]);
    $owned = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));

    // A product this shop does not own, or one that is hidden or archived,
    // silently becomes "none" rather than an error — the same thing the
    // website does with a tampered form.
    $featured = (int)($body['featured'] ?? 0);
    if (!isset($owned[$featured])) $featured = 0;

    $slots = [];
    foreach ((array)($body['slots'] ?? []) as $pid) {
        $pid = (int)$pid;
        if ($pid && $pid !== $featured && isset($owned[$pid]) && !in_array($pid, $slots, true)) {
            $slots[] = $pid;
        }
    }

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE products SET is_featured = 0, storefront_order = NULL WHERE business_id = ?')
        ->execute([$businessId]);
    if ($featured) {
        $pdo->prepare('UPDATE products SET is_featured = 1 WHERE id = ? AND business_id = ?')
            ->execute([$featured, $businessId]);
    }
    $pos = 1;
    $upd = $pdo->prepare('UPDATE products SET storefront_order = ? WHERE id = ? AND business_id = ?');
    foreach ($slots as $pid) {
        $upd->execute([$pos++, $pid, $businessId]);
    }
    $pdo->commit();

    api_json(['ok' => true, 'featured' => $featured, 'slots' => $slots]);
}

// ── Bank account name ────────────────────────────────────────────────
if ($action === 'bank_name') {
    $accountName = trim((string)($body['account_name'] ?? ''));

    if ($accountName === '') {
        $fields['account_name'] = 'Enter the name on the bank account.';
    } elseif (biz_len($accountName) > 100) {
        $fields['account_name'] = 'That is too long. Keep it under 100 characters.';
    }
    if ($fields) {
        api_json(['error' => 'invalid', 'fields' => $fields], 422);
    }

    $stmt = $pdo->prepare('SELECT aba_qr, aba_account_name FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    $current = $stmt->fetch();

    // The name on its own is not a payout destination. Without a QR there is
    // nowhere for the money to go, and the app cannot upload one yet.
    if (empty($current['aba_qr'])) {
        api_json(['error' => 'no_qr'], 409);
    }

    // Saving the name it already had is not a change, so nothing is held and
    // nobody is emailed.
    if ((string)$current['aba_account_name'] === $accountName) {
        api_json(['ok' => true, 'unchanged' => true]);
    }

    $pdo->prepare('UPDATE vendors SET aba_account_name = ?, aba_changed_at = NOW() WHERE id = ?')
        ->execute([$accountName, $userId]);

    // The same warning the website sends, for the same reason: a hijacked
    // account must not be able to redirect payouts quietly. The real vendor
    // gets an email even when the change was not theirs, and payouts are held
    // for 24 hours. A mail failure must never undo the save.
    audit_log($pdo, 'vendor.bank_change', 'vendor', $userId, [
        'changed'      => 'account name',
        'account_name' => $accountName,
        'source'       => 'app',
    ], ['id' => null, 'label' => 'vendor#' . $userId]);

    notify($pdo, 'vendor', $userId, 'bank_changed',
        'Your payout bank details were changed. Payouts are held for 24 hours.',
        '/business-vendor/');

    try {
        $stmt = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
        $stmt->execute([$userId]);
        $who = $stmt->fetch();
        if ($who && $who['email']) {
            [$subj, $html] = render_email_template($pdo, 'vendor_bank_changed', [
                'name'         => htmlspecialchars($who['name'] ?? ''),
                'account_name' => htmlspecialchars($accountName),
                'changed_at'   => date('M j, Y g:ia'),
                'cta_url'      => 'https://teepsaa.com/contact/',
            ]);
            if ($html !== '') send_email($who['email'], $subj, $html);
        }
    } catch (Throwable $e) {
        error_log('[api bank_name] notice failed for vendor ' . $userId . ': ' . $e->getMessage());
    }

    api_json(['ok' => true, 'held' => true]);
}

// ── Resubmit for review ──────────────────────────────────────────────
if ($action === 'resubmit') {
    // Only a rejected shop may go back in the queue. approved = 0 is already
    // waiting, and approved = 1 must never be pulled off the marketplace by a
    // stray tap.
    $stmt = $pdo->prepare('
        UPDATE businesses
           SET approved = 0, rejection_reason = NULL
         WHERE id = ? AND user_id = ? AND deleted_at IS NULL AND approved = -1
    ');
    $stmt->execute([$businessId, $userId]);

    if ($stmt->rowCount() === 0) {
        api_json(['error' => 'not_rejected'], 409);
    }

    api_json(['ok' => true]);
}
