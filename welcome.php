<?php
session_start();

// محافظت از دسترسی مستقیم به صفحه
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], 'register.php') === false) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خوش‌آمدگویی</title>
<link href="/assets/css/tailwind.min.css" rel="stylesheet">
<script src="/assets/js/lucide.min.js"></script>
<script>
  lucide.createIcons(); // رندر آیکون‌ها
</script>
    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
            font-family: 'Vazirmatn', 'Arial', sans-serif;
        }
        input {
            font-family: 'Vazirmatn', 'Arial', sans-serif;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/@fontsource/vazirmatn/400.css" rel="stylesheet">
</head>
<body class="min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-2xl rounded-3xl p-8 w-full max-w-md mx-4">
        <div class="text-center mb-6">
            <img src="https://cdn-icons-png.flaticon.com/512/3064/3064197.png" alt="Logo" class="w-16 h-16 mx-auto mb-2">
            <h2 class="text-2xl font-bold text-gray-800">تبریک! ثبت‌نام شما موفقیت‌آمیز بود</h2>
            <p class="text-sm text-gray-600 mt-2">حساب شما با موفقیت ایجاد شد. برای دسترسی به همه امکانات، لطفاً منتظر تأیید حساب توسط ادمین باشید.</p>
        </div>

        <div class="bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-xl mb-6 text-center">
            <i data-lucide="check-circle" class="inline-block w-5 h-5 ml-2"></i>
            ثبت‌نام شما تکمیل شد! اکنون می‌توانید وارد سیستم شوید.
        </div>

        <div class="text-center">
            <a href="login.php" class="bg-blue-600 text-white font-semibold py-3 px-6 rounded-md hover:bg-blue-700 transition inline-flex items-center gap-2">
                <i data-lucide="log-in"></i> ورود به سیستم
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>