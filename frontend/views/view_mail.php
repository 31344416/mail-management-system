<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();

if (hasRole('admin')) {
    die("Administrators cannot view mails.");
}

$mailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($mailId <= 0) {
    die("Mail ID not specified.");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ViewMailController.php';

$mail = getMailDetails($pdo, $mailId);
if (!$mail) {
    die("Mail not found.");
}

// Check if current user is a recipient
$stmt = $pdo->prepare("SELECT id FROM mail_recipients WHERE mail_id = ? AND recipient_id = ?");
$stmt->execute([$mailId, $_SESSION['user_id']]);
$isRecipient = $stmt->fetchColumn() ? true : false;

if (isset($_GET['clear_notif']) && $_GET['clear_notif'] == 1 && $mail['sender_id'] == $_SESSION['user_id']) {
    $stmt = $pdo->prepare("DELETE FROM mail_tracking WHERE mail_id = ? AND user_id = ? AND action = 'opened_by_recipient'");
    $stmt->execute([$mailId, $_SESSION['user_id']]);
}

if ($isRecipient) {
    markAsReadAndArchive($pdo, $mailId, $_SESSION['user_id']);
    notifySenderOpened($pdo, $mailId, $_SESSION['user_id'], $_SESSION['full_name']);
} else {
    $trackModel = new Tracking($pdo);
    $trackModel->add($mailId, $_SESSION['user_id'], 'opened_by_top_manager', 'Top manager viewed this mail');
}

$replies = getMailReplies($pdo, $mailId);
$originalMail = getOriginalMail($pdo, $mailId);
// Note: $canReply is no longer used; we use !hasRole('admin') instead
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Mail - <?= htmlspecialchars($mail['subject'] ?? 'No Subject') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar (unchanged) -->
        <div class="col-md-2 bg-dark sidebar p-3">
            <h5 class="text-white text-center">MMS</h5>
            <hr class="text-secondary">
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <?php if (!hasRole('admin')): ?>
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archive</a>
                <?php endif; ?>
                <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
                <?php if (hasRole('admin')): ?>
                    <hr><a href="admin_structures.php" class="nav-link"><i class="fas fa-building"></i> Structures</a>
                    <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                    <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                <?php endif; ?>
                <hr><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <div class="col-md-10 p-4">
            <div class="card">
                <div class="card-header bg-primary text-white"><h4><?= htmlspecialchars($mail['subject'] ?? 'No Subject') ?></h4></div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p><strong>Reference:</strong> <?= htmlspecialchars($mail['ref_number'] ?? '') ?></p>
                            <p><strong>From:</strong> <?= htmlspecialchars($mail['sender_name'] ?? '') ?> (<?= htmlspecialchars($mail['sender_role'] ?? '') ?>)</p>
                            <p><strong>Date:</strong> <?= isset($mail['created_at']) ? date('d/m/Y H:i', strtotime($mail['created_at'])) : 'N/A' ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($mail['type'] ?? '')) ?></p>
                            <p><strong>Priority:</strong> <span class="badge bg-<?= ($mail['priority'] ?? '') == 'urgent' ? 'danger' : (($mail['priority'] ?? '') == 'important' ? 'warning' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($mail['priority'] ?? 'normal')) ?></span></p>
                        </div>
                    </div>

                    <?php if (!empty($mail['file_path']) && trim($mail['file_path']) !== ''): ?>
                        <p><strong>Attachment:</strong> <a href="../../backend/download.php?file=<?= urlencode(basename($mail['file_path'])) ?>" class="btn btn-sm btn-success" target="_blank"><i class="fas fa-download"></i> Download File</a></p>
                    <?php endif; ?>

                    <hr><p><strong>Content:</strong></p>
                    <div class="border p-4 bg-light" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($mail['content'] ?? '')) ?></div>

                    <?php if ($originalMail): ?>
                        <hr><div class="alert alert-info"><i class="fas fa-reply-all"></i> <strong>This is a reply to:</strong><br><a href="view_mail.php?id=<?= $originalMail['id'] ?>"><?= htmlspecialchars($originalMail['subject'] ?? '') ?></a> — <?= htmlspecialchars($originalMail['sender_name'] ?? '') ?> (<?= isset($originalMail['created_at']) ? date('d/m/Y', strtotime($originalMail['created_at'])) : '' ?>)</div>
                    <?php endif; ?>

                    <?php if (count($replies) > 0): ?>
                        <hr><div class="alert alert-success"><i class="fas fa-comments"></i> <strong>Replies (<?= count($replies) ?>)</strong><ul class="mt-2"><?php foreach ($replies as $reply): ?><li><a href="view_mail.php?id=<?= $reply['id'] ?>"><?= htmlspecialchars($reply['subject'] ?? '') ?></a> — <?= htmlspecialchars($reply['sender_name'] ?? '') ?>, <?= isset($reply['created_at']) ? date('d/m/Y H:i', strtotime($reply['created_at'])) : '' ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                </div>
                <div class="card-footer d-flex gap-2">
                    <a href="receive.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Inbox</a>
                    <?php if (!hasRole('admin')): ?>
                        <form method="GET" action="send.php" style="display: inline;">
                            <input type="hidden" name="reply_to" value="<?= $mailId ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-reply"></i> Reply
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>