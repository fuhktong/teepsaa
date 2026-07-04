<?php
session_start();
$lang = $_POST['lang'] ?? 'en';
$lang = in_array($lang, ['en', 'km'], true) ? $lang : 'en';
$_SESSION['lang'] = $lang;

// Persist the choice to the account so it follows the user across
// sessions and devices (buyers and vendors have a `lang` column).
if (!empty($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['buyer', 'vendor'], true)) {
    require __DIR__ . '/../config/db.php';
    $table = $_SESSION['role'] === 'vendor' ? 'vendors' : 'buyers';
    $pdo->prepare("UPDATE {$table} SET lang = ? WHERE id = ?")
        ->execute([$lang, (int)$_SESSION['user_id']]);
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
