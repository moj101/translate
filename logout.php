<?php
session_start();
require_once 'db_connect.php';

// بررسی وجود user_id قبل از ثبت لاگ خروج
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address, timestamp) VALUES (?, 'logout', ?, NOW())");
    $stmt->execute([$user_id, $ip_address]);
}

// پاک کردن متغیرهای جلسه و تخریب جلسه
session_unset();
session_destroy();

// هدایت به صفحه login.php
header("Location: login.php");
exit;
?>