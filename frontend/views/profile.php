<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
requirePasswordChange();

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ProfileController.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = $_SESSION['user_id'];
$user = getUserProfile($pdo, $userId);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF error. Please reload the page.";
    } else {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        $result = updateUserPassword($pdo, $userId, $current, $new, $confirm);
        
        if ($result === true) {
            $success = "Password changed successfully.";
        } else {
            $error = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Mail Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark sidebar p-3">
                <h5 class="text-white text-center">MMS</h5>
                <hr class="text-secondary">
                <nav class="nav flex-column">
                    <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
                    
                    <?php if (!hasRole('admin')): ?>
                        <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                        <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                        <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archive</a>
                    <?php endif; ?>
                    
                    <a href="profile.php" class="nav-link active"><i class="fas fa-user"></i> My Profile</a>
                    
                    <?php if (hasRole('admin')): ?>
                        <hr class="text-secondary">
                        <a href="admin_structures.php" class="nav-link"><i class="fas fa-building"></i> Structures</a>
                        <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                        <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                    <?php endif; ?>
                    
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Personal Information -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5><i class="fas fa-info-circle"></i> Personal Information</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Username</th>
                                        <td><?= htmlspecialchars($user['username'] ?? '') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Full Name</th>
                                        <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Email</th>
                                        <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Structure</th>
                                        <td><?= htmlspecialchars($user['structure_name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th>Role</th>
                                        <td>
                                            <span class="badge bg-<?= $user['role']=='admin' ? 'danger' : ($user['role']=='top_manager' ? 'danger' : ($user['role']=='middle_manager' ? 'warning' : 'info')) ?>">
                                                <?= str_replace('_', ' ', ucfirst($user['role'] ?? '')) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Account Status</th>
                                        <td><?= $user['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Last Login</th>
                                        <td><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                    </tr>
                                    <tr>
                                        <th>Account Created</th>
                                        <td><?= isset($user['created_at']) ? date('d/m/Y H:i', strtotime($user['created_at'])) : 'N/A' ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5><i class="fas fa-key"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" required>
                                        <small class="text-muted">Minimum 4 characters</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>

                                    <button type="submit" name="change_password" class="btn btn-primary w-100">
                                        Update Password
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Quick Info -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5><i class="fas fa-question-circle"></i> Quick Information</h5>
                            </div>
                            <div class="card-body">
                                <p class="small mb-0">
                                    <i class="fas fa-envelope"></i> You can send and receive mails according to your role.<br>
                                    <i class="fas fa-archive"></i> Archived mails can be restored or permanently deleted.<br>
                                    <i class="fas fa-lock"></i> Keep your password secure and change it regularly.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>