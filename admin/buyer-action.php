<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/audit.php';
require __DIR__ . '/../config/notify.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('buyers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/buyers.php');
    exit;
}

csrf_verify();

$action  = $_POST['action'] ?? '';
$buyerId = (int)($_POST['buyer_id'] ?? 0);

if (!$buyerId) {
    header('Location: /admin/buyers.php');
    exit;
}

$returnUrl = '/admin/buyer.php?id=' . $buyerId;

// Email the buyer about a suspension change, if the admin ticked "notify".
// Unticked is the silent path for spam/bot accounts. Returns false when there
// is nobody to mail or no template; send_email() handles SMTP failure itself
// by logging to mail.log, so a bad mailbox never blocks the suspension.
$notifyBuyer = function (string $template, array $tokens) use ($pdo, $buyerId): bool {
    $stmt = $pdo->prepare('SELECT name, email FROM buyers WHERE id = ?');
    $stmt->execute([$buyerId]);
    $buyer = $stmt->fetch();
    if (!$buyer || !$buyer['email']) return false;

    [$subj, $html] = render_email_template($pdo, $template, $tokens + [
        'name'    => htmlspecialchars($buyer['name']),
        'cta_url' => 'https://teepsaa.com/contact/',
    ]);
    if ($html === '') return false;

    try {
        send_email($buyer['email'], $subj, $html);
        return true;
    } catch (Throwable $e) {
        return false;
    }
};

if ($action === 'suspend') {
    $reason = trim($_POST['suspension_reason'] ?? '');
    if (!$reason) {
        $_SESSION['admin_error'] = 'A reason is required to suspend an account.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $pdo->prepare('UPDATE buyers SET suspended = 1, suspension_reason = ?, suspended_at = NOW() WHERE id = ?')
        ->execute([$reason, $buyerId]);
    audit_log($pdo, 'buyer.suspend', 'buyer', $buyerId, ['reason' => $reason]);

    if (!empty($_POST['notify_user'])) {
        $sent = $notifyBuyer('buyer_suspended', ['reason' => htmlspecialchars($reason)]);
        $_SESSION['admin_success'] = $sent
            ? 'Account suspended. The buyer has been emailed.'
            : 'Account suspended, but the email could not be sent.';
    } else {
        $_SESSION['admin_success'] = 'Account suspended (no email sent).';
    }

} elseif ($action === 'unsuspend') {
    $pdo->prepare('UPDATE buyers SET suspended = 0, suspension_reason = NULL, suspended_at = NULL WHERE id = ?')
        ->execute([$buyerId]);
    audit_log($pdo, 'buyer.unsuspend', 'buyer', $buyerId);

    if (!empty($_POST['notify_user'])) {
        $sent = $notifyBuyer('buyer_reinstated', ['cta_url' => 'https://teepsaa.com/login-buyer/']);
        $_SESSION['admin_success'] = $sent
            ? 'Suspension lifted. The buyer has been emailed.'
            : 'Suspension lifted, but the email could not be sent.';
    } else {
        $_SESSION['admin_success'] = 'Suspension lifted (no email sent).';
    }

} elseif ($action === 'note') {
    $note = trim($_POST['admin_note'] ?? '');
    $pdo->prepare('UPDATE buyers SET admin_note = ? WHERE id = ?')
        ->execute([$note ?: null, $buyerId]);
    $_SESSION['admin_success'] = 'Note saved.';
}

header('Location: ' . $returnUrl);
exit;
