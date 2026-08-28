<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/audit.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

// Super-only: this is the oversight tool, so the people it oversees must not
// be able to hold it. admin_can() enforces the same rule.
admin_require('audit');

// ── Filters ───────────────────────────────────────────────────────────────
$fAction = $_GET['action_filter'] ?? '';
$fAdmin  = (int)($_GET['admin'] ?? 0);
$fRisk   = !empty($_GET['risk']);
$fFrom   = $_GET['from'] ?? '';
$fTo     = $_GET['to']   ?? '';
if ($fFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fFrom)) $fFrom = '';
if ($fTo   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fTo))   $fTo   = '';
if ($fAction !== '' && !isset(AUDIT_ACTION_LABELS[$fAction])) $fAction = '';

$where  = [];
$params = [];
if ($fAction !== '') { $where[] = 'action = ?';      $params[] = $fAction; }
if ($fAdmin)         { $where[] = 'admin_id = ?';    $params[] = $fAdmin; }
if ($fFrom !== '')   { $where[] = 'created_at >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo   !== '')   { $where[] = 'created_at <= ?'; $params[] = $fTo   . ' 23:59:59'; }
if ($fRisk) {
    $where[]  = 'action IN (' . implode(',', array_fill(0, count(AUDIT_HIGH_RISK), '?')) . ')';
    $params   = array_merge($params, AUDIT_HIGH_RISK);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage = 100;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("
    SELECT id, admin_id, admin_email, action, entity_type, entity_id, detail, ip, created_at
    FROM admin_audit
    $whereSql
    ORDER BY created_at DESC, id DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$admins = $pdo->query('SELECT id, email FROM admins ORDER BY email')->fetchAll();

// Badge counts for the shared nav
$pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn();
$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$adminSection = 'orders';
$adminTab     = 'audit';

// Where an entity row links to, when it links anywhere.
function audit_entity_url(?string $type, ?int $id): ?string {
    if (!$id) return null;
    return match ($type) {
        'order'    => '/admin/order.php?id=' . $id,
        'business' => '/admin/vendor.php?id=' . $id,
        'vendor'   => '/admin/vendor.php?id=' . $id,
        'admin'    => '/admin/admins.php',
        default    => null,
    };
}

// Render the JSON detail blob as a short readable line.
function audit_detail_text(?string $json): string {
    if (!$json) return '';
    $d = json_decode($json, true);
    if (!is_array($d)) return '';
    $bits = [];
    foreach ($d as $k => $v) {
        if ($v === null || $v === '' || $v === []) continue;
        if (is_bool($v)) $v = $v ? 'yes' : 'no';
        if ($k === 'amount') $v = '$' . number_format((float)$v, 2);
        $bits[] = str_replace('_', ' ', $k) . ': ' . (is_scalar($v) ? (string)$v : json_encode($v));
    }
    return implode(' · ', $bits);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Activity Log</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/audit.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Activity Log</h1>
    <p class="audit-intro">
        Every state-changing admin action, newest first. Append-only — nothing in the
        app edits or deletes these rows.
    </p>

    <form method="GET" action="/admin/audit.php" class="audit-filters">
        <select name="action_filter">
            <option value="">All actions</option>
            <?php foreach (AUDIT_ACTION_LABELS as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $fAction === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="admin">
            <option value="0">All admins</option>
            <?php foreach ($admins as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $fAdmin === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['email']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?= htmlspecialchars($fFrom) ?>" class="admin-date-input">
        <span class="admin-date-sep">to</span>
        <input type="date" name="to" value="<?= htmlspecialchars($fTo) ?>" class="admin-date-input">
        <label class="audit-risk-toggle">
            <input type="checkbox" name="risk" value="1" <?= $fRisk ? 'checked' : '' ?>> High-risk only
        </label>
        <button type="submit" class="acct-filter-btn">Apply</button>
        <?php if ($fAction || $fAdmin || $fFrom || $fTo || $fRisk): ?>
        <a href="/admin/audit.php" class="admin-filter-clear">Clear</a>
        <?php endif; ?>
    </form>

    <p class="audit-count"><?= number_format($totalRows) ?> entr<?= $totalRows === 1 ? 'y' : 'ies' ?></p>

    <?php if (!$rows): ?>
        <p class="empty">No activity recorded yet.</p>
    <?php else: ?>
    <div class="audit-table-wrap">
        <table class="audit-table">
            <thead>
                <tr><th>When</th><th>Who</th><th>What</th><th>Where</th><th>Detail</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <?php
                $isRisk = in_array($r['action'], AUDIT_HIGH_RISK, true);
                $url    = audit_entity_url($r['entity_type'], $r['entity_id'] ? (int)$r['entity_id'] : null);
                ?>
                <tr class="<?= $isRisk ? 'audit-row--risk' : '' ?>">
                    <td class="audit-when"><?= date('M j, Y g:ia', strtotime($r['created_at'])) ?></td>
                    <td><?= htmlspecialchars($r['admin_email'] ?? 'system') ?></td>
                    <td>
                        <?= htmlspecialchars(audit_label($r['action'])) ?>
                        <?php if ($isRisk): ?><span class="audit-risk-flag">review</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($url): ?>
                            <a href="<?= $url ?>"><?= htmlspecialchars($r['entity_type']) ?> #<?= (int)$r['entity_id'] ?></a>
                        <?php elseif ($r['entity_type']): ?>
                            <?= htmlspecialchars($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . (int)$r['entity_id'] : '' ?>
                        <?php endif; ?>
                    </td>
                    <td class="audit-detail"><?= htmlspecialchars(audit_detail_text($r['detail'])) ?></td>
                    <td class="audit-ip"><?= htmlspecialchars($r['ip'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <?php $qs = fn(int $pg): string => '?' . http_build_query(array_filter([
        'action_filter' => $fAction, 'admin' => $fAdmin ?: null,
        'from' => $fFrom, 'to' => $fTo, 'risk' => $fRisk ? 1 : null, 'page' => $pg,
    ])); ?>
    <nav class="audit-pager">
        <?php if ($page > 1): ?><a href="<?= $qs($page - 1) ?>">← Newer</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= $qs($page + 1) ?>">Older →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
