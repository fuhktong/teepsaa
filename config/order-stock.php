<?php
// Putting an order's stock back when it is cancelled — by an admin, or because
// its payment was rejected.
//
// Checkout takes stock from the option bought (product_variants) when there is
// one, and from the product otherwise; for a product with options the
// product's own stock is only the sum of its options, kept in step. Giving it
// back has to follow the same split. Adding to products.stock alone, as this
// used to, left the option sold out and was overwritten by the sum the next
// time the vendor saved the product.
//
// An option deleted since the order (variant_id set NULL, label still on the
// item) has nowhere to go back to and is skipped.
//
// Runs inside the caller's transaction.

require_once __DIR__ . '/low-stock.php';

function order_restore_stock(PDO $pdo, array $orderIds): void {
    $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
    if (!$orderIds) return;

    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("SELECT product_id, variant_id, variant_label, quantity FROM order_items WHERE order_id IN ($in) AND product_id IS NOT NULL");
    $stmt->execute($orderIds);

    $toVariant = $pdo->prepare('UPDATE product_variants SET stock = stock + ? WHERE id = ? AND product_id = ?');
    $resync    = $pdo->prepare('UPDATE products p SET p.stock = (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) WHERE p.id = ?');
    $toProduct = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');

    $touched = [];
    foreach ($stmt->fetchAll() as $item) {
        $productId = (int)$item['product_id'];
        if ($item['variant_id']) {
            $toVariant->execute([(int)$item['quantity'], (int)$item['variant_id'], $productId]);
            $resync->execute([$productId]);
        } elseif ($item['variant_label'] === null || $item['variant_label'] === '') {
            $toProduct->execute([(int)$item['quantity'], $productId]);
        } else {
            continue;
        }
        $touched[$productId] = true;
    }

    // Back above its low-stock number: warn the vendor again next time it runs
    // low, as saving the product does.
    if ($touched) {
        $clear = $pdo->prepare('UPDATE products p SET p.low_stock_notified_at = NULL WHERE p.id = ? AND NOT ' . low_stock_sql('p'));
        foreach (array_keys($touched) as $productId) $clear->execute([$productId]);
    }
}
