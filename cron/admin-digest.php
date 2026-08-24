<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';

// Daily digest of everything waiting on admin action. Sends one email to
// ADMIN_EMAIL, and only when at least one queue is non-empty.

// Admin pages answer only on the admin subdomain (config/subdomain.php), and
// its BASE_URL_* constants are empty under CLI — so the base is fixed here.
//
// Every link goes through /admin/go.php rather than straight at the page. The
// session cookie is SameSite=Strict, so a click that starts in a mail client
// arrives without it and the admin page bounces you to the login form. go.php
// needs no cookie, then redirects same-site, and that request does carry one.
// The keys below are the ones GO_TARGETS defines — see the top of that file.
$adminBase = 'https://admin.teepsaa.com';
$go        = fn(string $key): string => $adminBase . '/admin/go.php?to=' . $key;
$today     = (new DateTime('now', new DateTimeZone('Asia/Phnom_Penh')))->format('D, M j, Y');

$pendingPayments = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending_confirmation'")->fetchColumn();
$refundRequests  = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$refundsToPay    = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'return_received'")->fetchColumn();
$pendingBiz      = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0 AND deleted_at IS NULL")->fetchColumn();
$unreadSupport   = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor','guest') AND read_at IS NULL")->fetchColumn();
$payoutsDue      = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$spotChecksDue   = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 1 AND deleted_at IS NULL AND approved_at <= NOW() - INTERVAL 7 DAY AND spot_checked_at IS NULL")->fetchColumn();
// Canvassing follow-ups ride along in this digest rather than getting a cron
// and an email of their own — one morning email is easier to actually read.
// A prospect that signed up or said no is finished, whatever date is on it.
$followupsDue    = (int)$pdo->query("SELECT COUNT(*) FROM prospects WHERE next_followup_at IS NOT NULL AND next_followup_at <= CURDATE() AND status NOT IN ('signed_up','not_interested','closed_down')")->fetchColumn();

$rows = [
    ['Payments awaiting confirmation', $pendingPayments, $go('payments')],
    ['Refund requests',                $refundRequests,  $go('refunds')],
    ['Refunds awaiting payment',       $refundsToPay,    $go('refunds-pay')],
    ['Businesses pending approval',    $pendingBiz,      $go('businesses')],
    ['Unread support threads',         $unreadSupport,   $go('messages')],
    ['Payouts due',                    $payoutsDue,      $go('payouts')],
    ['Vendor spot-checks due',         $spotChecksDue,   $go('spot-checks')],
    ['Canvassing follow-ups due',      $followupsDue,    $go('prospects')],
];

$total = $pendingPayments + $refundRequests + $refundsToPay + $pendingBiz + $unreadSupport + $payoutsDue + $spotChecksDue + $followupsDue;
if ($total === 0) {
    exit; // nothing pending — no email today
}

$body = '<table style="border-collapse:collapse;width:100%;max-width:420px">';
foreach ($rows as [$label, $count, $url]) {
    if ($count === 0) continue;
    $body .= '<tr>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb">'
        . '<a href="' . $url . '" style="color:#111827;text-decoration:none">' . $label . '</a></td>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700">' . $count . '</td>'
        . '</tr>';
}
$body .= '</table>';
$body .= '<p style="margin-top:14px;color:#6b7280;font-size:0.85rem">This digest is sent once a day and only when something is waiting.</p>';

$heading = $today . ' — ' . $total . ' item' . ($total === 1 ? '' : 's') . ' waiting for you';
$html    = notification_email_html($heading, $body, 'Open admin dashboard', $go('home'));

send_email(ADMIN_EMAIL, 'teepsaa daily digest ' . $today . ' — ' . $total . ' pending item' . ($total === 1 ? '' : 's'), $html);
