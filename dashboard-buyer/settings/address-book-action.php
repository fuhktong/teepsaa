<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'buyer') {
    header('Location: /login-buyer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard-buyer/settings/?tab=address');
    exit;
}

csrf_verify();

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $label        = mb_substr(trim($_POST['label'] ?? ''), 0, 100);
    $houseNumber  = mb_substr(trim($_POST['house_number'] ?? ''), 0, 50) ?: null;
    $address      = mb_substr(trim($_POST['address'] ?? ''), 0, 255) ?: null;
    $addressNotes = mb_substr(trim($_POST['address_notes'] ?? ''), 0, 255) ?: null;
    $khan         = mb_substr(trim($_POST['khan'] ?? ''), 0, 100) ?: null;
    $sangkat      = mb_substr(trim($_POST['sangkat'] ?? ''), 0, 100) ?: null;
    $lat          = ($_POST['lat'] ?? '') !== '' ? (float)$_POST['lat'] : null;
    $lng          = ($_POST['lng'] ?? '') !== '' ? (float)$_POST['lng'] : null;

    if (!$label && !$address && !$khan) {
        $_SESSION['settings_error'] = 'Please fill in at least a label and address.';
        header('Location: /dashboard-buyer/settings/?tab=address');
        exit;
    }

    // First use of the address book: import the existing main address from
    // the buyers table so it appears in the list instead of being stranded
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM buyer_addresses WHERE buyer_user_id = ?');
    $countStmt->execute([$userId]);
    if ((int)$countStmt->fetchColumn() === 0) {
        $bStmt = $pdo->prepare('SELECT house_number, address, address_notes, khan, sangkat, lat, lng FROM buyers WHERE id = ?');
        $bStmt->execute([$userId]);
        $b = $bStmt->fetch();
        if ($b && ($b['address'] !== null || $b['khan'] !== null || $b['house_number'] !== null)) {
            $pdo->prepare('INSERT INTO buyer_addresses (buyer_user_id, label, house_number, address, address_notes, khan, sangkat, lat, lng, is_default) VALUES (?,?,?,?,?,?,?,?,?,1)')
                ->execute([$userId, $b['khan'] ?: 'Address', $b['house_number'], $b['address'], $b['address_notes'], $b['khan'], $b['sangkat'], $b['lat'], $b['lng']]);
        }
    }

    $pdo->prepare('INSERT INTO buyer_addresses (buyer_user_id, label, house_number, address, address_notes, khan, sangkat, lat, lng) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$userId, $label, $houseNumber, $address, $addressNotes, $khan, $sangkat, $lat, $lng]);

    $_SESSION['settings_success'] = 'Address saved.';

} elseif ($action === 'set_default') {
    $addrId = (int)($_POST['address_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM buyer_addresses WHERE id = ? AND buyer_user_id = ?');
    $stmt->execute([$addrId, $userId]);
    $addr = $stmt->fetch();

    if (!$addr) {
        header('Location: /dashboard-buyer/settings/?tab=address');
        exit;
    }

    $pdo->prepare('UPDATE buyer_addresses SET is_default = 0 WHERE buyer_user_id = ?')->execute([$userId]);
    $pdo->prepare('UPDATE buyer_addresses SET is_default = 1 WHERE id = ?')->execute([$addrId]);

    // Sync to buyers table so delivery calculation uses this address
    $pdo->prepare('UPDATE buyers SET house_number=?, address=?, address_notes=?, khan=?, sangkat=?, lat=?, lng=? WHERE id=?')
        ->execute([$addr['house_number'], $addr['address'], $addr['address_notes'], $addr['khan'], $addr['sangkat'], $addr['lat'], $addr['lng'], $userId]);

    $_SESSION['settings_success'] = 'Default address updated.';

} elseif ($action === 'delete') {
    $addrId = (int)($_POST['address_id'] ?? 0);
    $pdo->prepare('DELETE FROM buyer_addresses WHERE id = ? AND buyer_user_id = ?')->execute([$addrId, $userId]);
    $_SESSION['settings_success'] = 'Address removed.';
}

header('Location: /dashboard-buyer/settings/?tab=address');
exit;
