<?php
// Everything the buyer app's Account screen can change: name and phone,
// password, language, and the avatar colour or photo removal. The buyer
// version of api/v1/settings-action.php; uploading a photo is avatar.php,
// because a picture arrives as a multipart form and not as JSON.
//
// Every rule from settings-buyer/profile-action.php, password-action.php,
// avatar-color-action.php and avatar-action.php (delete) is repeated. Three
// deliberate differences from the website, the same three the vendor app has:
//
//  1. Errors come back keyed by field, {error:'invalid', fields:{}}.
//  2. The password change is rate limited — on an open endpoint an unlimited
//     current-password check is a guessing machine for whoever holds a token.
//  3. Changing the password signs every other phone out; this one stays in.
//
// Email is not changeable here, for the same reason as the vendor app: it has
// to be confirmed again, and letting a token rewrite it hands over the account.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';
require __DIR__ . '/../../../config/notify.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body   = api_body();
$action = trim((string)($body['action'] ?? ''));

$allowed = ['profile', 'password', 'avatar_color', 'avatar_remove', 'lang'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

// ── Name and phone ───────────────────────────────────────────────────
//
// Phone is required, as on the website: the driver calls it on delivery and
// checkout refuses without one. That is the one place this differs from the
// vendor version, where a phone is optional.
if ($action === 'profile') {
    $name  = trim((string)($body['name']  ?? ''));
    $phone = trim((string)($body['phone'] ?? ''));

    $errors = [];
    if ($name === '')                 $errors['name']  = 'Enter your name.';
    elseif (mb_strlen($name) > 255)   $errors['name']  = 'That name is too long.';
    if ($phone === '')                $errors['phone'] = 'Enter a phone number — the driver calls it on delivery.';
    elseif (mb_strlen($phone) > 20)   $errors['phone'] = 'That phone number is too long.';

    if ($errors) api_json(['error' => 'invalid', 'fields' => $errors], 422);

    $stmt = $pdo->prepare('SELECT name, phone FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $current = $stmt->fetch();

    if ($current && $current['name'] === $name && (string)($current['phone'] ?? '') === $phone) {
        api_json(['ok' => true, 'unchanged' => true]);
    }

    $pdo->prepare('UPDATE buyers SET name = ?, phone = ? WHERE id = ?')
        ->execute([$name, $phone, $userId]);

    api_json(['ok' => true, 'name' => $name, 'phone' => $phone]);
}

// ── Password ─────────────────────────────────────────────────────────
if ($action === 'password') {
    $currentPw = (string)($body['current_password'] ?? '');
    $newPw     = (string)($body['new_password']     ?? '');
    $confirmPw = (string)($body['confirm_password'] ?? '');

    check_rate_limit($pdo, 'password_change', 'buyer:' . $userId);

    $stmt = $pdo->prepare('SELECT name, email, password FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) api_json(['error' => 'not_found'], 404);

    $errors = [];
    if ($currentPw === '')            $errors['current_password'] = 'Enter your current password.';
    if (mb_strlen($newPw) < 8)        $errors['new_password']     = 'Use at least 8 characters.';
    if ($newPw !== $confirmPw)        $errors['confirm_password'] = 'The two passwords do not match.';

    if (!$errors && !password_verify($currentPw, $row['password'])) {
        record_failed_attempt($pdo, 'password_change', 'buyer:' . $userId);
        $errors['current_password'] = 'That is not the current password.';
    }

    if ($errors) api_json(['error' => 'invalid', 'fields' => $errors], 422);

    $pdo->prepare('UPDATE buyers SET password = ? WHERE id = ?')
        ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);

    $stmt = $pdo->prepare('
        DELETE FROM api_tokens
         WHERE user_id = ? AND role = ? AND token_hash <> ?
    ');
    $stmt->execute([$userId, 'buyer', hash('sha256', api_bearer_token())]);
    $signedOut = $stmt->rowCount();

    [$subj, $html] = render_email_template($pdo, 'password_changed', [
        'name' => htmlspecialchars($row['name']),
    ]);
    if ($html !== '') send_email($row['email'], $subj, $html);

    api_json(['ok' => true, 'signed_out' => $signedOut]);
}

// ── Language ─────────────────────────────────────────────────────────
//
// For the emails and the website; the app has already switched its own words.
if ($action === 'lang') {
    $lang = (string)($body['lang'] ?? '');
    if ($lang !== 'en' && $lang !== 'km') {
        api_json(['error' => 'invalid', 'fields' => ['lang' => 'Pick English or Khmer.']], 422);
    }
    $pdo->prepare('UPDATE buyers SET lang = ? WHERE id = ?')->execute([$lang, $userId]);
    api_json(['ok' => true, 'lang' => $lang]);
}

// ── Avatar colour ────────────────────────────────────────────────────
if ($action === 'avatar_color') {
    $color = (int)($body['color'] ?? -1);
    if ($color < 0 || $color > 4) {
        api_json(['error' => 'invalid', 'fields' => ['color' => 'Pick one of the colours shown.']], 422);
    }
    $pdo->prepare('UPDATE buyers SET avatar_color = ? WHERE id = ?')->execute([$color, $userId]);
    api_json(['ok' => true, 'avatar_color' => $color]);
}

// ── Remove the avatar photo ──────────────────────────────────────────
if ($action === 'avatar_remove') {
    $stmt = $pdo->prepare('SELECT avatar FROM buyers WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();

    if (!$old) api_json(['error' => 'no_avatar'], 409);

    $pdo->prepare('UPDATE buyers SET avatar = NULL WHERE id = ?')->execute([$userId]);

    $oldPath = upload_dir() . '/' . basename((string)$old);
    if (is_file($oldPath)) @unlink($oldPath);
    image_delete_derivatives($old);

    api_json(['ok' => true]);
}
