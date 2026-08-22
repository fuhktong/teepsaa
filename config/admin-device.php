<?php
// "Remember this device" for admin login. See database/migration-admin-devices.sql.
//
// Why it exists: canvassing runs from a phone, and an iOS home-screen app has
// its own cookie jar that gets emptied whenever the app is closed for long
// enough. Retyping an admin password on a pavement is the fastest way to stop
// using the tool. This keeps one device signed in for 30 days.
//
// The cookie is only ever honoured inside the admin area, and only creates a
// session when there is none — see the tail of config/admin-auth.php.

const ADMIN_DEVICE_COOKIE     = 'teepsaa_admin_device';
const ADMIN_DEVICE_DAYS       = 30;
const ADMIN_DEVICE_GRACE_SECS = 120;   // how long a just-rotated validator stays valid

function admin_device_cookie_params(int $expires): array {
    return [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ];
}

function admin_device_set_cookie(string $selector, string $validator, int $expires): void {
    if (headers_sent()) return;
    setcookie(ADMIN_DEVICE_COOKIE, $selector . ':' . $validator, admin_device_cookie_params($expires));
    $_COOKIE[ADMIN_DEVICE_COOKIE] = $selector . ':' . $validator;
}

function admin_device_clear_cookie(): void {
    if (!headers_sent()) {
        setcookie(ADMIN_DEVICE_COOKIE, '', admin_device_cookie_params(time() - 3600));
    }
    unset($_COOKIE[ADMIN_DEVICE_COOKIE]);
}

// Splits the cookie into its two halves, or null if it is not the right shape.
function admin_device_parse_cookie(): ?array {
    $raw = (string)($_COOKIE[ADMIN_DEVICE_COOKIE] ?? '');
    if (substr_count($raw, ':') !== 1) return null;
    [$selector, $validator] = explode(':', $raw, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector))  return null;
    if (!preg_match('/^[a-f0-9]{64}$/', $validator)) return null;
    return [$selector, $validator];
}

// A human name for the device list. Deliberately coarse — it only has to be
// enough to tell "my iPhone" from "the office laptop".
function admin_device_label(): string {
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $os = match (true) {
        str_contains($ua, 'iPhone')          => 'iPhone',
        str_contains($ua, 'iPad')            => 'iPad',
        str_contains($ua, 'Android')         => 'Android',
        str_contains($ua, 'Macintosh')       => 'Mac',
        str_contains($ua, 'Windows')         => 'Windows',
        default                              => 'Unknown device',
    };
    $browser = match (true) {
        str_contains($ua, 'Edg/')            => 'Edge',
        str_contains($ua, 'Chrome/')         => 'Chrome',
        str_contains($ua, 'Firefox/')        => 'Firefox',
        str_contains($ua, 'Safari/')         => 'Safari',
        default                              => '',
    };
    return $browser !== '' ? $os . ' · ' . $browser : $os;
}

