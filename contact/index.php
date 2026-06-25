<?php
session_start();

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
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/contact/contact.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="contact-header">
        <h1>Contact Us</h1>
        <p class="contact-lead">Have a question? Send us a message and we'll get back to you.</p>
    </div>

    <p class="contact-help-note">
        Already have an account? <a href="/login-buyer/">Sign in</a> to contact support with your order details, or <a href="/help/">check our Help Center</a> for quick answers.
    </p>

    <?php if ($error): ?>
        <p class="contact-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/contact/submit.php" class="contact-form">
        <input type="text" name="website" value="" autocomplete="off" tabindex="-1" style="display:none;position:absolute;left:-9999px;" aria-hidden="true">

        <div class="contact-field">
            <label for="name">Your name <span class="contact-req">*</span></label>
            <input type="text" id="name" name="name" maxlength="100" required
                   placeholder="Full name"
                   value="<?= htmlspecialchars($old['name'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="email">Email address <span class="contact-req">*</span></label>
            <input type="email" id="email" name="email" maxlength="255" required
                   placeholder="you@example.com"
                   value="<?= htmlspecialchars($old['email'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="subject">Subject <span class="contact-req">*</span></label>
            <input type="text" id="subject" name="subject" maxlength="255" required
                   placeholder="Brief summary of your question"
                   value="<?= htmlspecialchars($old['subject'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="body">Message <span class="contact-req">*</span></label>
            <textarea id="body" name="body" rows="6" maxlength="2000" required
                      placeholder="How can we help?"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
            <span class="contact-hint-text">Max 2000 characters</span>
        </div>

        <div class="contact-actions">
            <button type="submit" class="contact-btn">Send message</button>
        </div>
    </form>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
