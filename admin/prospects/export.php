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

// Mirrors the list's filters so "Export" hands you exactly what is on screen.
$statusFilter = isset($_GET['status']) && isset(PROSPECT_STATUSES[$_GET['status']]) ? $_GET['status'] : '';
$q            = trim((string)($_GET['q'] ?? ''));

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

$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT COUNT(*)         FROM prospect_visits v WHERE v.prospect_id = p.id) AS visit_count,
           (SELECT MAX(v.visited_at) FROM prospect_visits v WHERE v.prospect_id = p.id) AS last_visit,
           (SELECT COUNT(*)         FROM prospect_photos ph WHERE ph.prospect_id = p.id) AS photo_count
      FROM prospects p
      $whereSql
     ORDER BY p.business_name ASC
");
$stmt->execute($params);

$filename = 'teepsaa-prospects-' . date('Y-m-d') . ($statusFilter !== '' ? '-' . $statusFilter : '') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');

// Excel reads a UTF-8 CSV as Latin-1 without this, which mangles Khmer names.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'ID', 'Business name', 'Business name (Khmer)', 'Owner', 'Phone', 'Telegram',
    'Category', 'Address', 'Latitude', 'Longitude', 'Status', 'Follow up on',
    'Visits', 'Last visit', 'Photos', 'Notes', 'Added',
]);

while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $p['id'],
        $p['business_name'],
        $p['business_name_km'],
        $p['owner_name'],
        // A leading apostrophe stops Excel eating the + and the leading zero
        // off a Cambodian mobile number.
        $p['phone'] !== null && $p['phone'] !== '' ? "'" . $p['phone'] : '',
        $p['telegram'],
        $p['category'],
        $p['address'],
        $p['lat'],
        $p['lng'],
        prospect_status_label($p['status']),
        $p['next_followup_at'],
        $p['visit_count'],
        $p['last_visit'],
        $p['photo_count'],
        $p['notes'],
        $p['created_at'],
    ]);
}

fclose($out);
