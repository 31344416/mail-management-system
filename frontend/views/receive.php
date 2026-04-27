<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
if (hasRole('admin')) { header('Location: dashboard.php'); exit; }
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/ReceiveController.php';

$search = $_GET['search'] ?? '';
$priority = $_GET['priority'] ?? '';
$mails = getReceivedMails($pdo, $_SESSION['user_id'], $search, $priority);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Arrivals - Mail Management</title>
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
                    <a href="receive.php" class="nav-link active"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send</a>
                    <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archivage</a>
                    <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profil</a>
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Log out</a>
                </nav>
            </div>
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <h2><i class="fas fa-inbox"></i> Incoming Mails</h2>
                <!-- Filter form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control" placeholder="Search by reference, subject or sender" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="priority" class="form-select">
                            <option value="">All priorities</option>
                            <option value="urgent" <?= $priority == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                            <option value="important" <?= $priority == 'important' ? 'selected' : '' ?>>Important</option>
                            <option value="normal" <?= $priority == 'normal' ? 'selected' : '' ?>>Normal</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="receive.php" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </form>
                <!-- Mails table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-dark">
                            <tr><th>Ref</th><th>Subject</th><th>Sender</th><th>Type</th><th>Priority</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (count($mails) > 0): ?>
                                <?php foreach ($mails as $mail): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mail['ref_number']) ?></td>
                                        <td><?= htmlspecialchars($mail['subject']) ?></td>
                                        <td><?= htmlspecialchars($mail['sender_name']) ?> (<?= $mail['sender_role'] ?>)</small></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($mail['type']) ?></span></td>
                                        <td><span class="badge bg-<?= $mail['priority'] == 'urgent' ? 'danger' : ($mail['priority'] == 'important' ? 'warning' : 'secondary') ?>"><?= $mail['priority'] ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($mail['created_at'])) ?></td>
                                        <td><?= $mail['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-info">Unread</span>' ?></td>
                                        <td>
                                            <a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">Open</a>
                                            <a href="archive.php?action=archive&id=<?= $mail['id'] ?>" class="btn btn-sm btn-warning btn-archive">Archive</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8" class="text-center">No incoming mails found.<?php if (hasRole('admin')): ?> Administrators cannot receive mails.<?php endif; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/main.js"></script>
</body>
</html>