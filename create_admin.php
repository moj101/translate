<?php
   require_once 'db_connect.php';

   // اطلاعات کاربر ادمین
   $username = 'admin';
   $password = 'admin123';
   $email = 'admin@example.com';

   try {
       $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
       $stmt->execute([$username]);
       if ($stmt->fetch()) {
           die("کاربر با این نام کاربری قبلاً وجود دارد!");
       }

       $hashed_password = password_hash($password, PASSWORD_DEFAULT);
       $stmt = $pdo->prepare("INSERT INTO users (username, password, email, is_admin) VALUES (?, ?, ?, TRUE)");
       $stmt->execute([$username, $hashed_password, $email]);
       $user_id = $pdo->lastInsertId();

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
