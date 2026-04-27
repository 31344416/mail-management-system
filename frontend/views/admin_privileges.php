<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/AdminPrivilegesController.php';

$error = '';
$success = '';
$search = $_GET['search'] ?? '';

// Role change via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $userId = $_POST['user_id'];
    $newRole = $_POST['new_role'];
    if (changeUserRole($pdo, $userId, $newRole)) $success = "Role updated.";
    else $error = "Role update failed.";
}
// Toggle status
if (isset($_GET['toggle'])) {
    if (toggleUserStatusPrivileges($pdo, $_GET['toggle'])) $success = "Status updated.";
    else $error = "Status update failed.";
}
// Reset password
if (isset($_GET['reset'])) {
    if (resetUserPasswordPrivileges($pdo, $_GET['reset'])) $success = "Password reset to 'admin123'.";
    else $error = "Password reset failed.";
}

$users = getAllUsersForPrivileges($pdo, $search);
?>
<!DOCTYPE html>
<html>
<head><title>Manage Privileges</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"></head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark text-white vh-100 p-3">
            <h5>MMS - Admin</h5><hr>
            <a href="dashboard.php" class="text-white d-block py-2">Home</a>
            <a href="admin_structures.php" class="text-white d-block py-2">Structures</a>
            <a href="admin_users.php" class="text-white d-block py-2">Employees</a>
            <a href="admin_privileges.php" class="text-white d-block py-2 bg-primary px-2 rounded">Privileges</a>
            <hr><a href="logout.php" class="text-white d-block py-2">Log out</a>
        </div>
        <div class="col-md-10 p-4">
            <h2>Manage Privileges</h2>
            <?php if($error):?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>
            <?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>

            <!-- Search -->
            <form method="GET" class="mb-4"><div class="row g-2"><div class="col-md-8"><input type="text" name="search" class="form-control" placeholder="Search by username, full name or email" value="<?=htmlspecialchars($search)?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary">Search</button></div><div class="col-md-2"><a href="admin_privileges.php" class="btn btn-secondary">Reset</a></div></div></form>

            <div class="card"><div class="card-header bg-secondary text-white"><h5>User Permissions</h5></div>
            <div class="card-body"><div class="table-responsive"><table class="table table-bordered"><thead class="table-dark"><tr><th>User</th><th>Structure</th><th>Role</th><th>Status</th><th>Last Login</th><th>Mails</th><th>Change Role</th><th>Actions</th></tr></thead>
            <tbody><?php foreach($users as $u):?>
            <tr><td><strong><?=htmlspecialchars($u['full_name'])?></strong><br><small><?=htmlspecialchars($u['username'])?></small></td>
            <td><?=htmlspecialchars($u['structure_name']??'N/A')?></td>
            <td><span class="badge bg-<?=$u['role']=='top_manager'?'danger':($u['role']=='middle_manager'?'warning':'info')?>"><?=str_replace('_',' ',$u['role'])?></span></td>
            <td><?=$u['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-danger">Suspended</span>'?></td>
            <td><?=$u['last_login']?date('d/m/Y H:i',strtotime($u['last_login'])):'Never'?></td>
            <td><span title="Sent: <?=$u['sent_count']?>, Received: <?=$u['received_count']?>">📤 <?=$u['sent_count']?> / 📥 <?=$u['received_count']?></span></td>
            <td><form method="POST"><input type="hidden" name="user_id" value="<?=$u['id']?>"><select name="new_role" onchange="this.form.submit()"><option value="top_manager" <?=$u['role']=='top_manager'?'selected':''?>>Top Manager</option><option value="middle_manager" <?=$u['role']=='middle_manager'?'selected':''?>>Middle Manager</option><option value="end_manager" <?=$u['role']=='end_manager'?'selected':''?>>End Manager</option></select><input type="hidden" name="change_role" value="1"></form></td>
            <td><a href="?toggle=<?=$u['id']?>" class="btn btn-sm btn-info" onclick="return confirm('Toggle status?')"><i class="fas <?=$u['is_active']?'fa-pause':'fa-play'?>"></i></a> <a href="?reset=<?=$u['id']?>" class="btn btn-sm btn-warning" onclick="return confirm('Reset password?')"><i class="fas fa-key"></i></a></td></tr>
            <?php endforeach;?></tbody></table></div></div></div>

            <!-- Info card -->
            <div class="card mt-4"><div class="card-header bg-info text-white"><h5>Role Permissions</h5></div><div class="card-body"><div class="row"><div class="col-md-4"><h6><span class="badge bg-danger">Top Manager</span></h6><ul><li>Send: Demande, Orientation, Rapport, Reponse</li><li>Receive: All types</li><li>Reply: All types</li></ul></div><div class="col-md-4"><h6><span class="badge bg-warning">Middle Manager</span></h6><ul><li>Send: Demande, Rapport, Orientation</li><li>Receive: All types</li><li>Reply: Demande, Rapport, Orientation</li></ul></div><div class="col-md-4"><h6><span class="badge bg-info">End Manager</span></h6><ul><li>Send: Rapport, Reponse</li><li>Receive: All types</li><li>Reply: Rapport, Reponse</li></ul></div></div></div></div>
        </div>
    </div>
</div>
</body>
</html>