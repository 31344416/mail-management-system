<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/Tracking.php';

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
$trackingId = isset($input['id']) ? (int)$input['id'] : 0;

if ($trackingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

$trackModel = new Tracking($pdo);
$result = $trackModel->markNotificationAsRead($trackingId, $_SESSION['user_id']);

header('Content-Type: application/json');
echo json_encode(['success' => $result]);
?>