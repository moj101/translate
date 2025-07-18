<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

// ایجاد شی جدید
$phpWord = new PhpWord();

// اضافه کردن بخش
$section = $phpWord->addSection();

// اضافه کردن متن با فونت فارسی
$section->addText(
    'سلام دنیا! این یک مثال ساده از فایل Word ساخته شده با PHPWord است.',
    [
        'name' => 'Tahoma', // فونت مناسب برای فارسی
        'size' => 16
    ],
    [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END // راست‌چین
    ]
);

// ذخیره به عنوان فایل Word
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('example.docx');

echo "✅ فایل example.docx با موفقیت ایجاد شد.";