<?php
require __DIR__ . '/../config/db.php';

// Delete tokens older than 24 hours (used or expired)
$pdo->exec("DELETE FROM password_resets WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
