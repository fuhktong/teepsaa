<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php';
require __DIR__ . '/../config/audit.php';

// Yesterday's completed admin actions — the counterpart to cron/admin-digest.php.
//
// admin-digest.php answers "what is waiting for me". This one answers "what
// was done, and by whom". The distinction matters: a to-do list gets QUIETER
// when someone works through the queue, including someone working through it
// fraudulently. Money leaving the business should generate mail, not silence.
//
// Runs daily. Suggested cron (Asia/Phnom_Penh):
//   10 7 * * *  php /home/USER/domains/teepsaa.com/public_html/cron/admin-activity-digest.php

$tz    = new DateTimeZone('Asia/Phnom_Penh');
$start = (new DateTime('yesterday 00:00:00', $tz))->format('Y-m-d H:i:s');
$end   = (new DateTime('yesterday 23:59:59', $tz))->format('Y-m-d H:i:s');
$label = (new DateTime('yesterday', $tz))->format('D, M j, Y');

$stmt = $pdo->prepare('
    SELECT id, admin_id, admin_email, action, entity_type, entity_id, detail, ip, created_at
    FROM admin_audit
    WHERE created_at BETWEEN ? AND ?
    ORDER BY created_at ASC
');
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll();

if (!$rows) {
    exit; // a quiet day is not worth an email
}

// Links go through /admin/go.php for the same SameSite=Strict reason
// cron/admin-digest.php does — a click straight from an email arrives with no
// session cookie and bounces to the login form.
$adminBase = 'https://admin.teepsaa.com';
$auditUrl  = $adminBase . '/admin/go.php?to=audit';

// Money actually moved, split by direction. This is the number to read first.
$paidOut  = 0.0;
$paidCount = 0;
$refunded = 0.0;
$refundCount = 0;
$confirmed = 0.0;
$confirmedCount = 0;

$highRisk = [];
$byAction = [];

foreach ($rows as $r) {
    $d = $r['detail'] ? (json_decode($r['detail'], true) ?: []) : [];

    switch ($r['action']) {
        case 'payout.complete':
            $paidOut += (float)($d['amount'] ?? 0); $paidCount++; break;
        case 'refund.complete':
            $refunded += (float)($d['amount'] ?? 0); $refundCount++; break;
        case 'payment.confirm':
            $confirmed += (float)($d['total'] ?? 0); $confirmedCount++; break;
    }

    if (in_array($r['action'], AUDIT_HIGH_RISK, true)) {
        $highRisk[] = $r;
    }

    $key = $r['action'] . '|' . ($r['admin_email'] ?? 'system');
    $byAction[$key] = ($byAction[$key] ?? 0) + 1;
}

$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
$money = fn(float $v) => '$' . number_format($v, 2);

$body = '';

// ── Money ─────────────────────────────────────────────────────────────────
$body .= '<table style="border-collapse:collapse;width:100%;max-width:460px;margin-bottom:20px">';
$moneyRows = [
    ['Paid out to vendors', $paidCount,      $paidOut,   '#b91c1c'],
    ['Refunded to buyers',  $refundCount,    $refunded,  '#b91c1c'],
    ['Payments confirmed',  $confirmedCount, $confirmed, '#15803d'],
];
foreach ($moneyRows as [$lbl, $n, $amt, $colour]) {
    if ($n === 0) continue;
    $body .= '<tr>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb">' . $esc($lbl)
        . ' <span style="color:#9ca3af">(' . $n . ')</span></td>'
        . '<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:right;font-weight:700;color:' . $colour . '">'
        . $esc($money($amt)) . '</td></tr>';
}
$body .= '</table>';

// ── Needs a look ──────────────────────────────────────────────────────────
if ($highRisk) {
    $body .= '<div style="border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:12px 14px;margin-bottom:20px">'
        . '<strong style="color:#92400e;display:block;margin-bottom:8px">Worth a look</strong>'
        . '<ul style="margin:0;padding-left:18px;color:#444;font-size:0.88rem;line-height:1.6">';
    foreach ($highRisk as $r) {
        $d      = $r['detail'] ? (json_decode($r['detail'], true) ?: []) : [];
        $amount = isset($d['amount']) ? ' — ' . $money((float)$d['amount']) : '';
        $entity = $r['entity_type'] ? ' (' . $r['entity_type'] . ' #' . (int)$r['entity_id'] . ')' : '';
        $body .= '<li>' . $esc(audit_label($r['action'])) . $esc($entity) . $esc($amount)
            . '<br><span style="color:#6b7280;font-size:0.82rem">'
            . $esc($r['admin_email'] ?? 'system') . ' · ' . date('g:ia', strtotime($r['created_at']))
            . '</span></li>';
    }
    $body .= '</ul></div>';
}

// ── Everything else ───────────────────────────────────────────────────────
ksort($byAction);
$body .= '<strong style="display:block;margin-bottom:8px;color:#111">All activity</strong>';
$body .= '<table style="border-collapse:collapse;width:100%;max-width:460px">';
foreach ($byAction as $key => $count) {
    [$action, $who] = explode('|', $key, 2);
    $body .= '<tr>'
        . '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;font-size:0.88rem">'
        . $esc(audit_label($action))
        . '<br><span style="color:#9ca3af;font-size:0.8rem">' . $esc($who) . '</span></td>'
        . '<td style="padding:6px 12px;border-bottom:1px solid #f3f4f6;text-align:right;font-weight:700">' . $count . '</td>'
        . '</tr>';
}
$body .= '</table>';

$total   = count($rows);
$heading = $label . ' — ' . $total . ' admin action' . ($total === 1 ? '' : 's');
$subject = 'teepsaa activity ' . $label . ' — ' . $money($paidOut) . ' paid out'
    . ($highRisk ? ', ' . count($highRisk) . ' to review' : '');

$html = notification_email_html($heading, $body, 'Open the activity log', $auditUrl);

send_email(ADMIN_EMAIL, $subject, $html);
