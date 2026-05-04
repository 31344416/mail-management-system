<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontend/views/login.php');
    exit;
}

$file = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';
if (empty($file)) {
    die('No attachment specified.');
}

$basePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/mail_management/uploads/';
$filePath = $basePath . $file;

if (!file_exists($filePath)) {
    $fallback = __DIR__ . '/../uploads/' . $file;
    if (file_exists($fallback)) {
        $filePath = $fallback;
    } else {
        die('File not found. Tried: ' . $filePath);
    }
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>