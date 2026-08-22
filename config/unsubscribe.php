<?php
// Marketing opt-out. Consulted only by promotional email — announcements and
// the abandoned-cart nudge. Transactional mail (orders, refunds, password
// resets, account notices) ignores it entirely: those aren't something an
// account holder can opt out of and still use the site.

require_once __DIR__ . '/app.php';

function unsubscribe_table(string $role): string {
    return $role === 'vendor' ? 'vendors' : 'buyers';
}

// Returns the account's opt-out token, minting one on first use.
// Null when the account row is gone.
function unsubscribe_token(PDO $pdo, string $role, int $userId): ?string {
    $table = unsubscribe_table($role);
    $stmt  = $pdo->prepare("SELECT unsubscribe_token FROM {$table} WHERE id = ?");
    $stmt->execute([$userId]);
    $tok = $stmt->fetchColumn();
    if ($tok === false) return null;
    if ($tok) return $tok;

    $tok = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE {$table} SET unsubscribe_token = ? WHERE id = ?")->execute([$tok, $userId]);
    return $tok;
}

// The full link to put in an email, or null when the account has vanished.
function unsubscribe_link(PDO $pdo, string $role, int $userId): ?string {
    $tok = unsubscribe_token($pdo, $role, $userId);
    return $tok ? unsubscribe_url($role, $tok) : null;
}

function unsubscribe_url(string $role, string $token): string {
    return SITE_URL . '/unsubscribe/?r=' . ($role === 'vendor' ? 'v' : 'b') . '&t=' . urlencode($token);
}

// True when this account has opted out of promotional email.
function is_unsubscribed(PDO $pdo, string $role, int $userId): bool {
    $table = unsubscribe_table($role);
    $stmt  = $pdo->prepare("SELECT unsubscribed_at FROM {$table} WHERE id = ?");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

// Extra footer line, shown under the standard "do not reply" note.
function unsubscribe_footer_html(string $url): string {
    $u = htmlspecialchars($url);
    return 'អ្នកទទួលបានអ៊ីមែលនេះព្រោះអ្នកមានគណនីនៅ teepsaa។ '
        . '<a href="' . $u . '" style="color:#888">ឈប់ទទួលអ៊ីមែលផ្សព្វផ្សាយ</a><br>'
        . 'You received this because you have a teepsaa account. '
        . '<a href="' . $u . '" style="color:#888">Unsubscribe from promotional emails</a>';
}
