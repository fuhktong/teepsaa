<?php
// One-time backfill: assigns a random public_id (UUID v4) to every existing
// business/product/order row that doesn't have one yet. Safe to re-run —
// only touches rows where public_id IS NULL. Run once after applying
// database/migration-public-ids.sql, before deploying the public_id-based
// lookup code.

require __DIR__ . '/../config/db.php';

foreach (['businesses', 'products', 'orders'] as $table) {
    $ids = $pdo->query("SELECT id FROM {$table} WHERE public_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare("UPDATE {$table} SET public_id = ? WHERE id = ?");
    foreach ($ids as $id) {
        $update->execute([uuid_v4(), $id]);
    }
    echo "{$table}: backfilled " . count($ids) . " row(s)\n";
}
