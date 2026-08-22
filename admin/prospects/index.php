<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/prospects.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('prospects');

$adminSection = 'marketing';
$adminTab     = 'prospects';

$statusFilter = isset($_GET['status']) && isset(PROSPECT_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$q            = trim((string)($_GET['q'] ?? ''));
$sort         = $_GET['sort'] ?? 'recent';

// Supplied by the "Sort by nearest" button in /js/geo-capture.js.
$myLat = isset($_GET['lat']) && $_GET['lat'] !== '' ? (float)$_GET['lat'] : null;
$myLng = isset($_GET['lng']) && $_GET['lng'] !== '' ? (float)$_GET['lng'] : null;
if ($myLat === null || $myLng === null) {
    $myLat = $myLng = null;
    if ($sort === 'near') $sort = 'recent';
}

$where  = [];
$params = [];
if ($statusFilter !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $statusFilter;
}
if ($q !== '') {
    $where[] = '(p.business_name LIKE ? OR p.business_name_km LIKE ? OR p.owner_name LIKE ? OR p.phone LIKE ? OR p.address LIKE ?)';
    $like    = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orderSql = match ($sort) {
    'name'     => 'p.business_name ASC',
    'followup' => 'p.next_followup_at IS NULL, p.next_followup_at ASC',
    'oldest'   => 'p.updated_at ASC',
    default    => 'p.updated_at DESC',
};

$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT ph.filename FROM prospect_photos ph
             WHERE ph.prospect_id = p.id ORDER BY ph.id DESC LIMIT 1) AS thumb,
           (SELECT MAX(v.visited_at) FROM prospect_visits v WHERE v.prospect_id = p.id) AS last_visit,
           (SELECT COUNT(*)         FROM prospect_visits v WHERE v.prospect_id = p.id) AS visit_count
      FROM prospects p
      $whereSql
     ORDER BY $orderSql
     LIMIT 500
");
$stmt->execute($params);
$prospects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distance is worked out here rather than in SQL — the list is small and the
// haversine is already in config/prospects.php.
if ($myLat !== null) {
    foreach ($prospects as &$p) {
        $p['distance'] = ($p['lat'] !== null && $p['lng'] !== null)
            ? prospect_distance_m($myLat, $myLng, (float)$p['lat'], (float)$p['lng'])
            : null;
    }
    unset($p);

    if ($sort === 'near') {
        // Prospects with no pin sink to the bottom rather than sorting as 0 m.
        usort($prospects, fn($a, $b) => ($a['distance'] ?? INF) <=> ($b['distance'] ?? INF));
    }
}

$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM prospects GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[$row['status']] = (int)$row['n'];
}
$totalCount = array_sum($counts);

$dueCount = (int)$pdo->query(
    'SELECT COUNT(*) FROM prospects WHERE next_followup_at IS NOT NULL AND next_followup_at <= CURDATE()
       AND status NOT IN ("signed_up","not_interested","closed_down")'
)->fetchColumn();

$note  = $_SESSION['psp_success'] ?? '';
$error = $_SESSION['psp_error']   ?? '';
unset($_SESSION['psp_success'], $_SESSION['psp_error']);

