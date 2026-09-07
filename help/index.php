<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';

$role = $_SESSION['role'] ?? '';

if ($role === 'buyer') {
    $contactUrl = '/contact-buyer/';
} elseif ($role === 'vendor') {
    $contactUrl = '/contact-vendor/';
} else {
    $contactUrl = '/contact/';
}

$lang = current_lang();

try {
    $faqRows = $pdo->query('SELECT * FROM faq_items WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
} catch (PDOException $e) {
    $faqRows = [];
}

$faqs = [];
foreach ($faqRows as $row) {
    $section = pick_lang($row['section_en'], $row['section_km']);
    $faqs[$section][] = [
        'q' => pick_lang($row['question_en'], $row['question_km']),
        'a' => pick_lang($row['answer_en'], $row['answer_km']),
    ];
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<?php
// Title and description come from the translation file so they match the
// language the body actually renders in — an English title over a Khmer page
// makes Google write its own snippet. head.php would load these itself, but
// the title is built from them, so they're needed a line earlier.
require_once __DIR__ . '/../config/seo.php';
$t = seo_t();

$headTitle = $t['footer_help_center'] . ' — teepsaa';
$headDesc  = $t['seo_desc_help'];
$headUrl   = 'https://teepsaa.com/help/';
$headCss   = ['/breadcrumb/breadcrumb.css', '/help/help.css'];

// Structured data: the questions below, restated so Google can show them
// inside the result itself. Nearly free — $faqs is already built and already
// looped over in the body, in the current language.
require_once __DIR__ . '/../config/schema.php';

// One array, two consumers: the visible trail below and the hidden block here.
$crumbs = [
    [$t['crumb_home'],         '/'],
    [$t['footer_help_center'], ''],
];

$headExtra = schema_graph(schema_faq($faqs), schema_breadcrumb($crumbs));
require __DIR__ . '/../head/head.php';
?>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/../breadcrumb/breadcrumb.php'; ?>

    <div class="help-hero">
        <h1><?= $t['footer_help_center'] ?></h1>
        <p><?= $lang === 'km' ? 'រកចម្លើយចំពោះសំណួរទូទៅខាងក្រោម។' : 'Find answers to common questions below.' ?></p>
    </div>

    <div class="help-toc">
        <?php foreach (array_keys($faqs) as $section): ?>
            <a href="#<?= urlencode($section) ?>" class="help-toc-link"><?= htmlspecialchars($section) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="help-sections">
        <?php foreach ($faqs as $section => $items): ?>
        <section class="help-section" id="<?= urlencode($section) ?>">
            <h2 class="help-section-title"><?= htmlspecialchars($section) ?></h2>
            <div class="help-faqs">
                <?php foreach ($items as $item): ?>
                <details class="faq-item">
                    <summary class="faq-q"><?= htmlspecialchars($item['q']) ?></summary>
                    <p class="faq-a"><?= htmlspecialchars($item['a']) ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <div class="help-contact-cta">
        <h2><?= $lang === 'km' ? 'នៅតែត្រូវការជំនួយ?' : 'Still need help?' ?></h2>
        <p><?= $lang === 'km' ? 'ប្រសិនបើអ្នករកមិនឃើញអ្វីដែលអ្នកកំពុងស្វែងរក ក្រុមការងារជំនួយរបស់យើងនៅទីនេះ។' : 'If you couldn\'t find what you were looking for, our support team is here.' ?></p>
        <a href="<?= $contactUrl ?>" class="help-contact-btn"><?= $t['messages_contact'] ?></a>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
