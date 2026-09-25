<?php
// The cart — cart/index.php without the HTML. Everything the screen draws, in
// one reply: see buyer_cart() in config/buyer-cart.php for the shape and for
// the one thing done differently from the website (the cart is shown even
// while the address or phone is missing, with `missing` and `fix` naming it).

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';
require __DIR__ . '/../../../config/buyer-cart.php';

api_require_method('GET');

$buyer = api_require_buyer($pdo);

api_json(buyer_cart($pdo, (int)$buyer['id']));
