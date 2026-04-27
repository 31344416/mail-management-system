<?php
require_once __DIR__ . '/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../frontend/views/login.php');
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] == $role;
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        die("Access denied. Required role: $role");
    }
}

function getSendTypesForRole() {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'top_manager': return ['demande', 'orientation', 'rapport', 'reponse'];
        case 'middle_manager': return ['demande', 'rapport', 'orientation'];
        case 'end_manager': return ['rapport', 'reponse'];
        default: return [];
    }
}

function canReplyToType($type) {
    $role = $_SESSION['role'] ?? '';
    switch ($role) {
        case 'top_manager': return in_array($type, ['demande', 'orientation', 'rapport', 'reponse']);
        case 'middle_manager': return in_array($type, ['demande', 'rapport', 'orientation']);
        case 'end_manager': return in_array($type, ['rapport', 'reponse']);
        default: return false;
    }
}
?>