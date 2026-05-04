<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();

if (hasRole('admin')) {
    die("Administrators cannot view conversations.");
}

$conversationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($conversationId <= 0) {
    die("Conversation ID not specified.");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/models/Mail.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/models/Tracking.php';

$mailModel = new Mail($pdo);
$trackModel = new Tracking($pdo);

$original = $mailModel->findById($conversationId);
if (!$original) {
    die("Conversation not found.");
}

$replies = $mailModel->getReplies($conversationId);

function canReplyToMail($mail) {
    $role = $_SESSION['role'] ?? '';
    $type = $mail['type'] ?? '';
    if ($role == 'top_manager') return true;
    if ($role == 'middle_manager') return in_array($type, ['demande', 'rapport', 'orientation']);
    if ($role == 'end_manager') return in_array($type, ['rapport', 'reponse']);
    return false;
}

function notifySenderOpened($pdo, $mailId, $recipientId, $recipientName) {
    $mailModel = new Mail($pdo);
    $senderId = $mailModel->getSenderId($mailId);
    if ($senderId && $senderId != $recipientId) {
        $trackModel = new Tracking($pdo);
        $comment = "Your mail was opened by " . $recipientName;
        $trackModel->add($mailId, $senderId, 'opened_by_recipient', $comment);
    }
}

$allMails = array_merge([$original], $replies);
foreach ($allMails as $m) {
    $stmt = $pdo->prepare("SELECT id FROM mail_recipients WHERE mail_id = ? AND recipient_id = ?");
    $stmt->execute([$m['id'], $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $mailModel->markAsReadAndArchive($m['id'], $_SESSION['user_id']);
        if ($m['id'] == $original['id']) {
            notifySenderOpened($pdo, $m['id'], $_SESSION['user_id'], $_SESSION['full_name']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversation - <?= htmlspecialchars($original['subject'] ?? 'No Subject') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 bg-dark sidebar p-3">
            <h5 class="text-white text-center">MMS</h5><hr>
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Home</a>
                <?php if (!hasRole('admin')): ?><a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archive</a><?php endif; ?>
                <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a><hr>
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><i class="fas fa-comments"></i> Conversation: <?= htmlspecialchars($original['subject'] ?? 'No Subject') ?></h2>
                <a href="archive.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Archive</a>
            </div>

            <!-- Original mail -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white"><strong>Original Mail</strong> <span class="float-end"><?= date('d/m/Y H:i', strtotime($original['created_at'])) ?></span></div>
                <div class="card-body">
                    <p><strong>From:</strong> <?= htmlspecialchars($original['sender_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($original['sender_role'] ?? 'N/A') ?>)</p>
                    <p><strong>Reference:</strong> <?= htmlspecialchars($original['ref_number'] ?? '') ?></p>
                    <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($original['type'] ?? '')) ?> | <strong>Priority:</strong> <span class="badge bg-<?= ($original['priority'] ?? '') == 'urgent' ? 'danger' : (($original['priority'] ?? '') == 'important' ? 'warning' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($original['priority'] ?? 'normal')) ?></span></p>
                    <?php if (!empty($original['file_path']) && trim($original['file_path']) !== ''): ?>
                        <p><strong>Attachment:</strong> <a href="../../backend/download.php?file=<?= urlencode(basename($original['file_path'])) ?>" class="btn btn-sm btn-success" target="_blank">Download</a></p>
                    <?php endif; ?>
                    <hr><div class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($original['content'] ?? '')) ?></div>
                    <?php if (canReplyToMail($original) && !hasRole('admin')): ?><div class="mt-3"><a href="send.php?reply_to=<?= $original['id'] ?>" class="btn btn-sm btn-primary">Reply to original</a></div><?php endif; ?>
                </div>
            </div>

            <!-- Replies -->
            <?php if (count($replies) > 0): ?>
                <h4><i class="fas fa-reply-all"></i> Replies (<?= count($replies) ?>)</h4>
                <?php foreach ($replies as $reply): ?>
                    <div class="card mb-3 border-secondary">
                        <div class="card-header bg-secondary text-white"><strong>Reply from <?= htmlspecialchars($reply['sender_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($reply['sender_role'] ?? 'N/A') ?>)</strong> <span class="float-end"><?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></span></div>
                        <div class="card-body">
                            <p><strong>Reference:</strong> <?= htmlspecialchars($reply['ref_number'] ?? '') ?></p>
                            <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($reply['type'] ?? '')) ?> | <strong>Priority:</strong> <span class="badge bg-<?= ($reply['priority'] ?? '') == 'urgent' ? 'danger' : (($reply['priority'] ?? '') == 'important' ? 'warning' : 'secondary') ?>"><?= ucfirst(htmlspecialchars($reply['priority'] ?? 'normal')) ?></span></p>
                            <?php if (!empty($reply['file_path']) && trim($reply['file_path']) !== ''): ?>
                                <p><strong>Attachment:</strong> <a href="../../backend/download.php?file=<?= urlencode(basename($reply['file_path'])) ?>" class="btn btn-sm btn-success" target="_blank">Download</a></p>
                            <?php endif; ?>
                            <hr><div class="bg-light p-3 rounded" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($reply['content'] ?? '')) ?></div>
                            <?php if (canReplyToMail($reply) && !hasRole('admin')): ?><div class="mt-3"><a href="send.php?reply_to=<?= $reply['id'] ?>" class="btn btn-sm btn-primary">Reply to this</a></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">No replies yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>