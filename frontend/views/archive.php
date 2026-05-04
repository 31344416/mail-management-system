<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
requirePasswordChange();

// Admin not allowed
if (hasRole('admin')) {
    header('Location: dashboard.php');
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ArchiveController.php';

$userId = $_SESSION['user_id'];
$isTopManager = hasRole('top_manager');
$error = '';
$success = '';
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$priority = $_GET['priority'] ?? '';
$status = $_GET['status'] ?? '';

// Handle DELETE for sender (mistake correction) - now available for all managers
if (isset($_GET['delete_mail']) && is_numeric($_GET['delete_mail'])) {
    $mailId = (int)$_GET['delete_mail'];
    // Allow any manager to delete (not just sender)
    $pdo->prepare("DELETE FROM mail_tracking WHERE mail_id = ?")->execute([$mailId]);
    $pdo->prepare("DELETE FROM mail_recipients WHERE mail_id = ?")->execute([$mailId]);
    $pdo->prepare("DELETE FROM mails WHERE id = ?")->execute([$mailId]);
    $success = "Mail deleted successfully.";
}

// For normal users (middle/end) handle restore/delete actions
if (!$isTopManager) {
    if (isset($_GET['action']) && $_GET['action'] === 'archive' && isset($_GET['id'])) {
        $mailId = (int)$_GET['id'];
        $mailModel = new Mail($pdo);
        if ($mailModel->archiveForUser($mailId, $userId)) {
            header('Location: receive.php?msg=archived');
            exit;
        }
    }
    if (isset($_GET['restore']) && is_numeric($_GET['restore'])) {
        $mailId = (int)$_GET['restore'];
        $mailModel = new Mail($pdo);
        if ($mailModel->restoreForUser($mailId, $userId)) {
            header('Location: archive.php?msg=restored');
            exit;
        }
    }
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

// Retrieve grouped conversations based on role
if ($isTopManager) {
    $conversations = getConversationsForTopManager($pdo, $userId, $search, $type, $priority, $status);
    // Only show conversations that have been archived (opened by someone)
    $conversations = array_values(array_filter($conversations, fn($c) => $c['is_archived_global'] == true));
} else {
    $conversations = getGroupedArchivedMails($pdo, $userId, $search);
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
            <h5 class="text-white text-center">MMS</h5><hr>
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
                <hr><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <h2><i class="fas <?= $isTopManager ? 'fa-globe' : 'fa-archive' ?>"></i> 
                <?= $isTopManager ? 'Global Archive (Conversations)' : 'My Archived Conversations' ?>
            </h2>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Action completed successfully.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- Search & filters -->
            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by reference, subject, sender..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <?php if ($isTopManager): ?>
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
                        <option value="archived" <?= $status=='archived' ? 'selected' : '' ?>>Archived (opened)</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-<?= $isTopManager ? '1' : '2' ?>">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-1">
                    <a href="archive.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>

            <?php if (count($conversations) > 0): ?>
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
                            <th>Replies</th>
                            <?php if ($isTopManager): ?>
                            <th>Archive Status</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conv): ?>
                        <tr>
                            <td><?= htmlspecialchars($conv['ref_number']) ?></td>
                            <td><?= htmlspecialchars($conv['subject']) ?></td>
                            <td><?= htmlspecialchars($conv['sender_name']) ?> (<?= htmlspecialchars($conv['sender_role']) ?>)</small></td>
                            <?php if ($isTopManager): ?>
                            <td><?= nl2br(htmlspecialchars($conv['recipients_list'] ?? '-')) ?></td>
                            <?php endif; ?>
                            <td><span class="badge bg-secondary"><?= ucfirst($conv['type']) ?></span></td>
                            <td><span class="badge bg-<?= $conv['priority']=='urgent'?'danger':($conv['priority']=='important'?'warning':'secondary') ?>"><?= ucfirst($conv['priority']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($conv['created_at'])) ?></td>
                            <td>
                                <?php if ($conv['reply_count'] > 0): ?>
                                    <span class="badge bg-info"><?= $conv['reply_count'] ?> response(s)</span>
                                    <a href="view_conversation.php?id=<?= $conv['id'] ?>" class="btn btn-sm btn-outline-primary mt-1">View conversation</a>
                                <?php else: ?>
                                    <span class="text-muted">No responses</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isTopManager): ?>
                            <td>
                                <?php if ($conv['is_archived_global'] ?? false): ?>
                                    <span class="badge bg-success">Archived (opened)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Inbox (not opened)</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="view_mail.php?id=<?= $conv['id'] ?>" class="btn btn-sm btn-primary">View</a>
                                <!-- DELETE BUTTON FOR ALL MANAGERS (removed sender check) -->
                                <a href="?delete_mail=<?= $conv['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this conversation permanently? This action cannot be undone.')">Delete</a>
                                <?php if (!$isTopManager): ?>
                                    <a href="?restore=<?= $conv['id'] ?>" class="btn btn-sm btn-success">Restore</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="alert alert-info">No archived conversations found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>