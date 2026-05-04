<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
requirePasswordChange();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/AdminPrivilegesController.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$search = $_GET['search'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF error. Please reload the page.";
    } else {
        if (isset($_POST['change_role'])) {
            $userId = (int)$_POST['user_id'];
            $newRole = $_POST['new_role'];
            if (changeUserRole($pdo, $userId, $newRole)) {
                $success = "User role updated successfully.";
            } else {
                $error = "Failed to update role.";
            }
        }
        if (isset($_POST['toggle_user'])) {
            $userId = (int)$_POST['user_id'];
            toggleUserStatusPrivileges($pdo, $userId);
            $success = "User status updated successfully.";
        }
        if (isset($_POST['reset_password'])) {
            $userId = (int)$_POST['user_id'];
            resetUserPasswordPrivileges($pdo, $userId);
            $success = "Password has been reset to 'admin123'.";
        }
    }
}

$users = getAllUsersForPrivileges($pdo, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Privileges - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark sidebar p-3">
            <h5 class="text-white text-center">MMS - Admin</h5>
            <hr class="text-secondary">
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
                <a href="admin_structures.php" class="nav-link"><i class="fas fa-building"></i> Structures</a>
                <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                <a href="admin_privileges.php" class="nav-link active"><i class="fas fa-key"></i> Privileges</a>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        
        <div class="col-md-10 p-4">
            <h2>Manage User Privileges</h2>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- Search Form -->
            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by username, full name or email" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Search</button></div>
                    <div class="col-md-2"><a href="admin_privileges.php" class="btn btn-secondary w-100">Reset</a></div>
                </div>
            </form>

            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5>User Privileges & Permissions</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>User</th>
                                    <th>Structure</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Mails</th>
                                    <th>Change Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($u['username']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($u['structure_name'] ?? 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $u['role']=='top_manager'?'danger':($u['role']=='middle_manager'?'warning':'info') ?>">
                                            <?= str_replace('_', ' ', ucfirst($u['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>' ?>
                                    </td>
                                    <td>
                                        <?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Never' ?>
                                    </td>
                                    <td>
                                        📤 <?= $u['sent_count'] ?> / 📥 <?= $u['received_count'] ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="new_role" onchange="this.form.submit()" class="form-select form-select-sm d-inline w-auto">
                                                <option value="top_manager" <?= $u['role']=='top_manager'?'selected':'' ?>>Top Manager</option>
                                                <option value="middle_manager" <?= $u['role']=='middle_manager'?'selected':'' ?>>Middle Manager</option>
                                                <option value="end_manager" <?= $u['role']=='end_manager'?'selected':'' ?>>End Manager</option>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" name="toggle_user" class="btn btn-sm btn-info">
                                                <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                                            </button>
                                            <button type="submit" name="reset_password" 
                                                    class="btn btn-sm btn-warning" 
                                                    onclick="return confirm('Reset password to admin123?')">
                                                Reset Password
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
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