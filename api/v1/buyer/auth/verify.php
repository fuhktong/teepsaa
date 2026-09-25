<?php
// Proving the email address — the same job as verify-email/verify.php, and the
// last step of signing up in the app.
//
// The website knows who is verifying from the half-signed-in session it set
// during registration. There is no session here, so the app sends the email
// address back along with the code. That makes the code guessable in principle,
// which is why the rate limit below is keyed on the account and shares its
// budget with the website's own verify page: five wrong codes in fifteen
// minutes, wherever they are typed.
//
// Unlike the website, this ends by minting a token. The email is proved, so
// there is nothing left to ask for — making the buyer type the password they
// chose one screen ago would be a step with no purpose.

require __DIR__ . '/../../../../config/api.php';
require __DIR__ . '/../../../../config/rate-limit.php';
require __DIR__ . '/../../../../config/notify.php';

api_require_method('POST');

$body  = api_body();
$email = trim((string)($body['email'] ?? ''));
$code  = preg_replace('/\D/', '', (string)($body['code'] ?? ''));

// Free text for a "signed-in devices" list later. Never matched on.
$device = mb_substr(trim((string)($body['device_name'] ?? '')), 0, 120);

$fields = [];
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fields['email'] = 'That does not look like an email address.';
if (strlen($code) !== 6)                        $fields['code']  = 'Enter all 6 digits.';

if ($fields) api_json(['error' => 'invalid', 'fields' => $fields], 422);

$stmt = $pdo->prepare(
    'SELECT id, name, lang, suspended, verify_token, verify_code_expires, email_verified_at
       FROM buyers
      WHERE email = ? AND deleted_at IS NULL'
);
$stmt->execute([$email]);
$buyer = $stmt->fetch();

if (!$buyer) api_json(['error' => 'not_found'], 404);

// Checked before anything is written. login.php refuses a suspended account, so
// this one has to as well — and refusing after stamping the email verified and
// sending a welcome would leave the account changed by a call that failed.
if ($buyer['suspended']) api_json(['error' => 'suspended'], 403);

// Already done. Its own answer rather than a wrong-code error, because the app
// can send this buyer to sign in instead of asking for a code that no longer
// exists — which is what happens when the same code is submitted twice.
if ($buyer['email_verified_at']) api_json(['error' => 'already_verified'], 409);

if (!$buyer['verify_token'] || !$buyer['verify_code_expires']) {
    api_json(['error' => 'no_code'], 409);
}

if (new DateTime() > new DateTime($buyer['verify_code_expires'])) {
    api_json(['error' => 'code_expired'], 409);
}

$rlId = 'buyer:' . (int)$buyer['id'];
check_rate_limit($pdo, 'verify', $rlId);

// hash_equals rather than === because the compared values are a secret and a
// guess; a plain comparison stops at the first wrong character and the time it
// took says how many were right.
if (!hash_equals((string)$buyer['verify_token'], $code)) {
    record_failed_attempt($pdo, 'verify', $rlId);
    api_json(['error' => 'invalid_code', 'fields' => ['code' => 'That code is not right.']], 422);
}

$pdo->prepare('UPDATE buyers SET email_verified_at = NOW(), verify_token = NULL, verify_code_expires = NULL WHERE id = ?')
    ->execute([$buyer['id']]);

[$subj, $html] = render_email_template($pdo, 'welcome_buyer', [
    'name'    => htmlspecialchars((string)$buyer['name'], ENT_QUOTES),
    'cta_url' => 'https://teepsaa.com/',
]);
if ($html !== '') send_email($email, $subj, $html);

// Same as auth/login.php: 32 random bytes hex-encoded, only the hash stored.
// This is the one moment the plaintext exists on the server.
$token = bin2hex(random_bytes(32));

$pdo->prepare(
    'INSERT INTO api_tokens (user_id, role, token_hash, device_name)
     VALUES (?, ?, ?, ?)'
)->execute([
    $buyer['id'],
    'buyer',
    hash('sha256', $token),
    $device !== '' ? $device : null,
]);

api_json([
    'token' => $token,
    'buyer' => [
        'id'   => (int)$buyer['id'],
        'name' => $buyer['name'],
        'lang' => $buyer['lang'] ?: 'km',
    ],
]);
