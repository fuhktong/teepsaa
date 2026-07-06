<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/admin-auth.php';
require __DIR__ . '/../config/csrf.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

admin_require('faq');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/faq.php');
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function redirect_faq(string $msg = '', bool $error = false, string $anchor = ''): never {
    if ($msg) {
        $_SESSION[$error ? 'faq_error' : 'faq_success'] = $msg;
    }
    header('Location: /admin/faq.php' . $anchor);
    exit;
}

match ($action) {
    'add'       => do_add(),
    'edit'      => do_edit($id),
    'delete'    => do_delete($id),
    'toggle'    => do_toggle($id),
    'move_up'   => do_move($id, 'up'),
    'move_down' => do_move($id, 'down'),
    default     => redirect_faq(),
};

function read_faq_fields(): array {
    return [
        'section_en'  => trim($_POST['section_en']  ?? ''),
        'section_km'  => trim($_POST['section_km']  ?? ''),
        'question_en' => trim($_POST['question_en'] ?? ''),
        'question_km' => trim($_POST['question_km'] ?? ''),
        'answer_en'   => trim($_POST['answer_en']   ?? ''),
        'answer_km'   => trim($_POST['answer_km']   ?? ''),
    ];
}

function do_add(): void {
    global $pdo;

    $f = read_faq_fields();
    foreach ($f as $v) {
        if ($v === '') redirect_faq('All fields are required.', true, '?add=1#faq-add');
    }

    $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM faq_items')->fetchColumn();

    $stmt = $pdo->prepare('INSERT INTO faq_items (section_en, section_km, question_en, question_km, answer_en, answer_km, sort_order, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->execute([$f['section_en'], $f['section_km'], $f['question_en'], $f['question_km'], $f['answer_en'], $f['answer_km'], $maxSort + 1]);

    redirect_faq('FAQ item added.');
}

function do_edit(int $id): void {
    global $pdo;
    if (!$id) redirect_faq('Invalid item.', true);

    $f = read_faq_fields();
    foreach ($f as $v) {
        if ($v === '') redirect_faq('All fields are required.', true, '?edit=' . $id . '#faq-' . $id);
    }

    $stmt = $pdo->prepare('UPDATE faq_items SET section_en = ?, section_km = ?, question_en = ?, question_km = ?, answer_en = ?, answer_km = ? WHERE id = ?');
    $stmt->execute([$f['section_en'], $f['section_km'], $f['question_en'], $f['question_km'], $f['answer_en'], $f['answer_km'], $id]);

    redirect_faq('FAQ item updated.', false, '#faq-' . $id);
}

function do_delete(int $id): void {
    global $pdo;
    if (!$id) redirect_faq('Invalid item.', true);
    $pdo->prepare('DELETE FROM faq_items WHERE id = ?')->execute([$id]);
    redirect_faq('FAQ item deleted.');
}

function do_toggle(int $id): void {
    global $pdo;
    if (!$id) redirect_faq();
    $pdo->prepare('UPDATE faq_items SET active = 1 - active WHERE id = ?')->execute([$id]);
    redirect_faq();
}

function do_move(int $id, string $dir): void {
    global $pdo;
    if (!$id) redirect_faq();

    $rows = $pdo->query('SELECT id, sort_order FROM faq_items ORDER BY sort_order ASC, id ASC')->fetchAll();
    $idx  = array_search($id, array_column($rows, 'id'));

    if ($idx === false) redirect_faq();

    $swapIdx = $dir === 'up' ? $idx - 1 : $idx + 1;
    if ($swapIdx < 0 || $swapIdx >= count($rows)) redirect_faq();

    $a = $rows[$idx];
    $b = $rows[$swapIdx];

    $stmt = $pdo->prepare('UPDATE faq_items SET sort_order = ? WHERE id = ?');
    $stmt->execute([$b['sort_order'], $a['id']]);
    $stmt->execute([$a['sort_order'], $b['id']]);

    if ($a['sort_order'] === $b['sort_order']) {
        $stmt->execute([$swapIdx, $b['id']]);
        $stmt->execute([$idx,     $a['id']]);
    }

    redirect_faq();
}
