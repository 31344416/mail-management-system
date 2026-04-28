<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();

// Admin is not allowed in archive page
if (hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ArchiveController.php';

$userId = $_SESSION['user_id'];
$isTopManager = hasRole('top_manager');
$error = '';
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$priority = $_GET['priority'] ?? '';
$status = $_GET['status'] ?? '';

// For normal users (middle/end managers) handle archive/restore actions
if (!$isTopManager) {
    // Manual archive from receive.php
    if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
        $mailId = (int)$_GET['id'];
        $mailModel = new Mail($pdo);
        if ($mailModel->archiveForUser($mailId, $userId)) {
            header('Location: receive.php?msg=archived');
            exit;
        }
    }

    // Restore action
    if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
        $mailId = (int)$_GET['restore'];
        $mailModel = new Mail($pdo);
        if ($mailModel->restoreForUser($mailId, $userId)) {
            header('Location: archive.php?msg=restored');
            exit;
        }
    }

    // Permanent delete (only for own archived mails)
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $mailId = (int)$_GET['delete'];
        $pdo->prepare("DELETE FROM mail_tracking WHERE mail_id = ? AND user_id = ?")->execute([$mailId, $userId]);
        $pdo->prepare("DELETE FROM mail_recipients WHERE mail_id = ? AND recipient_id = ?")->execute([$mailId, $userId]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients WHERE mail_id = ?");
        $stmt->execute([$mailId]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("DELETE FROM mails WHERE id = ?")->execute([$mailId]);
        }
        header('Location: archive.php?msg=deleted');
        exit;
    }
}

// Retrieve data depending on role
if ($isTopManager) {
    $mails = getGlobalMailsForTopManager($pdo, $userId, $search, $type, $priority, $status);
} else {
    $mails = getArchivedMails($pdo, $userId, $search);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isTopManager ? 'Global Archive' : 'Archive' ?> - Mail Management System</title>
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
                <?php if (!$isTopManager): ?>
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link active"><i class="fas fa-archive"></i> Archive</a>
                <?php else: ?>
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link active"><i class="fas fa-globe"></i> Global Archive</a>
                <?php endif; ?>
                <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <h2><i class="fas <?= $isTopManager ? 'fa-globe' : 'fa-archive' ?>"></i> 
                <?= $isTopManager ? 'Global Archive (All System Emails)' : 'My Archived Mails' ?>
            </h2>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Action completed successfully.</div>
            <?php endif; ?>

            <!-- Search & Filters (only for Top Manager) -->
            <?php if ($isTopManager): ?>
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by ref, subject, sender, recipient..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="demande" <?= $type=='demande' ? 'selected' : '' ?>>Demande</option>
                        <option value="orientation" <?= $type=='orientation' ? 'selected' : '' ?>>Orientation</option>
                        <option value="rapport" <?= $type=='rapport' ? 'selected' : '' ?>>Rapport</option>
                        <option value="reponse" <?= $type=='reponse' ? 'selected' : '' ?>>Reponse</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <option value="normal" <?= $priority=='normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="important" <?= $priority=='important' ? 'selected' : '' ?>>Important</option>
                        <option value="urgent" <?= $priority=='urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="unread" <?= $status=='unread' ? 'selected' : '' ?>>Unread (no one opened)</option>
                        <option value="archived" <?= $status=='archived' ? 'selected' : '' ?>>Archived (opened by someone)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <a href="archive.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>
            <?php else: ?>
            <!-- Simple search for normal users -->
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
            <?php endif; ?>

            <!-- Mails Table -->
            <?php if (count($mails) > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Ref</th>
                            <th>Subject</th>
                            <th>Sender</th>
                            <?php if ($isTopManager): ?>
                            <th>Recipients</th>
                            <?php endif; ?>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Date</th>
                            <th>Archive Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mails as $mail): ?>
                        <tr>
                            <td><?= htmlspecialchars($mail['ref_number']) ?></td>
                            <td><?= htmlspecialchars($mail['subject']) ?></td>
                            <td><?= htmlspecialchars($mail['sender_name']) ?> 
                                (<?= htmlspecialchars($mail['sender_role']) ?>)
                            </td>
                            <?php if ($isTopManager): ?>
                            <td><?= nl2br(htmlspecialchars($mail['recipients_list'] ?? '-')) ?></td>
                            <?php endif; ?>
                            <td><span class="badge bg-secondary"><?= ucfirst($mail['type']) ?></span></td>
                            <td>
                                <span class="badge bg-<?= $mail['priority']=='urgent'?'danger':($mail['priority']=='important'?'warning':'secondary') ?>">
                                    <?= ucfirst($mail['priority']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($mail['created_at'])) ?></td>
                            <td>
                                <?php if ($mail['is_archived_global'] ?? false): ?>
                                    <span class="badge bg-success">Archived (opened by someone)</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Unread (no one opened)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isTopManager): ?>
                                    <a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                    <?php if (!empty($mail['opened_by_you_date'])): ?>
                                        <br><small class="text-muted">Opened: <?= date('d/m/Y H:i', strtotime($mail['opened_by_you_date'])) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                    <a href="?restore=<?= $mail['id'] ?>" class="btn btn-sm btn-success">Restore</a>
                                    <a href="?delete=<?= $mail['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Permanently delete?')">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No mails found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>