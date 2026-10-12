<?php
// Recognising someone who was suspended and comes back with a new account.
//
// When an account is suspended, its email and phone are kept as fingerprints:
// a keyed hash (HMAC-SHA256) of each, never the email or phone itself. A new
// sign-up, or an account that adds or changes a phone, is checked against them,
// and a match is recorded in account_matches for an admin to look at. Nothing
// is blocked automatically — a shared family phone or a recycled number is
// a real possibility, so a person decides.
//
// The fingerprints outlive the account. That is the point: a suspended account
// erased by an admin (config/account-erase.php) still leaves them behind, and
// the privacy policy says so. Lifting a suspension forgets them, because the
// account is in good standing again.
//
// Why keyed and not a plain hash: Cambodian phone numbers are short enough that
// anyone with the table could hash every possible number in an afternoon and
// read the plain hash back. With the key, the table alone gives nothing away.
// The key lives only on the server in config/fingerprint-key.php (excluded from
// the deploy and from git, like db.php). Lose it and every saved fingerprint
// stops matching; a copy is kept in teepsaa-private/.
//
// Every function here swallows database errors. Checking a fingerprint must
// never be the reason a sign-up or a suspension fails — including before the
// migration has been run.

const FINGERPRINT_KEY_FILE = __DIR__ . '/fingerprint-key.php';

function fingerprint_key(): ?string {
    static $key = false;
    if ($key === false) {
        $key = is_file(FINGERPRINT_KEY_FILE) ? (string)(require FINGERPRINT_KEY_FILE) : '';
        if ($key === '') error_log('fingerprint: config/fingerprint-key.php missing, fingerprints are off');
    }
    return $key === '' ? null : $key;
}

// The same address written differently must give the same fingerprint:
// case, a +tag, and dots in a Gmail name all reach the same inbox.
function fingerprint_normalise_email(string $email): string {
    $email = strtolower(trim($email));
    if (!str_contains($email, '@')) return '';
    [$local, $domain] = explode('@', $email, 2);
    $local = explode('+', $local, 2)[0];
    if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
        $local  = str_replace('.', '', $local);
        $domain = 'gmail.com';
    }
    return $local === '' ? '' : $local . '@' . $domain;
}

// Digits only, with +855 / 855 folded to the leading 0 it stands for.
function fingerprint_normalise_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '855')) $digits = '0' . substr($digits, 3);
    return strlen($digits) >= 8 ? $digits : '';
}

function fingerprint_hash(string $kind, ?string $value): ?string {
    $key = fingerprint_key();
    if ($key === null || $value === null) return null;
    $norm = $kind === 'email' ? fingerprint_normalise_email($value) : fingerprint_normalise_phone($value);
    return $norm === '' ? null : hash_hmac('sha256', $kind . ':' . $norm, $key);
}

// The account's current email and phone, as [kind => hash], blanks dropped.
function fingerprint_hashes(?string $email, ?string $phone): array {
    return array_filter([
        'email' => fingerprint_hash('email', $email),
        'phone' => fingerprint_hash('phone', $phone),
    ]);
}

function fingerprint_account_details(PDO $pdo, string $role, int $id): ?array {
    $table = $role === 'vendor' ? 'vendors' : 'buyers';
    $stmt  = $pdo->prepare("SELECT email, phone FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

// Called on suspension, and again just before a suspended account is erased in
// case the details changed in between.
function fingerprint_save(PDO $pdo, string $role, int $id, ?string $reason): void {
    try {
        $row = fingerprint_account_details($pdo, $role, $id);
        if (!$row) return;
        $stmt = $pdo->prepare('
            INSERT INTO account_fingerprints (role, account_id, kind, hash, reason)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE reason = VALUES(reason)
        ');
        foreach (fingerprint_hashes($row['email'], $row['phone']) as $kind => $hash) {
            $stmt->execute([$role, $id, $kind, $hash, $reason !== null ? mb_substr($reason, 0, 255) : null]);
        }
    } catch (PDOException $e) {
        error_log('fingerprint_save: ' . $e->getMessage());
    }
}

// Lifting a suspension. The matches recorded against these fingerprints go
// with them (ON DELETE CASCADE).
function fingerprint_forget(PDO $pdo, string $role, int $id): void {
    try {
        $pdo->prepare('DELETE FROM account_fingerprints WHERE role = ? AND account_id = ?')->execute([$role, $id]);
    } catch (PDOException $e) {
        error_log('fingerprint_forget: ' . $e->getMessage());
    }
}

// Called after a sign-up and after a phone is saved. Pass whatever the caller
// has; null leaves that kind unchecked. Matches across roles count — a vendor
// suspended for fraud who comes back as a buyer is worth knowing about. An
// account never matches its own fingerprints.
function fingerprint_check(PDO $pdo, string $role, int $id, ?string $email, ?string $phone): void {
    try {
        $hashes = fingerprint_hashes($email, $phone);
        if (!$hashes) return;

        $find = $pdo->prepare('
            SELECT id FROM account_fingerprints
             WHERE kind = ? AND hash = ? AND NOT (role = ? AND account_id = ?)
        ');
        $record = $pdo->prepare('INSERT IGNORE INTO account_matches (role, account_id, fingerprint_id) VALUES (?, ?, ?)');
        foreach ($hashes as $kind => $hash) {
            $find->execute([$kind, $hash, $role, $id]);
            foreach ($find->fetchAll(PDO::FETCH_COLUMN) as $fpId) {
                $record->execute([$role, $id, $fpId]);
            }
        }
    } catch (PDOException $e) {
        error_log('fingerprint_check: ' . $e->getMessage());
    }
}

// What the admin account page shows: every suspended account this one matched.
function fingerprint_matches_for(PDO $pdo, string $role, int $id): array {
    try {
        $stmt = $pdo->prepare('
            SELECT m.id, m.created_at, m.reviewed_at, f.kind, f.role AS old_role, f.account_id AS old_id, f.reason
              FROM account_matches m
              JOIN account_fingerprints f ON f.id = m.fingerprint_id
             WHERE m.role = ? AND m.account_id = ?
             ORDER BY m.created_at DESC
        ');
        $stmt->execute([$role, $id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function fingerprint_unreviewed_count(PDO $pdo): int {
    try {
        return (int)$pdo->query('SELECT COUNT(*) FROM account_matches WHERE reviewed_at IS NULL')->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
