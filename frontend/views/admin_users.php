<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';

$error = '';
$success = '';
$editMode = false;
$editId = 0;
$editUsername = '';
$editFullName = '';
$editEmail = '';
$editStructureId = '';
$editRole = '';
$search = $_GET['search'] ?? '';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF error. Please reload the page.";
    } else {
        if (isset($_POST['delete_user'])) {
            $userId = (int)$_POST['user_id'];
            $result = deleteUser($pdo, $userId);
            if ($result === true) $success = "User deleted successfully.";
            else $error = $result;
        } elseif (isset($_POST['toggle_user'])) {
            $userId = (int)$_POST['user_id'];
            toggleUserActive($pdo, $userId);
            $success = "User status updated.";
        } elseif (isset($_POST['reset_password'])) {
            $userId = (int)$_POST['user_id'];
            resetUserPassword($pdo, $userId);
            $success = "Password has been reset to 'admin123'.";
        } elseif (isset($_POST['save_user'])) {
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $structure_id = $_POST['structure_id'];
            $role = $_POST['role'];
            $custom_password = trim($_POST['custom_password'] ?? '');

            if (empty($username) || empty($full_name) || empty($email) || empty($structure_id)) {
                $error = "Please fill all required fields.";
            } else {
                $data = compact('username', 'full_name', 'email', 'structure_id', 'role');
                if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
                    if (updateUser($pdo, $_POST['edit_id'], $data)) {
                        $success = "User updated successfully.";
                        $editMode = false;
                    } else {
                        $error = "Failed to update user.";
                    }
                } else {
                    if (addUser($pdo, $data, $custom_password)) {
                        $success = "User added successfully. Default password: " . ($custom_password ?: 'admin123');
                    } else {
                        $error = "Failed to add user (username may already exist).";
                    }
                }
            }
        }
    }
}

// Load user for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $user = getUserForEdit($pdo, $_GET['edit']);
    if ($user) {
        $editMode = true;
        $editId = $user['id'];
        $editUsername = $user['username'];
        $editFullName = $user['full_name'];
        $editEmail = $user['email'];
        $editStructureId = $user['structure_id'];
        $editRole = $user['role'];
    }
}

$users = getAllUsers($pdo, $search);
$structures = getAllStructuresForSelect($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Employees - Admin Panel</title>
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
                <a href="admin_users.php" class="nav-link active"><i class="fas fa-users"></i> Employees</a>
                <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        
        <div class="col-md-10 p-4">
            <h2>Manage Employees</h2>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- Search -->
            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by username, full name or email" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Search</button></div>
                    <div class="col-md-2"><a href="admin_users.php" class="btn btn-secondary w-100">Reset</a></div>
                </div>
            </form>

            <!-- Add / Edit Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><?= $editMode ? 'Edit Employee' : 'Add New Employee' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="save_user" value="1">
                        <?php if($editMode): ?><input type="hidden" name="edit_id" value="<?= $editId ?>"><?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="username" class="form-control" placeholder="Username" 
                                       value="<?= htmlspecialchars($editUsername) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="full_name" class="form-control" placeholder="Full Name" 
                                       value="<?= htmlspecialchars($editFullName) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <input type="email" name="email" class="form-control" placeholder="Email" 
                                       value="<?= htmlspecialchars($editEmail) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-success w-100">
                                    <?= $editMode ? 'Update Employee' : 'Add Employee' ?>
                                </button>
                                <?php if($editMode): ?>
                                    <a href="admin_users.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <select name="structure_id" class="form-select" required>
                                    <option value="">Select Structure</option>
                                    <?php foreach($structures as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $editStructureId == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select name="role" class="form-select" required>
                                    <option value="top_manager" <?= $editRole=='top_manager'?'selected':'' ?>>Top Manager</option>
                                    <option value="middle_manager" <?= $editRole=='middle_manager'?'selected':'' ?>>Middle Manager</option>
                                    <option value="end_manager" <?= $editRole=='end_manager'?'selected':'' ?>>End Manager</option>
                                </select>
                            </div>
                            <?php if(!$editMode): ?>
                            <div class="col-md-4">
                                <input type="password" name="custom_password" class="form-control" 
                                       placeholder="Custom password (optional)">
                            </div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users List -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5>Employees List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Structure</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Mails</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['structure_name'] ?? 'N/A') ?></td>
                                <td><span class="badge bg-info"><?= str_replace('_',' ', ucfirst($u['role'])) ?></span></td>
                                <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>' ?></td>
                                <td><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                                <td>📤 <?= $u['sent_count'] ?> / 📥 <?= $u['received_count'] ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <button type="submit" name="reset_password" class="btn btn-sm btn-warning" onclick="return confirm('Reset password to admin123?')">Reset</button>
                                        <button type="submit" name="toggle_user" class="btn btn-sm btn-info">
                                            <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                                        </button>
                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user permanently?')">Delete</button>
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