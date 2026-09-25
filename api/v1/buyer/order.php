<?php
// One order — orders-buyer/order.php without the HTML. Read-only; everything
// the buyer can do to it goes through order-action.php, which answers with
// this same shape (buyer_order_detail() in config/buyer-orders.php).
//
// ?id= is the public id. The buyer_user_id match means someone else's order
// reads as not_found, never as "not yours".

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/buyer-orders.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

$publicId = trim((string)($_GET['id'] ?? ''));
if ($publicId === '') api_json(['error' => 'missing_id'], 400);

$order = buyer_order_detail($pdo, (int)$buyer['id'], $publicId);
if (!$order) api_json(['error' => 'not_found'], 404);

api_json($order);
