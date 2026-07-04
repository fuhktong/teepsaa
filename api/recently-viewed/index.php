<?php
session_start();
require __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$raw = $_GET['ids'] ?? '';
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
$ids = array_slice($ids, 0, 20);

if (!$ids) {
    echo json_encode([]);
    exit;
}

$ph   = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT p.id, p.name, p.name_km, p.price, p.sale_price, p.sale_ends_at, b.name AS business_name, b.name_km AS business_name_km, pp.filename AS photo
     FROM products p
     JOIN businesses b ON p.business_id = b.id
     LEFT JOIN product_photos pp ON pp.product_id = p.id AND pp.is_primary = 1
     WHERE p.id IN ($ph) AND p.active = 1 AND p.archived = 0 AND b.approved = 1"
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
