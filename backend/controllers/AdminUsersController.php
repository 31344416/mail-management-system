<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Structure.php';

function getAllUsers($pdo, $search = '') {
    $userModel = new User($pdo);
    return $userModel->getAllNonAdmin($search);
}

function getUserForEdit($pdo, $id) {
    $userModel = new User($pdo);
    return $userModel->getById($id);
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
    // Check if user has mails
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE sender_id = ? OR id IN (SELECT mail_id FROM mail_recipients WHERE recipient_id = ?)");
    $stmt->execute([$id, $id]);
    if ($stmt->fetchColumn() > 0) {
        return "Cannot delete user: they have sent or received mails.";
    }
    return $userModel->delete($id) ? true : "Delete failed.";
}

function toggleUserActive($pdo, $id) {
    $userModel = new User($pdo);
    return $userModel->toggleActive($id);
}

function resetUserPassword($pdo, $id) {
    $userModel = new User($pdo);
    return $userModel->resetPassword($id, password_hash('admin123', PASSWORD_DEFAULT));
}

function getAllStructuresForSelect($pdo) {
    $structModel = new Structure($pdo);
    return $structModel->getAll();
}
?>