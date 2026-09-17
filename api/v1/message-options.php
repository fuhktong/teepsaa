<?php
// What the app needs to draw the "new support request" form: the issue types
// teepsaa files requests under, and the vendor's recent orders so one can be
// attached for context.
//
// The types are served rather than hardcoded in the app because the stored
// value has to match contact-vendor/submit.php's list exactly, and app versions
// live on phones for months. The label comes in the vendor's own language; the
// value is the English string that goes in the column.

require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$lang = in_array($vendor['lang'] ?? 'en', ['en', 'km'], true) ? $vendor['lang'] : 'en';
$t    = require __DIR__ . '/../../lang/' . $lang . '.php';

$issueTypes = [
    ['value' => 'Order dispute',         'label' => $t['contact_issue_dispute']],
    ['value' => 'Payout issue',          'label' => $t['contact_issue_payout']],
    ['value' => 'Product/listing issue', 'label' => $t['contact_issue_listing']],
    ['value' => 'Account issue',         'label' => $t['contact_issue_account']],
    ['value' => 'Other',                 'label' => $t['contact_issue_other']],
];

// The same fifty the website's dropdown offers, newest first, every status
// included — a cancelled or refunded order is exactly the kind a vendor writes
// in about.
$stmt = $pdo->prepare('
    SELECT o.id, o.public_id, o.created_at,
           GROUP_CONCAT(
               CONCAT(oi.product_name, " x", oi.quantity)
               ORDER BY oi.id SEPARATOR ", "
           ) AS items
      FROM orders o
      JOIN businesses b ON b.id = o.business_id
      JOIN order_items oi ON oi.order_id = o.id
     WHERE b.user_id = ?
     GROUP BY o.id
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT 50
');
$stmt->execute([$userId]);

$orders = [];
foreach ($stmt->fetchAll() as $o) {
    $orders[] = [
        // Named by public id, the only id the app ever holds.
        'id'    => $o['public_id'],
        'ref'   => date('ymd', strtotime($o['created_at']))
                 . '-' . str_pad((string)$o['id'], 4, '0', STR_PAD_LEFT),
        'items' => $o['items'],
    ];
}

api_json([
    'issue_types'   => $issueTypes,
    'orders'        => $orders,
    'subject_max'   => 255,
    'body_max'      => 2000,
]);
