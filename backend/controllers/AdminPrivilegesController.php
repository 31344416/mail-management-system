<?php
require_once __DIR__ . '/../models/User.php';

function getAllUsersForPrivileges($pdo, $search = '') {
    $userModel = new User($pdo);
    return $userModel->getAllNonAdmin($search);
}

function changeUserRole($pdo, $userId, $newRole) {
    $userModel = new User($pdo);
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
    return $stmt->execute([$newRole, $userId]);
}

function toggleUserStatusPrivileges($pdo, $userId) {
    $userModel = new User($pdo);
    return $userModel->toggleActive($userId);
}

function resetUserPasswordPrivileges($pdo, $userId) {
    $userModel = new User($pdo);
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    return $userModel->resetPassword($userId, $hash);
}
?>