<?php
// session_start(); // REMOVED – session already started in config.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
requirePasswordChange();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';

// ========== HELPER: Build code path for a structure (e.g., "STOS-DRH") ==========
function getStructureCodePath($pdo, $structureId) {
    if (!$structureId) return 'N/A';
    $stmt = $pdo->prepare("SELECT id, code, parent_id FROM structures");
    $stmt->execute();
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $parentMap = [];
    $codeMap = [];
    foreach ($all as $s) {
        $parentMap[$s['id']] = $s['parent_id'];
        $codeMap[$s['id']] = $s['code'];
    }
    $pathCodes = [];
    $current = $structureId;
    $max = 100;
    while ($current !== null && $max-- > 0) {
        $code = $codeMap[$current];
        if ($code !== 'SONA') { // exclude root SONELGAZ
            array_unshift($pathCodes, $code);
        }
        $current = $parentMap[$current] ?? null;
    }
    return implode('-', $pathCodes);
}

// ========== EMBEDDED HELPER FUNCTIONS WITH IMPROVED SEARCH ==========
function getAllUsers($pdo, $search) {
    $sql = "SELECT u.*, s.id as structure_id
            FROM users u LEFT JOIN structures s ON u.structure_id = s.id
            WHERE u.role != 'admin'";
    $params = [];

    if (!empty($search)) {
        // Split search into individual words
        $words = preg_split('/\s+/', trim($search));
        $fullNameConditions = [];
        foreach ($words as $word) {
            $fullNameConditions[] = "LOWER(u.full_name) LIKE LOWER(?)";
            $params[] = "%$word%";
        }
        $fullNameSql = implode(' OR ', $fullNameConditions);

        $sql .= " AND (LOWER(u.username) LIKE LOWER(?) 
                    OR LOWER(u.email) LIKE LOWER(?) 
                    OR ($fullNameSql))";
        $params[] = "%$search%";  // for username
        $params[] = "%$search%";  // for email
        // full_name words already added to $params
    }

    $sql .= " ORDER BY SUBSTRING_INDEX(u.full_name, ' ', -1) ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as &$u) {
        $u['structure_code_path'] = getStructureCodePath($pdo, $u['structure_id']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE sender_id = ?");
        $stmt->execute([$u['id']]);
        $u['sent_count'] = $stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients WHERE recipient_id = ?");
        $stmt->execute([$u['id']]);
        $u['received_count'] = $stmt->fetchColumn();
    }
    return $users;
}

function getUserForEdit($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function usernameExists($pdo, $username, $excludeId = 0) {
    $sql = "SELECT id FROM users WHERE username = ?";
    $params = [$username];
    if ($excludeId > 0) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() !== false;
}

function addUser($pdo, $data, $customPassword) {
    if (usernameExists($pdo, $data['username'])) return false;
    $password = !empty($customPassword) ? $customPassword : 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, structure_id, role, is_active, password_changed) VALUES (?, ?, ?, ?, ?, ?, 1, 0)");
    return $stmt->execute([$data['username'], $hash, $data['email'], $data['full_name'], $data['structure_id'], $data['role']]);
}

function updateUser($pdo, $id, $data) {
    if (usernameExists($pdo, $data['username'], $id)) return false;
    $stmt = $pdo->prepare("UPDATE users SET username=?, full_name=?, email=?, structure_id=?, role=? WHERE id=? AND role != 'admin'");
    return $stmt->execute([$data['username'], $data['full_name'], $data['email'], $data['structure_id'], $data['role'], $id]);
}

