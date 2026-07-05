<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);


if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'buyer') {
        header('Location: /contact-buyer/');
        exit;
    }
    if ($role === 'vendor') {
        header('Location: /contact-vendor/');
        exit;
    }
}

$error = $_SESSION['contact_guest_error'] ?? '';
$old   = $_SESSION['contact_guest_old']   ?? [];
unset($_SESSION['contact_guest_error'], $_SESSION['contact_guest_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/contact/contact.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="contact-header">
        <h1><?= $t['contact_title'] ?></h1>
        <p class="contact-lead"><?= $t['contact_lead'] ?></p>
    </div>

    <p class="contact-help-note">
        <?= sprintf($t['contact_signin_note'], '<a href="/login-buyer/">' . $t['footer_sign_in'] . '</a>', '<a href="/help/">' . $t['contact_help_link'] . '</a>') ?>
    </p>

    <?php if ($error): ?>
        <p class="contact-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/contact/submit.php" class="contact-form">
        <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="display:none;position:absolute;left:-9999px;" aria-hidden="true">

        <div class="contact-field">
            <label for="name"><?= $t['contact_name'] ?> <span class="contact-req">*</span></label>
            <input type="text" id="name" name="name" maxlength="100" required
                   placeholder="<?= htmlspecialchars($t['register_name']) ?>"
                   value="<?= htmlspecialchars($old['name'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="email"><?= $t['contact_email'] ?> <span class="contact-req">*</span></label>
            <input type="email" id="email" name="email" maxlength="255" required
                   placeholder="you@example.com"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="subject"><?= $t['contact_subject'] ?> <span class="contact-req">*</span></label>
            <input type="text" id="subject" name="subject" maxlength="255" required
                   placeholder="<?= htmlspecialchars($t['contact_subject_ph_generic']) ?>"
                   value="<?= htmlspecialchars($old['subject'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="body"><?= $t['contact_message'] ?> <span class="contact-req">*</span></label>
            <textarea id="body" name="body" rows="6" maxlength="2000" required
                      placeholder="<?= htmlspecialchars($t['contact_message_ph_generic']) ?>"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
            <span class="contact-hint-text"><?= $t['contact_max_chars'] ?></span>
        </div>

        <div class="contact-actions">
            <button type="submit" class="contact-btn"><?= $t['contact_send'] ?></button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
