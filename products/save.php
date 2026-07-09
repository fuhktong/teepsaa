<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'vendor') {
    header('Location: /login-vendor/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /products/');
    exit;
}

csrf_verify();

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$stmt = $pdo->prepare('SELECT id FROM businesses WHERE user_id = ? AND approved = 1');
$stmt->execute([$userId]);
$ownedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($ownedIds)) {
    header('Location: /products/');
    exit;
}

$name        = trim($_POST['name'] ?? '');
$nameKm      = trim($_POST['name_km'] ?? '');
$description = trim($_POST['description'] ?? '');
$descriptionKm = trim($_POST['description_km'] ?? '');
$price       = (float)($_POST['price'] ?? 0);
$stock       = max(0, (int)($_POST['stock'] ?? 0));
$deliveryMethod = in_array($_POST['delivery_method'] ?? '', ['bike','tuktuk']) ? $_POST['delivery_method'] : 'bike';
$salePriceRaw   = trim($_POST['sale_price'] ?? '');
$saleDateRaw    = trim($_POST['sale_date']  ?? '');
$saleTimeRaw    = trim($_POST['sale_time']  ?? '');
$salePrice      = ($salePriceRaw !== '' && is_numeric($salePriceRaw) && (float)$salePriceRaw > 0) ? (float)$salePriceRaw : null;
$saleEndsRaw    = ($saleDateRaw !== '' && $saleTimeRaw !== '') ? $saleDateRaw . ' ' . $saleTimeRaw . ':00' : '';
$saleEndsAt     = ($saleEndsRaw !== '' && strtotime($saleEndsRaw) > time()) ? date('Y-m-d H:i:s', strtotime($saleEndsRaw)) : null;
if ($salePrice === null || $saleEndsAt === null) { $salePrice = null; $saleEndsAt = null; }
$businessId  = (int)($_POST['business_id'] ?? 0);
$categoryId  = (int)($_POST['category_id'] ?? 0) ?: null;

$leafCheck = $categoryId ? $pdo->prepare('SELECT id FROM categories WHERE id = ? AND id NOT IN (SELECT DISTINCT parent_id FROM categories WHERE parent_id IS NOT NULL)') : null;
if ($leafCheck) { $leafCheck->execute([$categoryId]); }
$isLeaf = $leafCheck && $leafCheck->fetch();

if (!$name || $price < 0 || !$categoryId || !$isLeaf || !in_array($businessId, array_map('intval', $ownedIds))) {
    $_SESSION['product_error'] = 'Invalid product data.';
    header('Location: /products/');
    exit;
}

$photo      = null;
$uploadDir  = __DIR__ . '/../uploads/';
$allowed    = ['image/jpeg', 'image/png'];
$uploadError = null;

if (!empty($_FILES['photo']['name'])) {
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Photo upload failed (error code ' . $_FILES['photo']['error'] . ').';
    } else {
        $tmp  = $_FILES['photo']['tmp_name'];
        $size = $_FILES['photo']['size'];
        $mime = image_type_from_magic($tmp);

        if (!in_array($mime, $allowed, true)) {
            $uploadError = 'Photo must be a JPEG or PNG.';
        } elseif ($size > 2 * 1024 * 1024) {
            $uploadError = 'Photo must be under 2MB.';
        } else {
            $ext      = $mime === 'image/png' ? 'png' : 'jpg';
            $filename = bin2hex(random_bytes(16)) . '.' . $ext;
            if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
                $uploadError = 'Photo could not be saved.';
            } else {
                $photo = $filename;
            }
        }
    }
}

if ($uploadError) {
    $_SESSION['product_error'] = $uploadError;
    header('Location: /products/');
    exit;
}

function save_gallery_photos(PDO $pdo, string $uploadDir, array $allowed, int $productId): void {
    if (empty($_FILES['gallery_photos']['name'][0])) return;

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM product_photos WHERE product_id = ?');
    $countStmt->execute([$productId]);
    $total = (int)$countStmt->fetchColumn();
    $slots = max(0, 9 - $total);
    if ($slots === 0) return;

    $files    = $_FILES['gallery_photos'];
    $count    = count($files['name']);
    $sortBase = $total;

    $hasPrimary = $total > 0;
    for ($i = 0; $i < min($count, $slots); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp  = $files['tmp_name'][$i];
        $size = $files['size'][$i];
        $mime = image_type_from_magic($tmp);
        if (!in_array($mime, $allowed, true) || $size > 2 * 1024 * 1024) continue;
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $fn  = bin2hex(random_bytes(16)) . '.' . $ext;
        if (move_uploaded_file($tmp, $uploadDir . $fn)) {
            $isPrimary = $hasPrimary ? 0 : 1;
            $hasPrimary = true;
            $pdo->prepare('INSERT INTO product_photos (product_id, filename, sort_order, is_primary) VALUES (?, ?, ?, ?)')
                ->execute([$productId, $fn, $sortBase++, $isPrimary]);
        }
    }
}

