<?php
// فعال‌سازی لاگ‌گیری برای اشکال‌زدایی
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

// بررسی و مقداردهی اولیه سشن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Session started in register.php");
}

if (isset($_SESSION['user_id'])) {
    error_log("User {$_SESSION['user_id']} already logged in, redirecting to index.php");
    header("Location: index.php");
    exit;
}

// تولید توکن CSRF اگر وجود نداشته باشد
if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        error_log("CSRF token generated: {$_SESSION['csrf_token']}");
    } catch (Exception $e) {
        error_log("Error generating CSRF token: " . $e->getMessage());
        $error = "خطا در تولید توکن امنیتی. لطفاً دوباره تلاش کنید.";
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // بررسی توکن CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "توکن CSRF نامعتبر است. لطفاً دوباره فرم را ارسال کنید.";
        error_log("Invalid CSRF token in register.php. Sent: " . ($_POST['csrf_token'] ?? 'none') . ", Expected: " . ($_SESSION['csrf_token'] ?? 'none'));
    } else {
        require_once 'db_connect.php';
        
        if (!$pdo) {
            $error = "خطا در اتصال به پایگاه داده. لطفاً با مدیر سرور تماس بگیرید.";
            error_log("Database connection failed in register.php");
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $email = trim($_POST['email'] ?? '');
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            $errors = [];
            if (empty($username) || strlen($username) < 4) {
                $errors[] = 'نام کاربری باید حداقل 4 کاراکتر باشد.';
            }
            if (empty($password) || strlen($password) < 6) {
                $errors[] = 'رمز عبور باید حداقل 6 کاراکتر باشد.';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'ایمیل معتبر نیست.';
            }
            if (empty($first_name)) {
                $errors[] = 'نام الزامی است.';
            }
            if (empty($last_name)) {
                $errors[] = 'نام خانوادگی الزامی است.';
            }
            if (empty($phone) || !preg_match('/^[0-9]{10,12}$/', $phone)) {
                $errors[] = 'شماره تلفن معتبر نیست (باید 10 تا 12 رقم باشد).';
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetchColumn() > 0) {
                        $errors[] = 'نام کاربری یا ایمیل قبلاً ثبت شده است.';
                    }
                } catch (Exception $e) {
                    $errors[] = 'خطا در بررسی اطلاعات: ' . htmlspecialchars($e->getMessage());
                    error_log("Error checking username/email in register.php: " . $e->getMessage());
                }
            }

            if (empty($errors)) {
                $pdo->beginTransaction();
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, phone, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
                    $stmt->execute([$username, $hashed_password, $email, $first_name, $last_name, $phone]);
                    $user_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO permissions (user_id, can_translate_text, can_translate_pdf, can_extract_pdf) VALUES (?, 1, 0, 0)");
                    $stmt->execute([$user_id]);
                    $pdo->commit();

                    error_log("User {$username} registered successfully");
                    unset($_SESSION['csrf_token']); // حذف توکن پس از ثبت‌نام موفق
                    header("Location: welcome.php");
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "خطا در ثبت‌نام: " . htmlspecialchars($e->getMessage());
                    error_log("Error registering user {$username}: " . $e->getMessage());
                }
            } else {
                $error = implode('<br>', $errors);
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
    <title>ثبت‌نام در سامانه ترجمه</title>
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
            <h2 class="text-2xl font-bold text-gray-800">ثبت‌نام در سامانه ترجمه هوشمند</h2>
            <p class="text-sm text-gray-600">لطفاً اطلاعات خود را وارد نمایید</p>
        </div>

        <?php if (!empty($error)): ?>
            <p class="text-red-600 text-center font-medium bg-red-100 p-2 rounded-md mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
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
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">ایمیل</label>
                <input type="email" name="email" id="email" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>
            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">نام</label>
                <input type="text" name="first_name" id="first_name" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>
            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">نام خانوادگی</label>
                <input type="text" name="last_name" id="last_name" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">شماره تلفن</label>
                <input type="text" name="phone" id="phone" required
                       class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white font-semibold py-3 rounded-md hover:bg-blue-700 transition flex items-center justify-center gap-2">
                <i data-lucide="user-plus"></i> ثبت‌نام
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="login.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-6 rounded-md inline-flex items-center gap-2">
                <i data-lucide="log-in"></i> ورود
            </a>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>