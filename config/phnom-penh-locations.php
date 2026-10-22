<?php
// Phnom Penh's khans and sangkats as { "Khan": ["Sangkat", …] }, in the shape
// every address form and endpoint has always used — including
// api/v1/buyer/addresses.php, whose `locations` old buyer apps read.
//
// Built from config/cambodia-locations.php, which holds the whole country.
// Spellings and order are unchanged from when this file held the list itself
// (Wikipedia, May 2026: 14 khans, 105 sangkats).
$all = require __DIR__ . '/cambodia-locations.php';

return array_map(
    fn($d) => array_keys($d['communes']),
    $all['Phnom Penh']['districts']
);
