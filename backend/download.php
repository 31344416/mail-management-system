<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontend/views/login.php');
    exit;
}

$file = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($file)) {
    die('No file specified.');
}

$uploadsDir = __DIR__ . '/../uploads/';
$filePath = $uploadsDir . $file;
$filePath = str_replace('\\', '/', realpath($filePath));

if ($filePath === false || !file_exists($filePath)) {
    die('File not found.');
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