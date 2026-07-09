<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

$userId = $_SESSION['user_id'];
$error  = $_SESSION['contact_error'] ?? '';
$old    = $_SESSION['contact_old']   ?? [];
unset($_SESSION['contact_error'], $_SESSION['contact_old']);

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM support_threads WHERE sender_id = ? AND sender_role = 'vendor' AND status = 'pending'");
$pendingStmt->execute([$userId]);
$hasPending = (int)$pendingStmt->fetchColumn() > 0;

$orders = $pdo->prepare('
    SELECT DISTINCT o.id, o.created_at,
           GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ", ") AS items
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p ON p.id = oi.product_id
    JOIN businesses b ON b.id = p.business_id
    WHERE b.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 50
');
$orders->execute([$userId]);
$orders = $orders->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/contact-vendor/contact-vendor.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="contact-header">
        <h1><?= $t['messages_contact'] ?></h1>
        <p class="contact-lead"><?= $t['contact_support_lead'] ?></p>
    </div>

    <?php if ($hasPending): ?>
        <p class="contact-blocked"><?= $t['contact_pending'] ?></p>
        <p><a href="/messages-vendor/"><?= $t['contact_view_messages'] ?></a></p>
    <?php else: ?>

    <?php if ($error): ?>
        <p class="contact-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/contact-vendor/submit.php" class="contact-form">
        <?= csrf_input() ?>

        <div class="contact-field">
            <label for="issue_type"><?= $t['contact_issue_type'] ?> <span class="contact-req">*</span></label>
            <select id="issue_type" name="issue_type" required>
                <option value=""><?= $t['contact_select_issue'] ?></option>
                <?php foreach (['Order dispute' => $t['contact_issue_dispute'], 'Payout issue' => $t['contact_issue_payout'], 'Product/listing issue' => $t['contact_issue_listing'], 'Account issue' => $t['contact_issue_account'], 'Other' => $t['contact_issue_other']] as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= ($old['issue_type'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="contact-field">
            <label for="order_id"><?= $t['contact_related_order'] ?> <span class="contact-hint"><?= $t['form_optional'] ?></span></label>
            <select id="order_id" name="order_id">
                <option value=""><?= $t['contact_no_order'] ?></option>
                <?php foreach ($orders as $o):
                    $ref   = date('ymd', strtotime($o['created_at'])) . '-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT);
                    $label = '#' . $ref . ' — ' . mb_strimwidth($o['items'], 0, 60, '…');
                ?>
                    <option value="<?= $o['id'] ?>" <?= ($old['order_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="contact-field">
            <label for="subject"><?= $t['contact_subject'] ?> <span class="contact-req">*</span></label>
            <input type="text" id="subject" name="subject" maxlength="255" required
                   placeholder="<?= htmlspecialchars($t['contact_subject_ph']) ?>"
                   value="<?= htmlspecialchars($old['subject'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="body"><?= $t['contact_message'] ?> <span class="contact-req">*</span></label>
            <textarea id="body" name="body" rows="6" maxlength="2000" required
                      placeholder="<?= htmlspecialchars($t['contact_message_ph']) ?>"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
            <span class="contact-hint-text"><?= $t['contact_max_chars'] ?></span>
        </div>

        <div class="contact-actions">
            <button type="submit" class="contact-btn"><?= $t['contact_submit'] ?></button>
            <a href="/messages-vendor/" class="contact-btn contact-btn--secondary"><?= $t['btn_cancel'] ?></a>
        </div>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
