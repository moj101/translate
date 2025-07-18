<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug_text.log');
set_time_limit(60);
ini_set('memory_limit', '512M');
ini_set('default_socket_timeout', 60);
ob_start();
require_once 'config.php'; // فایل تنظیمات


$apiKey = $apiConfig['apiKey'];
$model = $apiConfig['model'];

// تابع نمایش خطا
function showError($message, $logMessage = null) {
    if ($logMessage) {
        file_put_contents(__DIR__ . '/debug_text.log', "❌ $logMessage\n", FILE_APPEND);
    }
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    ob_flush();
    flush();
    exit;
}

// تابع پاک‌سازی متن برای XML
function cleanTextForXML($text) {
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $text);
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return $text;
}

// تابع تبدیل کد زبان به نام کامل
function getLanguageName($langCode) {
    $languages = [
        'fa' => 'Persian (Farsi)',
        'en' => 'English',
        'fr' => 'French',
        'ar' => 'Arabic',
        'es' => 'Spanish'
    ];
    return $languages[$langCode] ?? 'Unknown';
}

// تابع تقسیم متن با حفظ ساختار
function splitTextWithStructure($text, $maxLength) {
    $lines = preg_split('/(\r?\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $chunks = [];
    $currentChunk = '';
    $currentLength = 0;

    foreach ($lines as $line) {
        $lineLength = mb_strlen($line, 'UTF-8');
        if ($currentLength + $lineLength <= $maxLength) {
            $currentChunk .= $line;
            $currentLength += $lineLength;
        } else {
            if (!empty($currentChunk)) {
                $chunks[] = $currentChunk;
            }
            $currentChunk = $line;
            $currentLength = $lineLength;
        }
    }
    if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
    }
    return $chunks;
}

// تابع ترجمه با AvalAI
function translateWithAvalAi($text, $sourceLang, $targetLang, $apiKey) {
    $text = cleanTextForXML($text);
    if (empty($text)) {
        file_put_contents(__DIR__ . '/debug_text.log', "⚠️ متن ورودی خالی است\n", FILE_APPEND);
        return '';
    }

    $sourceLangName = getLanguageName($sourceLang);
    $targetLangName = getLanguageName($targetLang);

    $url = "https://api.avalapis.ir/v1/chat/completions";
    $postData = [
        "model" => 'gpt-4.1-nano-2025-04-14',
        "messages" => [
            [
                "role" => "system",
                "content" => "You are a professional translator. Translate the given text from $sourceLangName to $targetLangName accurately and naturally. Strictly preserve the original structure, including line breaks, numbering, and formatting. Do not add or remove line breaks unless they exist in the input. Maintain any numbering or bullet points exactly as they appear."
            ],
            ["role" => "user", "content" => $text]
        ],
        "temperature" => 0.3,
        "max_tokens" => 4096
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode != 200) {
        file_put_contents(__DIR__ . '/debug_text.log', "❌ خطا در ارتباط با AvalAI (HTTP Code: $httpCode, Error: $curlError, Request: " . json_encode($postData, JSON_UNESCAPED_UNICODE) . ")\n", FILE_APPEND);
        return '';
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || isset($data['error'])) {
        file_put_contents(__DIR__ . '/debug_text.log', "❌ خطا از سمت AvalAI: " . ($data['error']['message'] ?? 'JSON decode error') . ", Response: $response\n", FILE_APPEND);
        return '';
    }

    $translatedText = $data['choices'][0]['message']['content'] ?? '';
    if (empty($translatedText)) {
        file_put_contents(__DIR__ . '/debug_text.log', "❌ پاسخ API خالی است برای متن: " . substr($text, 0, 50) . "...\n", FILE_APPEND);
    }

    return $translatedText;
}

// پردازش درخواست
$apiKey = "aa-RdVuGuZPZp0I9JFwTZwp03wJyajFcSRsp3m5fmgUaQDikgpm";
$maxTextLength = 5000;
$allowedLanguages = ['fa', 'en', 'fr', 'ar', 'es'];

file_put_contents(__DIR__ . '/debug_text.log', "🚀 شروع اجرای اسکریپت - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['text']) || !isset($_POST['sourceLang']) || !isset($_POST['targetLang'])) {
    showError("لطفاً متنی برای ترجمه و زبان‌های مبدأ و مقصد را مشخص کنید.", "درخواست نامعتبر یا بدون متن/زبان");
}

$inputText = trim($_POST['text']);
$sourceLang = $_POST['sourceLang'];
$targetLang = $_POST['targetLang'];

if (empty($inputText)) {
    showError("متن ورودی خالی است.", "متن ورودی خالی دریافت شد");
}

if (mb_strlen($inputText, 'UTF-8') > $maxTextLength) {
    showError("متن ورودی بیش از 5000 کاراکتر است.", "طول متن: " . mb_strlen($inputText, 'UTF-8'));
}

if (!in_array($sourceLang, $allowedLanguages) || !in_array($targetLang, $allowedLanguages)) {
    showError("زبان انتخاب‌شده نامعتبر است.", "زبان‌های انتخاب‌شده: مبدأ=$sourceLang, مقصد=$targetLang");
}

if ($sourceLang === $targetLang) {
    showError("زبان مبدأ و مقصد نمی‌توانند یکسان باشند.", "زبان‌های یکسان: $sourceLang");
}

file_put_contents(__DIR__ . '/debug_text.log', "📜 متن ورودی: " . substr($inputText, 0, 200) . "... (مبدأ: $sourceLang, مقصد: $targetLang)\n", FILE_APPEND);

// تقسیم متن برای حفظ ساختار
$chunks = splitTextWithStructure($inputText, $maxTextLength);
$translatedChunks = [];

foreach ($chunks as $chunk) {
    $translatedChunk = translateWithAvalAi($chunk, $sourceLang, $targetLang, $apiKey);
    if (empty($translatedChunk)) {
        file_put_contents(__DIR__ . '/debug_text.log', "❌ ترجمه ناموفق برای بخش: " . substr($chunk, 0, 50) . "...\n", FILE_APPEND);
        continue;
    }
    $translatedChunks[] = $translatedChunk;
}

$translatedText = implode('', $translatedChunks);
if (empty($translatedText)) {
    showError("خطا در ترجمه متن. لطفاً دوباره تلاش کنید.", "ترجمه ناموفق برای متن: " . substr($inputText, 0, 50) . "...");
}

file_put_contents(__DIR__ . '/debug_text.log', "✅ ترجمه موفق: " . substr($translatedText, 0, 200) . "...\n", FILE_APPEND);

ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['translated_text' => $translatedText], JSON_UNESCAPED_UNICODE);
ob_flush();
flush();
?>