<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT can_translate_text FROM permissions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$permission = $stmt->fetch();

if (!$permission || !$permission['can_translate_text']) {
    header("Location: home.php?error=access_denied");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ترجمه متن</title>
  <link href="/assets/css/tailwind.min.css" rel="stylesheet">
  <script src="/assets/js/lucide.js"></script>
  <script>
    lucide.createIcons(); // در صورت نیاز به اجرای خودکار آیکون‌ها
 </script>
  <style>
    body {
      background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center font-sans">
  <div class="bg-white shadow-2xl rounded-3xl p-10 max-w-5xl w-full mx-4 border border-blue-100">
    <h1 class="text-4xl font-extrabold text-center text-indigo-700 mb-6">📝 ترجمه متن هوشمند</h1>
    <p class="text-center text-gray-600 mb-10">هوش مصنوعی در خدمت ترجمه سریع و دقیق</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- ورودی -->
      <div class="bg-indigo-50 rounded-2xl p-5 shadow-inner">
        <label for="sourceLang" class="block mb-2 font-bold text-indigo-700">زبان مبدأ:</label>
        <select id="sourceLang" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 mb-4">
          <option value="en" selected>انگلیسی</option>
          <option value="fa">فارسی</option>
          <option value="fr">فرانسوی</option>
          <option value="ar">عربی</option>
          <option value="es">اسپانیایی</option>
        </select>
        <textarea id="inputText" maxlength="5000" placeholder="متن خود را اینجا وارد کنید..." class="w-full h-48 p-4 border border-indigo-200 rounded-lg shadow-sm focus:ring-indigo-500 resize-y"></textarea>
        <div class="flex justify-between items-center mt-2 text-sm text-gray-500">
          <span id="charCounter">0/5000</span>
          <button id="clearInputBtn" class="text-red-500 hover:text-red-700"><i data-lucide="trash-2"></i> پاک کردن</button>
        </div>
      </div>

      <!-- خروجی -->
      <div class="bg-sky-50 rounded-2xl p-5 shadow-inner">
        <label for="targetLang" class="block mb-2 font-bold text-sky-700">زبان مقصد:</label>
        <select id="targetLang" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500 mb-4">
          <option value="fa" selected>فارسی</option>
          <option value="en">انگلیسی</option>
          <option value="fr">فرانسوی</option>
          <option value="ar">عربی</option>
          <option value="es">اسپانیایی</option>
        </select>
        <textarea id="outputText" readonly placeholder="ترجمه در اینجا نمایش داده می‌شود" class="w-full h-48 p-4 bg-white border border-sky-200 rounded-lg shadow-sm"></textarea>
        <div class="flex justify-end mt-2 text-sm">
          <button id="clearOutputBtn" class="text-red-500 hover:text-red-700"><i data-lucide="trash-2"></i> پاک کردن</button>
        </div>
      </div>
    </div>

    <!-- دکمه‌ها -->
    <div class="flex flex-wrap justify-center gap-4 mt-10">
      <button id="translateBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-xl shadow"><i data-lucide="book"></i> ترجمه</button>
      <button id="downloadBtn" style="display: none;" class="bg-emerald-500 hover:bg-emerald-600 text-white py-2 px-6 rounded-xl shadow"><i data-lucide="download"></i> دانلود</button>
      <button id="copyBtn" style="display: none;" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-6 rounded-xl shadow"><i data-lucide="copy"></i> کپی</button>
      <a href="home.php" class="bg-orange-100 hover:bg-orange-200 text-orange-800 py-2 px-6 rounded-xl shadow"><i data-lucide="home"></i> بازگشت</a>
    </div>

    <!-- لودینگ -->
    <div class="flex justify-center mt-6" id="loadingSpinner" style="display: none;">
      <svg class="animate-spin h-8 w-8 text-indigo-600" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
      </svg>
    </div>

    <!-- پیام‌ها -->
    <div id="result" class="mt-6 text-center text-sm"></div>
  </div>

  <script>
    const inputText = document.getElementById('inputText');
    const outputText = document.getElementById('outputText');
    const charCounter = document.getElementById('charCounter');
    const translateBtn = document.getElementById('translateBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const copyBtn = document.getElementById('copyBtn');
    const clearInputBtn = document.getElementById('clearInputBtn');
    const clearOutputBtn = document.getElementById('clearOutputBtn');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const resultDiv = document.getElementById('result');
    const sourceLang = document.getElementById('sourceLang');
    const targetLang = document.getElementById('targetLang');

    // مقداردهی آیکون‌های Lucide
    lucide.createIcons();

    inputText.addEventListener('input', () => {
      const length = inputText.value.length;
      charCounter.textContent = `${length}/5000`;
    });

    translateBtn.addEventListener('click', () => {
      const text = inputText.value.trim();
      const source = sourceLang.value;
      const target = targetLang.value;

      if (!text) return showError('متنی برای ترجمه وارد نشده است.');
      if (source === target) return showError('زبان مبدأ و مقصد نمی‌توانند یکسان باشند.');

      loadingSpinner.style.display = 'flex';
      resultDiv.innerHTML = '';
      outputText.value = '';
      downloadBtn.style.display = 'none';
      copyBtn.style.display = 'none';

      fetch('translate_text.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `text=${encodeURIComponent(text)}&sourceLang=${source}&targetLang=${target}`
      })
        .then(response => response.text())
        .then(data => {
          loadingSpinner.style.display = 'none';
          try {
            const jsonData = JSON.parse(data);
            if (jsonData.error) return showError(jsonData.error);
            outputText.value = jsonData.translated_text || '';
            if (jsonData.translated_text) {
              downloadBtn.style.display = 'inline-block';
              copyBtn.style.display = 'inline-block';
            }
          } catch (e) {
            showError('خطا در پردازش پاسخ سرور.');
          }
        })
        .catch(err => {
          loadingSpinner.style.display = 'none';
          showError('ارتباط با سرور برقرار نشد.');
        });
    });

    downloadBtn.addEventListener('click', () => {
      const text = outputText.value;
      if (!text) return;
      const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'translated_text.txt';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });

    copyBtn.addEventListener('click', () => {
      const text = outputText.value;
      if (!text) return;
      navigator.clipboard.writeText(text).then(() => showSuccess('کپی شد!'));
    });

    clearInputBtn.addEventListener('click', () => {
      inputText.value = '';
      charCounter.textContent = '0/5000';
    });

    clearOutputBtn.addEventListener('click', () => {
      outputText.value = '';
      downloadBtn.style.display = 'none';
      copyBtn.style.display = 'none';
    });

    function showError(message) {
      resultDiv.innerHTML = `<p class="text-red-600 font-medium">${message}</p>`;
    }

    function showSuccess(message) {
      resultDiv.innerHTML = `<p class="text-green-600 font-medium">${message}</p>`;
    }
  </script>
</body>
</html>