function save_variants(PDO $pdo, int $productId): void {
    $variantIds    = $_POST['variant_id']    ?? [];
    $variantLabels = $_POST['variant_label'] ?? [];
    $variantLabelsKm = $_POST['variant_label_km'] ?? [];
    $variantStocks = $_POST['variant_stock'] ?? [];
    $variantPrices = $_POST['variant_price'] ?? [];

    $submittedIds = [];

    foreach ($variantLabels as $i => $rawLabel) {
        $label = trim($rawLabel);
        if ($label === '') continue;

        $labelKm = trim($variantLabelsKm[$i] ?? '') ?: null;
        $vStock = max(0, (int)($variantStocks[$i] ?? 0));
        $vPriceRaw = $variantPrices[$i] ?? '';
        $vPrice = ($vPriceRaw !== '' && is_numeric($vPriceRaw) && (float)$vPriceRaw >= 0)
            ? (float)$vPriceRaw
            : null;
        $vId = (int)($variantIds[$i] ?? 0);

        if ($vId) {
            $pdo->prepare('UPDATE product_variants SET label=?, label_km=?, stock=?, price_override=?, sort_order=? WHERE id=? AND product_id=?')
                ->execute([$label, $labelKm, $vStock, $vPrice, $i, $vId, $productId]);
            $submittedIds[] = $vId;
        } else {
            $pdo->prepare('INSERT INTO product_variants (product_id, label, label_km, stock, price_override, sort_order) VALUES (?,?,?,?,?,?)')
                ->execute([$productId, $label, $labelKm, $vStock, $vPrice, $i]);
            $submittedIds[] = (int)$pdo->lastInsertId();
        }
    }

    if ($submittedIds) {
        $ph = implode(',', array_fill(0, count($submittedIds), '?'));
        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($ph)")
            ->execute(array_merge([$productId], $submittedIds));
        // Sync product stock to sum of variant stocks
        $pdo->prepare('UPDATE products p SET p.stock = (SELECT COALESCE(SUM(v.stock),0) FROM product_variants v WHERE v.product_id = p.id) WHERE p.id = ?')
            ->execute([$productId]);
    } else {
        // No variants submitted — clear any existing
        $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$productId]);
    }
}

if ($action === 'gallery_upload') {
    $productId    = (int)($_POST['product_id'] ?? 0);
    $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));
    $stmt = $pdo->prepare("SELECT id, public_id FROM products WHERE id = ? AND business_id IN ($placeholders)");
    $stmt->execute(array_merge([$productId], array_map('intval', $ownedIds)));
    $galleryProduct = $stmt->fetch();
    if (!$galleryProduct) { header('Location: /products/'); exit; }
    save_gallery_photos($pdo, $uploadDir, $allowed, $productId);
    header('Location: /products/?action=edit&id=' . $galleryProduct['public_id']);
    exit;
}

if ($action === 'add') {
    $newPublicId = uuid_v4();
    $stmt = $pdo->prepare('INSERT INTO products (business_id, category_id, name, name_km, description, description_km, price, stock, delivery_method, sale_price, sale_ends_at, public_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$businessId, $categoryId, $name, $nameKm ?: null, $description, $descriptionKm ?: null, $price, $stock, $deliveryMethod, $salePrice, $saleEndsAt, $newPublicId]);
    $newId = (int)$pdo->lastInsertId();
    if ($photo) {
        $pdo->prepare('INSERT INTO product_photos (product_id, filename, sort_order, is_primary) VALUES (?, ?, 0, 1)')
            ->execute([$newId, $photo]);
    }
    save_gallery_photos($pdo, $uploadDir, $allowed, $newId);
    save_variants($pdo, $newId);
    $_SESSION['product_success'] = 'Product added.';

} elseif ($action === 'edit') {
    $productId    = (int)($_POST['product_id'] ?? 0);
    $placeholders = implode(',', array_fill(0, count($ownedIds), '?'));
    $stmt = $pdo->prepare("SELECT id, public_id FROM products WHERE id = ? AND business_id IN ($placeholders)");
    $stmt->execute(array_merge([$productId], array_map('intval', $ownedIds)));
    $ownedProduct = $stmt->fetch();

    if (!$ownedProduct) {
        header('Location: /products/');
        exit;
    }
    $productPublicId = $ownedProduct['public_id'];

    $active = ($_POST['active'] ?? '0') === '1' ? 1 : 0;

    $stmt = $pdo->prepare('UPDATE products SET category_id=?, name=?, name_km=?, description=?, description_km=?, price=?, stock=?, delivery_method=?, active=?, sale_price=?, sale_ends_at=? WHERE id=?');
    $stmt->execute([$categoryId, $name, $nameKm ?: null, $description, $descriptionKm ?: null, $price, $stock, $deliveryMethod, $active, $salePrice, $saleEndsAt, $productId]);
    // If stock was replenished above threshold, clear the notification flag so vendor gets alerted again if it drops low again
    $pdo->prepare('UPDATE products SET low_stock_notified_at = NULL WHERE id = ? AND stock > low_stock_threshold')
        ->execute([$productId]);
    save_gallery_photos($pdo, $uploadDir, $allowed, $productId);
    save_variants($pdo, $productId);
    $_SESSION['product_success'] = 'Product updated.';
}

header('Location: /products/?action=edit&id=' . ($productPublicId ?? $newPublicId));
exit;
