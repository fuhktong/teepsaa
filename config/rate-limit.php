<?php

define('RATE_LIMIT_MAX',     5);   // max failures per account before lockout
define('RATE_LIMIT_IP_MAX',  30);  // max failures per IP before lockout
define('RATE_LIMIT_WINDOW',  15);  // minutes to look back

function get_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * $kind separates the flows. Without it a burst of job applications or password
 * resets from one IP would spend the same budget as login, and lock that IP out
 * of signing in — the flows share nothing but an address.
 *
 * $identifier is the account being tried (an email, or "buyer:12"). It is the
 * bucket that actually stops a targeted brute force, and it works even when
 * everyone arrives on one address. The IP bucket is a much looser backstop for
 * that reason: a NAT or a proxy can legitimately produce a lot of failures, so
 * spending five of them should not lock out an office or a whole mobile carrier.
 */
function check_rate_limit(PDO $pdo, string $kind = 'login', string $identifier = ''): void {
    $cutoff     = date('Y-m-d H:i:s', strtotime('-' . RATE_LIMIT_WINDOW . ' minutes'));
    $identifier = rate_limit_key($identifier);

    if ($identifier !== '') {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE kind = ? AND identifier = ? AND attempted_at >= ?'
        );
        $stmt->execute([$kind, $identifier, $cutoff]);
        if ((int)$stmt->fetchColumn() >= RATE_LIMIT_MAX) rate_limit_stop();
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE kind = ? AND ip = ? AND attempted_at >= ?'
    );
    $stmt->execute([$kind, get_client_ip(), $cutoff]);
    if ((int)$stmt->fetchColumn() >= RATE_LIMIT_IP_MAX) rate_limit_stop();
}

// The identifier is raw user input at the login and reset endpoints, and the
// column is VARCHAR(191) — an over-long value would abort the INSERT under
// strict mode. Lowercased so "A@b.com" and "a@b.com" share one bucket.
function rate_limit_key(string $identifier): string {
    return mb_substr(mb_strtolower($identifier), 0, 191);
}

function rate_limit_stop(): void {
    http_response_code(429);
    // The window rolls, so the wait is however long ago the oldest failure was —
    // RATE_LIMIT_WINDOW is the worst case and the only honest number to quote.
    exit('Too many attempts. Please wait ' . RATE_LIMIT_WINDOW . ' minutes and try again.');
}

function record_failed_attempt(PDO $pdo, string $kind = 'login', string $identifier = ''): void {
    $pdo->prepare('INSERT INTO login_attempts (ip, kind, identifier) VALUES (?, ?, ?)')
        ->execute([get_client_ip(), $kind, rate_limit_key($identifier)]);

    // Prune records older than the window to keep the table small
    $cutoff = date('Y-m-d H:i:s', strtotime('-' . RATE_LIMIT_WINDOW . ' minutes'));
    $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$cutoff]);
}
