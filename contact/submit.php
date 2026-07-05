<?php
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact/');
    exit;
}

require __DIR__ . '/../config/db.php';

// Honeypot — bots fill hidden fields, humans don't
if (($_POST['website'] ?? '') !== '') {
    header('Location: /contact/thanks/');
    exit;
}

// Rate limit — 1 submission per 60 seconds per session
$lastSubmit = $_SESSION['contact_guest_last'] ?? 0;
if (time() - $lastSubmit < 60) {
    $_SESSION['contact_guest_error'] = 'Please wait a moment before submitting again.';
    header('Location: /contact/');
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

$errors = [];
if ($name === '')                                              $errors[] = 'Name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($subject === '')                                           $errors[] = 'Subject is required.';
if ($body === '')                                             $errors[] = 'Message is required.';

if ($errors) {
    $_SESSION['contact_guest_error'] = implode(' ', $errors);
    $_SESSION['contact_guest_old']   = compact('name', 'email', 'subject', 'body');
    header('Location: /contact/');
    exit;
}

$pdo->prepare("
    INSERT INTO support_threads (sender_id, sender_role, guest_name, guest_email, subject, status)
    VALUES (NULL, 'guest', ?, ?, ?, 'pending')
")->execute([$name, $email, mb_substr($subject, 0, 255)]);

$threadId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO support_messages (thread_id, sender, body) VALUES (?, 'guest', ?)")
    ->execute([$threadId, mb_substr($body, 0, 2000)]);

$_SESSION['contact_guest_last'] = time();

header('Location: /contact/thanks/');
exit;
