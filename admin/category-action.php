<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('categories');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/categories.php');
    exit;
}

csrf_verify();

$action   = $_POST['action'] ?? '';

// Delete is handled before the add/edit field validation below, since a
// delete POST carries no name or rate to validate.
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    $catStmt = $pdo->prepare('SELECT name FROM categories WHERE id = ?');
    $catStmt->execute([$id]);
    $catName = $catStmt->fetchColumn();

    if ($catName === false) {
        $_SESSION['admin_error'] = 'Category not found.';
        header('Location: /admin/categories.php');
        exit;
    }

    $childStmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE parent_id = ?');
    $childStmt->execute([$id]);
    $childCount = (int)$childStmt->fetchColumn();

    $prodStmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
    $prodStmt->execute([$id]);
    $productCount = (int)$prodStmt->fetchColumn();

    // Both foreign keys are ON DELETE SET NULL, so deleting a category that is
    // still in use would silently orphan its rows rather than fail loudly:
    // children would jump to top level, and products would lose their category.
    // A product with no category reads as a 0% royalty rate at checkout
    // (COALESCE(cat.royalty_rate, 0) in checkout/confirm.php), so it would earn
    // nothing from then on with nothing on screen to say why. Refuse instead.
    if ($childCount > 0 || $productCount > 0) {
        $blockers = [];
        if ($childCount > 0) {
            $blockers[] = $childCount . ' sub-categor' . ($childCount === 1 ? 'y' : 'ies');
        }
        if ($productCount > 0) {
            $blockers[] = $productCount . ' product' . ($productCount === 1 ? '' : 's');
        }
        $_SESSION['admin_error'] = 'Cannot delete "' . $catName . '" — it still has '
            . implode(' and ', $blockers) . '. Re-parent or reassign them first.';
    } else {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        $_SESSION['admin_success'] = 'Category "' . $catName . '" deleted.';
    }

    header('Location: /admin/categories.php');
    exit;
}

$name     = trim($_POST['name'] ?? '');
$nameKm   = trim($_POST['name_km'] ?? '');
$rate     = round((float)($_POST['royalty_rate'] ?? 0) / 100, 4);
$parentId = (int)($_POST['parent_id'] ?? 0) ?: null;

if (!$name || $rate < 0 || $rate > 1) {
    $_SESSION['admin_error'] = 'Invalid category data.';
    header('Location: /admin/categories.php');
    exit;
}

if ($action === 'add') {
    $stmt = $pdo->prepare('INSERT INTO categories (parent_id, name, name_km, royalty_rate) VALUES (?, ?, ?, ?)');
    $stmt->execute([$parentId, $name, $nameKm ?: null, $rate]);
    $_SESSION['admin_success'] = 'Category "' . htmlspecialchars($name) . '" added.';

} elseif ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        $_SESSION['admin_error'] = 'Missing category ID.';
        header('Location: /admin/categories.php');
        exit;
    }
    $stmt = $pdo->prepare('UPDATE categories SET parent_id = ?, name = ?, name_km = ?, royalty_rate = ? WHERE id = ?');
    $stmt->execute([$parentId, $name, $nameKm ?: null, $rate, $id]);
    $_SESSION['admin_success'] = 'Category updated.';
}

header('Location: /admin/categories.php');
exit;
