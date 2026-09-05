<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

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

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$back    = '/';
if ($referer) {
    $refHost = parse_url($referer, PHP_URL_HOST);
    if ($refHost !== null && $refHost === ($_SERVER['HTTP_HOST'] ?? null)) {
        $back = $referer;
    }
}

// Strip any ?lang= from the page we're returning to. A visitor who arrived on
// an English link from Google is on an address that names its own language,
// and current_lang() lets the address win — so sending them back to it would
// silently undo the switch they just clicked.
$backParts = parse_url($back);
$backQuery = [];
if (!empty($backParts['query'])) {
    parse_str($backParts['query'], $backQuery);
    unset($backQuery['lang']);
}
$back = ($backParts['path'] ?? '/') . ($backQuery ? '?' . http_build_query($backQuery) : '');
header('Location: ' . $back);
exit;
