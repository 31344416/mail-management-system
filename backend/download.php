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
    http_response_code(400);
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
// -----------------------------------------------------------------
// Resolve the uploads directory robustly.
//
// SendController saves files to:
//   $_SERVER['DOCUMENT_ROOT'] . '/mail_management/uploads/'
//
// __DIR__ here is: <docroot>/mail_management/backend/
// So uploads is always one level up, then /uploads/
// Using __DIR__ avoids any DOCUMENT_ROOT mis-configuration.
// -----------------------------------------------------------------
$uploadsDir = realpath(__DIR__ . '/../uploads');

// If the directory doesn't exist yet, create it
if ($uploadsDir === false) {
    $target = __DIR__ . '/../uploads';
    if (!mkdir($target, 0755, true)) {
        http_response_code(500);
        die('Error: Uploads directory could not be created.');
    }
    $uploadsDir = realpath($target);
}

// Build the full path and verify it stays inside uploadsDir (path traversal guard)
$requestedPath = $uploadsDir . DIRECTORY_SEPARATOR . $file;
$realFile      = realpath($requestedPath);
// Strict security check
if ($realFile === false || strpos($realFile, $realBase
if ($realFile === false || strpos($realFile, $uploadsDir) !== 0) {
    http_response_code(404);
    die('Error 404: File not found or access denied.');
}

if (!is_file($realFile)) {
    http_response_code(404);
    die('Error 404: File not found.');
}

// Block dangerous file types
$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$forbidden = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'htaccess', 'htpasswd'];

if (in_array($ext, $forbidden)) {
    http_response_code(403);
    die('Error: This file type is not allowed.');
}

if (!file_exists($realFile)) {
    http_response_code(404);
    die('Error 404: File not found.');
}

// Serve the file as download
// Map extensions to proper MIME types so browsers handle the file correctly
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'zip'  => 'application/zip',
];
$mime = $mimeTypes[$ext] ?? 'application/octet-stream';

// Serve the file as a download
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Type: ' . $mime);
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