function admin_device_ip(): string {
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

// Called from the login handler when the box is ticked.
function admin_device_issue(PDO $pdo, int $adminId): void {
    $selector  = bin2hex(random_bytes(12));   // 24 hex chars
    $validator = bin2hex(random_bytes(32));   // 64 hex chars
    $expires   = time() + ADMIN_DEVICE_DAYS * 86400;

    $pdo->prepare(
        'INSERT INTO admin_devices (admin_id, selector, validator_hash, label, user_agent, ip, last_used_at, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
    )->execute([
        $adminId,
        $selector,
        hash('sha256', $validator),
        admin_device_label(),
        mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        admin_device_ip(),
        date('Y-m-d H:i:s', $expires),
    ]);

    admin_device_set_cookie($selector, $validator, $expires);
}

// Rebuilds the admin session from the cookie. Returns true when it signed
// someone in. Only ever called with no admin session present.
function admin_device_restore(PDO $pdo): bool {
    $parsed = admin_device_parse_cookie();
    if (!$parsed) {
        if (isset($_COOKIE[ADMIN_DEVICE_COOKIE])) admin_device_clear_cookie();
        return false;
    }
    [$selector, $validator] = $parsed;

    $stmt = $pdo->prepare(
        'SELECT d.*, a.admin_role, a.is_active
           FROM admin_devices d
           JOIN admins a ON a.id = d.admin_id
          WHERE d.selector = ?'
    );
    $stmt->execute([$selector]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$d) {
        admin_device_clear_cookie();
        return false;
    }

    if (strtotime($d['expires_at']) < time() || !$d['is_active']) {
        $pdo->prepare('DELETE FROM admin_devices WHERE id = ?')->execute([$d['id']]);
        admin_device_clear_cookie();
        return false;
    }

    $given = hash('sha256', $validator);
    $ok    = hash_equals($d['validator_hash'], $given);

    // A validator we rotated away from very recently is still accepted — that
    // is a second request from the same device overlapping the first, not an
    // attack. Outside the grace window it is treated as one.
    if (!$ok && $d['prev_validator_hash'] !== null && $d['rotated_at'] !== null
        && (time() - strtotime($d['rotated_at'])) <= ADMIN_DEVICE_GRACE_SECS) {
        $ok = hash_equals($d['prev_validator_hash'], $given);
    }

    if (!$ok) {
        // A real selector with the wrong validator means the cookie was copied.
        // Drop every remembered device for that admin, not just this one.
        $pdo->prepare('DELETE FROM admin_devices WHERE admin_id = ?')->execute([$d['admin_id']]);
        admin_device_clear_cookie();
        error_log('admin_device: validator mismatch for admin ' . $d['admin_id'] . ' — all devices revoked');
        return false;
    }

    // Rotate, and push the expiry out so a device in daily use never lapses.
    $new     = bin2hex(random_bytes(32));
    $expires = time() + ADMIN_DEVICE_DAYS * 86400;
    $pdo->prepare(
        'UPDATE admin_devices
            SET prev_validator_hash = validator_hash, rotated_at = NOW(),
                validator_hash = ?, last_used_at = NOW(), expires_at = ?, ip = ?
          WHERE id = ?'
    )->execute([hash('sha256', $new), date('Y-m-d H:i:s', $expires), admin_device_ip(), $d['id']]);
    admin_device_set_cookie($selector, $new, $expires);

    $permissions = [];
    if ($d['admin_role'] !== 'super') {
        $permStmt = $pdo->prepare('SELECT section FROM admin_permissions WHERE admin_id = ?');
        $permStmt->execute([$d['admin_id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']          = (int)$d['admin_id'];
    $_SESSION['admin_role']        = $d['admin_role'];
    $_SESSION['admin_permissions'] = $permissions;
    // Flags a session that came from a cookie rather than a typed password.
    $_SESSION['admin_restored']    = true;
    if (empty($_SESSION['user_id'])) {
        $_SESSION['lang'] = 'en';
    }
    return true;
}

// Logging out forgets only the device you are logging out from.
function admin_device_forget(PDO $pdo): void {
    $parsed = admin_device_parse_cookie();
    if ($parsed) {
        $pdo->prepare('DELETE FROM admin_devices WHERE selector = ?')->execute([$parsed[0]]);
    }
    admin_device_clear_cookie();
}

function admin_device_list(PDO $pdo, int $adminId): array {
    $stmt = $pdo->prepare(
        'SELECT * FROM admin_devices
          WHERE admin_id = ? AND expires_at > NOW()
          ORDER BY last_used_at DESC, id DESC'
    );
    $stmt->execute([$adminId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Which row in that list is the browser you are reading it in.
function admin_device_current_selector(): string {
    $parsed = admin_device_parse_cookie();
    return $parsed ? $parsed[0] : '';
}

function admin_device_revoke(PDO $pdo, int $adminId, int $deviceId): void {
    $stmt = $pdo->prepare('SELECT selector FROM admin_devices WHERE id = ? AND admin_id = ?');
    $stmt->execute([$deviceId, $adminId]);
    $selector = $stmt->fetchColumn();
    if ($selector === false) return;

    $pdo->prepare('DELETE FROM admin_devices WHERE id = ?')->execute([$deviceId]);
    if ($selector === admin_device_current_selector()) {
        admin_device_clear_cookie();
    }
}

function admin_device_revoke_others(PDO $pdo, int $adminId): int {
    $current = admin_device_current_selector();
    $stmt = $pdo->prepare('DELETE FROM admin_devices WHERE admin_id = ? AND selector <> ?');
    $stmt->execute([$adminId, $current]);
    return $stmt->rowCount();
}
