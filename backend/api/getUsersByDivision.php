<?php
// No session_start() here – config.php already starts the session
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please login.']);
    exit;
}

// Get division ID from request
$divisionId = isset($_GET['division_id']) ? (int)$_GET['division_id'] : 0;
$currentUserId = $_SESSION['user_id'];

// Validate division ID
if ($divisionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid division ID (must be positive integer)']);
    exit;
}

// Verify that the division exists
$checkStmt = $pdo->prepare("SELECT id FROM structures WHERE id = ?");
$checkStmt->execute([$divisionId]);
if (!$checkStmt->fetch()) {
    echo json_encode(['error' => 'Division not found with ID: ' . $divisionId]);
    exit;
}

// Get active users belonging to this division
$stmt = $pdo->prepare("
    SELECT id, full_name, role 
    FROM users 
    WHERE structure_id = ? 
      AND is_active = 1 
      AND role != 'admin' 
      AND id != ? 
    ORDER BY full_name
");
$stmt->execute([$divisionId, $currentUserId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($users);
?>