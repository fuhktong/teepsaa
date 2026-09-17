<?php
// Deleting the vendor account from the app. The website's equivalent is
// settings-vendor/delete-action.php, reached from the danger tab.
//
// This is its own file rather than one more action on settings-action.php
// because it is the only call in the app that ends the account, and a file that
// can do that should not also be the file that saves a phone number.
//
// "Delete" is a soft delete, exactly as on the website: the vendor row stays,
// deleted_at is stamped, and the businesses are closed with it. Completed orders,
// payouts and reviews are accounting records — a marketplace cannot drop its own
// history of money that moved. Registering again with the same email revives the
// same row, which is the website's behaviour and the reason the row is kept.
//
// Three deliberate differences from the website:
//
//  1. The password check is rate limited, for the same reason the password
//     change is: behind a login form and a session cookie an unlimited guess
//     loop is survivable, on an open endpoint it is a guessing machine.
//  2. It refuses unless the body says confirm: true. The app asks twice before
//     it sends anything, so this costs the vendor nothing; it means a call that
//     arrives with only a password — a wrong URL in some later screen, a
//     replayed request — cannot erase a shop by accident.
//  3. Being blocked by open orders comes back with the number of them. The
//     website says only "you have open orders", which leaves the vendor to
//     count them on the orders page.
//
// Every token for this vendor is deleted, so every phone signed in to the
// account is signed out. api_require_vendor() would refuse them anyway the
// moment deleted_at is set — it joins on deleted_at IS NULL — but leaving dead
// rows behind would make any future "your devices" screen lie.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/rate-limit.php';
require __DIR__ . '/../../config/notify.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body     = api_body();
$password = (string)($body['password'] ?? '');
$confirm  = $body['confirm'] ?? false;

if ($confirm !== true) {
    api_json(['error' => 'confirm_required'], 400);
}

// Keyed to the account, with its own budget. Not 'login': a vendor who mistypes
// this password should not also be locked out of signing in.
check_rate_limit($pdo, 'account_delete', 'vendor:' . $userId);

$stmt = $pdo->prepare('SELECT name, email, password FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) api_json(['error' => 'not_found'], 404);

if ($password === '') {
    api_json(['error' => 'invalid', 'fields' => ['password' => 'Enter your password.']], 422);
}

// Checked before the open-orders count so a wrong password does not reveal how
// much of the shop is still running.
if (!password_verify($password, $row['password'])) {
    record_failed_attempt($pdo, 'account_delete', 'vendor:' . $userId);
    api_json(['error' => 'invalid', 'fields' => ['password' => 'That is not your password.']], 422);
}

// An order a buyer has paid for and not received is the one thing that cannot
// survive the shop closing, so it blocks the delete. 'delivered' counts as open:
// the buyer has the parcel but the order is not settled yet.
$stmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE b.user_id = ?
       AND b.deleted_at IS NULL
       AND o.status NOT IN ('completed', 'cancelled', 'refunded')
");
$stmt->execute([$userId]);
$openOrders = (int)$stmt->fetchColumn();

if ($openOrders > 0) {
    api_json(['error' => 'open_orders', 'count' => $openOrders], 409);
}

$pdo->prepare('UPDATE vendors SET deleted_at = NOW() WHERE id = ?')
    ->execute([$userId]);

// approved = -1 is how the website marks a business closed by its own owner, as
// opposed to one awaiting approval (0) or live (1).
$pdo->prepare('UPDATE businesses SET deleted_at = NOW(), approved = -1 WHERE user_id = ? AND deleted_at IS NULL')
    ->execute([$userId]);

$stmt = $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ? AND role = ?');
$stmt->execute([$userId, 'vendor']);
$signedOut = $stmt->rowCount();

[$subj, $html] = render_email_template($pdo, 'account_deleted', [
    'name' => htmlspecialchars($row['name']),
]);
if ($html !== '') send_email($row['email'], $subj, $html);

api_json(['ok' => true, 'signed_out' => $signedOut]);
