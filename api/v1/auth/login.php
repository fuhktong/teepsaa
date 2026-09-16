<?php
// Vendor login for the mobile app. The checks below are the same ones
// login-vendor/login-vendor.php performs, in the same order, because this is a
// second front door to the same account — a check that exists on only one of
// them is a check that does not exist.
//
// What is deliberately absent: session_start() and csrf_verify(). There is no
// cookie here, so there is no session to fixate and nothing for CSRF to forge.
// What replaces the session is the token minted at the bottom of this file.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/rate-limit.php';

api_require_method('POST');

$body     = api_body();
$email    = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

// Free text the vendor sees later on a "signed-in devices" list. Never matched
// on, so it needs no validation beyond a length the column can hold.
$device = mb_substr(trim((string)($body['device_name'] ?? '')), 0, 120);

if ($email === '' || $password === '') {
    api_json(['error' => 'missing_fields'], 400);
}

// All three portals and this endpoint share the 'login' budget on purpose:
// someone working through a stolen password list should not get a fresh five
// tries by switching to the app.
check_rate_limit($pdo, 'login', $email);

$stmt = $pdo->prepare(
    'SELECT id, name, password, suspended, email_verified_at, lang
       FROM vendors
      WHERE email = ? AND deleted_at IS NULL'
);
$stmt->execute([$email]);
$vendor = $stmt->fetch();

if (!$vendor || !password_verify($password, $vendor['password'])) {
    record_failed_attempt($pdo, 'login', $email);
    api_json(['error' => 'invalid_credentials'], 401);
}

if ($vendor['suspended']) {
    api_json(['error' => 'suspended'], 403);
}

// The website sends an unverified vendor to /resend-verification/. The app has
// no such screen yet, so it is told which case this is and handles it itself —
// a redirect would be meaningless to a phone.
if (!$vendor['email_verified_at']) {
    api_json(['error' => 'email_unverified'], 403);
}

// 32 random bytes, hex-encoded to 64 characters. Only the SHA-256 hash is
// stored; this is the one and only moment the plaintext exists on the server,
// and it is never written to a log or returned again.
$token = bin2hex(random_bytes(32));

$pdo->prepare(
    'INSERT INTO api_tokens (user_id, role, token_hash, device_name)
     VALUES (?, ?, ?, ?)'
)->execute([
    $vendor['id'],
    'vendor',
    hash('sha256', $token),
    $device !== '' ? $device : null,
]);

api_json([
    'token' => $token,
    'vendor' => [
        'id'   => (int)$vendor['id'],
        'name' => $vendor['name'],
        'lang' => $vendor['lang'] ?: 'km',
    ],
]);
