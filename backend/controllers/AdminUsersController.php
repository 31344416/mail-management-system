<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Structure.php';

function getAllUsers($pdo, $search = '') {
    $userModel = new User($pdo);
    return $userModel->getAllNonAdmin($search);
}

function getUserById($pdo, $id) {
    $userModel = new User($pdo);
    return $userModel->findById($id);
}

function addUser($pdo, $data, $customPassword = null) {
    $userModel = new User($pdo);
    $password = !empty($customPassword) ? $customPassword : 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return $userModel->create($data, $hash);
}

function updateUser($pdo, $id, $data) {
    $userModel = new User($pdo);
    return $userModel->update($id, $data);
}

function deleteUser($pdo, $id) {
    $userModel = new User($pdo);
    // Check if user has mails (sent or received)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE sender_id = ?");
    $stmt->execute([$id]);
    $sent = $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients WHERE recipient_id = ?");
    $stmt->execute([$id]);
    $received = $stmt->fetchColumn();
    if ($sent > 0 || $received > 0) {
        return "Cannot delete user: they have sent or received mails. Suspend instead.";
    }
    return $userModel->delete($id) ? true : "Delete failed.";
}

function toggleUserStatus($pdo, $id) {
    $userModel = new User($pdo);
    return $userModel->toggleActive($id);
}

function resetUserPassword($pdo, $id) {
    $userModel = new User($pdo);
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    return $userModel->resetPassword($id, $hash);
}

function getAllStructuresForSelect($pdo) {
    $structModel = new Structure($pdo);
    return $structModel->getAll();
}
?>