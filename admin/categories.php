<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('categories');

$success = $_SESSION['admin_success'] ?? '';
$error   = $_SESSION['admin_error']   ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

$allCats = $pdo->query('SELECT id, parent_id, name, name_km, royalty_rate FROM categories ORDER BY name ASC')->fetchAll();

function buildCatTree(array $cats, $parentId = null): array {
    $branch = [];
    foreach ($cats as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $cat['children'] = buildCatTree($cats, $cat['id']);
            $branch[] = $cat;
        }
    }
    return $branch;
}

function flattenCatTree(array $nodes, int $depth = 0): array {
    $result = [];
    foreach ($nodes as $node) {
        $node['depth'] = $depth;
        $children = $node['children'];
        unset($node['children']);
        $result[] = $node;
        $result = array_merge($result, flattenCatTree($children, $depth + 1));
    }
    return $result;
}

// Returns IDs of all descendants of $targetId using the depth-first flat list
function getDescendantIds(array $flat, int $targetId): array {
    $ids = [];
    $collecting = false;
    $targetDepth = null;
    foreach ($flat as $node) {
        if ($node['id'] === $targetId) {
            $collecting = true;
            $targetDepth = $node['depth'];
            continue;
        }
        if ($collecting) {
            if ($node['depth'] > $targetDepth) {
                $ids[] = $node['id'];
            } else {
                break;
            }
        }
    }
    return $ids;
}

$refundCount        = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'refund_requested'")->fetchColumn();
$pendingPayoutCount = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at < DATE_SUB(NOW(), INTERVAL " . PAYOUT_WINDOW_SECONDS . " SECOND)")->fetchColumn();
$unreadMsgCount     = (int)$pdo->query("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender IN ('buyer','vendor','guest') AND read_at IS NULL")->fetchColumn();
$tree      = buildCatTree($allCats);
$flat      = flattenCatTree($tree);
$parentIds = array_column(array_filter($allCats, fn($c) => $c['parent_id'] !== null), 'parent_id');
$adminSection = 'admin';
$adminTab     = 'categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Categories</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <?php if (!isset($pendingVendorCount)) { $pendingVendorCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE approved = 0")->fetchColumn(); } ?>
    <?php require __DIR__ . '/admin-tabs.php'; ?>
    <h1>Categories</h1>
    <p class="cat-page-desc">Hierarchical product categories. The royalty rate is deducted from vendor payouts at checkout.</p>

    <?php if ($success): ?>
        <p class="admin-success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="admin-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($flat)): ?>
    <table class="cat-tree-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Rate <span style="font-weight:400;text-transform:none;letter-spacing:0;">(terminal only)</span></th>
                <th>Parent</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($flat as $cat):
            $excludeIds = array_merge([$cat['id']], getDescendantIds($flat, $cat['id']));
            $isLeaf = !in_array($cat['id'], $parentIds);
        ?>
        <tr>
            <form method="POST" action="/admin/category-action.php">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                <?php if (!$isLeaf): ?>
                    <input type="hidden" name="royalty_rate" value="<?= number_format($cat['royalty_rate'] * 100, 1) ?>">
                <?php endif; ?>
                <td>
                    <?php if ($cat['depth'] > 0): ?>
                        <span class="cat-tree-dash"><?= str_repeat('— ', $cat['depth']) ?></span>
                    <?php endif; ?>
                    <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required class="cat-name-input" placeholder="English">
                    <input type="text" name="name_km" value="<?= htmlspecialchars($cat['name_km'] ?? '') ?>" class="cat-name-input" placeholder="ខ្មែរ (optional)" style="margin-top:4px;">
                </td>
                <td>
                    <?php if ($isLeaf): ?>
                    <div class="cat-rate-wrap">
                        <input type="number" name="royalty_rate" min="0" max="100" step="1"
                               value="<?= number_format($cat['royalty_rate'] * 100, 1) ?>" required>
                        <span>%</span>
                    </div>
                    <?php else: ?>
                    <span class="cat-no-rate">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="parent_id" class="cat-parent-select">
                        <option value="">— Top level —</option>
                        <?php foreach ($flat as $opt):
                            if (in_array($opt['id'], $excludeIds, true)) continue;
                        ?>
                        <option value="<?= $opt['id'] ?>" <?= $opt['id'] == $cat['parent_id'] ? 'selected' : '' ?>>
                            <?= str_repeat('— ', $opt['depth']) . htmlspecialchars($opt['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="submit" class="btn-save">Save</button></td>
            </form>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="empty" style="margin-bottom:1.5rem;">No categories yet.</p>
    <?php endif; ?>

    <div class="cat-add-section">
        <h2>Add category</h2>
        <form method="POST" action="/admin/category-action.php" class="cat-add-row">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add">
            <input type="text" name="name" placeholder="Category name" required class="cat-name-input">
            <input type="text" name="name_km" placeholder="ខ្មែរ (optional)" class="cat-name-input">
            <select name="parent_id" class="cat-parent-select">
                <option value="">— Top level —</option>
                <?php foreach ($flat as $opt): ?>
                <option value="<?= $opt['id'] ?>">
                    <?= str_repeat('— ', $opt['depth']) . htmlspecialchars($opt['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="cat-rate-wrap">
                <input type="number" name="royalty_rate" min="0" max="100" step="1" value="5" required>
                <span>%</span>
            </div>
            <button type="submit" class="btn-save">Add</button>
        </form>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>

</body>
</html>
