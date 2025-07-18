<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استخراج متن و جداول از PDF</title>
    <link href="/assets/css/tailwind.min.css" rel="stylesheet">
    <script src="/assets/js/lucide.min.js"></script>
    <script>
      lucide.createIcons(); // فعال‌سازی آیکون‌ها
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php
    session_start();
    require_once 'db_connect.php';
    require_once 'vendor/autoload.php'; // برای PhpWord

    use PhpOffice\PhpWord\PhpWord;
    use PhpOffice\PhpWord\IOFactory;
    use PhpOffice\PhpWord\Style\Font;

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    // بررسی مجوز استخراج PDF
    $stmt = $pdo->prepare("SELECT can_extract_pdf FROM permissions WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$permission['can_extract_pdf']) {
        $errorMessage = '<p class="text-red-500 text-center">شما مجوز دسترسی به استخراج PDF را ندارید.</p>';
    } else {
        $errorMessage = '';
        $successMessage = '';
        $tokensDisplay = '';
        $downloadButtons = '';
        $textContentDisplay = '';
        $tablesDisplay = '';

        if (isset($_POST['submit']) && isset($_FILES['pdf_file'])) {
            $apiKey = "aa-RdVuGuZPZp0I9JFwTZwp03wJyajFcSRsp3m5fmgUaQDikgpm";

            // تابع برای تخمین تعداد توکن‌ها
            function estimateTokens($text, $fileSize = 0) {
                $textTokens = ceil(mb_strlen($text, 'UTF-8') / 4);
                $fileTokens = ceil($fileSize / 1000 * 250);
                return $textTokens + $fileTokens;
            }

            // تابع برای ارسال درخواست به API
            function askAvalAI($base64, $prompt, $apiKey) {
                $payload = [
                    "model" => "gemini-2.0-flash-lite",
                    "messages" => [
                        [
                            "role" => "user",
                            "content" => [
                                ["type" => "text", "text" => $prompt],
                                ["type" => "file", "file" => [
                                    "file_data" => "data:application/pdf;base64," . $base64
                                ]]
                            ]
                        ]
                    ]
                ];

                $ch = curl_init("https://api.avalai.ir/v1/chat/completions");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "Authorization: Bearer $apiKey"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
                $response = curl_exec($ch);
                curl_close($ch);

                $resultData = json_decode($response, true);
                return [
                    'content' => $resultData['choices'][0]['message']['content'] ?? '',
                    'tokens' => [
                        'input' => $resultData['usage']['prompt_tokens'] ?? 0,
                        'output' => $resultData['usage']['completion_tokens'] ?? 0
                    ]
                ];
            }

            // تابع برای ایجاد فایل Word با متن ساده
            function createWordDocument($textContent, $tables, $fileName) {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection(['marginTop' => 600, 'marginBottom' => 600]);

                // تنظیم فونت برای پشتیبانی از فارسی
                $fontStyle = ['name' => 'Vazir', 'size' => 12, 'rtl' => true];
                $paragraphStyle = ['spaceBefore' => 240, 'spaceAfter' => 240, 'lineHeight' => 1.15];

                // افزودن عنوان
                $section->addText('محتوای استخراج‌شده از PDF', ['name' => 'Vazir', 'size' => 14, 'bold' => true], ['alignment' => 'center']);
                $section->addTextBreak(2);

                // افزودن متن به‌صورت پاراگراف
                if (!empty($textContent)) {
                    $section->addText('متن استخراج‌شده:', ['name' => 'Vazir', 'size' => 12, 'bold' => true]);
                    $section->addTextBreak();
                    // تقسیم متن به بخش‌های کوچک‌تر برای جلوگیری از مشکلات حافظه
                    $chunkSize = 10000; // تعداد کاراکتر در هر بخش
                    $chunks = str_split($textContent, $chunkSize);
                    foreach ($chunks as $chunk) {
                        $paragraphs = preg_split('/\n+/', trim($chunk), -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($paragraphs as $paragraph) {
                            if (trim($paragraph) !== '') {
                                $section->addText($paragraph, $fontStyle, $paragraphStyle);
                            }
                        }
                    }
                    $section->addTextBreak(2);
                }

                // افزودن جداول به‌صورت متن ساده
                if (!empty($tables)) {
                    $section->addText('جداول استخراج‌شده (به‌صورت متنی):', ['name' => 'Vazir', 'size' => 12, 'bold' => true]);
                    $section->addTextBreak();
                    foreach ($tables as $tableData) {
                        $tableText = implode(' | ', $tableData['headers']) . "\n" . str_repeat('-', 10) . "\n";
                        foreach ($tableData['rows'] as $row) {
                            $tableText .= implode(' | ', $row) . "\n";
                        }
                        $section->addText($tableText, $fontStyle, $paragraphStyle);
                        $section->addTextBreak(2);
                    }
                }

                // ذخیره فایل
                $tempFile = tempnam(sys_get_temp_dir(), 'word_') . '.docx';
                $writer = IOFactory::createWriter($phpWord, 'Word2007');
                $writer->save($tempFile);
                return $tempFile;
            }

            // پرامپت برای استخراج متن و جداول
            $prompt = <<<PROMPT
لطفاً تمام محتوای فایل PDF را استخراج کنید، شامل متن خام و جداول. 

- برای متن غیرجدولی، تمام متن را به‌صورت کامل و خوانا ارائه دهید. هر پاراگراف را با دو خط جدید (\n\n) جدا کنید و هیچ بخشی از متن را حذف نکنید، حتی اگر شامل نشانگرهای OCR (مانند ==End of OCR for page X==) باشد.
- برای جداول، ساختار جدول را به‌صورت Markdown (با فرمت | ستون1 | ستون2 | ...) استخراج کنید. اطمینان حاصل کنید که تمام ستون‌ها در هر ردیف تعداد یکسانی داشته باشند.
- متن و جداول را با خطوط جداکننده واضح (متن: و جداول:) از هم تفکیک کنید.
- هیچ توضیح اضافی یا قالب‌بندی دیگری اضافه نکنید.
مثال خروجی:
متن:
[متن پاراگراف اول]\n\n[متن پاراگراف دوم]\n\n==End of OCR for page X==\n\n[متن پاراگراف بعدی]

جداول:
| ستون1 | ستون2 |
|-------|-------|
| داده1 | داده2 |
PROMPT;

            // پردازش فایل آپلود شده
            $file = $_FILES['pdf_file'];
            if ($file['error'] === UPLOAD_ERR_OK && $file['type'] === 'application/pdf') {
                $fileContent = file_get_contents($file['tmp_name']);
                $base64 = base64_encode($fileContent);
                $fileSize = $file['size'];
                $fileName = $file['name'];

                // تخمین توکن‌های ورودی
                $inputTokensEstimate = estimateTokens($prompt, $fileSize);

                // ارسال درخواست به API
                $response = askAvalAI($base64, $prompt, $apiKey);
                $responseText = trim($response['content']);
                $tokens = $response['tokens'];

                if ($tokens['input'] == 0) {
                    $tokens['input'] = $inputTokensEstimate;
                }
                if ($tokens['output'] == 0) {
                    $tokens['output'] = estimateTokens($responseText);
                }

                // ثبت لاگ در user_logs
                $user_id = $_SESSION['user_id'];
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address, timestamp) VALUES (?, 'pdf_extract', ?, NOW())");
                $stmt->execute([$user_id, $ip_address]);

                // ثبت لاگ در pdf_extraction_logs
                $stmt = $pdo->prepare("INSERT INTO pdf_extraction_logs (user_id, file_name, input_tokens, output_tokens, timestamp) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user_id, $fileName, $tokens['input'], $tokens['output']]);

                if (mb_strlen($responseText) < 10) {
                    $errorMessage = '<p class="text-red-500 text-center">❌ خطا: محتوای استخراج‌شده نامعتبر یا خیلی کوتاه است.</p>';
                } else {
                    $successMessage = '<p class="text-green-500 text-center">✅ متن و جداول با موفقیت استخراج شدند.</p>';

                    // نمایش توکن‌ها
                    $tokensDisplay = '<div class="bg-gray-100 p-4 rounded-lg mt-4">';
                    $tokensDisplay .= '<p>توکن‌های ورودی: ' . $tokens['input'] . '</p>';
                    $tokensDisplay .= '<p>توکن‌های خروجی: ' . $tokens['output'] . '</p>';
                    $tokensDisplay .= '<p>مجموع توکن‌ها: ' . ($tokens['input'] + $tokens['output']) . '</p>';
                    $tokensDisplay .= '</div>';

                    // آماده‌سازی محتوا برای دانلود فایل متنی
                    $downloadContent = $responseText;
                    $base64Content = base64_encode($downloadContent);
                    $downloadLinkText = 'data:text/plain;charset=utf-8;base64,' . $base64Content;

                    // آماده‌سازی داده برای فایل Word و نمایش در مرورگر
                    // استفاده از responseText برای textContent
                    $textContent = $responseText;
                    // حذف برچسب‌های "متن:" و "جداول:"
                    $textContent = preg_replace('/(متن:|جداول:)/u', '', $textContent);
                    // حذف خطوط خالی اضافی
                    $textContent = preg_replace('/\n{3,}/u', "\n\n", trim($textContent));

                    // پردازش جداول برای جعبه متن
                    $tables = [];
                    $sections = preg_split('/(جداول:)/u', $responseText, -1, PREG_SPLIT_DELIM_CAPTURE);
                    $currentSection = '';
                    foreach ($sections as $section) {
                        $section = trim($section);
                        if ($section === 'جداول:') {
                            $currentSection = 'tables';
                            continue;
                        }
                        if ($currentSection === 'tables' && !empty($section)) {
                            $lines = explode("\n", $section);
                            $tableStarted = false;
                            $headers = [];
                            $rows = [];
                            $maxColumns = 0;
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) continue;
                                if (preg_match('/^\|(.+)\|$/u', $line, $matches)) {
                                    $cells = array_map('trim', explode('|', trim($matches[1], ' |')));
                                    // حذف خطوط جداکننده Markdown
                                    if (!preg_match('/^-+(:?\s*-+)*$/u', $line)) {
                                        if (!$tableStarted) {
                                            $headers = $cells;
                                            $maxColumns = count($headers);
                                            $tableStarted = true;
                                        } else {
                                            // اطمینان از یکسان بودن تعداد ستون‌ها
                                            $cells = array_pad($cells, $maxColumns, '');
                                            $rows[] = $cells;
                                        }
                                    }
                                }
                            }
                            if (!empty($headers)) {
                                $tables[] = ['headers' => $headers, 'rows' => $rows];
                            }
                        }
                    }

                    // ایجاد فایل Word
                    $wordFile = createWordDocument($textContent, $tables, $fileName);
                    $wordFileName = 'extracted_content_' . time() . '.docx';
                    $wordBase64 = base64_encode(file_get_contents($wordFile));
                    $downloadLinkWord = 'data:application/vnd.openxmlformats-officedocument.wordprocessingml.document;base64,' . $wordBase64;

                    // دکمه‌های دانلود
                    $downloadButtons = '<div class="flex space-x-4 mt-4">';
                    $downloadButtons .= '<a href="' . $downloadLinkText . '" download="extracted_content.txt" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">دانلود فایل متنی</a>';
                    $downloadButtons .= '<a href="' . $downloadLinkWord . '" download="' . $wordFileName . '" class="inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">دانلود فایل Word</a>';
                    $downloadButtons .= '</div>';

                    // نمایش متن استخراج‌شده
                    if (!empty($textContent)) {
                        $textContentDisplay = '<div class="bg-white p-6 rounded-lg shadow-md mt-6">';
                        $textContentDisplay .= '<h3 class="text-lg font-semibold mb-4">متن استخراج‌شده</h3>';
                        $textContentDisplay .= '<div class="text-gray-700 leading-relaxed">' . nl2br(htmlspecialchars($textContent, ENT_QUOTES, 'UTF-8')) . '</div>';
                        $textContentDisplay .= '</div>';
                    }

                    // نمایش جداول
                    if (!empty($tables)) {
                        $tablesDisplay = '<div class="bg-white p-6 rounded-lg shadow-md mt-6">';
                        $tablesDisplay .= '<h3 class="text-lg font-semibold mb-4">جداول استخراج‌شده</h3>';
                        foreach ($tables as $tableData) {
                            $tablesDisplay .= '<table class="w-full border-collapse mt-4">';
                            $tablesDisplay .= '<thead><tr class="bg-gray-200">';
                            foreach ($tableData['headers'] as $header) {
                                $tablesDisplay .= '<th class="p-3 border text-right">' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
                            }
                            $tablesDisplay .= '</tr></thead><tbody>';
                            foreach ($tableData['rows'] as $row) {
                                $tablesDisplay .= '<tr>';
                                foreach ($row as $cell) {
                                    $tablesDisplay .= '<td class="p-3 border text-right">' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
                                }
                                $tablesDisplay .= '</tr>';
                            }
                            $tablesDisplay .= '</tbody></table>';
                        }
                        $tablesDisplay .= '</div>';
                    }

                    // پاکسازی فایل موقت
                    unlink($wordFile);
                }
            } else {
                $errorMessage = '<p class="text-red-500 text-center">❌ خطا: لطفاً یک فایل PDF معتبر آپلود کنید.</p>';
            }
        }
    }
    ?>
    <div class="container mx-auto p-4">
        <!-- منوی ناوبری -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h3 class="text-xl font-semibold mb-4">ناوبری</h3>
            <div class="flex flex-wrap gap-4">
                <a href="home.php" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-6 rounded-xl shadow">🏠 صفحه اصلی</a>
                <?php if ($_SESSION['username'] === 'admin'): ?>
                    <a href="dashboard.php" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-6 rounded-xl shadow">📊 داشبورد مدیریت</a>
                    <a href="reports.php" class="bg-purple-100 hover:bg-purple-200 text-purple-700 font-medium py-2 px-6 rounded-xl shadow">📊 گزارش‌ها</a>
                <?php endif; ?>
                <a href="index1.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 font-medium py-2 px-6 rounded-xl shadow">📄 ترجمه فایل PDF</a>
                <a href="index2.php" class="bg-sky-100 hover:bg-sky-200 text-sky-700 font-medium py-2 px-6 rounded-xl shadow">📝 ترجمه متن</a>
                <a href="logout.php" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-6 rounded-xl shadow">خروج</a>
            </div>
        </div>

        <!-- عنوان صفحه -->
        <h2 class="text-2xl font-bold mb-4 text-center">استخراج متن و جداول از PDF</h2>

        <!-- نمایش پیام‌ها و خروجی‌ها -->
        <?php
        echo $errorMessage;
        echo $successMessage;
        echo $tokensDisplay;
        echo $downloadButtons;
        echo $textContentDisplay;
        echo $tablesDisplay;
        ?>

        <!-- فرم آپلود PDF -->
        <div class="bg-white p-6 rounded-lg shadow-lg mt-6">
            <h3 class="text-xl font-semibold mb-4">آپلود فایل PDF</h3>
            <form action="pdf_extract.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label for="pdf_file" class="block text-sm font-medium text-gray-700">فایل PDF را انتخاب کنید:</label>
                    <input type="file" id="pdf_file" name="pdf_file" accept=".pdf" required class="mt-1 p-2 w-full border rounded-md">
                </div>
                <button type="submit" name="submit" class="w-full bg-blue-600 text-white p-2 rounded-md hover:bg-blue-700">استخراج متن و جداول</button>
            </form>
        </div>
    </div>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>