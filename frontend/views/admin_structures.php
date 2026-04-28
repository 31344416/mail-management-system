<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireRole('admin');
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/AdminStructuresController.php';

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';
$editMode = false;
$editId = 0;
$editCode = '';
$editName = '';
$editParentId = '';
$editAddress = '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $result = deleteStructure($pdo, $_GET['delete']);
    if ($result === true) {
        $success = "Structure deleted successfully.";
    } else {
        $error = $result;
    }
}

// Handle Add / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "CSRF error. Please reload the page.";
    } else {
        $code = trim($_POST['code']);
        $name = trim($_POST['name']);
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $address = trim($_POST['address']);

        if (empty($code) || empty($name)) {
            $error = "Code and Name are required.";
        } else {
            $data = compact('code', 'name', 'parent_id', 'address');
            if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
                if (updateStructure($pdo, $_POST['edit_id'], $data)) {
                    $success = "Structure updated successfully.";
                    $editMode = false;
                } else {
                    $error = "Failed to update structure.";
                }
            } else {
                if (addStructure($pdo, $data)) {
                    $success = "Structure added successfully.";
                } else {
                    $error = "Failed to add structure.";
                }
            }
        }
    }
}

// Load structure for editing
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $struct = getStructureForEdit($pdo, $_GET['edit']);
    if ($struct) {
        $editMode = true;
        $editId = $struct['id'];
        $editCode = $struct['code'];
        $editName = $struct['name'];
        $editParentId = $struct['parent_id'];
        $editAddress = $struct['address'];
    }
}

$structures = getAllStructures($pdo, $search);
$allStructs = getAllStructuresForDropdown($pdo);
$isSearching = !empty($search);

if ($isSearching) {
    $tableHtml = '';
    if (count($structures) > 0) {
        foreach ($structures as $struct) {
            $tableHtml .= '<tr>';
            $tableHtml .= '<td>' . htmlspecialchars($struct['code']) . '</td>';
            $tableHtml .= '<td>' . htmlspecialchars($struct['name']) . '</td>';
            $tableHtml .= '<td>' . ($struct['parent_id'] ?: '-') . '</td>';
            $tableHtml .= '<td>' . htmlspecialchars($struct['address'] ?: '-') . '</td>';
            $tableHtml .= '<td>
                            <a href="?edit=' . $struct['id'] . '" class="btn btn-sm btn-primary">Edit</a>
                            <a href="?delete=' . $struct['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this structure?\')">Delete</a>
                          </td>';
            $tableHtml .= '</tr>';
        }
    } else {
        $tableHtml = '<tr><td colspan="5" class="text-center">No structures found matching "' . htmlspecialchars($search) . '"</td></tr>';
    }
} else {
    $tableHtml = buildStructureTreeHtml($structures);
    if (empty($tableHtml)) {
        $tableHtml = '<tr><td colspan="5" class="text-center">No structures found.</td></tr>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Structures - Admin Panel</title>
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
                <a href="admin_structures.php" class="nav-link active"><i class="fas fa-building"></i> Structures</a>
                <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        
        <div class="col-md-10 p-4">
            <h2>Manage Structures</h2>
            
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

            <!-- Search Form -->
            <form method="GET" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search by name or code" 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Search</button></div>
                    <div class="col-md-2"><a href="admin_structures.php" class="btn btn-secondary w-100">Reset</a></div>
                </div>
            </form>

            <!-- Add / Edit Form -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5><?= $editMode ? 'Edit Structure' : 'Add New Structure' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <?php if($editMode): ?><input type="hidden" name="edit_id" value="<?= $editId ?>"><?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" name="code" class="form-control" placeholder="Code" 
                                       value="<?= htmlspecialchars($editCode) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="name" class="form-control" placeholder="Structure Name" 
                                       value="<?= htmlspecialchars($editName) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <select name="parent_id" class="form-select">
                                    <option value="">-- No Parent --</option>
                                    <?php foreach($allStructs as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $editParentId == $s['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success w-100">
                                    <?= $editMode ? 'Update Structure' : 'Add Structure' ?>
                                </button>
                                <?php if($editMode): ?>
                                    <a href="admin_structures.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-12">
                                <input type="text" name="address" class="form-control" 
                                       placeholder="Address (optional)" 
                                       value="<?= htmlspecialchars($editAddress) ?>">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Structures Table -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5><?= $isSearching ? 'Search Results' : 'Structures Hierarchy (Tree View)' ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Parent ID</th>
                                    <th>Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?= $tableHtml ?>
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