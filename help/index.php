<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
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

$lang = $_SESSION['lang'] ?? 'km';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/help/help.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
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
