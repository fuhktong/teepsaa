<?php
// The Orders tab — orders-buyer/index.php without the HTML, paged. One list,
// no tabs: every order but cancelled ones, newest first, as the website shows
// it. See buyer_order_list() in config/buyer-orders.php for the shape.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

// Clamped, not trusted — as api/v1/orders.php.
$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

api_json(buyer_order_list($pdo, (int)$buyer['id'], $limit, $offset));
