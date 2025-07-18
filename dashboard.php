<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد مدیریت</title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <script src="/assets/js/lucide.min.js"></script>
    <script>
      lucide.createIcons();
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    if ($_SESSION['username'] !== 'admin') {
        header("Location: home.php");
        exit;
    }

    // افزودن کاربر جدید
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = trim($_POST['email']);
        $can_translate_text = 1; // دسترسی پیش‌فرض برای ترجمه متن
        $can_translate_pdf = 0; // بدون دسترسی پیش‌فرض
        $can_extract_pdf = 0; // بدون دسترسی پیش‌فرض
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, email, is_verified) VALUES (?, ?, ?, 0)");
            $stmt->execute([$username, $password, $email]);
            $user_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("INSERT INTO permissions (user_id, can_translate_text, can_translate_pdf, can_extract_pdf) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $can_translate_text, $can_translate_pdf, $can_extract_pdf]);
            $pdo->commit();
            echo '<p class="text-green-500 text-center">کاربر با موفقیت اضافه شد.</p>';
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<p class="text-red-500 text-center">خطا در افزودن کاربر: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    
    // ویرایش دسترسی‌های کاربر
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
        $user_id = $_POST['user_id'];
        $can_translate_text = isset($_POST['can_translate_text']) ? 1 : 0;
        $can_translate_pdf = isset($_POST['can_translate_pdf']) ? 1 : 0;
        $can_extract_pdf = isset($_POST['can_extract_pdf']) ? 1 : 0;
        
        $stmt = $pdo->prepare("UPDATE permissions SET can_translate_text = ?, can_translate_pdf = ?, can_extract_pdf = ? WHERE user_id = ?");
        $stmt->execute([$can_translate_text, $can_translate_pdf, $can_extract_pdf, $user_id]);
        echo '<p class="text-green-500 text-center">دسترسی‌ها با موفقیت به‌روزرسانی شد.</p>';
    }
    
    // تغییر رمز عبور
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $user_id = $_POST['user_id'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $user_id]);
        echo '<p class="text-green-500 text-center">رمز عبور با موفقیت تغییر کرد.</p>';
    }
    
    // تغییر وضعیت تأیید
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_verification'])) {
        $user_id = $_POST['user_id'];
        $is_verified = $_POST['is_verified'] == 1 ? 0 : 1; // تغییر وضعیت
        
        $stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
        $stmt->execute([$is_verified, $user_id]);
        echo '<p class="text-green-500 text-center">وضعیت تأیید کاربر با موفقیت تغییر کرد.</p>';
    }
    
    // حذف کاربر
    if (isset($_GET['delete_user'])) {
        $user_id = $_GET['delete_user'];
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM permissions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $pdo->commit();
            echo '<p class="text-green-500 text-center">کاربر با موفقیت حذف شد.</p>';
        } catch (Exception $e) {
            $pdo->rollBack();
            echo '<p class="text-red-500 text-center">خطا در حذف کاربر: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    
    // دریافت لیست کاربران
    $stmt = $pdo->query("SELECT u.id, u.username, u.email, u.is_verified, p.can_translate_text, p.can_translate_pdf, p.can_extract_pdf 
                         FROM users u 
                         LEFT JOIN permissions p ON u.id = p.user_id");
    $users = $stmt->fetchAll();
    ?>
    <div class="container mx-auto p-4">
        <h2 class="text-2xl font-bold mb-4 text-center">مدیریت کاربران</h2>
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h3 class="text-xl font-semibold mb-4">ناوبری</h3>
            <div class="flex flex-wrap gap-4">
                <a href="home.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-6 rounded-xl shadow">🏠 صفحه اصلی</a>
                <a href="index1.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-medium py-2 px-6 rounded-xl shadow">📄 ترجمه فایل PDF</a>
                <a href="index2.php" class="bg-sky-100 hover:bg-sky-200 text-sky-700 font-medium py-2 px-6 rounded-xl shadow">📝 ترجمه متن</a>
                <a href="pdf_extract.php" class="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-2 px-6 rounded-xl shadow">🗃 استخراج PDF</a>
                <a href="reports.php" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-6 rounded-xl shadow">📊 گزارش‌ها</a>
                <a href="logout.php" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-6 rounded-xl shadow">خروج</a>
            </div>
        </div>
        
        <!-- فرم افزودن کاربر -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h3 class="text-xl font-semibold mb-4">افزودن کاربر جدید</h3>
            <form action="dashboard.php" method="POST" class="space-y-4">
                <input type="hidden" name="add_user" value="1">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">نام کاربری</label>
                    <input type="text" name="username" id="username" required class="mt-1 p-2 w-full border rounded-md">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">رمز عبور</label>
                    <input type="password" name="password" id="password" required class="mt-1 p-2 w-full border rounded-md">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">ایمیل</label>
                    <input type="email" name="email" id="email" required class="mt-1 p-2 w-full border rounded-md">
                </div>
                <div class="flex space-x-4">
                    <label><input type="checkbox" name="can_translate_text" checked disabled> دسترسی به ترجمه متن</label>
                    <label><input type="checkbox" name="can_translate_pdf"> دسترسی به ترجمه فایل</label>
                    <label><input type="checkbox" name="can_extract_pdf"> دسترسی به استخراج PDF</label>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded-md hover:bg-blue-700">افزودن</button>
            </form>
        </div>
        
        <!-- لیست کاربران -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-xl font-semibold mb-4">لیست کاربران</h3>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 border">نام کاربری</th>
                        <th class="p-2 border">ایمیل</th>
                        <th class="p-2 border">وضعیت تأیید</th>
                        <th class="p-2 border">دسترسی‌ها</th>
                        <th class="p-2 border">تغییر رمز عبور</th>
                        <th class="p-2 border">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="p-2 border"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="p-2 border"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="p-2 border text-center">
                            <form action="dashboard.php" method="POST">
                                <input type="hidden" name="toggle_verification" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="is_verified" value="<?php echo $user['is_verified']; ?>">
                                <button type="submit" class="px-2 py-1 rounded-md <?php echo $user['is_verified'] ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-yellow-600 text-white hover:bg-yellow-700'; ?>">
                                    <?php echo $user['is_verified'] ? 'تأییدشده' : 'در انتظار تأیید'; ?>
                                </button>
                            </form>
                        </td>
                        <td class="p-2 border text-center">
                            <form action="dashboard.php" method="POST">
                                <input type="hidden" name="edit_user" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <div class="flex justify-center space-x-4">
                                    <label><input type="checkbox" name="can_translate_text" <?php echo $user['can_translate_text'] ? 'checked' : ''; ?>> متن</label>
                                    <label><input type="checkbox" name="can_translate_pdf" <?php echo $user['can_translate_pdf'] ? 'checked' : ''; ?>> ترجمه فایل</label>
                                    <label><input type="checkbox" name="can_extract_pdf" <?php echo $user['can_extract_pdf'] ? 'checked' : ''; ?>> استخراج PDF</label>
                                    <button type="submit" class="bg-blue-600 text-white px-2 py-1 rounded-md hover:bg-blue-700">ذخیره</button>
                                </div>
                            </form>
                        </td>
                        <td class="p-2 border text-center">
                            <form action="dashboard.php" method="POST" class="space-y-2">
                                <input type="hidden" name="change_password" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="password" name="new_password" placeholder="رمز عبور جدید" required class="p-1 border rounded-md">
                                <button type="submit" class="bg-green-600 text-white px-2 py-1 rounded-md hover:bg-green-700">تغییر</button>
                            </form>
                        </td>
                        <td class="p-2 border">
                            <a href="dashboard.php?delete_user=<?php echo $user['id']; ?>" class="text-red-600 hover:underline" onclick="return confirm('آیا مطمئن هستید؟')">حذف</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>