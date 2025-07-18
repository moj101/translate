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

$limit = 20;
$page_logs = isset($_GET['log_page']) ? max(1, intval($_GET['log_page'])) : 1;
$page_pdfs = isset($_GET['pdf_page']) ? max(1, intval($_GET['pdf_page'])) : 1;
$page_translations = isset($_GET['trans_page']) ? max(1, intval($_GET['trans_page'])) : 1;

$offset_logs = ($page_logs - 1) * $limit;
$offset_pdfs = ($page_pdfs - 1) * $limit;
$offset_translations = ($page_translations - 1) * $limit;

$totalLogs = $pdo->query("SELECT COUNT(*) FROM user_logs")->fetchColumn();
$totalLogPages = ceil($totalLogs / $limit);
$stmtLogs = $pdo->prepare("SELECT ul.*, u.username FROM user_logs ul LEFT JOIN users u ON ul.user_id = u.id ORDER BY ul.timestamp DESC LIMIT :limit OFFSET :offset");
$stmtLogs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtLogs->bindValue(':offset', $offset_logs, PDO::PARAM_INT);
$stmtLogs->execute();
$logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

$totalPdfs = $pdo->query("SELECT COUNT(*) FROM pdf_extraction_logs")->fetchColumn();
$totalPdfPages = ceil($totalPdfs / $limit);
$stmtPdfs = $pdo->prepare("SELECT pel.*, u.username FROM pdf_extraction_logs pel LEFT JOIN users u ON pel.user_id = u.id ORDER BY pel.timestamp DESC LIMIT :limit OFFSET :offset");
$stmtPdfs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtPdfs->bindValue(':offset', $offset_pdfs, PDO::PARAM_INT);
$stmtPdfs->execute();
$pdfs = $stmtPdfs->fetchAll(PDO::FETCH_ASSOC);

