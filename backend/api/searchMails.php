<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/Mail.php';

$userId = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$priority = $_GET['priority'] ?? '';

$mailModel = new Mail($pdo);
$mails = $mailModel->getReceivedForUser($userId, false, $search, $priority);
header('Content-Type: application/json');
echo json_encode($mails);
?>