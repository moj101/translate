<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT can_translate_pdf FROM permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$permission = $stmt->fetch();

if (!$permission || !$permission['can_translate_pdf']) {
    header("Location: home.php?error=access_denied");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ترجمه فایل PDF</title>
  <link href="/assets/css/tailwind.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
    }
   </style>
</head>

<body class="min-h-screen flex items-center justify-center font-sans">
  <div class="bg-white shadow-2xl rounded-3xl p-10 max-w-2xl w-full mx-4 border border-slate-200">
    <h1 class="text-3xl font-extrabold text-center text-indigo-700 mb-6">📄 ترجمه فایل PDF به فارسی</h1>
    <p class="text-center text-gray-600 mb-6">فایل خود را آپلود کرده و ترجمه‌ای هوشمند دریافت کنید</p>

    <form id="uploadForm" action="translate_pdf.php" method="post" enctype="multipart/form-data">
      <div id="uploadArea" class="cursor-pointer border-4 border-dashed border-indigo-300 bg-indigo-50 rounded-2xl p-10 text-center transition hover:shadow-lg hover:bg-indigo-100">
        <p class="text-lg text-indigo-700">برای انتخاب فایل کلیک کنید یا فایل PDF را اینجا بکشید و رها کنید</p>
        <input type="file" id="pdfFile" name="pdf_file" accept=".pdf" class="hidden">
      </div>
    </form>

    <div id="progressContainer" class="mt-8 hidden">
      <p class="mb-2 text-sm text-gray-700">پیشرفت: <span id="progressText">0%</span></p>
      <div class="w-full bg-gray-200 rounded-full h-4">
        <div id="progressBar" class="bg-green-500 h-4 rounded-full transition-all duration-300" style="width: 0%;"></div>
      </div>
      <div id="logContainer" class="mt-4 p-3 bg-white border border-gray-200 rounded-lg text-sm max-h-48 overflow-y-auto"></div>
    </div>

    <div id="result" class="mt-6 text-center text-sm"></div>

    <div class="text-center mt-6">
      <a href="home.php" class="bg-orange-100 hover:bg-orange-200 text-orange-700 font-medium py-2 px-6 rounded-xl shadow">🏠 بازگشت به صفحه اصلی</a>
         <a href="archive.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-6 rounded-xl shadow">📚 آرشیو ترجمه‌ها</a>
    </div>
  </div>

  <script>
    const uploadArea = document.getElementById('uploadArea');
    const pdfFileInput = document.getElementById('pdfFile');
    const uploadForm = document.getElementById('uploadForm');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const logContainer = document.getElementById('logContainer');
    const resultDiv = document.getElementById('result');

    uploadArea.addEventListener('click', () => pdfFileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      uploadArea.classList.add('bg-indigo-100');
    });

    uploadArea.addEventListener('dragleave', () => {
      uploadArea.classList.remove('bg-indigo-100');
    });

    uploadArea.addEventListener('drop', (e) => {
      e.preventDefault();
      uploadArea.classList.remove('bg-indigo-100');
      const file = e.dataTransfer.files[0];
      if (file && file.type === 'application/pdf') {
        if (file.size > 10 * 1024 * 1024) {
          showError('حجم فایل بیش از 10 مگابایت است.');
          return;
        }
        pdfFileInput.files = e.dataTransfer.files;
        uploadForm.submit();
      } else {
        showError('فقط فایل‌های PDF مجاز هستند.');
      }
    });

    pdfFileInput.addEventListener('change', () => {
      if (pdfFileInput.files.length > 0) {
        const file = pdfFileInput.files[0];
        if (file.type !== 'application/pdf') {
          showError('فرمت فایل نامعتبر است. فقط PDF پشتیبانی می‌شود.');
          return;
        }
        if (file.size > 10 * 1024 * 1024) {
          showError('فایل بیش از 10 مگابایت است.');
          return;
        }
        uploadForm.submit();
      }
    });

    function showError(message) {
      resultDiv.innerHTML = `<p class="text-red-600 font-medium">❌ ${message}</p>`;
    }
  </script>
</body>
</html>