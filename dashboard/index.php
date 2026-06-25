<?php
session_start();
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, category, approved FROM businesses WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$businesses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/dashboard/dashboard.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="dashboard-header">
        <h1>My Businesses</h1>
        <a href="/submit/" class="btn">+ Submit a business</a>
    </div>

    <?php if (empty($businesses)): ?>
        <p class="empty">You haven't submitted any businesses yet.</p>
    <?php else: ?>
        <table class="business-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($businesses as $b): ?>
                    <?php
                    if ($b['approved'] === 1) {
                        $status = 'Approved';
                        $class  = 'status-approved';
                    } elseif ($b['approved'] === -1) {
                        $status = 'Rejected';
                        $class  = 'status-rejected';
                    } else {
                        $status = 'Pending';
                        $class  = 'status-pending';
                    }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($b['name']) ?></td>
                        <td><?= htmlspecialchars($b['category']) ?></td>
                        <td><span class="status <?= $class ?>"><?= $status ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
