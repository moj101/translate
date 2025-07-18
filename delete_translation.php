<?php
session_start();
require 'db_connect.php';

if (!isset($_POST['id']) || !isset($_SESSION['user_id'])) exit;

$id = intval($_POST['id']);
$stmt = $pdo->prepare("SELECT * FROM translation_logs WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    if (!empty($row['final_txt_path']) && file_exists($row['final_txt_path'])) unlink($row['final_txt_path']);
    if (!empty($row['final_docx_path']) && file_exists($row['final_docx_path'])) unlink($row['final_docx_path']);

    $pdo->prepare("DELETE FROM translation_logs WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
}

header("Location: dashboard.php");
exit;
?>
