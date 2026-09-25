<?php
// Buyer sign-up for the buyer app — the same job as
// register-buyer/register-buyer.php, in the same order, because a check that
// exists on only one front door is a check that does not exist.
//
// Three deliberate differences from the website page:
//
//  1. It is rate limited. The website page is protected by a CSRF token and a
//     browser; this one can be called in a loop, and each call sends an email.
//     Every request counts, not only the failures, because the abuse here is the
//     volume of mail one address can be made to receive.
//  2. No token comes back. The email is not verified yet, so there is nothing to
//     sign in to — the app moves on to the code screen and gets its token from
//     auth/verify.php.
//  3. Field errors come back as a `fields` object instead of one sentence in the
//     session, so the app can put each message under the box it belongs to.
//
// The revive branch at the bottom is the website's behaviour and matters: a
// buyer who deleted their account and comes back gets their old row, so their
// kept order history is still theirs.

require __DIR__ . '/../../../../config/api.php';
require __DIR__ . '/../../../../config/rate-limit.php';
require __DIR__ . '/../../../../config/notify.php';

api_require_method('POST');

$body     = api_body();
$name     = trim((string)($body['name'] ?? ''));
$email    = trim((string)($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');
$confirm  = (string)($body['password_confirm'] ?? '');

$fields = [];
if ($name === '')                                      $fields['name']             = 'Enter your name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $fields['email']            = 'That does not look like an email address.';
if (strlen($password) < 8)                             $fields['password']         = 'Use at least 8 characters.';
elseif ($password !== $confirm)                        $fields['password_confirm'] = 'The two passwords are not the same.';
if ($name !== '' && mb_strlen($name) > 255)            $fields['name']             = 'That name is too long.';

if ($fields) api_json(['error' => 'invalid', 'fields' => $fields], 422);

$stmt = $pdo->prepare('SELECT id, deleted_at FROM buyers WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

// A live account already owns this address. Said plainly because the website
// says it plainly too — hiding it here would only mean the app cannot explain
// why sign-up did nothing.
if ($existing && $existing['deleted_at'] === null) {
    api_json(['error' => 'email_taken'], 409);
}

// Checked after the cheap validation so a mistyped password confirmation does
// not spend one of the five tries, and before the write so a locked-out caller
// changes nothing.
check_rate_limit($pdo, 'register', $email);
record_failed_attempt($pdo, 'register', $email);

$code    = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

if ($existing) {
    // Re-registering with a soft-deleted account's email revives that row, so
    // the orders still hanging off it stay linked. The email has to be proved
    // again before it can sign in.
    $pdo->prepare(
        'UPDATE buyers
            SET name = ?, password = ?, verify_token = ?, verify_code_expires = ?,
                email_verified_at = NULL, deleted_at = NULL
          WHERE id = ?'
    )->execute([$name, password_hash($password, PASSWORD_DEFAULT), $code, $expires, $existing['id']]);
    $newId = (int)$existing['id'];
} else {
    $pdo->prepare(
        'INSERT INTO buyers (email, name, password, verify_token, verify_code_expires)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), $code, $expires]);
    $newId = (int)$pdo->lastInsertId();
}

$codeHtml = '<div style="font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;margin:12px 0;color:#111">' . $code . '</div>';
[$subj, $html] = render_email_template($pdo, 'verify_code', [
    'name' => htmlspecialchars($name, ENT_QUOTES),
    'code' => $codeHtml,
]);
if ($html !== '') send_email($email, $subj, $html);

// The email is echoed back so the code screen can print it and send it on to
// auth/verify.php without the app having to remember what was typed.
api_json(['ok' => true, 'email' => $email, 'revived' => (bool)$existing]);
