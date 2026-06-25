<?php
session_start();
require __DIR__ . '/../config/csrf.php';
require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/');
    exit;
}

csrf_verify();

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['auth_error'] = 'Invalid email or password.';
    header('Location: /login/');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
header('Location: /dashboard/');
exit;
