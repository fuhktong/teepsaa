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
require __DIR__ . '/../config/fingerprint.php';
require __DIR__ . '/../config/account-erase.php';

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
    fingerprint_save($pdo, 'buyer', $buyerId, $reason);

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
    fingerprint_forget($pdo, 'buyer', $buyerId);

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
} elseif ($action === 'erase') {
    // For someone who cannot sign in to delete the account themselves — most
    // often a suspended account asking through the contact form. Same erase as
    // "delete my account" (config/account-erase.php). A suspended account's
    // email and phone are fingerprinted first, so a return under a new account
    // is still flagged; the privacy policy says so. No goodbye email: the
    // request came by message and is answered there.
    $stmt = $pdo->prepare('SELECT suspended, suspension_reason, deleted_at, email FROM buyers WHERE id = ?');
    $stmt->execute([$buyerId]);
    $acct = $stmt->fetch();
    if (!$acct || str_ends_with($acct['email'], '@deleted.invalid')) {
        $_SESSION['admin_error'] = 'This account is already erased.';
        header('Location: ' . $returnUrl);
        exit;
    }
    $open = account_open_orders_buyer($pdo, $buyerId);
    if ($open > 0) {
        $_SESSION['admin_error'] = "Cannot erase — $open order(s) still open. They must be completed, cancelled or refunded first.";
        header('Location: ' . $returnUrl);
        exit;
    }

    if ($acct['suspended']) fingerprint_save($pdo, 'buyer', $buyerId, $acct['suspension_reason']);
    account_erase_buyer($pdo, $buyerId);
    audit_log($pdo, 'buyer.erase', 'buyer', $buyerId, ['was_suspended' => (bool)$acct['suspended']]);
    $_SESSION['admin_success'] = 'Account erased. Orders are kept under "Deleted account".'
        . ($acct['suspended'] ? ' The email and phone fingerprint is kept.' : '');

} elseif ($action === 'match_reviewed') {
    $matchId = (int)($_POST['match_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE account_matches SET reviewed_at = NOW(), reviewed_by = ?
                       WHERE id = ? AND role = 'buyer' AND account_id = ? AND reviewed_at IS NULL")
            ->execute([(int)$_SESSION['admin_id'], $matchId, $buyerId]);
        $_SESSION['admin_success'] = 'Match marked as reviewed.';
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = 'Could not save — has the fingerprint migration been run?';
    }
}

header('Location: ' . $returnUrl);
exit;
