<?php
require __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$stmt = $pdo->query('SELECT b.id, b.name, b.category, b.description, b.address, b.lat, b.lng, GROUP_CONCAT(p.filename) AS photos FROM businesses b LEFT JOIN photos p ON p.business_id = b.id WHERE b.approved = 1 GROUP BY b.id');

$businesses = [];
foreach ($stmt->fetchAll() as $row) {
    $row['photos'] = $row['photos'] ? explode(',', $row['photos']) : [];
    $businesses[]  = $row;
}

echo json_encode($businesses);
