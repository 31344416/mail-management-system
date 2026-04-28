<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();

if (hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ArchiveController.php';

$userId = $_SESSION['user_id'];
handleArchiveAction($pdo, $userId);

$search = $_GET['search'] ?? '';

// Handle Restore
if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
    $mailId = (int)$_GET['restore'];
    $mailModel = new Mail($pdo);
    if ($mailModel->restoreForUser($mailId, $userId)) {
        header('Location: archive.php?msg=restored');
        exit;
    }
}

// Handle Permanent Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $mailId = (int)$_GET['delete'];
    
    // Delete tracking records for this user
    $pdo->prepare("DELETE FROM mail_tracking WHERE mail_id = ? AND user_id = ?")
         ->execute([$mailId, $userId]);
    
    // Delete recipient record for this user
    $pdo->prepare("DELETE FROM mail_recipients WHERE mail_id = ? AND recipient_id = ?")
         ->execute([$mailId, $userId]);
    
    // Check if any other recipients remain
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients WHERE mail_id = ?");
    $stmt->execute([$mailId]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // No more recipients → delete the main mail
        $pdo->prepare("DELETE FROM mails WHERE id = ?")->execute([$mailId]);
    }
    
    header('Location: archive.php?msg=deleted');
    exit;
}

$archivedMails = getArchivedMails($pdo, $userId, $search);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - Mail Management System</title>
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
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link active"><i class="fas fa-archive"></i> Archive</a>
                    <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-archive"></i> Archived Mails</h2>

                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-success">
                        Action completed successfully.
                    </div>
                <?php endif; ?>

                <!-- Search Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-8">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search archived mails..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                    <div class="col-md-2">
                        <a href="archive.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>

                <?php if (count($archivedMails) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Ref</th>
                                    <th>Subject</th>
                                    <th>Sender</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($archivedMails as $mail): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mail['ref_number']) ?></td>
                                        <td><?= htmlspecialchars($mail['subject']) ?></td>
                                        <td><?= htmlspecialchars($mail['sender_name']) ?> 
                                            (<?= htmlspecialchars($mail['sender_role']) ?>)</td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($mail['type']) ?></span></td>
                                        <td>
                                            <span class="badge bg-<?= $mail['priority']=='urgent'?'danger':($mail['priority']=='important'?'warning':'secondary') ?>">
                                                <?= ucfirst($mail['priority']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($mail['created_at'])) ?></td>
                                        <td>
                                            <a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                            <a href="?restore=<?= $mail['id'] ?>" class="btn btn-sm btn-success">Restore</a>
                                            <a href="?delete=<?= $mail['id'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to permanently delete this mail?')">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No archived mails found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>