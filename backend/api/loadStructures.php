<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../models/Structure.php';

$structureModel = new Structure($pdo);
$structures = $structureModel->getTree();
header('Content-Type: application/json');
echo json_encode($structures);
?>