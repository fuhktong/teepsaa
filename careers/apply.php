<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/notify.php'; // pulls in mail.php (send_email)
require __DIR__ . '/../config/rate-limit.php';

// Résumé validation by magic bytes — pdf / doc / docx, max 5 MB.
function resume_ext_from_magic(string $tmp): string|false {
    $bytes = @file_get_contents($tmp, false, null, 0, 8);
    if ($bytes === false) return false;
    if (str_starts_with($bytes, '%PDF'))                  return 'pdf';
    if (str_starts_with($bytes, "\xD0\xCF\x11\xE0"))      return 'doc';  // legacy .doc (OLE)
    if (str_starts_with($bytes, "PK\x03\x04"))            return 'docx'; // .docx (zip container)
    return false;
}

$jobId = (int) ($_GET['job'] ?? $_POST['job_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM job_postings WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch();

$notFound = !$job || !$job['is_open'];
$submitted = false;
$error = '';

if (!$notFound && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    check_rate_limit($pdo);
    record_failed_attempt($pdo);

    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || $email === '') {
        $error = 'Please provide your name and email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    // Optional résumé upload.
    $resumeFile = null;
    if (!$error && !empty($_FILES['resume']['tmp_name'])) {
        $file = $_FILES['resume'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Résumé upload failed. Please try again.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Résumé must be under 5 MB.';
        } else {
            $ext = resume_ext_from_magic($file['tmp_name']);
            if ($ext === false) {
                $error = 'Résumé must be a PDF, DOC, or DOCX file.';
            } else {
                if (!is_dir(RESUME_DIR)) {
                    @mkdir(RESUME_DIR, 0775, true);
                }
                $resumeFile = 'resume_' . bin2hex(random_bytes(16)) . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], RESUME_DIR . '/' . $resumeFile)) {
                    $error = 'Could not save your résumé. Please try again.';
                    $resumeFile = null;
                }
            }
        }
    }

    if (!$error) {
        $ins = $pdo->prepare('INSERT INTO job_applications (job_id, name, email, phone, message, resume_file) VALUES (?, ?, ?, ?, ?, ?)');
        $ins->execute([$job['id'], $name, $email, $phone ?: null, $message ?: null, $resumeFile]);

        // Notify admin by email (falls back to mail.log in dev).
        $body = 'New application for <strong>' . htmlspecialchars($job['title']) . '</strong>.<br><br>'
            . 'Name: ' . htmlspecialchars($name) . '<br>'
            . 'Email: ' . htmlspecialchars($email) . '<br>'
            . 'Phone: ' . htmlspecialchars($phone ?: '—') . '<br>'
            . ($resumeFile ? 'Résumé attached.' : 'No résumé attached.');
        send_email(
            ADMIN_EMAIL,
            'New job application: ' . $job['title'],
            notification_email_html('New job application', $body, 'Review applications', SITE_URL . '/admin/careers-applications.php?job=' . $job['id'])
        );

        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $job ? htmlspecialchars($job['title']) . ' — Apply' : 'Apply' ?> — teepsaa</title>
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
        <?php if ($notFound): ?>
        <h1><?= $t['apply_unavailable_title'] ?></h1>
        <p><?= sprintf($t['apply_unavailable_body'], '<a href="/careers/">' . $t['apply_see_all'] . '</a>') ?></p>

        <?php elseif ($submitted): ?>
        <h1><?= $t['apply_received_title'] ?></h1>
        <p class="careers-lead"><?= sprintf($t['apply_received_body'], '<strong>' . htmlspecialchars(lang_field($job, 'title')) . '</strong>') ?></p>
        <a class="job-card-apply" href="/careers/"><?= $t['apply_back_careers'] ?></a>

        <?php else: ?>
        <?php $empDisp = $job['employment_type'] ? ($empLabels[$job['employment_type']] ?? $job['employment_type']) : ''; ?>
        <p class="apply-back"><a href="/careers/">&larr; <?= $t['apply_all_roles'] ?></a></p>
        <h1><?= $t['apply_prefix'] ?> <?= htmlspecialchars(lang_field($job, 'title')) ?></h1>
        <?php if ($job['location'] || $job['employment_type']): ?>
        <p class="job-card-meta"><?= htmlspecialchars($job['location'] ? lang_field($job, 'location') : '') ?><?= ($job['location'] && $job['employment_type']) ? ' · ' : '' ?><?= htmlspecialchars($empDisp) ?></p>
        <?php endif; ?>

        <?php if ($error): ?>
        <p class="apply-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form class="apply-form" method="POST" action="/careers/apply.php?job=<?= $job['id'] ?>" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

            <label for="name"><?= $t['register_name'] ?> <span class="req">*</span></label>
            <input type="text" id="name" name="name" maxlength="120" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

            <label for="email"><?= $t['login_email'] ?> <span class="req">*</span></label>
            <input type="email" id="email" name="email" maxlength="190" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

            <label for="phone"><?= $t['apply_phone'] ?> <span class="opt"><?= $t['form_optional'] ?></span></label>
            <input type="text" id="phone" name="phone" maxlength="40" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

            <label for="message"><?= $t['apply_why_fit'] ?> <span class="opt"><?= $t['form_optional'] ?></span></label>
            <textarea id="message" name="message" maxlength="5000" rows="6"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>

            <label for="resume"><?= $t['apply_resume'] ?> <span class="opt"><?= $t['apply_resume_hint'] ?></span></label>
            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">

            <button type="submit" class="apply-submit"><?= $t['apply_submit'] ?></button>
        </form>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
