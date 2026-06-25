-- Teepsaa category seed — Clothing tree
-- Parent categories have no royalty_rate displayed (admin shows —); leaf nodes default to 5%.
-- Run once on a fresh categories table.

INSERT INTO categories (id, parent_id, name, royalty_rate) VALUES

-- Root
(1,  NULL, 'Clothing',               0.0500),

-- ── Men's ──────────────────────────────────────────
(2,  1,    'Men\'s',                 0.0500),
(3,  2,    'Tops & T-Shirts',        0.0500),
(4,  2,    'Shirts & Dress Shirts',  0.0500),
(5,  2,    'Hoodies & Sweatshirts',  0.0500),
(6,  2,    'Jackets & Coats',        0.0500),
(7,  2,    'Trousers & Chinos',      0.0500),
(8,  2,    'Jeans',                  0.0500),
(9,  2,    'Shorts',                 0.0500),
(10, 2,    'Suits & Blazers',        0.0500),
(11, 2,    'Activewear',             0.0500),
(12, 2,    'Underwear & Socks',      0.0500),
(13, 2,    'Sleepwear',              0.0500),

-- ── Women's ────────────────────────────────────────
(14, 1,    'Women\'s',               0.0500),
(15, 14,   'Tops & Blouses',         0.0500),
(16, 14,   'T-Shirts',               0.0500),
(17, 14,   'Hoodies & Sweatshirts',  0.0500),
(18, 14,   'Jackets & Coats',        0.0500),
(19, 14,   'Dresses',                0.0500),
(20, 14,   'Skirts',                 0.0500),
(21, 14,   'Trousers & Chinos',      0.0500),
(22, 14,   'Jeans',                  0.0500),
(23, 14,   'Shorts',                 0.0500),
(24, 14,   'Activewear',             0.0500),
(25, 14,   'Underwear & Lingerie',   0.0500),
(26, 14,   'Sleepwear',              0.0500),

-- ── Kids' ──────────────────────────────────────────
(27, 1,    'Kids\'',                 0.0500),
(28, 27,   'Boys\' Clothing',        0.0500),
(29, 27,   'Girls\' Clothing',       0.0500),
(30, 27,   'Baby & Toddler',         0.0500),

-- ── Accessories ────────────────────────────────────
(31, 1,    'Accessories',            0.0500),
(32, 31,   'Hats & Caps',            0.0500),
(33, 31,   'Scarves & Wraps',        0.0500),
(34, 31,   'Belts',                  0.0500),
(35, 31,   'Bags & Purses',          0.0500),
(36, 31,   'Wallets',                0.0500),
(37, 31,   'Sunglasses',             0.0500),
(38, 31,   'Jewellery',              0.0500),
(39, 31,   'Watches',                0.0500),

-- ── Footwear ───────────────────────────────────────
(40, 1,    'Footwear',               0.0500),
(41, 40,   'Men\'s Shoes',           0.0500),
(42, 40,   'Women\'s Shoes',         0.0500),
(43, 40,   'Kids\' Shoes',           0.0500),
(44, 40,   'Sandals & Flip Flops',   0.0500),
(45, 40,   'Sneakers',               0.0500),
(46, 40,   'Boots',                  0.0500),

-- ── Traditional & Cultural Wear ────────────────────
(47, 1,    'Traditional & Cultural Wear', 0.0500),
(48, 47,   'Khmer Traditional',      0.0500),
(49, 47,   'Formal & Ceremony',      0.0500);
