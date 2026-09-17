<?php
// Send the six-digit code again — the same job as
// resend-verification/resend.php.
//
// The website knows the account from its half-signed-in session; the app sends
// the email address instead. Every request counts against the limit, not only
// the failures, because what is being limited is how much mail one account can
// be made to send — a successful resend is exactly the thing being abused.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';
require __DIR__ . '/../../../config/notify.php';

api_require_method('POST');

$body  = api_body();
$email = trim((string)($body['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_json(['error' => 'invalid', 'fields' => ['email' => 'That does not look like an email address.']], 422);
}

$stmt = $pdo->prepare('SELECT id, name, email_verified_at FROM vendors WHERE email = ? AND deleted_at IS NULL');
$stmt->execute([$email]);
$vendor = $stmt->fetch();

if (!$vendor) api_json(['error' => 'not_found'], 404);

// Nothing to send. The app sends this vendor to sign in rather than leaving
// them waiting for an email that is never coming.
if ($vendor['email_verified_at']) api_json(['error' => 'already_verified'], 409);

$rlId = 'vendor:' . (int)$vendor['id'];
check_rate_limit($pdo, 'resend', $rlId);
record_failed_attempt($pdo, 'resend', $rlId);

$code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// The old code stops working the moment this one is written. That is the point:
// two live codes would double the guesses one rate-limit budget buys.
$pdo->prepare('UPDATE vendors SET verify_token = ?, verify_code_expires = ? WHERE id = ?')
    ->execute([$code, $expires, $vendor['id']]);

$codeHtml = '<div style="font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;margin:12px 0;color:#111">' . $code . '</div>';
[$subj, $html] = render_email_template($pdo, 'verify_code', [
    'name' => htmlspecialchars((string)$vendor['name'], ENT_QUOTES),
    'code' => $codeHtml,
]);
if ($html !== '') send_email($email, $subj, $html);

api_json(['ok' => true]);
