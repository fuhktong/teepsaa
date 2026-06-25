<?php
if (!defined('RESEND_API_KEY')) {
    require_once __DIR__ . '/resend.php';
}

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $html): void {
        if (!RESEND_API_KEY) {
            $entry = sprintf("[%s] TO: %s\nSUBJECT: %s\n\n%s\n%s\n",
                date('Y-m-d H:i:s'), $to, $subject, strip_tags($html), str_repeat('-', 60));
            file_put_contents(__DIR__ . '/../mail.log', $entry, FILE_APPEND | LOCK_EX);
            return;
        }

        $from    = MAIL_FROM_NAME . ' <' . MAIL_FROM . '>';
        $payload = json_encode(['from' => $from, 'to' => [$to], 'subject' => $subject, 'html' => $html]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . RESEND_API_KEY,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
