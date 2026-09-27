<?php
// GET /api/v1/shop/page.php?slug=returns&lang=en — one of the website's text
// pages, for the buyer app's Account tab to show inside the app.
//
//   returns, shipping, terms, privacy → { slug, title, html }
//     From content_pages, the same row returns/index.php and the others print,
//     so an edit in admin reaches the app with no deploy. `html` is
//     render_markdown()'s output: everything escaped first, then only its own
//     small set of tags. Its links may be website paths ('/contact/'); the app
//     makes them absolute.
//
//   help → { slug, title, sections: [{ title, items: [{ q, a }] }] }
//     From faq_items, as help/index.php groups them. Plain text, not HTML.
//
// About is not here: the website builds it from lang/ words, which the app
// already has.
//
// Public, like the rest of api/v1/shop/: it answers what the website's pages
// answer to anyone.

require __DIR__ . '/../../../config/api.php';
require __DIR__ . '/../../../config/api-shop.php';
require __DIR__ . '/../../../config/markdown.php';

api_require_method('GET');

$slug = (string)($_GET['slug'] ?? '');
$t    = require __DIR__ . '/../../../lang/' . current_lang() . '.php';

if ($slug === 'help') {
    $rows = $pdo->query('SELECT * FROM faq_items WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

    // Grouped by section in the order the sections first appear, as the page does.
    $sections = [];
    foreach ($rows as $row) {
        $section = pick_lang($row['section_en'], $row['section_km']);
        $sections[$section][] = [
            'q' => pick_lang($row['question_en'], $row['question_km']),
            'a' => pick_lang($row['answer_en'], $row['answer_km']),
        ];
    }

    $out = [];
    foreach ($sections as $title => $items) {
        $out[] = ['title' => $title, 'items' => $items];
    }

    shop_json(['slug' => 'help', 'title' => $t['footer_help_center'], 'sections' => $out]);
}

// Each slug's fallback title is the footer's word for it, as on the pages.
$titles = [
    'returns'  => 'footer_returns',
    'shipping' => 'footer_shipping',
    'terms'    => 'footer_terms',
    'privacy'  => 'footer_privacy',
];

if (!isset($titles[$slug])) {
    api_json(['error' => 'not_found'], 404);
}

$stmt = $pdo->prepare('SELECT * FROM content_pages WHERE slug = ?');
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    api_json(['error' => 'not_found'], 404);
}

shop_json([
    'slug'  => $slug,
    'title' => pick_lang($page['title_en'], $page['title_km']) ?: $t[$titles[$slug]],
    'html'  => render_markdown(pick_lang($page['body_en'], $page['body_km'])),
]);
