<?php
require_once __DIR__ . '/../models/User.php';

class LoginController {
    private $userModel;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }
    
    public function login() {
        session_start();
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $user = $this->userModel->findByUsername($username);
            if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
                $this->userModel->updateLastLogin($user['id']);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['structure_id'] = $user['structure_id'];
                header('Location: ../frontend/views/dashboard.php');
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        }
        // Include the login view
        include __DIR__ . '/../../frontend/views/login.php';
    }
}
?>