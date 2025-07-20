<?php
require_once 'db_connect.php';

// اطلاعات کاربر ادمین
$username = 'admin';
$password = 'admin123';
$email = 'admin@example.com';
$first_name = 'ادمین';
$last_name = 'سیستم';
$phone = '09100000000';

try {
    // بررسی وجود کاربر با همین نام کاربری
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        die("کاربر با این نام کاربری قبلاً وجود دارد!");
    }

    // هش کردن رمز عبور
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // درج کاربر جدید
    $stmt = $pdo->prepare("INSERT INTO users 
        (username, first_name, last_name, phone, password, email, is_verified, is_admin) 
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
    $stmt->execute([$username, $first_name, $last_name, $phone, $hashed_password, $email]);

    $user_id = $pdo->lastInsertId();

    // اختصاص دسترسی‌ها
    $stmt = $pdo->prepare("INSERT INTO permissions (user_id, can_translate_text, can_translate_pdf) VALUES (?, 1, 1)");
    $stmt->execute([$user_id]);

    echo "کاربر ادمین با موفقیت ایجاد شد!<br>";
    echo "نام کاربری: $username<br>";
    echo "رمز عبور: $password<br>";
    echo "ایمیل: $email<br>";
    echo "اکنون می‌توانید به <a href='login.php'>صفحه ورود</a> بروید.";
} catch (Exception $e) {
    die("خطا در ایجاد کاربر ادمین: " . $e->getMessage());
}
?>
