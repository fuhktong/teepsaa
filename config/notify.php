<?php
require_once __DIR__ . '/mail.php';

// $message is the English fallback (shown for old rows or unknown types).
// $data holds the dynamic parts (order ref, product name…) so the notification
// can be re-rendered in the reader's current language at display time.
function notify(PDO $pdo, string $role, int $userId, string $type, string $message, ?string $link = null, ?array $data = null): void {
    $pdo->prepare('INSERT INTO notifications (role, user_id, type, message, data, link) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$role, $userId, $type, $message, $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null, $link]);
}

// Render a notification row in the given language. Falls back to the stored
// English $message when there's no template or the params are missing.
function notification_text(array $row, array $t): string {
    $type = $row['type'] ?? '';
    $key  = 'notif_' . $type;
    if (!isset($t[$key])) return $row['message'] ?? '';
    $data = !empty($row['data']) ? (json_decode($row['data'], true) ?: []) : [];

    switch ($type) {
        case 'new_order':
        case 'payout_sent':
        case 'payment_confirmed':
        case 'order_dispatched':
        case 'delivery_confirmed':
        case 'review_reminder':
        case 'refund_requested':
        case 'refund_approved':
        case 'refund_rejected':
        case 'refund_sent':
        case 'return_dispatched':
        case 'return_received':
            return isset($data['ref']) ? sprintf($t[$key], $data['ref']) : ($row['message'] ?? '');
        case 'low_stock':
            return isset($data['name']) ? sprintf($t[$key], $data['name'], $data['units'] ?? 0) : ($row['message'] ?? '');
        case 'abandoned_cart':
            return $t[$key];
        default:
            return $row['message'] ?? '';
    }
}

function order_display_id(int $id, string $createdAt): string {
    return date('ymd', strtotime($createdAt)) . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

function notification_email_html(string $heading, string $body, ?string $ctaText = null, ?string $ctaUrl = null): string {
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<p style="margin:24px 0 0"><a href="' . htmlspecialchars($ctaUrl) . '" '
            . 'style="display:inline-block;background:#2d3a6b;color:#fff;padding:10px 22px;border-radius:6px;'
            . 'text-decoration:none;font-size:0.9rem;font-weight:700">'
            . htmlspecialchars($ctaText) . '</a></p>';
    }
    return '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#333;max-width:580px;margin:0 auto;padding:24px 16px">'
        . '<div style="border-bottom:2px solid #2d3a6b;padding-bottom:14px;margin-bottom:22px">'
        . '<strong style="font-size:1.15rem;color:#2d3a6b">teepsaa</strong></div>'
        . '<h2 style="font-size:1.05rem;font-weight:700;margin:0 0 12px;color:#111">' . htmlspecialchars($heading) . '</h2>'
        . '<p style="line-height:1.65;margin:0;color:#444">' . $body . '</p>'
        . $cta
        . '<p style="font-size:0.78rem;color:#aaa;margin-top:36px;border-top:1px solid #eee;padding-top:14px">'
        . 'This is an automated message from teepsaa. Please do not reply.</p>'
        . '</body></html>';
}

// Bilingual email: Khmer block on top, English block below, one shared CTA
// button (Khmer · English) and a bilingual footer. Bodies are raw HTML.
function notification_email_html_bi(
    string $headingKm, string $bodyKm,
    string $headingEn, string $bodyEn,
    ?string $ctaKm = null, ?string $ctaEn = null, ?string $ctaUrl = null
): string {
    $cta = '';
    if ($ctaUrl && ($ctaKm || $ctaEn)) {
        $label = trim(($ctaKm ?? '') . (($ctaKm && $ctaEn) ? '  ·  ' : '') . ($ctaEn ?? ''));
        $cta = '<p style="margin:24px 0 0"><a href="' . htmlspecialchars($ctaUrl) . '" '
            . 'style="display:inline-block;background:#2d3a6b;color:#fff;padding:10px 22px;border-radius:6px;'
            . 'text-decoration:none;font-size:0.9rem;font-weight:700">'
            . htmlspecialchars($label) . '</a></p>';
    }
    $block = function (string $heading, string $body): string {
        return '<h2 style="font-size:1.05rem;font-weight:700;margin:0 0 12px;color:#111">' . htmlspecialchars($heading) . '</h2>'
            . '<p style="line-height:1.65;margin:0;color:#444">' . $body . '</p>';
    };
    return '<!DOCTYPE html><html><body style="font-family:Arial,\'Khmer OS\',sans-serif;color:#333;max-width:580px;margin:0 auto;padding:24px 16px">'
        . '<div style="border-bottom:2px solid #2d3a6b;padding-bottom:14px;margin-bottom:22px">'
        . '<strong style="font-size:1.15rem;color:#2d3a6b">teepsaa</strong></div>'
        . $block($headingKm, $bodyKm)
        . '<hr style="border:none;border-top:1px solid #eee;margin:22px 0">'
        . $block($headingEn, $bodyEn)
        . $cta
        . '<p style="font-size:0.78rem;color:#aaa;margin-top:36px;border-top:1px solid #eee;padding-top:14px">'
        . 'នេះជាសារស្វ័យប្រវត្តិពីទីផ្សារ។ សូមកុំឆ្លើយតប។<br>This is an automated message from teepsaa. Please do not reply.</p>'
        . '</body></html>';
}

// Compose a bilingual email subject line: "Khmer · English".
function email_subject_bi(string $km, string $en): string {
    return $km . '  ·  ' . $en;
}

// Built-in default email templates (source of truth + fallback).
function email_templates_defaults(): array {
    static $d = null;
    if ($d === null) $d = require __DIR__ . '/email-templates.php';
    return $d;
}

// Render a (staff-editable) email template into [subject, html]. Loads the
// editable row from `email_templates`, falling back to the built-in default
// so a missing/failed lookup never breaks a send. {tokens} in every field are
// replaced with values from $data; the button link comes from $data['cta_url'].
// Returns ['', ''] for an unknown key so callers can skip sending.
function render_email_template(PDO $pdo, string $key, array $data = []): array {
    $tpl = null;
    try {
        $stmt = $pdo->prepare('SELECT * FROM email_templates WHERE template_key = ?');
        $stmt->execute([$key]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $tpl = null;
    }
    if (!$tpl) {
        $defs = email_templates_defaults();
        if (!isset($defs[$key])) return ['', ''];
        $tpl = $defs[$key];
    }

    $sub = function (?string $s) use ($data): string {
        if ($s === null || $s === '') return '';
        foreach ($data as $k => $v) {
            $s = str_replace('{' . $k . '}', (string)$v, $s);
        }
        return $s;
    };

    $subject = email_subject_bi($sub($tpl['subject_km']), $sub($tpl['subject_en']));
    $html = notification_email_html_bi(
        $sub($tpl['heading_km']), $sub($tpl['body_km']),
        $sub($tpl['heading_en']), $sub($tpl['body_en']),
        $sub($tpl['cta_km'] ?? null) ?: null,
        $sub($tpl['cta_en'] ?? null) ?: null,
        $data['cta_url'] ?? null
    );
    return [$subject, $html];
}
