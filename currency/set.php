<?php
session_start();
$c = $_POST['currency'] ?? 'USD';
$_SESSION['currency'] = in_array($c, ['USD', 'KHR']) ? $c : 'USD';
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
