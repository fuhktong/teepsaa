<?php
// Everything the app's Dashboard tab shows, in one request — a phone on mobile
// data should not pay for four round trips to draw one screen.
//
// Every number here is calculated the same way as analytics/index.php. If one
// of those queries changes, change it here too, or a vendor sees one figure on
// the website and a different one in the app and trusts neither.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

// A vendor may have no business yet (they signed up but never submitted one),
// and the app has to draw something sensible in that case rather than error.
$stmt = $pdo->prepare('
    SELECT id, name, category, approved, suspended, suspension_reason,
           trial_starts_at, trial_ends_at, royalty_free_threshold
      FROM businesses
     WHERE user_id = ? AND deleted_at IS NULL
     ORDER BY created_at DESC
     LIMIT 1
');
$stmt->execute([$userId]);
$business = $stmt->fetch();

if (!$business) {
    api_json([
        'business'     => null,
        'trial'        => null,
        'stats'        => ['total_payout' => 0, 'month_payout' => 0, 'total_orders' => 0, 'month_orders' => 0],
        'best_sellers' => [],
        'open_orders'  => [],
        'open_orders_count' => 0,
    ]);
}

$businessId = (int)$business['id'];
$approved   = (int)$business['approved'];

// approved is 1 / 0 / -1 on the website. The app gets a word instead, because a
// number that means "rejected" only when it is -1 is exactly the kind of thing
// an old app version gets wrong after a schema change.
$status = $approved === 1 ? 'approved' : ($approved === -1 ? 'rejected' : 'pending');

// ── The royalty-free trial ───────────────────────────────────────────
// It runs until EITHER the end date passes OR total sales cross the threshold,
// so a vendor who is past the date but under the threshold is still on it.
$trial = null;
if ($business['trial_starts_at']) {
    $salesStmt = $pdo->prepare("
        SELECT COALESCE(SUM(subtotal), 0)
          FROM orders
         WHERE business_id = ? AND status IN ('delivered', 'completed')
    ");
    $salesStmt->execute([$businessId]);
    $completedSales = (float)$salesStmt->fetchColumn();

    $threshold      = (float)$business['royalty_free_threshold'];
    $withinTime     = strtotime($business['trial_ends_at']) > time();
    $belowThreshold = $completedSales < $threshold;

    if ($withinTime || $belowThreshold) {
        $trial = [
            'ends_at'     => $business['trial_ends_at'],
            'sales'       => round($completedSales, 2),
            'threshold'   => round($threshold, 2),
            'within_time' => $withinTime,
        ];
    }
}

// ── Sales numbers ────────────────────────────────────────────────────
// Only a business that passed review has real figures; an unapproved one has
// nothing to count, and the website hides this block entirely for them.
$stats = ['total_payout' => 0, 'month_payout' => 0, 'total_orders' => 0, 'month_orders' => 0];
$bestSellers = [];

if ($approved === 1) {
    // vendor_payout, not subtotal — this is what the vendor is actually paid
    // after commission, and it is the number the website shows as revenue.
    $stmtStats = $pdo->prepare("
        SELECT
            COALESCE(SUM(vendor_payout), 0) AS total_payout,
            COALESCE(SUM(CASE WHEN YEAR(o.created_at) = YEAR(NOW())
                               AND MONTH(o.created_at) = MONTH(NOW())
                          THEN vendor_payout ELSE 0 END), 0) AS month_payout,
            COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN YEAR(o.created_at) = YEAR(NOW())
                               AND MONTH(o.created_at) = MONTH(NOW())
                          THEN 1 ELSE 0 END), 0) AS month_orders
          FROM orders o
          JOIN businesses b ON b.id = o.business_id
         WHERE b.user_id = ? AND b.deleted_at IS NULL
           AND o.status IN ('delivered', 'completed')
    ");
    $stmtStats->execute([$userId]);
    $row = $stmtStats->fetch() ?: [];

    $stats = [
        'total_payout' => round((float)($row['total_payout'] ?? 0), 2),
        'month_payout' => round((float)($row['month_payout'] ?? 0), 2),
        'total_orders' => (int)($row['total_orders'] ?? 0),
        'month_orders' => (int)($row['month_orders'] ?? 0),
    ];

    // Grouped by product_name rather than product_id on purpose: order_items
    // keeps the name as it was at the time of sale, so a renamed or deleted
    // product still shows its history instead of vanishing from the totals.
    $stmtBest = $pdo->prepare("
        SELECT oi.product_name,
               SUM(oi.quantity) AS total_sold,
               SUM(oi.price_at_purchase * oi.quantity) AS revenue
          FROM order_items oi
          JOIN orders o ON o.id = oi.order_id
          JOIN businesses b ON b.id = o.business_id
         WHERE b.user_id = ? AND b.deleted_at IS NULL
           AND o.status IN ('delivered', 'completed')
         GROUP BY oi.product_name
         ORDER BY total_sold DESC
         LIMIT 5
    ");
    $stmtBest->execute([$userId]);

    foreach ($stmtBest->fetchAll() as $bs) {
        $bestSellers[] = [
            'product_name' => $bs['product_name'],
            'total_sold'   => (int)$bs['total_sold'],
            'revenue'      => round((float)$bs['revenue'], 2),
        ];
    }
}

// ── Orders needing attention ─────────────────────────────────────────
// 'pending' and 'paid' are the two states where the vendor still has work to
// do. Delivered and completed orders belong in the Orders tab's history, not
// on a screen meant to say "here is what to deal with today".
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
     WHERE b.user_id = ? AND b.deleted_at IS NULL
       AND o.status IN ('pending', 'paid')
");
$countStmt->execute([$userId]);
$openOrdersCount = (int)$countStmt->fetchColumn();

// Only the newest few. The Orders tab loads the full list; sending all of them
// here would make the dashboard slower the busier a vendor gets.
$stmtOrders = $pdo->prepare("
    SELECT o.id, o.public_id, o.subtotal, o.discount_amount, o.delivery_fee, o.status, o.created_at,
           u.name AS buyer_name,
           GROUP_CONCAT(oi.product_name, ' x', oi.quantity ORDER BY oi.id SEPARATOR ', ') AS items
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN buyers u ON u.id = o.buyer_user_id
      JOIN order_items oi ON oi.order_id = o.id
     WHERE b.user_id = ? AND b.deleted_at IS NULL
       AND o.status IN ('pending', 'paid')
     GROUP BY o.id
     ORDER BY o.created_at DESC
     LIMIT 5
");
$stmtOrders->execute([$userId]);

$openOrders = [];
foreach ($stmtOrders->fetchAll() as $o) {
    $openOrders[] = [
        // public_id is what the app uses to open one order — never the raw id.
        'public_id' => $o['public_id'],
        // The same human reference the website prints, built here so the app
        // and the website never disagree about what an order is called.
        'ref'        => date('ymd', strtotime($o['created_at'])) . '-' . str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT),
        'status'     => $o['status'],
        'subtotal'     => round((float)$o['subtotal'], 2),
        'discount'     => round((float)$o['discount_amount'], 2),
        'delivery_fee' => round((float)$o['delivery_fee'], 2),
        // Worked out here, not in the app. Money maths in two places is money
        // maths that will eventually disagree with itself.
        'total'        => round((float)$o['subtotal'] - (float)$o['discount_amount'] + (float)$o['delivery_fee'], 2),
        'buyer_name' => $o['buyer_name'],
        'items'      => $o['items'],
        'created_at' => $o['created_at'],
    ];
}

api_json([
    'business' => [
        'id'                => $businessId,
        'name'              => $business['name'],
        'category'          => $business['category'],
        'status'            => $status,
        'suspended'         => (int)$business['suspended'] === 1,
        'suspension_reason' => $business['suspension_reason'],
    ],
    'trial'             => $trial,
    'stats'             => $stats,
    'best_sellers'      => $bestSellers,
    'open_orders'       => $openOrders,
    'open_orders_count' => $openOrdersCount,
]);
