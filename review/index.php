<?php
session_start();
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

$userId = $_SESSION['user_id'];
$itemId = (int)($_GET['item'] ?? 0);

if (!$itemId) {
    header('Location: /dashboard-buyer/');
    exit;
}

$stmt = $pdo->prepare('
    SELECT oi.id, oi.product_name, oi.variant_label, oi.product_id,
           o.id AS order_id, o.status,
           b.id AS business_id
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN businesses b ON b.id = o.business_id
    WHERE oi.id = ? AND o.buyer_user_id = ?
');
$stmt->execute([$itemId, $userId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: /dashboard-buyer/');
    exit;
}

if (!in_array($item['status'], ['delivered', 'completed'])) {
    header('Location: /dashboard-buyer/order.php?id=' . $item['order_id']);
    exit;
}

$check = $pdo->prepare('SELECT id FROM reviews WHERE order_item_id = ?');
$check->execute([$itemId]);
if ($check->fetch()) {
    header('Location: /dashboard-buyer/order.php?id=' . $item['order_id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave a Review — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/review/review.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <a href="/dashboard-buyer/order.php?id=<?= $item['order_id'] ?>" style="display:inline-block;font-size:0.875rem;color:#6b7280;text-decoration:none;margin-bottom:1.25rem;">← Back to order</a>

    <h1 style="margin-bottom:0.25rem;">Leave a review</h1>
    <p style="color:#6b7280;font-size:0.875rem;margin-bottom:1.75rem;"><?= htmlspecialchars($item['product_name']) ?><?php if ($item['variant_label']): ?> — <?= htmlspecialchars($item['variant_label']) ?><?php endif; ?></p>

    <form method="POST" action="/review/submit.php" class="review-form">
        <?= csrf_input() ?>
        <input type="hidden" name="order_item_id" value="<?= $itemId ?>">

        <!-- Inputs in reverse order so the ~ sibling selector can fill leftward stars -->
        <div class="star-rating" id="starRating">
            <input type="radio" name="rating" id="star5" value="5" required>
            <label for="star5">★</label>
            <input type="radio" name="rating" id="star4" value="4">
            <label for="star4">★</label>
            <input type="radio" name="rating" id="star3" value="3">
            <label for="star3">★</label>
            <input type="radio" name="rating" id="star2" value="2">
            <label for="star2">★</label>
            <input type="radio" name="rating" id="star1" value="1">
            <label for="star1">★</label>
        </div>
        <p class="star-hint" id="starHint">Tap a star to rate</p>

        <textarea name="comment" rows="4" placeholder="Optional comment…" maxlength="1000" class="review-comment"></textarea>
        <p style="font-size:0.75rem;color:#9ca3af;text-align:right;margin:0;" id="charCount"></p>

        <button type="submit" class="btn-submit-review">Submit review</button>
    </form>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

<script>
(function () {
    var labels = { 1: 'Terrible', 2: 'Poor', 3: 'Average', 4: 'Good', 5: 'Excellent' };
    var hint     = document.getElementById('starHint');
    var counter  = document.getElementById('charCount');
    var textarea = document.querySelector('.review-comment');

    document.querySelectorAll('#starRating input[type=radio]').forEach(function (r) {
        r.addEventListener('change', function () {
            hint.textContent = labels[this.value] || '';
        });
    });

    textarea && textarea.addEventListener('input', function () {
        counter.textContent = this.value.length + ' / 1000';
    });
}());
</script>
</body>
</html>
