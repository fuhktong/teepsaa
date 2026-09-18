<?php
// The bank QR code, uploaded from the phone.
//
// This is the app's version of business-vendor/aba-qr-action.php, and every
// check there is repeated here.
//
// Why it is not an action inside business-action.php: that file reads its
// request as JSON, and a picture cannot travel as JSON without being base64'd
// into text, which makes it a third larger and has to be decoded to a temporary
// file before any check can look at it. This one arrives as an ordinary
// multipart form upload, exactly as it does from the website, so $_FILES,
// move_uploaded_file() and the magic-byte check all work unchanged.
//
// This is the most dangerous thing a vendor can change. The QR is where the
// money goes. So it repeats all four of the website's controls and none of them
// is optional: an audit row, an in-app notice, an email to the address on file,
// and a 24-hour hold on payouts (admin/payouts-action.php reads
// aba_changed_at). The email matters most when the change was *not* the
// vendor's — it is how a hijacked account gets noticed before a payout leaves.
//
// image_type_from_magic() arrives with api.php, which requires db.php, which
// requires upload.php. Requiring it here is a redeclare and a 500 before any
// code runs.
require __DIR__ . '/../../config/api.php';
require __DIR__ . '/../../config/notify.php';
// audit.php pulls in admin-auth.php, whose only include-time action is guarded
// on an active session inside the admin area. There is neither here, so it is
// inert — and audit_log() below is handed an explicit actor, so it never looks
// for an admin id.
require __DIR__ . '/../../config/audit.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// A file larger than PHP's post_max_size is thrown away before this script
// starts, and PHP hands us an empty $_POST *and* an empty $_FILES with no error
// of any kind. Without this check the reply would be 'missing_file', which
// sends whoever is debugging it to the wrong end of the problem.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

$stmt = $pdo->prepare('SELECT name, email, aba_qr, aba_account_name FROM vendors WHERE id = ?');
$stmt->execute([$userId]);
$current = $stmt->fetch();
if (!$current) {
    api_json(['error' => 'not_found'], 404);
}

// The website's form posts the name and the file together and refuses an empty
// name. The app's screen does the same, but a vendor who is only replacing the
// picture should not have to retype a name that is already right — so an
// account_name left out means "keep the one on file", and it is only an error
// when there is nothing on file either. A QR with no name on it is a payout
// nobody can match to a person.
$accountName = trim((string)($_POST['account_name'] ?? ''));
if ($accountName === '') {
    $accountName = (string)($current['aba_account_name'] ?? '');
}
if ($accountName === '') {
    api_json([
        'error'  => 'invalid',
        'fields' => ['account_name' => 'Enter the name on the bank account.'],
    ], 422);
}
if (mb_strlen($accountName) > 100) {
    api_json([
        'error'  => 'invalid',
        'fields' => ['account_name' => 'That is too long. Keep it under 100 characters.'],
    ], 422);
}

$file = $_FILES['qr'] ?? null;
if (!$file || !is_uploaded_file($file['tmp_name'] ?? '')) {
    api_json(['error' => 'missing_file'], 400);
}
if ((int)$file['error'] !== UPLOAD_ERR_OK) {
    // INI_SIZE and FORM_SIZE both mean the picture was too big; the rest are
    // faults on this end, and a vendor retrying is the right advice for both.
    $tooBig = in_array((int)$file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    api_json([
        'error' => $tooBig ? 'too_large' : 'upload_failed',
        'code'  => (int)$file['error'],
    ], $tooBig ? 413 : 400);
}

// The first bytes of the file itself, never the type the phone claimed. A name
// and a Content-Type are both just text in the request and either can say
// 'image/jpeg' about anything at all.
$mime = image_type_from_magic($file['tmp_name']);
if ($mime !== 'image/jpeg' && $mime !== 'image/png') {
    api_json(['error' => 'bad_type', 'allowed' => ['image/jpeg', 'image/png']], 422);
}
if ((int)$file['size'] > 2 * 1024 * 1024) {
    api_json(['error' => 'too_large', 'limit_mb' => 2], 413);
}

// Random name, extension decided by the magic bytes rather than by whatever the
// phone called the file. The uploads folder is served directly, so a name taken
// from the request is a name an attacker chooses.
$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $ext;

if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../../uploads/' . $filename)) {
    api_json(['error' => 'save_failed'], 500);
}

// The old file is left on disk, which is what the website does. It is the only
// copy of where payouts used to go, and an admin investigating a hijacked
// account would have nothing to compare against if this deleted it.

$nameChanged = (string)($current['aba_account_name'] ?? '') !== $accountName;

$pdo->prepare('UPDATE vendors SET aba_qr = ?, aba_account_name = ?, aba_changed_at = NOW() WHERE id = ?')
    ->execute([$filename, $accountName, $userId]);

// ── The warning, in four places ───────────────────────────────────────
//
// A mail failure must never undo the save — the QR is already stored and the
// hold is already running, so throwing here would leave the vendor looking at
// an error for a change that did happen.
$what = $nameChanged ? 'QR code and account name' : 'QR code';

audit_log($pdo, 'vendor.bank_change', 'vendor', $userId, [
    'changed'      => $what,
    'account_name' => $accountName,
    'source'       => 'app',
], ['id' => null, 'label' => 'vendor#' . $userId]);

notify($pdo, 'vendor', $userId, 'bank_changed',
    'Your payout bank details were changed. Payouts are held for 24 hours.',
    '/business-vendor/');

try {
    if (!empty($current['email'])) {
        [$subj, $html] = render_email_template($pdo, 'vendor_bank_changed', [
            'name'         => htmlspecialchars($current['name'] ?? ''),
            'account_name' => htmlspecialchars($accountName),
            'changed_at'   => date('M j, Y g:ia'),
            'cta_url'      => 'https://teepsaa.com/contact/',
        ]);
        if ($html !== '') send_email($current['email'], $subj, $html);
    }
} catch (Throwable $e) {
    error_log('[api business-qr] notice failed for vendor ' . $userId . ': ' . $e->getMessage());
}

api_json([
    'ok'           => true,
    'held'         => true,
    // Absolute, not the '/uploads/…' the website uses. The app runs from
    // https://localhost, so a root-relative address points the phone at itself
    // and the picture silently never loads.
    'qr'           => 'https://teepsaa.com/uploads/' . $filename,
    'account_name' => $accountName,
], 201);
