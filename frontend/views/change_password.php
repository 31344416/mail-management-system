<?php
// session_start(); // ← Absolument pas ici – déjà dans config.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['force_password_change'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = "Password must contain at least one number.";
    } elseif ($newPassword !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, password_changed = 1 WHERE id = ?");
        if ($stmt->execute([$hash, $userId])) {
            unset($_SESSION['force_password_change']);
            $success = "Password changed successfully. Redirecting to dashboard...";
            echo "<script>setTimeout(function(){ window.location.href = 'dashboard.php'; }, 2000);</script>";
        } else {
            $error = "Failed to update password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Mail Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="col-md-5">
        <div class="card shadow-lg">
            <div class="card-header bg-warning text-dark text-center">
                <h4><i class="fas fa-key"></i> Change Your Password</h4>
                <p class="mb-0">For security, you must change your password before continuing.</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                        <small class="text-muted">Minimum 6 characters, at least one number.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>