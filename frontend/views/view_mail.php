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

// If the current user is the sender and the clear_notif parameter is present, delete corresponding notifications
if (isset($_GET['clear_notif']) && $_GET['clear_notif'] == 1 && $mail['sender_id'] == $_SESSION['user_id']) {
    $stmt = $pdo->prepare("DELETE FROM mail_tracking 
                           WHERE mail_id = ? AND user_id = ? AND action = 'opened_by_recipient'");
    $stmt->execute([$mailId, $_SESSION['user_id']]);
}

// Mark as read and archive for current user
markAsReadAndArchive($pdo, $mailId, $_SESSION['user_id']);

// Notify sender that the mail was opened
notifySenderOpened($pdo, $mailId, $_SESSION['user_id'], $_SESSION['full_name']);

$replies = getMailReplies($pdo, $mailId);
$originalMail = getOriginalMail($pdo, $mailId);
$canReply = canReplyToType($mail['type'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Mail - <?= htmlspecialchars($mail['subject'] ?? 'No Subject') ?></title>
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
                    <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archive</a>
                    <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><?= htmlspecialchars($mail['subject'] ?? 'No Subject') ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <p><strong>Reference:</strong> <?= htmlspecialchars($mail['ref_number'] ?? '') ?></p>
                                <p><strong>From:</strong> <?= htmlspecialchars($mail['sender_name'] ?? '') ?> 
                                   (<?= htmlspecialchars($mail['sender_role'] ?? '') ?>)</p>
                                <p><strong>Date:</strong> 
                                    <?= isset($mail['created_at']) ? date('d/m/Y H:i', strtotime($mail['created_at'])) : 'N/A' ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($mail['type'] ?? '')) ?></p>
                                <p><strong>Priority:</strong> 
                                    <span class="badge bg-<?= ($mail['priority'] ?? '') == 'urgent' ? 'danger' : 
                                        (($mail['priority'] ?? '') == 'important' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst(htmlspecialchars($mail['priority'] ?? 'normal')) ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <?php if (!empty($mail['file_path'])): ?>
                            <p>
                                <strong>Attachment:</strong> 
                                <a href="/mail_management/backend/download.php?file=<?= urlencode(basename($mail['file_path'])) ?>" 
                                   class="btn btn-sm btn-success" target="_blank">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                            </p>
                        <?php endif; ?>

                        <hr>
                        <p><strong>Content:</strong></p>
                        <div class="border p-4 bg-light" style="white-space: pre-wrap; font-size: 1.05rem;">
                            <?= nl2br(htmlspecialchars($mail['content'] ?? '')) ?>
                        </div>

                        <?php if ($originalMail): ?>
                            <hr>
                            <div class="alert alert-info">
                                <i class="fas fa-reply-all"></i> 
                                <strong>This is a reply to:</strong><br>
                                <a href="view_mail.php?id=<?= $originalMail['id'] ?>">
                                    <?= htmlspecialchars($originalMail['subject'] ?? '') ?>
                                </a> 
                                — <?= htmlspecialchars($originalMail['sender_name'] ?? '') ?> 
                                (<?= isset($originalMail['created_at']) ? date('d/m/Y', strtotime($originalMail['created_at'])) : '' ?>)
                            </div>
                        <?php endif; ?>

                        <?php if (count($replies) > 0): ?>
                            <hr>
                            <div class="alert alert-success">
                                <i class="fas fa-comments"></i> 
                                <strong>Replies (<?= count($replies) ?>)</strong>
                                <ul class="mt-2">
                                    <?php foreach ($replies as $reply): ?>
                                        <li>
                                            <a href="view_mail.php?id=<?= $reply['id'] ?>">
                                                <?= htmlspecialchars($reply['subject'] ?? '') ?>
                                            </a> 
                                            — <?= htmlspecialchars($reply['sender_name'] ?? '') ?>, 
                                            <?= isset($reply['created_at']) ? date('d/m/Y H:i', strtotime($reply['created_at'])) : '' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer d-flex gap-2">
                        <a href="receive.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Inbox
                        </a>
                        <?php if ($canReply): ?>
                            <a href="send.php?reply_to=<?= $mailId ?>" class="btn btn-primary">
                                <i class="fas fa-reply"></i> Reply
                            </a>
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
