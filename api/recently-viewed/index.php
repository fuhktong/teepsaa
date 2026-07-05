<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$raw = $_GET['ids'] ?? '';
$uuidRe = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
$ids = array_values(array_unique(array_filter(explode(',', $raw), fn($id) => preg_match($uuidRe, $id))));
$ids = array_slice($ids, 0, 20);

if (!$ids) {
    echo json_encode([]);
    exit;
}

$ph   = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT p.public_id AS id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km, pp.filename AS photo
     FROM products p
     JOIN businesses b ON p.business_id = b.id
     LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
     WHERE p.public_id IN ($ph) AND p.active = 1 AND p.archived = 0 AND b.approved = 1"
);
$stmt->execute($ids);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byId    = [];
foreach ($rows as $r) {
    $r['name']          = lang_field($r, 'name');
    $r['business_name'] = pick_lang($r['business_name'], $r['business_name_km'] ?? null);
    unset($r['name_km'], $r['business_name_km']);
    $byId[$r['id']] = $r;
}
$ordered = array_values(array_filter(array_map(fn($id) => $byId[$id] ?? null, $ids)));

echo json_encode($ordered);
