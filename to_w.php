<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// تنظیمات API
$apiKey = "aa-RdVuGuZPZp0I9JFwTZwp03wJyajFcSRsp3m5fmgUaQDikgpm"; // کلید خود را جایگزین کنید

// ========== تابع ترجمه از AvalAI ==========
function translateWithAvalAi($text, $apiKey) {
    $url = "https://api.avalapis.ir/v1/chat/completions ";

    $postData = [
        "model" => "o4-mini",
        "messages" => [
            ["role" => "user", "content" => "متن زیر را به فارسی روان ترجمه کن:\n\n$text"]
        ],
        "temperature" => 0.3,
        "max_tokens" => 512
    ];

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    return $data['choices'][0]['message']['content'] ?? null;
}

// ========== ۱. متن تستی برای ترجمه ==========
$englishText = "Artificial Intelligence is a branch of computer science that aims to create software or machines that exhibit human-like intelligence.";

// ========== ۲. ترجمه با AvalAI ==========
$translatedText = translateWithAvalAi($englishText, $apiKey);

if (!$translatedText) {
    die("❌ خطا: ترجمه انجام نشد.");
}

// ========== ۳. ایجاد فایل Word ==========
$phpWord = new PhpWord();
$section = $phpWord->addSection();

// تنظیمات فونت فارسی
$fontStyle = ['name' => 'Tahoma', 'size' => 14];
$paragraphStyle = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END]; // راست‌چین

$section->addText($translatedText, $fontStyle, $paragraphStyle);

// ========== ۴. ذخیره فایل Word ==========
$outputFile = "translated_output.docx";
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save($outputFile);

echo "✅ ترجمه با موفقیت انجام شد و در فایل زیر ذخیره شد:\n";
echo "<br><a href='$outputFile' download>📥 دانلود فایل Word</a>";