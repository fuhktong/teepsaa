<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/messages/emails.php');
    exit;
}
csrf_verify();

$key = $_POST['key'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM email_templates WHERE template_key = ?');
$stmt->execute([$key]);
$tpl = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$tpl) {
    header('Location: /admin/messages/emails.php');
    exit;
}

$fields = ['subject_km', 'subject_en', 'heading_km', 'heading_en', 'body_km', 'body_en', 'cta_km', 'cta_en'];
$data = [];
foreach ($fields as $f) {
    $data[$f] = trim($_POST[$f] ?? '');
}

$backToEdit = function (string $msg) use ($key, $data) {
    $_SESSION['email_tpl_error'] = $msg;
    $_SESSION['email_tpl_old']   = $data;
    header('Location: /admin/messages/email-edit.php?key=' . urlencode($key));
    exit;
};

// Required fields
foreach (['subject_km', 'subject_en', 'heading_km', 'heading_en', 'body_km', 'body_en'] as $f) {
    if ($data[$f] === '') $backToEdit('Subject, heading and body are required in both languages.');
}

// Every placeholder token this template needs must still be present somewhere,
// so the code can inject the real value. This stops an accidental deletion.
$haystack = implode("\n", $data);
$missing  = [];
foreach (array_filter(array_map('trim', explode(',', $tpl['tokens'] ?? ''))) as $tok) {
    if ($tok !== '' && strpos($haystack, $tok) === false) $missing[] = $tok;
}
if ($missing) {
    $backToEdit('Missing required placeholder(s): ' . implode(', ', $missing) . '. Please keep them in the text.');
}

$upd = $pdo->prepare(
    'UPDATE email_templates
        SET subject_km = ?, subject_en = ?, heading_km = ?, heading_en = ?,
            body_km = ?, body_en = ?, cta_km = ?, cta_en = ?
     WHERE template_key = ?'
);
$upd->execute([
    $data['subject_km'], $data['subject_en'],
    $data['heading_km'], $data['heading_en'],
    $data['body_km'], $data['body_en'],
    $data['cta_km'] ?: null, $data['cta_en'] ?: null,
    $key,
]);

$_SESSION['email_tpl_success'] = 'Saved "' . $tpl['label'] . '".';
header('Location: /admin/messages/emails.php');
exit;
