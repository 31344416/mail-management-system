<?php
// backend/download.php
session_start();

// Security: Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../frontend/views/login.php');
    exit;
}

// Get and clean filename from URL
$file = isset($_GET['file']) ? basename(trim($_GET['file'])) : '';

if (empty($file)) {
    die('Error: No file specified.');
}

// Define uploads path using Document Root (most reliable method)
$documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$uploadsDir   = $documentRoot . '/mail_management/uploads/';

// Create directory if it doesn't exist
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$realBase = realpath($uploadsDir);
if ($realBase === false) {
    die('Error: Uploads directory configuration problem.');
}

// Full path to the requested file
$requestedPath = $uploadsDir . $file;
$realFile = realpath($requestedPath);

// Strict security check
if ($realFile === false || strpos($realFile, $realBase) !== 0) {
    http_response_code(404);
    die('Error 404: File not found or access denied.');
}

// Block dangerous file types
$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'htaccess', 'htpasswd'];

if (in_array($ext, $forbidden)) {
    die('Error: This file type is not allowed.');
}

if (!file_exists($realFile)) {
    http_response_code(404);
    die('Error 404: File not found.');
}

// Serve the file as download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($realFile));

// Clear any output buffer
while (ob_get_level()) {
    ob_end_clean();
}

readfile($realFile);
exit;
?>