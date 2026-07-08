<?php
if (!defined('SMTP_PASS')) {
    require_once __DIR__ . '/smtp.php';
}

if (!function_exists('send_email')) {
    function send_email(string $to, string $subject, string $html): void {
        if (!SMTP_PASS) {
            $entry = sprintf("[%s] TO: %s\nSUBJECT: %s\n\n%s\n%s\n",
                date('Y-m-d H:i:s'), $to, $subject, strip_tags($html), str_repeat('-', 60));
            file_put_contents(__DIR__ . '/../mail.log', $entry, FILE_APPEND | LOCK_EX);
            return;
        }

        try {
            smtp_deliver($to, $subject, $html);
        } catch (Exception $e) {
            $entry = sprintf("[%s] SEND FAILED TO: %s\nSUBJECT: %s\nERROR: %s\n%s\n",
                date('Y-m-d H:i:s'), $to, $subject, $e->getMessage(), str_repeat('-', 60));
            file_put_contents(__DIR__ . '/../mail.log', $entry, FILE_APPEND | LOCK_EX);
        }
    }

    function smtp_deliver(string $to, string $subject, string $html): void {
        $sock = stream_socket_client(
            'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['SNI_enabled' => true]])
        );
        if (!$sock) {
            throw new Exception("connect failed: [$errno] $errstr");
        }
        stream_set_timeout($sock, 15);

        try {
            smtp_expect($sock, 220);
            smtp_command($sock, 'EHLO teepsaa.com', 250);
            smtp_command($sock, 'AUTH LOGIN', 334);
            smtp_command($sock, base64_encode(SMTP_USER), 334);
            smtp_command($sock, base64_encode(SMTP_PASS), 235);
            smtp_command($sock, 'MAIL FROM:<' . MAIL_FROM . '>', 250);
            smtp_command($sock, 'RCPT TO:<' . $to . '>', 250);
            smtp_command($sock, 'DATA', 354);

            $headers = 'Date: ' . date('r') . "\r\n"
                . 'From: ' . mime_header(MAIL_FROM_NAME) . ' <' . MAIL_FROM . ">\r\n"
                . 'To: <' . $to . ">\r\n"
                . 'Subject: ' . mime_header($subject) . "\r\n"
                . 'Message-ID: <' . bin2hex(random_bytes(16)) . '@teepsaa.com>' . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n";
            $body = chunk_split(base64_encode($html), 76, "\r\n");

            fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            smtp_expect($sock, 250);
            fwrite($sock, "QUIT\r\n");
        } finally {
            fclose($sock);
        }
    }

    function smtp_command($sock, string $line, int $expect): void {
        fwrite($sock, $line . "\r\n");
        smtp_expect($sock, $expect, $line);
    }

    function smtp_expect($sock, int $expect, string $sent = ''): void {
        $reply = '';
        do {
            $line = fgets($sock, 512);
            if ($line === false) {
                throw new Exception("no response" . ($sent ? " to: $sent" : ''));
            }
            $reply .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        if ((int) substr($reply, 0, 3) !== $expect) {
            // never leak the password into mail.log
            if ($sent === base64_encode(SMTP_PASS)) {
                $sent = '[password]';
            }
            throw new Exception(trim("expected $expect" . ($sent ? " to: $sent" : '') . ", got: " . trim($reply)));
        }
    }

    function mime_header(string $text): string {
        return preg_match('/[^\x20-\x7e]/', $text)
            ? '=?UTF-8?B?' . base64_encode($text) . '?='
            : $text;
    }
}