// Keeps the current search/sort while swapping one parameter.
$linkWith = function (array $overrides) use ($statusFilter, $q, $sort, $myLat, $myLng): string {
    $params = array_filter([
        'status' => $statusFilter,
        'q'      => $q,
        'sort'   => $sort === 'recent' ? '' : $sort,
        'lat'    => $myLat !== null ? (string)$myLat : '',
        'lng'    => $myLng !== null ? (string)$myLng : '',
    ], fn($v) => $v !== '');
    $params = array_filter(array_merge($params, $overrides), fn($v) => $v !== '');
    return '/admin/prospects/' . ($params ? '?' . http_build_query($params) : '');
};

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Canvassing</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/prospects/prospects.css">
    <?php require __DIR__ . '/app-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/../admin-tabs.php'; ?>

    <div class="psp-head">
        <h1>Canvassing</h1>
        <div class="psp-head-actions">
            <a href="/admin/prospects/map.php" class="psp-btn">Map</a>
            <a href="/admin/prospects/new.php" class="psp-btn psp-btn-primary">+ Add prospect</a>
        </div>
    </div>

    <?php if ($note): ?><div class="psp-alert psp-alert-success"><?= htmlspecialchars($note) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="psp-alert psp-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($dueCount > 0): ?>
        <a class="psp-alert psp-alert-info" href="<?= htmlspecialchars($linkWith(['sort' => 'followup', 'status' => ''])) ?>">
            <?= $dueCount ?> follow-up<?= $dueCount === 1 ? '' : 's' ?> due today or overdue.
        </a>
    <?php endif; ?>

    <div class="psp-chips">
        <a href="<?= htmlspecialchars($linkWith(['status' => ''])) ?>"
           class="psp-chip <?= $statusFilter === '' ? 'active' : '' ?>">All <span><?= $totalCount ?></span></a>
        <?php foreach (PROSPECT_STATUSES as $key => $label): ?>
            <a href="<?= htmlspecialchars($linkWith(['status' => $key])) ?>"
               class="psp-chip <?= $statusFilter === $key ? 'active' : '' ?>">
                <span class="psp-dot" style="background:<?= prospect_status_color($key) ?>"></span>
                <?= htmlspecialchars($label) ?> <span><?= $counts[$key] ?? 0 ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="psp-toolbar">
        <form method="get" class="psp-search">
            <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search name, owner, phone, address">
            <button type="submit" class="psp-btn">Search</button>
            <?php if ($q !== ''): ?><a href="<?= htmlspecialchars($linkWith(['q' => ''])) ?>" class="psp-clear">Clear</a><?php endif; ?>
        </form>

        <div class="psp-sorts">
            <a href="<?= htmlspecialchars($linkWith(['sort' => ''])) ?>"         class="psp-sort <?= $sort === 'recent'   ? 'active' : '' ?>">Recent</a>
            <a href="<?= htmlspecialchars($linkWith(['sort' => 'name'])) ?>"     class="psp-sort <?= $sort === 'name'     ? 'active' : '' ?>">A–Z</a>
            <a href="<?= htmlspecialchars($linkWith(['sort' => 'followup'])) ?>" class="psp-sort <?= $sort === 'followup' ? 'active' : '' ?>">Follow-up</a>
            <button type="button" class="psp-sort <?= $sort === 'near' ? 'active' : '' ?>" data-geo-goto>Nearest</button>
        </div>
    </div>

    <?php
    // Export hands back whatever the status chip and the search box are
    // showing, so "the Toul Kork list" is one click rather than a re-query.
    $exportQuery = array_filter(['status' => $statusFilter, 'q' => $q], fn($v) => $v !== '');
    ?>

    <?php if (!$prospects): ?>
        <p class="psp-empty">
            <?= $q !== '' || $statusFilter !== '' ? 'Nothing matches that filter.' : 'No prospects yet. Tap "Add prospect" outside the first shop you pitch.' ?>
        </p>
    <?php else: ?>
        <div class="psp-list">
            <?php foreach ($prospects as $p):
                $due = $p['next_followup_at'] !== null && $p['next_followup_at'] <= $today
                       && !in_array($p['status'], ['signed_up', 'not_interested', 'closed_down'], true);
            ?>
                <div class="psp-row">
                    <a class="psp-row-main" href="/admin/prospects/prospect.php?id=<?= (int)$p['id'] ?>">
                        <?php if ($p['thumb']): ?>
                            <img class="psp-thumb" src="/uploads/<?= htmlspecialchars($p['thumb']) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="psp-thumb psp-thumb-empty">No<br>photo</span>
                        <?php endif; ?>

                        <span class="psp-row-text">
                            <span class="psp-row-name"><?= htmlspecialchars($p['business_name']) ?></span>
                            <?php if ($p['business_name_km']): ?>
                                <span class="psp-row-km"><?= htmlspecialchars($p['business_name_km']) ?></span>
                            <?php endif; ?>
                            <span class="psp-row-meta">
                                <span class="psp-badge" style="background:<?= prospect_status_color($p['status']) ?>">
                                    <?= htmlspecialchars(prospect_status_label($p['status'])) ?>
                                </span>
                                <?php if (isset($p['distance']) && $p['distance'] !== null): ?>
                                    <span class="psp-meta-bit"><?= htmlspecialchars(prospect_format_distance($p['distance'])) ?> away</span>
                                <?php endif; ?>
                                <?php if ($p['last_visit']): ?>
                                    <span class="psp-meta-bit">Last visit <?= date('j M', strtotime($p['last_visit'])) ?><?= (int)$p['visit_count'] > 1 ? ' · ' . (int)$p['visit_count'] . ' visits' : '' ?></span>
                                <?php endif; ?>
                                <?php if ($p['next_followup_at']): ?>
                                    <span class="psp-meta-bit <?= $due ? 'psp-due' : '' ?>">Follow up <?= date('j M', strtotime($p['next_followup_at'])) ?></span>
                                <?php endif; ?>
                            </span>
                            <?php if ($p['address']): ?>
                                <span class="psp-row-addr"><?= htmlspecialchars($p['address']) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>

                    <?php if ($p['phone']): ?>
                        <a class="psp-call" href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>">Call</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($prospects) === 500): ?>
            <p class="psp-empty">Showing the first 500 — narrow it down with a search. Export gives you all of them.</p>
        <?php endif; ?>

        <p class="psp-export">
            <a href="/admin/prospects/export.php<?= $exportQuery ? '?' . htmlspecialchars(http_build_query($exportQuery)) : '' ?>">
                Export <?= $statusFilter !== '' || $q !== '' ? 'these' : 'all' ?> as CSV
            </a>
        </p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../../footer/footer.php'; ?>
<script src="/js/geo-capture.js"></script>
</body>
</html>
