<?php
session_start();
$lang = $_POST['lang'] ?? 'en';
$_SESSION['lang'] = in_array($lang, ['en', 'km']) ? $lang : 'en';
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;
