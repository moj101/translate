<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');
set_time_limit(300);
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 300);
ini_set('default_socket_timeout', 300);
ob_start();

require 'vendor/autoload.php';
require_once 'db_connect.php'; // اتصال به دیتابیس
require_once 'config.php'; // فایل تنظیمات

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

// بررسی ورود کاربر
if (!isset($_SESSION['user_id'])) {
    showError("لطفاً ابتدا وارد سیستم شوید.", "کاربر وارد نشده است.");
}
$user_id = $_SESSION['user_id'];

// بررسی دسترسی ترجمه PDF
$stmt = $pdo->prepare("SELECT can_translate_pdf FROM permissions WHERE user_id = ?");
$stmt->execute([$user_id]);
$permission = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$permission || !$permission['can_translate_pdf']) {
    showError("شما دسترسی لازم برای ترجمه PDF را ندارید.", "عدم دسترسی به ترجمه PDF برای کاربر $user_id");
}

// تنظیمات
$uploadDir = __DIR__ . '/uploads/';
$docDir = __DIR__ . '/docs/';
$txtDir = __DIR__ . '/txt/';
$apiKey = $apiConfig['apiKey'];
$model = $apiConfig['model'];
$batchSize = 3;
$apiDelay = 1000000; // 1 ثانیه تأخیر بین درخواست‌ها
$maxTextLength = 1500;
$maxFileSize = 10 * 1024 * 1024; // 10MB
$maxRedirects = 100; // حداکثر تعداد ریدایرکت‌ها برای جلوگیری از حلقه بی‌پایان

file_put_contents(__DIR__ . '/debug.log', "🚀 شروع اجرای اسکریپت - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// ایجاد دایرکتوری‌ها
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($docDir)) mkdir($docDir, 0777, true);
if (!is_dir($txtDir)) mkdir($txtDir, 0777, true);

