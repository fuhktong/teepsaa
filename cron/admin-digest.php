<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

// Daily digest of everything waiting on admin action. Sends one email to
// ADMIN_EMAIL, and only when at least one queue is non-empty.

// Admin pages answer only on the admin subdomain (config/subdomain.php), and
// its BASE_URL_* constants are empty under CLI — so the base is fixed here.
$adminBase = 'https://admin.teepsaa.com';
$today     = (new DateTime('now', new DateTimeZone('Asia/Phnom_Penh')))->format('D, M j, Y');

$pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_confirmation'")->fetchColumn();
$refundRequests  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$refundsToPay    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'return_received'")->fetchColumn();
$pendingBiz      = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0 AND deleted_at IS NULL")->fetchColumn();
$unreadSupport   = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor','guest') AND read_at IS NULL")->fetchColumn();
$payoutsDue      = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$spotChecksDue   = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 1 AND deleted_at IS NULL AND approved_at <= NOW() - INTERVAL 7 DAY AND spot_checked_at IS NULL")->fetchColumn();

$rows = [
    ['Payments awaiting confirmation', $pendingPayments, '/admin/payments.php'],
    ['Refund requests',                $refundRequests,  '/admin/refunds.php?status=refund_requested'],
    ['Refunds awaiting payment',       $refundsToPay,    '/admin/refunds.php?status=return_received'],
    ['Businesses pending approval',    $pendingBiz,      '/admin/?status=pending'],
    ['Unread support threads',         $unreadSupport,   '/admin/messages/'],
    ['Payouts due',                    $payoutsDue,      '/admin/payouts.php'],
    ['Vendor spot-checks due',         $spotChecksDue,   '/admin/?status=spot_check'],
];

$total = $pendingPayments + $refundRequests + $refundsToPay + $pendingBiz + $unreadSupport + $payoutsDue + $spotChecksDue;
if ($total === 0) {
    exit; // nothing pending — no email today
}

$body = '<table style="border-collapse:collapse;width:100%;max-width:420px">';
foreach ($rows as [$label, $count, $path]) {
    if ($count === 0) continue;
    $body .= '<tr>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb">'
        . '<a href="' . $adminBase . $path . '" style="color:#111827;text-decoration:none">' . $label . '</a></td>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700">' . $count . '</td>'
        . '</tr>';
}
$body .= '</table>';
$body .= '<p style="margin-top:14px;color:#6b7280;font-size:0.85rem">This digest is sent once a day and only when something is waiting.</p>';

$heading = $today . ' — ' . $total . ' item' . ($total === 1 ? '' : 's') . ' waiting for you';
$html    = notification_email_html($heading, $body, 'Open admin dashboard', $adminBase . '/admin/');

send_email(ADMIN_EMAIL, 'teepsaa daily digest ' . $today . ' — ' . $total . ' pending item' . ($total === 1 ? '' : 's'), $html);
