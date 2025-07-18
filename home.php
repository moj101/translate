<?php
// فعال‌سازی لاگ‌گیری برای اشکال‌زدایی (در محیط تولید غیرفعال کنید)
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

session_start();
require_once 'db_connect.php';

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt to home.php");
    header("Location: login.php");
    exit;
}

// بررسی اتصال به پایگاه داده
if (!$pdo) {
    error_log("Database connection failed in home.php");
    die('<p class="text-red-500 text-center">خطا در اتصال به پایگاه داده. لطفاً با مدیر سرور تماس بگیرید.</p>');
}

// دریافت مجوزهای کاربر
try {
    $stmt = $pdo->prepare("SELECT can_translate_text, can_translate_pdf, can_extract_pdf FROM permissions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permissions) {
        error_log("No permissions found for user_id {$_SESSION['user_id']}");
        $permissions = ['can_translate_text' => 0, 'can_translate_pdf' => 0, 'can_extract_pdf' => 0];
    }
} catch (Exception $e) {
    error_log("Error fetching permissions for user_id {$_SESSION['user_id']}: " . $e->getMessage());
    $permissions = ['can_translate_text' => 0, 'can_translate_pdf' => 0, 'can_extract_pdf' => 0];
}

// دریافت وضعیت تأیید کاربر
try {
    $stmt = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_verified = $user['is_verified'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching is_verified for user_id {$_SESSION['user_id']}: " . $e->getMessage());
    $is_verified = 0;
}

$is_admin = ($_SESSION['username'] === 'admin');
$verification_message = '';
if (!$is_admin && !$is_verified) {
    $verification_message = 'حساب شما هنوز توسط ادمین تأیید نشده است. برای دسترسی به همه امکانات، لطفاً منتظر تأیید باشید.';
}

$error_message = '';
if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error_message = 'شما به این بخش دسترسی ندارید.';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>صفحه اصلی</title>
<link href="/assets/css/tailwind.min.css" rel="stylesheet">
<script src="/assets/js/lucide.min.js"></script>
<script>
  lucide.createIcons(); // فعال‌سازی آیکون‌ها
</script>
<style>
        body {
            font-family: 'Vazirmatn', 'Arial', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e0f2fe 100%);
        }
    </style>
   <link href="/assets/fonts/Vazirmatn-font-face.css" rel="stylesheet">    
   <style>
  body {
    font-family: 'Vazirmatn', sans-serif;
  }
</style>
   
</head>
<body class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-5xl mx-auto bg-white/70 backdrop-blur-md shadow-2xl rounded-3xl p-8 border border-blue-100">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-extrabold text-indigo-700">
                <?php echo $is_admin ? 'پنل مدیریت' : 'خوش آمدید، ' . htmlspecialchars($_SESSION['username']) . '!'; ?>
            </h2>
            <p class="text-gray-600 mt-1">لطفاً از گزینه‌های زیر استفاده کنید</p>
        </div>

        <?php if ($verification_message): ?>
            <div class="bg-yellow-100 border border-yellow-300 text-yellow-700 px-4 py-3 rounded-xl mb-6 text-center">
                <?php echo htmlspecialchars($verification_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-xl mb-6 text-center">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mb-10">
            <?php if ($is_admin || $permissions['can_translate_pdf']): ?>
                <a href="index1.php" class="transition-all duration-200 group flex flex-col items-center justify-center bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 py-6 px-4 rounded-2xl shadow hover:shadow-lg">
                    <i data-lucide="file-text" class="w-6 h-6 mb-2 group-hover:scale-110 transition"></i>
                    <span class="font-semibold">ترجمه فایل PDF</span>
                </a>
            <?php endif; ?>
            <?php if ($is_admin || $permissions['can_translate_text']): ?>
                <a href="index2.php" class="transition-all duration-200 group flex flex-col items-center justify-center bg-sky-50 hover:bg-sky-100 border border-sky-200 text-sky-700 py-6 px-4 rounded-2xl shadow hover:shadow-lg">
                    <i data-lucide="type" class="w-6 h-6 mb-2 group-hover:scale-110 transition"></i>
                    <span class="font-semibold">ترجمه متن</span>
                </a>
            <?php endif; ?>
            <?php if ($is_admin || $permissions['can_extract_pdf']): ?>
                <a href="pdf_extract.php" class="transition-all duration-200 group flex flex-col items-center justify-center bg-green-50 hover:bg-green-100 border border-green-200 text-green-700 py-6 px-4 rounded-2xl shadow hover:shadow-lg">
                    <i data-lucide="file-search" class="w-6 h-6 mb-2 group-hover:scale-110 transition"></i>
                    <span class="font-semibold">استخراج PDF</span>
                </a>
            <?php endif; ?>
            <?php if ($is_admin): ?>
                <a href="dashboard.php" class="transition-all duration-200 group flex flex-col items-center justify-center bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-700 py-6 px-4 rounded-2xl shadow hover:shadow-lg">
                    <i data-lucide="users" class="w-6 h-6 mb-2 group-hover:scale-110 transition"></i>
                    <span class="font-semibold">مدیریت کاربران</span>
                </a>
                <a href="reports.php" class="transition-all duration-200 group flex flex-col items-center justify-center bg-purple-50 hover:bg-purple-100 border border-purple-200 text-purple-700 py-6 px-4 rounded-2xl shadow hover:shadow-lg">
                    <i data-lucide="bar-chart" class="w-6 h-6 mb-2 group-hover:scale-110 transition"></i>
                    <span class="font-semibold">گزارش‌ها</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <a href="logout.php" class="inline-block bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-6 rounded-xl shadow transition flex items-center gap-2">
                <i data-lucide="log-out"></i> خروج از حساب
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>