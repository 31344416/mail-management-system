<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
require_once __DIR__ . '/../config.php';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients mr 
                       JOIN mails m ON mr.mail_id = m.id 
                       WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.is_archived = 0");
$stmt->execute([$_SESSION['user_id']]);
$count = $stmt->fetchColumn();
header('Content-Type: application/json');
echo json_encode(['unread' => $count]);
?>