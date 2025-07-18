<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// حذف منطقی فایل
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("UPDATE translation_logs SET is_deleted = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$delete_id, $user_id]);
        header("Location: archive.php?message=فایل+با+موفقیت+حذف+شد");
        exit;
    } catch (Exception $e) {
        $error = "خطا در حذف فایل: " . htmlspecialchars($e->getMessage());
    }
}

// دریافت لیست فایل‌های ترجمه‌شده
try {
    $stmt = $pdo->prepare("SELECT id, filename, timestamp, final_txt_path, final_docx_path FROM translation_logs WHERE user_id = ? AND is_deleted = 0 ORDER BY timestamp DESC");
    $stmt->execute([$user_id]);
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "خطا در دریافت لیست فایل‌ها: " . htmlspecialchars($e->getMessage());
}

$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آرشیو ترجمه‌ها</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css" rel="stylesheet">
    <style>
        body { font-family: 'Vazir', sans-serif; background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%); }
        .container { max-width: 900px; margin: 50px auto; }
        .table { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
        .btn-download { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-3xl font-extrabold text-center text-indigo-700 mb-6">📚 آرشیو فایل‌های ترجمه‌شده</h1>
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>نام فایل</th>
                    <th>زمان ترجمه</th>
                    <th>دانلود</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($translations)): ?>
                    <tr>
                        <td colspan="4" class="text-center">هیچ فایل ترجمه‌شده‌ای یافت نشد.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($translations as $translation): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($translation['filename']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($translation['timestamp'])); ?></td>
                            <td>
                                <?php if ($translation['final_txt_path']): ?>
                                    <a href="<?php echo htmlspecialchars($translation['final_txt_path']); ?>" download class="btn btn-primary btn-sm btn-download">فایل متنی</a>
                                <?php endif; ?>
                                <?php if ($translation['final_docx_path']): ?>
                                    <a href="<?php echo htmlspecialchars($translation['final_docx_path']); ?>" download class="btn btn-primary btn-sm">فایل Word</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="archive.php?delete_id=<?php echo $translation['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('آیا مطمئن هستید که می‌خواهید این فایل را حذف کنید؟');">حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p class="text-center mt-4">
            <a href="index1.php" class="btn btn-outline-secondary">🔙 بازگشت به ترجمه</a>
            <a href="home.php" class="btn btn-outline-secondary">🏠 صفحه اصلی</a>
        </p>
    </div>
</body>
</html>