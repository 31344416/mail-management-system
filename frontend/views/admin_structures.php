<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/AdminStructuresController.php';

$error = '';
$success = '';
$editMode = false;
$editId = 0;
$editCode = '';
$editName = '';
$editParentId = '';
$editAddress = '';
$search = $_GET['search'] ?? '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $result = deleteStructure($pdo, $_GET['delete']);
    if ($result === true) $success = "Structure deleted successfully.";
    else $error = $result;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] ?: null;
    $address = trim($_POST['address']);
    if (empty($code) || empty($name)) $error = "Code and Name required.";
    else {
        $data = compact('code','name','parent_id','address');
        if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
            if (updateStructure($pdo, $_POST['edit_id'], $data)) { $success = "Structure updated."; $editMode = false; }
            else $error = "Update failed.";
        } else {
            if (addStructure($pdo, $data)) $success = "Structure added.";
            else $error = "Add failed.";
        }
    }
}
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $struct = getStructureForEdit($pdo, $_GET['edit']);
    if ($struct) { $editMode = true; $editId = $struct['id']; $editCode = $struct['code']; $editName = $struct['name']; $editParentId = $struct['parent_id']; $editAddress = $struct['address']; }
}
$structures = getAllStructures($pdo, $search);
$allStructs = getAllStructuresForDropdown($pdo);
$tableHtml = buildStructureTreeHtml($structures);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Structures</title>
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
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <a href="admin_structures.php" class="nav-link active"><i class="fas fa-building"></i> Structures</a>
                <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Log out</a>
            </nav>
        </div>
        <div class="col-md-10 p-4">
            <h2>Manage Structures</h2>
            <?php if ($error):?><div class="alert alert-danger"><?= $error ?></div><?php endif;?>
            <?php if ($success):?><div class="alert alert-success"><?= $success ?></div><?php endif;?>
            <form method="GET" class="mb-4"><div class="row g-2"><div class="col-md-8"><input type="text" name="search" class="form-control" placeholder="Search by name or code" value="<?= htmlspecialchars($search) ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Search</button></div><div class="col-md-2"><a href="admin_structures.php" class="btn btn-secondary w-100">Reset</a></div></div></form>
            <div class="card mb-4"><div class="card-header bg-primary text-white"><h5><?= $editMode ? 'Edit' : 'Add' ?> Structure</h5></div><div class="card-body"><form method="POST"><?php if($editMode):?><input type="hidden" name="edit_id" value="<?= $editId ?>"><?php endif;?><div class="row"><div class="col-md-3"><input type="text" name="code" class="form-control" placeholder="Code" value="<?= htmlspecialchars($editCode) ?>" required></div><div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Name" value="<?= htmlspecialchars($editName) ?>" required></div><div class="col-md-3"><select name="parent_id" class="form-select"><option value="">-- No parent --</option><?php foreach($allStructs as $s):?><option value="<?= $s['id'] ?>" <?= $editParentId == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option><?php endforeach;?></select></div><div class="col-md-2"><button type="submit" class="btn btn-success w-100"><?= $editMode ? 'Update' : 'Add' ?></button><?php if($editMode):?><a href="admin_structures.php" class="btn btn-secondary w-100 mt-1">Cancel</a><?php endif;?></div></div><div class="row mt-2"><div class="col-md-12"><input type="text" name="address" class="form-control" placeholder="Address (optional)" value="<?= htmlspecialchars($editAddress) ?>"></div></div></form></div></div>
            <div class="card"><div class="card-header bg-secondary text-white"><h5>Structures Hierarchy</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-bordered"><thead class="table-dark"><tr><th>Code</th><th>Name</th><th>Parent ID</th><th>Address</th><th>Actions</th></tr></thead><tbody><?= $tableHtml ?></tbody></table></div></div></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>