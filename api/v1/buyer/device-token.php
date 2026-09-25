<?php
// POST /api/v1/buyer/device-token.php
//
//   {"fcm_token": "...", "platform": "android", "lang": "km"}   register/refresh
//   {"fcm_token": "...", "remove": true}                        forget this phone
//
// The buyer app's copy of api/v1/device-token.php — read that file for why it
// is an idempotent upsert and why logout must call remove BEFORE
// auth/logout.php. Same table; `role` is what separates the two apps, and the
// upsert writes it, so a phone that once ran the vendor app under the same
// Firebase token cannot keep dinging the vendor.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$buyer  = api_require_buyer($pdo);
$userId = (int)$buyer['id'];

$body  = api_body();
$token = trim((string)($body['fcm_token'] ?? ''));

if ($token === '' || strlen($token) > 255 || !preg_match('/^[A-Za-z0-9_:.\-]+$/', $token)) {
    api_json(['error' => 'bad_token'], 422);
}

if (!empty($body['remove'])) {
    // Scoped to this buyer, so a token alone cannot switch off someone else's.
    $pdo->prepare('DELETE FROM device_tokens WHERE fcm_token = ? AND role = ? AND user_id = ?')
        ->execute([$token, 'buyer', $userId]);
    api_json(['ok' => true, 'registered' => false]);
}

$platform = ($body['platform'] ?? '') === 'ios' ? 'ios' : 'android';
$lang     = ($body['lang'] ?? '') === 'km' ? 'km' : 'en';

$pdo->prepare(
    'INSERT INTO device_tokens (user_id, role, fcm_token, platform, lang)
          VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
          user_id      = VALUES(user_id),
          role         = VALUES(role),
          platform     = VALUES(platform),
          lang         = VALUES(lang),
          last_seen_at = NOW()'
)->execute([$userId, 'buyer', $token, $platform, $lang]);

api_json(['ok' => true, 'registered' => true]);
