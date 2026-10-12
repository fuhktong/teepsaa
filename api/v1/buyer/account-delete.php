<?php
// Deleting the buyer account from the app — settings-buyer/delete-action.php.
// Both app stores require that an account made in an app can be deleted in it.
//
// The same erase as the website (config/account-erase.php): the personal
// details go and the orders and reviews stay, because they are records of money
// that moved.
//
// Refused while an order is still open, as on the website: the shop reads the
// delivery address from the account until the order is settled. The reply
// carries a `message` in the buyer's language, which the app shows as it is.
// The same two additions as the vendor version:
//
//  1. The password check is rate limited.
//  2. It refuses unless the body says confirm: true, so a stray request carrying
//     only a password cannot end an account.
//
// Every token for the buyer is deleted, so every phone is signed out.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';
require __DIR__ . '/../../../config/notify.php';
require __DIR__ . '/../../../config/account-erase.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body     = api_body();
$password = (string)($body['password'] ?? '');

if (($body['confirm'] ?? false) !== true) {
    api_json(['error' => 'confirm_required'], 400);
}

check_rate_limit($pdo, 'account_delete', 'buyer:' . $userId);

$stmt = $pdo->prepare('SELECT name, email, password FROM buyers WHERE id = ?');
$stmt->execute([$userId]);
$row = $stmt->fetch();
if (!$row) api_json(['error' => 'not_found'], 404);

if ($password === '') {
    api_json(['error' => 'invalid', 'fields' => ['password' => 'Enter your password.']], 422);
}

if (!password_verify($password, $row['password'])) {
    record_failed_attempt($pdo, 'account_delete', 'buyer:' . $userId);
    api_json(['error' => 'invalid', 'fields' => ['password' => 'That is not your password.']], 422);
}

$openOrders = account_open_orders_buyer($pdo, $userId);
if ($openOrders > 0) {
    $t = require __DIR__ . '/../../../lang/' . ($buyer['lang'] === 'en' ? 'en' : 'km') . '.php';
    api_json([
        'error'   => 'open_orders',
        'count'   => $openOrders,
        'message' => sprintf($t['delete_open_orders_buyer'], $openOrders),
    ], 409);
}

$stmt = $pdo->prepare('SELECT COUNT(*) FROM api_tokens WHERE user_id = ? AND role = ?');
$stmt->execute([$userId, 'buyer']);
$signedOut = (int)$stmt->fetchColumn();

account_erase_buyer($pdo, $userId);

[$subj, $html] = render_email_template($pdo, 'account_deleted', [
    'name' => htmlspecialchars($row['name']),
]);
if ($html !== '') send_email($row['email'], $subj, $html);

api_json(['ok' => true, 'signed_out' => $signedOut]);
