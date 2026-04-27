<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
if (hasRole('admin')) { die("Administrators cannot send mails."); }

require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/SendController.php';

$userId = $_SESSION['user_id'];
$allowedTypes = getSendTypesForRole();
if (empty($allowedTypes)) { die("You are not authorized to send any mail type."); }

$replyToId = isset($_GET['reply_to']) ? intval($_GET['reply_to']) : 0;
$preSubject = '';
$preContent = '';
if ($replyToId) {
    $mailModel = new Mail($pdo);
    $orig = $mailModel->findById($replyToId);
    if ($orig) {
        $preSubject = 'Re: ' . $orig['subject'];
        $preContent = "\n\n----- Original Message -----\n" . $orig['content'];
    }
}
$result = handleSendMail($pdo, $userId, $allowedTypes, $replyToId);
$error = $result['error'];
$success = $result['success'] ?? '';
$recipients = getRecipients($pdo, $userId);
$allStructures = getAllStructuresForDropdown($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Mail - Mail Management</title>
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
                    <a href="send.php" class="nav-link active"><i class="fas fa-paper-plane"></i> Send</a>
                    <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archivage</a>
                    <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profil</a>
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Log out</a>
                </nav>
            </div>
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-paper-plane"></i> Send New Mail</h2>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if (isset($_GET['success'])): ?><div class="alert alert-success">Mail sent successfully!</div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Destination Division <span class="text-danger">*</span></label>
                        <select name="target_structure_id" class="form-select" required>
                            <option value="">-- Select a division --</option>
                            <?php foreach ($allStructures as $struct): ?>
                                <option value="<?= $struct['id'] ?>"><?= htmlspecialchars($struct['name']) ?> (<?= $struct['code'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">The reference number will include this division's code.</small>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select" required>
                                <option value="">-- Select type --</option>
                                <?php foreach ($allowedTypes as $t): ?>
                                    <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-select" required>
                                <option value="normal">Normal</option>
                                <option value="important">Important</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference (auto)</label>
                            <input type="text" class="form-control" placeholder="Will be generated" disabled>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" required value="<?= htmlspecialchars($preSubject) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content</label>
                        <textarea name="content" rows="6" class="form-control" required><?= htmlspecialchars($preContent) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachment (optional)</label>
                        <input type="file" name="attachment" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <select name="recipient_id" class="form-select" required>
                            <option value="">-- Select recipient --</option>
                            <?php foreach ($recipients as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?> (<?= $user['role'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Mail</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>