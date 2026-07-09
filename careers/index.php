<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';

try {
    $jobs = $pdo->query('SELECT * FROM job_postings WHERE is_open = 1 ORDER BY created_at DESC')->fetchAll();
} catch (PDOException $e) {
    $jobs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/careers/careers.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="careers-wrap">
        <?php $empLabels = ['Full-time'=>$t['emp_full_time'], 'Part-time'=>$t['emp_part_time'], 'Contract'=>$t['emp_contract'], 'Internship'=>$t['emp_internship'], 'Freelance'=>$t['emp_freelance']]; ?>
        <h1><?= $t['careers_title'] ?></h1>
        <p class="careers-lead"><?= $t['careers_lead'] ?></p>

        <?php if (empty($jobs)): ?>
        <p class="careers-empty"><?= $t['careers_empty'] ?></p>
        <?php else: ?>
        <ul class="job-list">
            <?php foreach ($jobs as $j): ?>
            <?php $empDisp = $j['employment_type'] ? ($empLabels[$j['employment_type']] ?? $j['employment_type']) : ''; ?>
            <li class="job-card">
                <div class="job-card-head">
                    <h2 class="job-card-title"><?= htmlspecialchars(lang_field($j, 'title')) ?></h2>
                    <?php if ($j['location'] || $j['employment_type']): ?>
                    <p class="job-card-meta"><?= $j['location'] ? htmlspecialchars(lang_field($j, 'location')) : '' ?><?= ($j['location'] && $j['employment_type']) ? ' · ' : '' ?><?= htmlspecialchars($empDisp) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (lang_field($j, 'description')): ?>
                <p class="job-card-desc"><?= nl2br(htmlspecialchars(lang_field($j, 'description'))) ?></p>
                <?php endif; ?>
                <a class="job-card-apply" href="/careers/apply.php?job=<?= $j['id'] ?>"><?= $t['careers_apply'] ?></a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
