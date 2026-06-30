<?php
session_start();
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
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/careers/careers.css">
</head>
<body>
<?php require __DIR__ . '/../header/header.php'; ?>
<main>
    <div class="careers-wrap">
        <h1>Careers</h1>
        <p class="careers-lead">We're building the go-to marketplace for Phnom Penh. If you'd like to be part of it, we'd love to hear from you.</p>

        <?php if (empty($jobs)): ?>
        <p class="careers-empty">No open positions at this time. Check back soon.</p>
        <?php else: ?>
        <ul class="job-list">
            <?php foreach ($jobs as $j): ?>
            <li class="job-card">
                <div class="job-card-head">
                    <h2 class="job-card-title"><?= htmlspecialchars($j['title']) ?></h2>
                    <?php if ($j['location'] || $j['employment_type']): ?>
                    <p class="job-card-meta"><?= $j['location'] ? htmlspecialchars($j['location']) : '' ?><?= ($j['location'] && $j['employment_type']) ? ' · ' : '' ?><?= $j['employment_type'] ? htmlspecialchars($j['employment_type']) : '' ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($j['description'])): ?>
                <p class="job-card-desc"><?= nl2br(htmlspecialchars($j['description'])) ?></p>
                <?php endif; ?>
                <a class="job-card-apply" href="/careers/apply.php?job=<?= $j['id'] ?>">Apply for this role</a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