// تابع نمایش خطا
function showError($message, $logMessage = null) {
    if ($logMessage) {
        file_put_contents(__DIR__ . '/debug.log', "❌ $logMessage\n", FILE_APPEND);
    }
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>خطا</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css" rel="stylesheet">
        <style>
            body { font-family: 'Vazir', sans-serif; }
            .container { max-width: 700px; margin: 50px auto; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>خطا:</strong> <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <p class="text-center"><a href="index1.php" class="btn btn-outline-secondary">🔙 بازگشت</a></p>
        </div>
    </body>
    </html>
    <?php
    ob_flush();
    flush();
    unset($_SESSION['pdf_process']); // پاک‌سازی سشن در صورت خطا
    exit;
}

// تابع شمارش توکن‌ها
function countTokens($text) {
    if (empty($text)) return 0;
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return count($words); // تخمین تعداد توکن‌ها بر اساس کلمات
}

// تابع ذخیره لاگ توکن‌ها و مسیر فایل‌ها
function logTokens($pdo, $user_id, $filename, $input_tokens, $output_tokens, $model, $final_txt_path = null, $final_docx_path = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO translation_logs (user_id, input_tokens, output_tokens, filename, model, timestamp, final_txt_path, final_docx_path) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([$user_id, $input_tokens, $output_tokens, $filename, $model, $final_txt_path, $final_docx_path]);
        file_put_contents(__DIR__ . '/debug.log', "✅ لاگ ذخیره شد: user_id=$user_id, input_tokens=$input_tokens, output_tokens=$output_tokens, filename=$filename, model=$model, txt_path=$final_txt_path, docx_path=$final_docx_path\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ذخیره لاگ: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// تابع پاک‌سازی متن برای XML
function cleanTextForXML($text) {
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $text);
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return $text;
}

// تابع نرمال‌سازی متن
function normalizeParagraphs($text) {
    $text = preg_replace('/(\r?\n\s*){2,}/', "\n\n", $text);
    $text = preg_replace('/([^\.\?!:;])\n([^\n])/', '$1 $2', $text);
    $text = preg_replace('/\n{2,}/', "\n\n", $text);
    return trim($text);
}

// تابع تقسیم متن
function splitText($text, $maxLength) {
    $chunks = [];
    while (strlen($text) > $maxLength) {
        $splitPos = strrpos(substr($text, 0, $maxLength), ' ');
        if ($splitPos === false) $splitPos = $maxLength;
        $chunks[] = substr($text, 0, $splitPos);
        $text = substr($text, $splitPos);
    }
    $chunks[] = $text;
    return array_filter(array_map('trim', $chunks));
}

// تابع بررسی لیست
function isListParagraph($text) {
    $lines = preg_split('/\r?\n/', $text);
    $listCount = 0;
    foreach ($lines as $line) {
        if (preg_match('/^\s*[\-\•\*]|\d+\s*[\.]\s+/u', trim($line))) {
            $listCount++;
        }
    }
    return $listCount >= 2;
}

// تابع ذخیره متن
function saveToText($translatedText, $batch_number, $filename, $txtDir, &$translatedFiles) {
    if (empty($translatedText)) {
        file_put_contents(__DIR__ . '/debug.log', "❌ متن ترجمه‌شده خالی است برای بخش $batch_number\n", FILE_APPEND);
        return;
    }
    $baseFilename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($filename, PATHINFO_FILENAME));
    $txtFile = $txtDir . $baseFilename . "_part_$batch_number.txt";
    try {
        file_put_contents($txtFile, $translatedText);
        chmod($txtFile, 0644);
        if (file_exists($txtFile) && filesize($txtFile) > 0) {
            $translatedFiles[] = $txtFile;
            file_put_contents(__DIR__ . '/debug.log', "✅ بخش $batch_number با موفقیت ذخیره شد: $txtFile (اندازه: " . filesize($txtFile) . " بایت)\n", FILE_APPEND);
        } else {
            file_put_contents(__DIR__ . '/debug.log', "❌ فایل $txtFile ایجاد نشد یا خالی است\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ذخیره فایل متنی: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// تابع ترجمه با AvalAI
function translateWithAvalAi($text, $apiKey, $model, $pdo, $user_id, $filename) {
    $text = cleanTextForXML($text);
    if (empty($text)) {
        file_put_contents(__DIR__ . '/debug.log', "⚠️ متن ورودی خالی است\n", FILE_APPEND);
        return '';
    }

    $input_tokens = countTokens($text);
    $url = "https://api.avalapis.ir/v1/chat/completions";
    $postData = [
        "model" => $model,
        "messages" => [
            ["role" => "system", "content" => "You are a professional translator. Translate the given text to Persian (Farsi) accurately and naturally. Preserve the structure and do not add extra newlines unless they exist in the input."],
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
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode != 200) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ارتباط با AvalAI (HTTP Code: $httpCode, Error: $curlError, Request: " . json_encode($postData, JSON_UNESCAPED_UNICODE) . ")\n", FILE_APPEND);
        return '';
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || isset($data['error'])) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا از سمت AvalAI: " . ($data['error']['message'] ?? 'JSON decode error') . ", Response: $response\n", FILE_APPEND);
        return '';
    }

    $translatedText = $data['choices'][0]['message']['content'] ?? '';
    $output_tokens = countTokens($translatedText);

    if (empty($translatedText)) {
        file_put_contents(__DIR__ . '/debug.log', "❌ پاسخ API خالی است برای متن: " . substr($text, 0, 50) . "...\n", FILE_APPEND);
    } else {
        // ذخیره توکن‌ها در دیتابیس
        logTokens($pdo, $user_id, $filename, $input_tokens, $output_tokens, $model);
    }

    return $translatedText;
}

// پردازش بخش‌ها
if (isset($_SESSION['pdf_process'])) {
    $process = &$_SESSION['pdf_process'];
    $filename = $process['filename'];
    $targetPath = $process['filepath'];
    $totalPages = $process['total_pages'];
    $current_page = $process['current_page'];
    $batch_number = $process['batch_number'];
    $translatedFiles = $process['translated_files'] ?? [];
    $redirect_count = isset($process['redirect_count']) ? $process['redirect_count'] : 0;

    // بررسی حداکثر تعداد ریدایرکت‌ها
    if ($redirect_count >= $maxRedirects) {
        unset($_SESSION['pdf_process']);
        showError("خطا: تعداد ریدایرکت‌ها بیش از حد مجاز است. لطفاً دوباره تلاش کنید.", "حلقه بی‌پایان شناسایی شد: redirect_count=$redirect_count");
    }

    // افزایش تعداد ریدایرکت‌ها
    $process['redirect_count'] = $redirect_count + 1;

    file_put_contents(__DIR__ . '/debug.log', "📊 پردازش بخش: current_page=$current_page, total_pages=$totalPages, batch_number=$batch_number, redirect_count=$redirect_count\n", FILE_APPEND);

    // بررسی اتمام پردازش
    if ($current_page >= $totalPages) {
        $finalText = '';
        $mergeSuccess = true;
        $totalInputTokens = 0;
        $totalOutputTokens = 0;

        foreach ($translatedFiles as $file) {
            if (!file_exists($file) || filesize($file) == 0) {
                file_put_contents(__DIR__ . '/debug.log', "❌ فایل موقت $file یافت نشد یا خالی است\n", FILE_APPEND);
                $mergeSuccess = false;
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                file_put_contents(__DIR__ . '/debug.log', "❌ خطا در خواندن فایل $file\n", FILE_APPEND);
                $mergeSuccess = false;
                continue;
            }

            $finalText .= $content . "\n\n";
            $totalInputTokens += countTokens($content);
            file_put_contents(__DIR__ . '/debug.log', "✅ فایل $file با موفقیت ادغام شد\n", FILE_APPEND);
        }

        $timestamp = date('YmdHis');
        $baseFilename = "translated_$timestamp";
        $finalTxtPath = $txtDir . $baseFilename . ".txt";
        $finalDocxPath = $docDir . $baseFilename . ".docx";
        $relativeTxtPath = '/txt/' . $baseFilename . ".txt";
        $relativeDocxPath = '/docs/' . $baseFilename . ".docx";

        if ($mergeSuccess && !empty($translatedFiles)) {
            // ذخیره فایل متنی نهایی
            file_put_contents($finalTxtPath, $finalText);
            chmod($finalTxtPath, 0644);

            if (file_exists($finalTxtPath) && filesize($finalTxtPath) > 0) {
                file_put_contents(__DIR__ . '/debug.log', "✅ فایل متنی نهایی ذخیره شد: $finalTxtPath (اندازه: " . filesize($finalTxtPath) . " بایت)\n", FILE_APPEND);

                $phpWord = new PhpWord();
                $section = $phpWord->addSection(['orientation' => 'portrait']);
                $fontStyle = ['name' => 'B Nazanin', 'size' => 12, 'rtl' => true];
                $boldFontStyle = ['name' => 'B Nazanin', 'size' => 14, 'bold' => true, 'rtl' => true];
                $paragraphStyle = [
                    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END,
                    'spaceAfter' => \PhpOffice\PhpWord\Shared\Converter::pixelToTwip(10),
                    'rtl' => true
                ];

                $outputBlocks = preg_split("/\nPAGE_BREAK\n/", $finalText, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($outputBlocks as $block) {
                    $block = trim($block);
                    if (empty($block)) continue;

                    $paragraphs = preg_split("/\n\n/", $block, -1, PREG_SPLIT_NO_EMPTY);

                    foreach ($paragraphs as $para) {
                        $para = trim($para);
                        if (empty($para)) continue;

                        if (str_contains($para, "LIST_START") && str_contains($para, "LIST_END")) {
                            $listContent = str_replace(["LIST_START\n", "\nLIST_END"], "", $para);
                            $items = preg_split('/\n/', $listContent, -1, PREG_SPLIT_NO_EMPTY);

                            foreach ($items as $li) {
                                $li = cleanTextForXML(trim($li));
                                if (!empty($li)) {
                                    $section->addListItem($li, 0, $fontStyle, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED], $paragraphStyle);
                                }
                            }
                            $section->addTextBreak(1);
                        } elseif (str_starts_with($para, "**") && str_ends_with($para, "**")) {
                            $title = cleanTextForXML(trim(str_replace(['**', '*'], '', $para)));
                            $section->addText($title, $boldFontStyle, $paragraphStyle);
                            $section->addTextBreak(1);
                        } else {
                            $lines = preg_split('/\n/', $para, -1, PREG_SPLIT_NO_EMPTY);
                            foreach ($lines as $line) {
                                $line = cleanTextForXML(trim($line));
                                if (!empty($line)) {
                                    $section->addText($line, $fontStyle, $paragraphStyle);
                                    $section->addTextBreak(1);
                                }
                            }
                        }
                    }

                    $section->addPageBreak();
                }

                try {
                    $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                    $objWriter->save($finalDocxPath);
                    chmod($finalDocxPath, 0644);

                    if (file_exists($finalDocxPath) && filesize($finalDocxPath) > 0) {
                        // محاسبه توکن‌های خروجی
                        $totalOutputTokens = countTokens($finalText);

                        // ذخیره لاگ با مسیر فایل‌ها
                        logTokens($pdo, $user_id, $filename, $totalInputTokens, $totalOutputTokens, $model, $relativeTxtPath, $relativeDocxPath);

                        // پاک‌سازی فایل‌های موقت
                        foreach ($translatedFiles as $file) {
                            if (file_exists($file)) {
                                unlink($file);
                                file_put_contents(__DIR__ . '/debug.log', "🗑️ فایل موقت $file حذف شد\n", FILE_APPEND);
                            }
                        }

                        ob_clean();
                        ?>
                        <!DOCTYPE html>
                        <html lang="fa" dir="rtl">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>ترجمه PDF</title>
                            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                            <link href="https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css" rel="stylesheet">
                            <style>
                                body { font-family: 'Vazir', sans-serif; }
                                .container { max-width: 700px; margin: 50px auto; }
                                .download-btn { margin: 10px; padding: 10px 20px; font-size: 1.1rem; border-radius: 8px; }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="text-center">
                                    <h3 class="text-success">🎉 تمام <?php echo $totalPages; ?> صفحه با موفقیت ترجمه شد!</h3>
                                    <a href="<?php echo htmlspecialchars($relativeDocxPath); ?>" download class="btn btn-primary download-btn">📥 دانلود فایل Word</a>
                                    <a href="<?php echo htmlspecialchars($relativeTxtPath); ?>" download class="btn btn-secondary download-btn">📄 دانلود فایل متنی</a>
                                </div>
                                <p class="text-center"><a href="index1.php" class="btn btn-outline-secondary">🔙 آپلود فایل جدید</a></p>
                            </div>
                        </body>
                        </html>
                        <?php
                        file_put_contents(__DIR__ . '/debug.log', "✅ فایل نهایی Word ذخیره شد: $finalDocxPath (اندازه: " . filesize($finalDocxPath) . " بایت)\n", FILE_APPEND);
                        unset($_SESSION['pdf_process']); // پاک‌سازی سشن
                        ob_flush();
                        flush();
                        exit;
                    } else {
                        showError("فایل Word نهایی ایجاد نشد. لطفاً دوباره تلاش کنید.", "فایل Word نهایی در $finalDocxPath ایجاد نشد یا خالی است");
                    }
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ذخیره فایل Word نهایی: " . $e->getMessage() . "\n", FILE_APPEND);
                    showError("خطا در ایجاد فایل Word نهایی. لطفاً با پشتیبانی تماس بگیرید.", "خطا در ذخیره Word: " . $e->getMessage());
                }
            } else {
                showError("فایل متنی نهایی ایجاد نشد. لطفاً دوباره تلاش کنید.", "فایل متنی نهایی در $finalTxtPath ایجاد نشد یا خالی است");
            }
        } else {
            showError("ادغام فایل‌ها با شکست مواجه شد. لطفاً فایل را دوباره آپلود کنید.", "خطا در ادغام فایل‌های موقت");
        }
    }

    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($targetPath);
        $pages = $pdf->getPages();
        $totalPages = count($pages); // بازمحاسبه totalPages برای اطمینان
        $process['total_pages'] = $totalPages; // به‌روزرسانی در سشن
        file_put_contents(__DIR__ . '/debug.log', "✅ PDF بازخوانی شد: $totalPages صفحه\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در خواندن PDF: " . $e->getMessage() . "\n", FILE_APPEND);
        showError("خطا در پردازش فایل PDF. لطفاً یک فایل PDF معتبر آپلود کنید.", "خطا در خواندن PDF: " . $e->getMessage());
    }

    $startPage = $current_page;
    $endPage = min($startPage + $batchSize, $totalPages);
    $translatedText = '';

    for ($i = $startPage; $i < $endPage; $i++) {
        $message = "📄 در حال پردازش صفحه " . ($i + 1) . " از $totalPages...";
        file_put_contents(__DIR__ . '/debug.log', "$message\n", FILE_APPEND);

        $rawText = trim($pages[$i]->getText());
        if (empty($rawText)) {
            file_put_contents(__DIR__ . '/debug.log', "⚠️ صفحه " . ($i + 1) . " خالی است\n", FILE_APPEND);
            continue;
        }

        $rawText = normalizeParagraphs($rawText);
        file_put_contents(__DIR__ . '/debug.log', "📜 متن خام صفحه " . ($i + 1) . ": " . substr($rawText, 0, 200) . "...\n", FILE_APPEND);

        $paragraphs = preg_split("/\r?\n{2,}/", $rawText, -1, PREG_SPLIT_NO_EMPTY);
        $translatedParagraphs = [];

        foreach ($paragraphs as $para) {
            $trimmedPara = trim($para);
            if (empty($trimmedPara)) continue;

            if (isListParagraph($trimmedPara)) {
                $items = preg_split('/\r?\n/', $trimmedPara);
                $translatedItems = [];

                foreach ($items as $item) {
                    $cleanItem = preg_replace('/^[\s\d\.\)\-•\*]+/', '', trim($item));
                    if (empty($cleanItem)) continue;

                    if (preg_match('/^[\s\d\.\)\-•\*]+\s*.+/', $item)) {
                        $textChunks = splitText($cleanItem, $maxTextLength);
                        $translatedChunk = '';
                        foreach ($textChunks as $chunk) {
                            $translated = translateWithAvalAi($chunk, $apiKey, $model, $pdo, $user_id, $filename);
                            if ($translated) {
                                $translatedChunk .= $translated . ' ';
                                file_put_contents(__DIR__ . '/debug.log', "✅ آیتم لیست ترجمه شد: $translated\n", FILE_APPEND);
                            } else {
                                file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ترجمه آیتم: $chunk\n", FILE_APPEND);
                            }
                            usleep($apiDelay);
                        }
                        if (!empty($translatedChunk)) {
                            $translatedItems[] = trim($translatedChunk);
                        }
                    }
                }

                if (!empty($translatedItems)) {
                    $translatedParagraphs[] = "LIST_START\n" . implode("\n", $translatedItems) . "\nLIST_END";
                }
            } else {
                $textChunks = splitText($trimmedPara, $maxTextLength);
                $translatedChunk = '';
                foreach ($textChunks as $chunk) {
                    $translated = translateWithAvalAi($chunk, $apiKey, $model, $pdo, $user_id, $filename);
                    if ($translated) {
                        $translatedChunk .= $translated . ' ';
                        file_put_contents(__DIR__ . '/debug.log', "✅ پاراگراف ترجمه شد: $translated\n", FILE_APPEND);
                    } else {
                        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در ترجمه پاراگراف: $chunk\n", FILE_APPEND);
                    }
                    usleep($apiDelay);
                }
                if (!empty($translatedChunk)) {
                    $translatedParagraphs[] = trim($translatedChunk);
                }
            }
        }

        $translatedText .= "PAGE_BREAK\n" . implode("\n\n", $translatedParagraphs) . "\n\n";
    }

    if (!empty($translatedText)) {
        saveToText($translatedText, $batch_number, $filename, $txtDir, $translatedFiles);
        $message = "✅ بخش $batch_number با موفقیت ترجمه و ذخیره شد.";
    } else {
        $message = "⚠️ هیچ متنی برای بخش $batch_number ترجمه نشد.";
        file_put_contents(__DIR__ . '/debug.log', "❌ هیچ متنی برای بخش $batch_number ترجمه نشد\n", FILE_APPEND);
    }

    $process['current_page'] = $endPage;
    $process['batch_number'] = $batch_number + 1;
    $process['translated_files'] = $translatedFiles;

    $progress = ($totalPages > 0) ? round(($endPage / $totalPages) * 100) : 0;
    $redirectUrl = $_SERVER['PHP_SELF'] . '?process_batch';
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="3;url=<?php echo $redirectUrl; ?>">
        <title>در حال پردازش</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css" rel="stylesheet">
        <style>
            body { font-family: 'Vazir', sans-serif; }
            .container { max-width: 700px; margin: 50px auto; }
            .progress-container { background: #f8f9fa; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
            .log-container { max-height: 200px; overflow-y: auto; margin-top: 10px; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; background: #fff; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="progress-container">
                <p>پیشرفت: <span><?php echo $progress; ?>%</span></p>
                <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="log-container">
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    ob_flush();
    flush();
    exit;
}

// آپلود فایل اولیه
if (!isset($_SESSION['pdf_process'])) {
    if (!isset($_FILES['pdf_file']) || !is_uploaded_file($_FILES['pdf_file']['tmp_name'])) {
        showError("لطفاً یک فایل PDF انتخاب کنید.", "هیچ فایلی آپلود نشد.");
    }

    $fileError = $_FILES['pdf_file']['error'];
    $fileType = $_FILES['pdf_file']['type'];
    $fileSize = $_FILES['pdf_file']['size'];
    $fileName = $_FILES['pdf_file']['name'];

    switch ($fileError) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            showError("حجم فایل بیش از حد مجاز (10 مگابایت) است.", "خطای حجم فایل: $fileName, اندازه: $fileSize بایت");
        case UPLOAD_ERR_PARTIAL:
            showError("فایل به طور کامل آپلود نشد. لطفاً دوباره تلاش کنید.", "آپلود ناقص: $fileName");
        case UPLOAD_ERR_NO_FILE:
            showError("لطفاً یک فایل PDF انتخاب کنید.", "هیچ فایلی انتخاب نشده است.");
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            showError("خطای سرور در ذخیره فایل. لطفاً بعداً تلاش کنید.", "خطای سرور در ذخیره فایل: $fileName");
        case UPLOAD_ERR_OK:
            break;
        default:
            showError("خطای ناشناخته در آپلود فایل. لطفاً دوباره تلاش کنید.", "خطای ناشناخته: $fileError, فایل: $fileName");
    }

    if ($fileType !== 'application/pdf') {
        showError("لطفاً فقط فایل PDF آپلود کنید.", "نوع فایل نامعتبر: $fileType, فایل: $fileName");
    }

    if ($fileSize > $maxFileSize) {
        showError("حجم فایل بیش از حد مجاز (10 مگابایت) است.", "حجم فایل زیاد: $fileSize بایت, فایل: $fileName");
    }

    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', basename($fileName));
    $targetPath = $uploadDir . $filename;

    if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
        showError("خطا در ذخیره فایل. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.", "خطا در انتقال فایل: $fileName");
    }

    try {
        $parser = new Parser();
        $pdf = $parser->parseFile($targetPath);
        $pages = $pdf->getPages();
        $totalPages = count($pages);
        file_put_contents(__DIR__ . '/debug.log', "✅ PDF با موفقیت پردازش شد: $totalPages صفحه\n", FILE_APPEND);
    } catch (Exception $e) {
        file_put_contents(__DIR__ . '/debug.log', "❌ خطا در پردازش PDF: " . $e->getMessage() . "\n", FILE_APPEND);
        showError("خطا در پردازش فایل PDF. لطفاً یک فایل PDF معتبر آپلود کنید.", "خطا در پردازش PDF: " . $e->getMessage());
    }

    $_SESSION['pdf_process'] = [
        'filename' => $filename,
        'filepath' => $targetPath,
        'total_pages' => $totalPages,
        'current_page' => 0,
        'batch_number' => 1,
        'translated_files' => [],
        'redirect_count' => 0 // مقدار اولیه تعداد ریدایرکت‌ها
    ];

    file_put_contents(__DIR__ . '/debug.log', "✅ فایل PDF با موفقیت ذخیره شد: $targetPath\n", FILE_APPEND);
    $redirectUrl = $_SERVER['PHP_SELF'] . '?process_batch';
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="refresh" content="3;url=<?php echo $redirectUrl; ?>">
        <title>در حال پردازش</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.fontcdn.ir/Font/Persian/Vazir/Vazir.css" rel="stylesheet">
        <style>
            body { font-family: 'Vazir', sans-serif; }
            .container { max-width: 700px; margin: 50px auto; }
            .progress-container { background: #f8f9fa; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); }
            .log-container { max-height: 200px; overflow-y: auto; margin-top: 10px; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; background: #fff; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="progress-container">
                <p>پیشرفت: <span>0%</span></p>
                <div class="progress">
                    <div class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="log-container">
                    <p>✅ فایل با موفقیت آپلود شد.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    ob_flush();
    flush();
    exit;
}
?>