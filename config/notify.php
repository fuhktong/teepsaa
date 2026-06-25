<?php
require_once __DIR__ . '/mail.php';

function notify(PDO $pdo, string $role, int $userId, string $type, string $message, ?string $link = null): void {
    $pdo->prepare('INSERT INTO notifications (role, user_id, type, message, link) VALUES (?, ?, ?, ?, ?)')
        ->execute([$role, $userId, $type, $message, $link]);
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