$totalTranslations = $pdo->query("SELECT COUNT(*) FROM translation_logs")->fetchColumn();
$totalTranslationPages = ceil($totalTranslations / $limit);
$stmtTranslations = $pdo->prepare("SELECT tl.*, u.username FROM translation_logs tl LEFT JOIN users u ON tl.user_id = u.id ORDER BY tl.timestamp DESC LIMIT :limit OFFSET :offset");
$stmtTranslations->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtTranslations->bindValue(':offset', $offset_translations, PDO::PARAM_INT);
$stmtTranslations->execute();
$translations = $stmtTranslations->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>گزارش‌ها</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <script src="/assets/js/lucide.js"></script>
    <script>
        lucide.createIcons(); // در صورت نیاز به اجرای خودکار آیکون‌ها
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
<div class="container mx-auto p-4">
    <h1 class="text-2xl font-bold text-center mb-6">📊 گزارش‌ها</h1>

    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
        <h3 class="text-xl font-semibold mb-4">ناوبری</h3>
        <div class="flex flex-wrap gap-4">
            <a href="home.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-6 rounded-xl shadow">🏠 صفحه اصلی</a>
            <a href="dashboard.php" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-6 rounded-xl shadow">📊 داشبورد مدیریت</a>
            <a href="index1.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-medium py-2 px-6 rounded-xl shadow">📄 ترجمه فایل PDF</a>
            <a href="index2.php" class="bg-sky-100 hover:bg-sky-200 text-sky-700 font-medium py-2 px-6 rounded-xl shadow">📝 ترجمه متن</a>
            <a href="pdf_extract.php" class="bg-green-100 hover:bg-green-200 text-green-700 font-medium py-2 px-6 rounded-xl shadow">🗃 استخراج PDF</a>
            <a href="logout.php" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-6 rounded-xl shadow">🚪 خروج</a>
        </div>
    </div>

    <div class="mb-6">
        <div class="flex border-b mb-2">
            <button onclick="showTab('logsTab')" id="logsTabBtn" class="px-4 py-2 font-medium border-b-2 border-blue-500 text-blue-600">ورود و خروج</button>
            <button onclick="showTab('pdfTab')" id="pdfTabBtn" class="px-4 py-2 font-medium border-b-2 border-transparent hover:text-blue-600">استخراج PDF</button>
            <button onclick="showTab('translateTab')" id="translateTabBtn" class="px-4 py-2 font-medium border-b-2 border-transparent hover:text-blue-600">ترجمه PDF</button>
        </div>

        <!-- تب‌ها -->
        <div id="logsTab" class="tab-content block bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-3">گزارش ورود و خروج</h3>
            <table class="w-full text-sm border">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="p-2 border">نام کاربری</th>
                        <th class="p-2 border">عملیات</th>
                        <th class="p-2 border">آی‌پی</th>
                        <th class="p-2 border">زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="p-2 border"><?php echo htmlspecialchars($log['username'] ?? '---'); ?></td>
                        <td class="p-2 border"><?php echo $log['action'] === 'login' ? 'ورود' : ($log['action'] === 'logout' ? 'خروج' : '---'); ?></td>
                        <td class="p-2 border"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td class="p-2 border"><?php echo $log['timestamp']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 flex justify-center gap-1">
                <?php for ($i = 1; $i <= $totalLogPages; $i++): ?>
                    <a href="?log_page=<?php echo $i; ?>&pdf_page=<?php echo $page_pdfs; ?>&trans_page=<?php echo $page_translations; ?>#logsTab" class="px-3 py-1 rounded <?php echo $i == $page_logs ? 'bg-blue-600 text-white' : 'bg-gray-200'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <div id="pdfTab" class="tab-content hidden bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-3">گزارش استخراج PDF</h3>
            <table class="w-full text-sm border">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="p-2 border">نام کاربری</th>
                        <th class="p-2 border">نام فایل</th>
                        <th class="p-2 border">توکن ورودی</th>
                        <th class="p-2 border">توکن خروجی</th>
                        <th class="p-2 border">زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pdfs as $pdf): ?>
                    <tr>
                        <td class="p-2 border"><?php echo htmlspecialchars($pdf['username'] ?? '---'); ?></td>
                        <td class="p-2 border"><?php echo htmlspecialchars($pdf['file_name']); ?></td>
                        <td class="p-2 border"><?php echo $pdf['input_tokens']; ?></td>
                        <td class="p-2 border"><?php echo $pdf['output_tokens']; ?></td>
                        <td class="p-2 border"><?php echo $pdf['timestamp']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 flex justify-center gap-1">
                <?php for ($i = 1; $i <= $totalPdfPages; $i++): ?>
                    <a href="?log_page=<?php echo $page_logs; ?>&pdf_page=<?php echo $i; ?>&trans_page=<?php echo $page_translations; ?>#pdfTab" class="px-3 py-1 rounded <?php echo $i == $page_pdfs ? 'bg-green-600 text-white' : 'bg-gray-200'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>

        <div id="translateTab" class="tab-content hidden bg-white rounded shadow p-4">
            <h3 class="text-lg font-semibold mb-3">گزارش ترجمه PDF</h3>
            <table class="w-full text-sm border">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="p-2 border">نام کاربری</th>
                        <th class="p-2 border">نام فایل</th>
                        <th class="p-2 border">توکن ورودی</th>
                        <th class="p-2 border">توکن خروجی</th>
                        <th class="p-2 border">زمان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($translations as $row): ?>
                    <tr>
                        <td class="p-2 border"><?php echo htmlspecialchars($row['username'] ?? '---'); ?></td>
                        <td class="p-2 border"><?php echo htmlspecialchars($row['filename']); ?></td>
                        <td class="p-2 border"><?php echo $row['input_tokens']; ?></td>
                        <td class="p-2 border"><?php echo $row['output_tokens']; ?></td>
                        <td class="p-2 border"><?php echo $row['timestamp']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="mt-4 flex justify-center gap-1">
                <?php for ($i = 1; $i <= $totalTranslationPages; $i++): ?>
                    <a href="?log_page=<?php echo $page_logs; ?>&pdf_page=<?php echo $page_pdfs; ?>&trans_page=<?php echo $i; ?>#translateTab" class="px-3 py-1 rounded <?php echo $i == $page_translations ? 'bg-indigo-600 text-white' : 'bg-gray-200'; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.getElementById(tabId).classList.remove('hidden');

    document.getElementById('logsTabBtn').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('pdfTabBtn').classList.remove('border-blue-500', 'text-blue-600');
    document.getElementById('translateTabBtn').classList.remove('border-blue-500', 'text-blue-600');

    if (tabId === 'logsTab') {
        document.getElementById('logsTabBtn').classList.add('border-blue-500', 'text-blue-600');
    } else if (tabId === 'pdfTab') {
        document.getElementById('pdfTabBtn').classList.add('border-blue-500', 'text-blue-600');
    } else if (tabId === 'translateTab') {
        document.getElementById('translateTabBtn').classList.add('border-blue-500', 'text-blue-600');
    }

    history.replaceState(null, '', '#' + tabId);
}

window.addEventListener('DOMContentLoaded', () => {
    const hash = location.hash || '#logsTab';
    showTab(hash.replace('#', ''));
    lucide.createIcons();
});
</script>
</body>
</html>
