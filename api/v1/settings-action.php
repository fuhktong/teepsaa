<?php
// Everything the app's Settings screen can change about the person signed in:
// contact details, password, and the avatar.
//
// The website splits these across four action files that each redirect back to
// the page with a sentence in the session. An app has no page to redirect to,
// so this answers with JSON and, when something is wrong, says which field.
//
// Four deliberate differences from the website:
//
//  1. Errors come back keyed by field — the same {error:'invalid', fields:{}}
//     shape business-action.php uses — so the app can put the message under the
//     box that caused it instead of one line at the top.
//  2. Changing the password is rate limited. settings-vendor/password-action.php
//     has no limit on the current-password check, which is survivable behind a
//     login form and a session cookie but not on an open endpoint — a stolen
//     token would otherwise be an unlimited guessing machine for the password
//     that token cannot itself reveal.
//  3. Changing the password signs the other phones out. The website cannot do
//     this — it has no list of live sessions — but tokens are a list, and a
//     vendor changing a password because a phone was stolen means the change to
//     do exactly this. The phone that made the change stays signed in, because
//     signing it out would look like the save failed.
//  4. Saving the same name and phone that are already on file does nothing and
//     says so, the same way the bank name does in business-action.php.
//
// Uploading an avatar photo from the phone is a later chapter, the same as the
// banner and the QR. Removing one is only clearing a column, so that is here,
// and so is the colour behind the drawn silhouette.
//
// Deleting the account is not here. Both app stores require it, it needs the
// password typed again, and it erases a shop — it gets its own endpoint rather
// than being one more branch on a file that also changes a phone number.

require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/rate-limit.php';
require __DIR__ . '/../../config/notify.php';

api_require_method('POST');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body   = api_body();
$action = trim((string)($body['action'] ?? ''));

$allowed = ['profile', 'password', 'avatar_color', 'avatar_remove'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

// ── Contact details ──────────────────────────────────────────────────
//
// Email is not in this list. Changing it means re-verifying it, and until that
// flow exists letting the app write the column would hand someone with a stolen
// token the account itself.
if ($action === 'profile') {
    $name  = trim((string)($body['name']  ?? ''));
    $phone = trim((string)($body['phone'] ?? ''));

    $errors = [];
    if ($name === '')                 $errors['name']  = 'Enter your name.';
    elseif (mb_strlen($name) > 255)   $errors['name']  = 'That name is too long.';
    // VARCHAR(20). Under strict mode a longer value aborts the UPDATE, so it is
    // refused here with a sentence instead of arriving as a 500.
    if (mb_strlen($phone) > 20)       $errors['phone'] = 'That phone number is too long.';

    if ($errors) api_json(['error' => 'invalid', 'fields' => $errors], 422);

    $stmt = $pdo->prepare('SELECT name, phone FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    $current = $stmt->fetch();

    if ($current && $current['name'] === $name && (string)($current['phone'] ?? '') === $phone) {
        api_json(['ok' => true, 'unchanged' => true]);
    }

    // '' and NULL both mean "no phone" on the website, and it writes NULL. The
    // same value has to be written here or the two would disagree about an
    // empty box every time one of them saved.
    $pdo->prepare('UPDATE vendors SET name = ?, phone = ? WHERE id = ?')
        ->execute([$name, $phone !== '' ? $phone : null, $userId]);

    api_json(['ok' => true, 'name' => $name, 'phone' => $phone]);
}

// ── Password ─────────────────────────────────────────────────────────
if ($action === 'password') {
    $currentPw = (string)($body['current_password'] ?? '');
    $newPw     = (string)($body['new_password']     ?? '');
    $confirmPw = (string)($body['confirm_password'] ?? '');

    // Its own budget, keyed to the account rather than to an email typed into a
    // box, because the account is already known here. 'login' is not reused:
    // locking a vendor out of signing in because they mistyped their old
    // password three times would punish the wrong thing.
    check_rate_limit($pdo, 'password_change', 'vendor:' . $userId);

    $stmt = $pdo->prepare('SELECT name, email, password FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) api_json(['error' => 'not_found'], 404);

    $errors = [];
    if ($currentPw === '')            $errors['current_password'] = 'Enter your current password.';
    if (mb_strlen($newPw) < 8)        $errors['new_password']     = 'Use at least 8 characters.';
    if ($newPw !== $confirmPw)        $errors['confirm_password'] = 'The two passwords do not match.';

    // Checked after the cheap ones so a typo in the new box does not spend a
    // try from the budget, but before anything is written.
    if (!$errors && !password_verify($currentPw, $row['password'])) {
        record_failed_attempt($pdo, 'password_change', 'vendor:' . $userId);
        $errors['current_password'] = 'That is not the current password.';
    }

    if ($errors) api_json(['error' => 'invalid', 'fields' => $errors], 422);

    $pdo->prepare('UPDATE vendors SET password = ? WHERE id = ?')
        ->execute([password_hash($newPw, PASSWORD_DEFAULT), $userId]);

    // Every other device signed out. The token doing the asking is spared by
    // its hash — the plaintext is never stored, so this is the only way to name
    // it, and it is the same comparison api_require_vendor() just made.
    $thisHash = hash('sha256', api_bearer_token());
    $stmt = $pdo->prepare('
        DELETE FROM api_tokens
         WHERE user_id = ? AND role = ? AND token_hash <> ?
    ');
    $stmt->execute([$userId, 'vendor', $thisHash]);
    $signedOut = $stmt->rowCount();

    // Same email the website sends. A password change nobody made is the one
    // thing a vendor has to hear about immediately, so a mail failure must not
    // take the save down with it — the password is already changed by here.
    [$subj, $html] = render_email_template($pdo, 'password_changed', [
        'name' => htmlspecialchars($row['name']),
    ]);
    if ($html !== '') send_email($row['email'], $subj, $html);

    api_json(['ok' => true, 'signed_out' => $signedOut]);
}

// ── Avatar colour ────────────────────────────────────────────────────
//
// The colour behind the drawn silhouette. It keeps working while a photo is on
// file — remove the photo and the colour underneath is the one picked here, not
// a default the vendor never chose.
if ($action === 'avatar_color') {
    $color = (int)($body['color'] ?? -1);
    if ($color < 0 || $color > 4) {
        api_json(['error' => 'invalid', 'fields' => ['color' => 'Pick one of the colours shown.']], 422);
    }

    $pdo->prepare('UPDATE vendors SET avatar_color = ? WHERE id = ?')
        ->execute([$color, $userId]);

    api_json(['ok' => true, 'avatar_color' => $color]);
}

// ── Remove the avatar photo ──────────────────────────────────────────
if ($action === 'avatar_remove') {
    $stmt = $pdo->prepare('SELECT avatar FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();

    if (!$old) api_json(['error' => 'no_avatar'], 409);

    // The column is cleared first. If a file cannot be deleted the vendor still
    // gets what they asked for — a shop page with no photo on it — and the
    // leftover file is an orphan nobody can reach, which is the better of the
    // two failures.
    $pdo->prepare('UPDATE vendors SET avatar = NULL WHERE id = ?')->execute([$userId]);

    // basename() because the value is a filename the server wrote, not a path,
    // and upload_dir() so this does not depend on where under api/ the file
    // requiring it happens to sit.
    $oldPath = upload_dir() . '/' . basename((string)$old);
    if (is_file($oldPath)) @unlink($oldPath);
    image_delete_derivatives($old);

    api_json(['ok' => true]);
}
