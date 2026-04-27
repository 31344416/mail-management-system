<?php
require_once __DIR__ . '/../models/User.php';

function getUserProfile($pdo, $userId) {
    $userModel = new User($pdo);
    return $userModel->getById($userId);
}

function updateUserPassword($pdo, $userId, $currentPassword, $newPassword, $confirmPassword) {
    $userModel = new User($pdo);
    $user = $userModel->findById($userId);
    
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        return "Current password is incorrect.";
    }
    if (strlen($newPassword) < 4) {
        return "New password must be at least 4 characters.";
    }
    if ($newPassword !== $confirmPassword) {
        return "New password and confirmation do not match.";
    }
    
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if ($userModel->resetPassword($userId, $newHash)) {
        return true;
    }
    return "Failed to update password.";
}
?>