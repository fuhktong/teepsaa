<?php
// Deleting the buyer account from the app — settings-buyer/delete-action.php.
// Both app stores require that an account made in an app can be deleted in it.
//
// A soft delete, as on the website: deleted_at is stamped and the row stays,
// because orders and reviews are records of money that moved. Registering again
// with the same email brings the account back.
//
// Unlike the vendor version there is no open-orders block, because the website
// has none for buyers — a buyer leaving does not strand anyone's parcel the way
// a shop closing does. The same two additions as the vendor version:
//
//  1. The password check is rate limited.
//  2. It refuses unless the body says confirm: true, so a stray request carrying
//     only a password cannot end an account.
//
// Every token for the buyer is deleted, so every phone is signed out.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';
require __DIR__ . '/../../../config/notify.php';

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

$pdo->prepare('UPDATE buyers SET deleted_at = NOW() WHERE id = ?')->execute([$userId]);

$stmt = $pdo->prepare('DELETE FROM api_tokens WHERE user_id = ? AND role = ?');
$stmt->execute([$userId, 'buyer']);
$signedOut = $stmt->rowCount();

[$subj, $html] = render_email_template($pdo, 'account_deleted', [
    'name' => htmlspecialchars($row['name']),
]);
if ($html !== '') send_email($row['email'], $subj, $html);

api_json(['ok' => true, 'signed_out' => $signedOut]);
