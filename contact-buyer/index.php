<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId = $_SESSION['user_id'];
$error  = $_SESSION['contact_error'] ?? '';
$old    = $_SESSION['contact_old']   ?? [];
unset($_SESSION['contact_error'], $_SESSION['contact_old']);

$pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM support_threads WHERE sender_id = ? AND sender_role = 'buyer' AND status = 'pending'");
$pendingStmt->execute([$userId]);
$hasPending = (int)$pendingStmt->fetchColumn() > 0;

$orders = $pdo->prepare('
    SELECT o.id, o.created_at, GROUP_CONCAT(p.name ORDER BY p.name SEPARATOR ", ") AS items
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN products p ON p.id = oi.product_id
    WHERE o.buyer_user_id = ?
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
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/contact-buyer/contact-buyer.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="contact-header">
        <h1>Contact Support</h1>
        <p class="contact-lead">Fill in the details below and our team will get back to you.</p>
    </div>

    <?php if ($hasPending): ?>
        <p class="contact-blocked">You already have a request pending review. Please wait for our team to respond before submitting another.</p>
        <p><a href="/messages-buyer/">View your messages →</a></p>
    <?php else: ?>

    <?php if ($error): ?>
        <p class="contact-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="/contact-buyer/submit.php" class="contact-form">
        <?= csrf_input() ?>

        <div class="contact-field">
            <label for="issue_type">Issue type <span class="contact-req">*</span></label>
            <select id="issue_type" name="issue_type" required>
                <option value="">— Select an issue —</option>
                <?php foreach (['Order issue', 'Payment issue', 'Account issue', 'Other'] as $type): ?>
                    <option value="<?= $type ?>" <?= ($old['issue_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= $type ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="contact-field">
            <label for="order_id">Related order <span class="contact-hint">(optional)</span></label>
            <select id="order_id" name="order_id">
                <option value="">— No specific order —</option>
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
            <label for="subject">Subject <span class="contact-req">*</span></label>
            <input type="text" id="subject" name="subject" maxlength="255" required
                   placeholder="Brief summary of your issue"
                   value="<?= htmlspecialchars($old['subject'] ?? '') ?>">
        </div>

        <div class="contact-field">
            <label for="body">Message <span class="contact-req">*</span></label>
            <textarea id="body" name="body" rows="6" maxlength="2000" required
                      placeholder="Describe your issue in detail…"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
            <span class="contact-hint-text">Max 2000 characters</span>
        </div>

        <div class="contact-actions">
            <button type="submit" class="contact-btn">Submit request</button>
            <a href="/messages-buyer/" class="contact-btn contact-btn--secondary">Cancel</a>
        </div>
    </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
