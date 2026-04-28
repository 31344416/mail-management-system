<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontend/views/login.php');
    exit;
}

$file = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';
if (empty($file)) {
    die('No file specified.');
}

$uploadsDir = realpath(__DIR__ . '/../uploads');
if ($uploadsDir === false) {
    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
    $uploadsDir = realpath($uploadsDir);
}

$filePath = $uploadsDir . DIRECTORY_SEPARATOR . $file;
$realPath = realpath($filePath);
if ($realPath === false || strpos($realPath, $uploadsDir) !== 0) {
    die('File not found or access denied.');
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($realPath) . '"');
header('Content-Length: ' . filesize($realPath));
readfile($realPath);
exit;
