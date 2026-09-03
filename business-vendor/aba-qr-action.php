<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/audit.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business-vendor/');
    exit;
}

csrf_verify();

// Tell the vendor their payout destination moved, and log it.
//
// This is the control against a hijacked vendor account quietly redirecting
// payouts: the real vendor gets an email even when the change was not theirs,
// and admin/payouts-action.php holds payouts to them for 24 hours
// (BANK_CHANGE_HOLD_SECONDS) so there is time to react. Never let a mail
// failure block the settings change itself.
function aba_change_notice(PDO $pdo, int $vendorId, string $accountName, string $what): void {
    $changedAt = date('M j, Y g:ia');

    audit_log($pdo, 'vendor.bank_change', 'vendor', $vendorId, [
        'changed'      => $what,
        'account_name' => $accountName,
    ], ['id' => null, 'label' => 'vendor#' . $vendorId]);

    notify($pdo, 'vendor', $vendorId, 'bank_changed',
        'Your payout bank details were changed. Payouts are held for 24 hours.',
        '/business-vendor/');

    try {
        $stmt = $pdo->prepare('SELECT name, email FROM vendors WHERE id = ?');
        $stmt->execute([$vendorId]);
        $vendor = $stmt->fetch();
        if (!$vendor || !$vendor['email']) return;

        [$subj, $html] = render_email_template($pdo, 'vendor_bank_changed', [
            'name'         => htmlspecialchars($vendor['name'] ?? ''),
            'account_name' => htmlspecialchars($accountName),
            'changed_at'   => $changedAt,
            'cta_url'      => 'https://teepsaa.com/contact/',
        ]);
        if ($html !== '') send_email($vendor['email'], $subj, $html);
    } catch (Throwable $e) {
        error_log('[aba-change] notice failed for vendor ' . $vendorId . ': ' . $e->getMessage());
    }
}

$userId    = $_SESSION['user_id'];
$uploadDir = __DIR__ . '/../uploads/';
$allowed   = ['image/jpeg', 'image/png'];

$accountName = trim($_POST['aba_account_name'] ?? '');
if ($accountName === '') {
    $_SESSION['settings_error'] = 'Account name is required.';
    header('Location: /business-vendor/');
    exit;
}
$accountName = mb_substr($accountName, 0, 100);

// New QR file is optional once one exists — the account name can be updated alone
if (empty($_FILES['aba_qr']['name'])) {
    $stmt = $pdo->prepare('SELECT aba_qr FROM vendors WHERE id = ?');
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        $_SESSION['settings_error'] = 'Please upload your bank QR code.';
        header('Location: /business-vendor/');
        exit;
    }
    $pdo->prepare('UPDATE vendors SET aba_account_name = ?, aba_changed_at = NOW() WHERE id = ?')
        ->execute([$accountName, $userId]);
    aba_change_notice($pdo, (int)$userId, $accountName, 'account name');
    $_SESSION['settings_success'] = 'Account name updated. Payouts are held for 24 hours while the change is verified.';
    header('Location: /business-vendor/');
    exit;
}

$tmp  = $_FILES['aba_qr']['tmp_name'];
$size = $_FILES['aba_qr']['size'];
$mime = image_type_from_magic($tmp);

if (!in_array($mime, $allowed, true) || $size > 2 * 1024 * 1024) {
    $_SESSION['settings_error'] = 'Invalid file. JPG or PNG only, max 2MB.';
    header('Location: /business-vendor/');
    exit;
}

$ext      = $mime === 'image/png' ? 'png' : 'jpg';
$filename = bin2hex(random_bytes(16)) . '.' . $ext;

if (move_uploaded_file($tmp, $uploadDir . $filename)) {
    $stmt = $pdo->prepare('UPDATE vendors SET aba_qr = ?, aba_account_name = ?, aba_changed_at = NOW() WHERE id = ?');
    $stmt->execute([$filename, $accountName, $userId]);
    aba_change_notice($pdo, (int)$userId, $accountName, 'QR code and account name');
    $_SESSION['settings_success'] = 'Bank QR code updated. Payouts are held for 24 hours while the change is verified.';
} else {
    $_SESSION['settings_error'] = 'Upload failed. Please try again.';
}

header('Location: /business-vendor/');
exit;
