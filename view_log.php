<?php
$logFile = 'debug.log';

if (!file_exists($logFile)) {
    die("⚠️ فایل لاگ وجود ندارد.");
}

$logContent = file_get_contents($logFile);
$logContent = nl2br(htmlspecialchars($logContent));

echo "<html lang='fa' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>مشاهده لاگ</title>
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background: #1e1e1e;
            color: #ffffff;
            padding: 20px;
        }
        pre {
            direction: ltr;
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #111;
            padding: 15px;
            border-radius: 10px;
            font-size: 14px;
        }
        h2 {
            color: #00ffff;
        }
        a {
            color: #00ccff;
            text-decoration: none;
        }
    </style>
</head>
<body>
<h2>📁 لاگ اجرای برنامه</h2>
<pre>$logContent</pre>
<p><a href='index.html'>🔙 بازگشت</a></p>
</body>
</html>";