<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/unsubscribe.php';

// Reached from a link in a promotional email, so there is no session to lean
// on — the token in the URL is the whole identity, and it only ever toggles
// this one flag. Opting out is a POST, not the bare GET: mail scanners and
// link previewers fetch every URL in a message, and a GET opt-out would let
// them unsubscribe people who never clicked.
$role  = ($_GET['r'] ?? $_POST['r'] ?? '') === 'v' ? 'vendor' : 'buyer';
$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
$table = unsubscribe_table($role);

$user = null;
if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    $stmt = $pdo->prepare("SELECT id, email, unsubscribed_at FROM {$table} WHERE unsubscribe_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$didUnsub = false;
$didResub = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    csrf_verify();
    if (($_POST['do'] ?? '') === 'resubscribe') {
        $pdo->prepare("UPDATE {$table} SET unsubscribed_at = NULL WHERE id = ?")->execute([$user['id']]);
        $user['unsubscribed_at'] = null;
        $didResub = true;
    } else {
        $pdo->prepare("UPDATE {$table} SET unsubscribed_at = NOW() WHERE id = ?")->execute([$user['id']]);
        $user['unsubscribed_at'] = date('Y-m-d H:i:s');
        $didUnsub = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Email preferences — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" href="/images/teepsaa-icon-192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="/images/teepsaa-icon-180.png">
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/unsubscribe/unsubscribe.css">
</head>
<body>

<?php require __DIR__ . '/../header/header.php'; ?>

<main>
    <div class="unsub-box">
        <?php if (!$user): ?>
            <h1>តំណមិនត្រឹមត្រូវ<span class="unsub-en">Link not recognised</span></h1>
            <p>តំណនេះលែងដំណើរការហើយ។ សូមប្រើតំណ «ឈប់ទទួលអ៊ីមែល» ពីអ៊ីមែលថ្មីបំផុតរបស់អ្នក ឬទាក់ទងមកយើង។</p>
            <p class="unsub-en-block">This unsubscribe link is no longer valid. Use the link in your most recent teepsaa email, or <a href="/contact/">contact us</a> and we'll take care of it.</p>

        <?php elseif ($user['unsubscribed_at']): ?>
            <h1><?= $didUnsub ? 'រួចរាល់' : 'អ្នកបានឈប់ទទួលហើយ' ?><span class="unsub-en"><?= $didUnsub ? 'Done' : 'You are unsubscribed' ?></span></h1>
            <p><strong><?= htmlspecialchars($user['email']) ?></strong> នឹងលែងទទួលអ៊ីមែលផ្សព្វផ្សាយពី teepsaa ទៀតហើយ។ អ៊ីមែលអំពីការបញ្ជាទិញ ការដឹកជញ្ជូន និងគណនីរបស់អ្នក នៅតែបញ្ជូនដដែល។</p>
            <p class="unsub-en-block"><strong><?= htmlspecialchars($user['email']) ?></strong> will no longer receive promotional emails from teepsaa. Emails about your orders, deliveries and account are not affected — we still need to send you those.</p>
            <form method="POST" action="/unsubscribe/">
                <?= csrf_input() ?>
                <input type="hidden" name="r" value="<?= $role === 'vendor' ? 'v' : 'b' ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="do" value="resubscribe">
                <button type="submit" class="unsub-btn unsub-btn--secondary">ទទួលឡើងវិញ · Resubscribe</button>
            </form>

        <?php else: ?>
            <h1><?= $didResub ? 'ទទួលឡើងវិញរួចរាល់' : 'ឈប់ទទួលអ៊ីមែលផ្សព្វផ្សាយ' ?><span class="unsub-en"><?= $didResub ? 'You are subscribed again' : 'Unsubscribe from promotional emails' ?></span></h1>
            <p><strong><?= htmlspecialchars($user['email']) ?></strong> — ចុចប៊ូតុងខាងក្រោម ដើម្បីឈប់ទទួលអ៊ីមែលផ្សព្វផ្សាយ។ អ៊ីមែលអំពីការបញ្ជាទិញ និងគណនីរបស់អ្នក នៅតែបញ្ជូនដដែល។</p>
            <p class="unsub-en-block"><strong><?= htmlspecialchars($user['email']) ?></strong> — press the button to stop receiving promotional emails. Emails about your orders and account will still be sent.</p>
            <form method="POST" action="/unsubscribe/">
                <?= csrf_input() ?>
                <input type="hidden" name="r" value="<?= $role === 'vendor' ? 'v' : 'b' ?>">
                <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
                <button type="submit" class="unsub-btn">ឈប់ទទួល · Unsubscribe</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../footer/footer.php'; ?>
</body>
</html>
