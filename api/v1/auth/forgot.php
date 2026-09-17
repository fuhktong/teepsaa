<?php
// Ask for a password reset link — the same job as
// forgot-password-vendor/request.php, and the one auth flow the app does not
// finish itself.
//
// The link in the email opens reset-password-vendor/ in the phone's browser,
// where the new password is set; the vendor then comes back to the app and signs
// in. Handling the reset inside the app would mean either a second code flow or
// a deep link into the app from an email, and neither is worth building for
// something done once.
//
// The reply is the same whether the address is registered or not. An answer that
// differed would turn this endpoint into a way to ask "does this shop have an
// account here", which is exactly what the website refuses to answer.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';
require __DIR__ . '/../../../config/app.php';
require __DIR__ . '/../../../config/notify.php';

api_require_method('POST');

$body  = api_body();
$email = trim((string)($body['email'] ?? ''));

// Counted before the address is even checked for shape, and every request
// counts. What is being limited is how much reset mail one address can be made
// to receive, and it has its own budget so a reset storm cannot also lock the
// victim out of signing in.
check_rate_limit($pdo, 'reset', $email);
record_failed_attempt($pdo, 'reset', $email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['error' => 'invalid', 'fields' => ['email' => 'That does not look like an email address.']], 422);
}

$stmt = $pdo->prepare('SELECT id, name FROM vendors WHERE email = ?');
$stmt->execute([$email]);
$vendor = $stmt->fetch();

if ($vendor) {
    // Any link already sent and not used stops working. One live link at a time
    // means the newest email is always the one that works, which is what
    // somebody who asked twice expects.
    $pdo->prepare('DELETE FROM password_resets WHERE role = ? AND user_id = ? AND used_at IS NULL')
        ->execute(['vendor', $vendor['id']]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_resets (role, user_id, token) VALUES (?, ?, ?)')
        ->execute(['vendor', $vendor['id'], $token]);

    $link     = SITE_URL . '/reset-password-vendor/?token=' . urlencode($token);
    $linkHtml = '<p><a href="' . $link . '">' . $link . '</a></p>';
    [$subj, $html] = render_email_template($pdo, 'reset_password', [
        'name' => htmlspecialchars((string)$vendor['name'], ENT_QUOTES),
        'link' => $linkHtml,
    ]);
    if ($html !== '') send_email($email, $subj, $html);
}

api_json(['ok' => true]);
