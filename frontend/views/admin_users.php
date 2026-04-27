<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/AdminUsersController.php';

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

// Handle actions
if (isset($_GET['delete'])) {
    $result = deleteUser($pdo, $_GET['delete']);
    if ($result === true) $success = "User deleted.";
    else $error = $result;
}
if (isset($_GET['toggle'])) {
    if (toggleUserStatus($pdo, $_GET['toggle'])) $success = "User status updated.";
    else $error = "Status update failed.";
}
if (isset($_GET['reset'])) {
    if (resetUserPassword($pdo, $_GET['reset'])) $success = "Password reset to 'admin123'.";
    else $error = "Password reset failed.";
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $structure_id = $_POST['structure_id'];
    $role = $_POST['role'];
    $custom_password = trim($_POST['custom_password'] ?? '');
    if (empty($username) || empty($full_name) || empty($email) || empty($structure_id)) {
        $error = "Please fill in all required fields.";
    } else {
        if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
            $id = $_POST['edit_id'];
            if (updateUser($pdo, $id, compact('username','full_name','email','structure_id','role'))) {
                $success = "User updated.";
                $editMode = false;
            } else $error = "Update failed.";
        } else {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Username already exists.";
            } else {
                if (addUser($pdo, compact('username','full_name','email','structure_id','role'), $custom_password)) {
                    $success = "User added. Password: " . ($custom_password ?: 'admin123');
                } else $error = "Add failed.";
            }
        }
    }
}
if (isset($_GET['edit'])) {
    $user = getUserById($pdo, $_GET['edit']);
    if ($user && $user['role'] != 'admin') {
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
<html>
<head><title>Manage Users</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark text-white vh-100 p-3">
            <h5>MMS - Admin</h5><hr>
            <a href="dashboard.php" class="text-white d-block py-2">Home</a>
            <a href="admin_structures.php" class="text-white d-block py-2">Structures</a>
            <a href="admin_users.php" class="text-white d-block py-2 bg-primary px-2 rounded">Employees</a>
            <a href="admin_privileges.php" class="text-white d-block py-2">Privileges</a>
            <hr><a href="logout.php" class="text-white d-block py-2">Log out</a>
        </div>
        <div class="col-md-10 p-4">
            <h2>Manage Employees</h2>
            <?php if ($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
            <?php if ($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>

            <!-- Search -->
            <form method="GET" class="mb-4"><div class="row g-2"><div class="col-md-8"><input type="text" name="search" class="form-control" placeholder="Search by username, full name or email" value="<?=htmlspecialchars($search)?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary">Search</button></div><div class="col-md-2"><a href="admin_users.php" class="btn btn-secondary">Reset</a></div></div></form>

            <!-- Add/Edit Form -->
            <div class="card mb-4"><div class="card-header bg-primary text-white"><h5><?=$editMode?'Edit':'Add'?> Employee</h5></div>
            <div class="card-body"><form method="POST"><?php if($editMode):?><input type="hidden" name="edit_id" value="<?=$editId?>"><?php endif;?>
            <div class="row"><div class="col-md-3"><input type="text" name="username" class="form-control" placeholder="Username" value="<?=htmlspecialchars($editUsername)?>" required></div>
            <div class="col-md-3"><input type="text" name="full_name" class="form-control" placeholder="Full Name" value="<?=htmlspecialchars($editFullName)?>" required></div>
            <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" value="<?=htmlspecialchars($editEmail)?>" required></div>
            <div class="col-md-3"><button type="submit" class="btn btn-success w-100"><?=$editMode?'Update':'Add'?></button><?php if($editMode):?><a href="admin_users.php" class="btn btn-secondary w-100 mt-1">Cancel</a><?php endif;?></div></div>
            <div class="row mt-2"><div class="col-md-4"><select name="structure_id" class="form-select" required><option value="">Structure</option><?php foreach($structures as $s):?><option value="<?=$s['id']?>" <?=$editStructureId==$s['id']?'selected':''?>><?=htmlspecialchars($s['name'])?></option><?php endforeach;?></select></div>
            <div class="col-md-4"><select name="role" class="form-select" required><option value="top_manager" <?=$editRole=='top_manager'?'selected':''?>>Top Manager</option><option value="middle_manager" <?=$editRole=='middle_manager'?'selected':''?>>Middle Manager</option><option value="end_manager" <?=$editRole=='end_manager'?'selected':''?>>End Manager</option></select></div>
            <?php if(!$editMode):?><div class="col-md-4"><input type="password" name="custom_password" class="form-control" placeholder="Custom password (optional)"></div><?php endif;?></div>
            </form></div></div>

            <!-- Users Table -->
            <div class="card"><div class="card-header bg-secondary text-white"><h5>Employee List</h5></div><div class="card-body">
            <div class="table-responsive"><table class="table table-bordered"><thead class="table-dark"><td><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Structure</th><th>Role</th><th>Status</th><th>Last Login</th><th>Mails</th><th>Actions</th></tr></thead>
            <tbody><?php foreach($users as $u):?><tr><td><?=$u['id']?></td><td><?=htmlspecialchars($u['username'])?></td><td><?=htmlspecialchars($u['full_name'])?></td><td><?=htmlspecialchars($u['email'])?></td><td><?=htmlspecialchars($u['structure_name']??'N/A')?></td>
            <td><span class="badge bg-info"><?=str_replace('_',' ',$u['role'])?></span></td>
            <td><?=$u['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-danger">Suspended</span>'?></td>
            <td><?=$u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'Never'?></td>
            <td><span title="Sent: <?=$u['sent_count']?>, Received: <?=$u['received_count']?>">📤 <?=$u['sent_count']?> / 📥 <?=$u['received_count']?></span></td>
            <td><a href="?edit=<?=$u['id']?>" class="btn btn-sm btn-primary">Edit</a> <a href="?reset=<?=$u['id']?>" class="btn btn-sm btn-warning" onclick="return confirm('Reset password to admin123?')">Reset</a> <a href="?toggle=<?=$u['id']?>" class="btn btn-sm btn-info"><?=$u['is_active']?'Suspend':'Activate'?></a> <a href="?delete=<?=$u['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete user?')">Delete</a></td></tr>
            <?php endforeach;?></tbody></table></div></div></div>
        </div>
    </div>
</div>
</body>
</html>