function deleteUser($pdo, $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE sender_id = ? OR id IN (SELECT mail_id FROM mail_recipients WHERE recipient_id = ?)");
    $stmt->execute([$id, $id]);
    if ($stmt->fetchColumn() > 0) {
        return "Cannot delete user: they have sent or received mails.";
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    return $stmt->execute([$id]) ? true : "Delete failed.";
}

function getAllStructuresForSelect($pdo) {
    $stmt = $pdo->query("SELECT id, name, parent_id FROM structures ORDER BY name");
    $structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($structures)) return [];

    $parentMap = [];
    $nameMap = [];
    foreach ($structures as $s) {
        $parentMap[$s['id']] = $s['parent_id'];
        $nameMap[$s['id']] = $s['name'];
    }

    $result = [];
    foreach ($structures as $s) {
        $id = $s['id'];
        $path = [];
        $current = $id;
        $max = 100;
        while ($current !== null && $max-- > 0) {
            $path[] = $nameMap[$current];
            $current = $parentMap[$current];
        }
        $path = array_reverse($path);
        $fullPath = implode(' → ', $path);
        $result[] = ['id' => $id, 'name' => $fullPath];
    }
    usort($result, fn($a,$b) => strcmp($a['name'], $b['name']));
    return $result;
}
// =================================================

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF error. Please reload the page.";
    } else {
        if (isset($_POST['delete_user'])) {
            $userId = (int)$_POST['user_id'];
            $result = deleteUser($pdo, $userId);
            if ($result === true) $success = "User deleted successfully.";
            else $error = $result;
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
                        $error = "Failed to update user. Username may already exist.";
                    }
                } else {
                    if (addUser($pdo, $data, $custom_password)) {
                        $success = "User added successfully. Default password: " . ($custom_password ?: 'admin123');
                    } else {
                        $error = "Failed to add user. Username already exists or data invalid.";
                    }
                }
            }
        }
    }
}

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

            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" placeholder="Search by username, full name or email (case‑insensitive, any word order)" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Search</button></div>
                    <div class="col-md-2"><a href="admin_users.php" class="btn btn-secondary w-100">Reset</a></div>
                </div>
            </form>

            <!-- Add / Edit Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white"><h5><?= $editMode ? 'Edit Employee' : 'Add New Employee' ?></h5></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="save_user" value="1">
                        <?php if($editMode): ?><input type="hidden" name="edit_id" value="<?= $editId ?>"><?php endif; ?>
                        <div class="row g-3">
                            <div class="col-md-3"><input type="text" name="username" class="form-control" placeholder="Username" value="<?= htmlspecialchars($editUsername) ?>" required></div>
                            <div class="col-md-3"><input type="text" name="full_name" class="form-control" placeholder="Full Name" value="<?= htmlspecialchars($editFullName) ?>" required></div>
                            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" value="<?= htmlspecialchars($editEmail) ?>" required></div>
                            <div class="col-md-3"><button type="submit" class="btn btn-success w-100"><?= $editMode ? 'Update' : 'Add' ?></button><?php if($editMode): ?><a href="admin_users.php" class="btn btn-secondary w-100 mt-2">Cancel</a><?php endif; ?></div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <select name="structure_id" class="form-select" required>
                                    <option value="">Select Structure</option>
                                    <?php foreach($structures as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $editStructureId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
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
                            <div class="col-md-4"><input type="password" name="custom_password" class="form-control" placeholder="Custom password (optional)"></div>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users List with Action Dropdown -->
            <div class="card">
                <div class="card-header bg-secondary text-white"><h5>Employees List</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Structure (Code Path)</th><th>Role</th><th>Status</th><th>Last Login</th><th>Mails</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><?= htmlspecialchars($u['full_name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($u['structure_code_path'] ?? 'N/A') ?></span></td>
                                <td><span class="badge bg-info"><?= str_replace('_',' ', ucfirst($u['role'])) ?></span></td>
                                <td><?= $u['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Suspended</span>' ?></td>
                                <td><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
                                <td>📤 <?= $u['sent_count'] ?> / 📥 <?= $u['received_count'] ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Manage ▼</button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="?edit=<?= $u['id'] ?>">Edit</a></li>
                                            <li>
                                                <form method="POST" style="display: inline; width: 100%;">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" name="delete_user" class="dropdown-item text-danger" onclick="return confirm('Delete this user permanently?')">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                  </div>
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