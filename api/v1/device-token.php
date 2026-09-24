<?php
/**
 * POST /api/v1/device-token.php
 *
 *   {"fcm_token": "...", "platform": "android", "lang": "km"}   register/refresh
 *   {"fcm_token": "...", "remove": true}                        forget this phone
 *
 * One row per phone that has agreed to be notified. The app calls this after
 * login, whenever Firebase hands it a new token, and when the vendor changes
 * language in Settings — all three are the same upsert, because the token is
 * the identity of the row and everything else about it can change.
 *
 * Registering is idempotent by design. The app is free to call it on every
 * launch and the answer is the same, which matters because the alternative —
 * the app trying to remember whether it has registered already — is the kind
 * of state that goes wrong after a reinstall and leaves a vendor silently
 * receiving nothing.
 *
 * The remove branch exists for logout, and the app must call it BEFORE
 * /auth/logout.php, because that call destroys the bearer token this one needs
 * to prove who is asking. If it is skipped — the phone was offline, the app was
 * killed — nothing breaks permanently: the next login on that phone upserts the
 * same row onto the new account. The gap is the stretch between one vendor
 * logging out and the next logging in, during which an order could ding a
 * phone nobody is signed in on. That is why removal is worth doing at all.
 *
 * No rate limit, matching notifications-read.php and the other ordinary
 * authenticated endpoints. It sends no mail, mints no credential and writes at
 * most one row, which a caller already holding a valid token may overwrite as
 * often as it likes.
 */
require __DIR__ . '/../../config/api.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body  = api_body();
$token = trim((string)($body['fcm_token'] ?? ''));

// Firebase tokens are URL-safe text with colons in them. Checking the shape
// keeps obvious rubbish out of a column that is then handed to Google on every
// notification, and bounds the length before it can fail the 255 the table
// allows. It is not a guarantee the token is real — only Google can say that,
// and it says so by returning 404, at which point send_push() deletes the row.
if ($token === '' || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9_:.\-]+$/', $token)) {
    api_json(['error' => 'bad_token'], 422);
}

if (!empty($body['remove'])) {
    // Scoped to this vendor as well as this token. Without the role and
    // user_id, holding any valid token would be enough to delete a row
    // belonging to somebody else and quietly switch off their notifications.
    $pdo->prepare('DELETE FROM device_tokens WHERE fcm_token = ? AND role = ? AND user_id = ?')
        ->execute([$token, 'vendor', $userId]);
    api_json(['ok' => true, 'registered' => false]);
}

$platform = ($body['platform'] ?? '') === 'ios' ? 'ios' : 'android';
$lang     = ($body['lang'] ?? '') === 'km' ? 'km' : 'en';

// The upsert the table's UNIQUE key exists for. user_id and role are in the
// update list on purpose: when a second vendor signs in on a phone the first
// one used, this row must change hands rather than multiply.
$pdo->prepare(
    'INSERT INTO device_tokens (user_id, role, fcm_token, platform, lang)
          VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
          user_id      = VALUES(user_id),
          role         = VALUES(role),
          platform     = VALUES(platform),
          lang         = VALUES(lang),
          last_seen_at = NOW()'
)->execute([$userId, 'vendor', $token, $platform, $lang]);

api_json(['ok' => true, 'registered' => true]);
