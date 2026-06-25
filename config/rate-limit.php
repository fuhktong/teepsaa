<?php

define('RATE_LIMIT_MAX',     5);   // max failures before lockout
define('RATE_LIMIT_WINDOW',  15);  // minutes to look back
define('RATE_LIMIT_LOCKOUT', 15);  // minutes to lock out

function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function check_rate_limit(PDO $pdo): void {
    $ip      = get_client_ip();
    $cutoff  = date('Y-m-d H:i:s', strtotime('-' . RATE_LIMIT_WINDOW . ' minutes'));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?'
    );
    $stmt->execute([$ip, $cutoff]);

    if ((int)$stmt->fetchColumn() >= RATE_LIMIT_MAX) {
        http_response_code(429);
        // Store a generic error and redirect — caller provides the login URL
        exit('Too many login attempts. Please wait ' . RATE_LIMIT_LOCKOUT . ' minutes and try again.');
    }
}

function record_failed_attempt(PDO $pdo): void {
    $ip = get_client_ip();
    $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);

    // Prune records older than the window to keep the table small
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . RATE_LIMIT_WINDOW . ' minutes'));
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
}
