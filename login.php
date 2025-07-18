<?php
// فعال‌سازی لاگ‌گیری برای اشکال‌زدایی (در محیط تولید غیرفعال کنید)
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

session_start();
if (isset($_SESSION['user_id'])) {
    error_log("User {$_SESSION['user_id']} already logged in, redirecting to index.php");
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db_connect.php';
    
    if (!$pdo) {
        $error = "خطا در اتصال به پایگاه داده. لطفاً با مدیر سرور تماس بگیرید.";
        error_log("Database connection failed in login.php");
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "نام کاربری و رمز عبور الزامی هستند.";
            error_log("Empty username or password in login attempt");
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, password FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;

                    $ip_address = $_SERVER['REMOTE_ADDR'];
                    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address, timestamp) VALUES (?, 'login', ?, NOW())");
                    $stmt->execute([$user['id'], $ip_address]);

                    error_log("User {$username} logged in successfully from IP {$ip_address}");
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "نام کاربری یا رمز عبور اشتباه است.";
                    error_log("Failed login attempt for username {$username}");
                }
            } catch (Exception $e) {
                $error = "خطا در ورود: " . htmlspecialchars($e->getMessage());
                error_log("Login error for username {$username}: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سامانه ترجمه</title>
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
            <h2 class="text-2xl font-bold text-gray-800">سامانه ترجمه هوشمند</h2>
            <p class="text-sm text-gray-600">لطفاً برای ورود اطلاعات خود را وارد نمایید</p>
        </div>

        <?php if (!empty($error)): ?>
            <p class="text-red-600 text-center font-medium bg-red-100 p-2 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">نام کاربری</label>
                <input type="text" name="username" id="username" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">رمز عبور</label>
                <input type="password" name="password" id="password" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>

            <button type="submit"
                    class="w-full bg-blue-600 text-white font-semibold py-3 rounded-md hover:bg-blue-700 transition flex items-center justify-center gap-2">
                <i data-lucide="log-in"></i> ورود
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="register.php" class="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-2 px-6 rounded-md inline-flex items-center gap-2">
                <i data-lucide="user-plus"></i> ثبت‌نام
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>