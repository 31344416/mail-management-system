<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/models/User.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $userModel = new User($pdo);
    $user = $userModel->findByUsername($username);
    
    if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
        $userModel->updateLastLogin($user['id']);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['structure_id'] = $user['structure_id'];
        
        // Check if password must be changed
        if ($user['password_changed'] == 0) {
            $_SESSION['force_password_change'] = true;
            header('Location: change_password.php');
            exit;
        } else {
            header('Location: dashboard.php');
            exit;
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mail Management Sonalgaz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f1724 0%, #1e2a3a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            max-width: 450px;
            width: 100%;
        }

        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #1abc9c, #16a085);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .login-header h1 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .login-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .login-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
            font-size: 0.9rem;
        }

        .form-group label i {
            margin-right: 8px;
            color: #1abc9c;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #1abc9c;
            box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2);
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, #1abc9c, #16a085);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 188, 156, 0.4);
        }

        .btn-login i {
            margin-right: 8px;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .login-footer {
            background: #f8f9fa;
            padding: 1rem 2rem;
            text-align: center;
            border-top: 1px solid #e9ecef;
            font-size: 0.75rem;
            color: #6c757d;
        }

        .forgot-password {
            text-align: right;
            margin-top: 0.5rem;
        }

        .forgot-password a {
            color: #1abc9c;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .security-note {
            font-size: 0.7rem;
            text-align: center;
            margin-top: 1rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Mail Management System</h1>
                <p>S-TOS / SONELGAZ</p>
            </div>
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="username" class="form-control" required autofocus>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                
                    <div class="security-note">
                        <i class="fas fa-shield-alt"></i> Reserved access to authorized personnel
                    </div>
                </form>
            </div>
            <div class="login-footer">
                © 2026 SONELGAZ - Groupe S-TOS | Electronic Mail Management System
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>