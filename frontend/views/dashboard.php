<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/DashboardController.php';

$data = getDashboardData($pdo, $_SESSION['user_id']);
extract($data);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mail Management</title>
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
                    <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Home</a>
                    <?php if (!hasRole('admin')): ?>
                        <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                        <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send</a>
                        <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archivage</a>
                    <?php endif; ?>
                    <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profil</a>
                    <?php if (hasRole('admin')): ?>
                        <hr class="text-secondary">
                        <a href="admin_structures.php" class="nav-link"><i class="fas fa-building"></i> Structures</a>
                        <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                        <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                    <?php endif; ?>
                    <hr class="text-secondary">
                    <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Log out</a>
                </nav>
            </div>
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
                    <span class="badge bg-secondary">Role: <?= $_SESSION['role'] ?></span>
                </div>
                <!-- Statistics cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="stat-card bg-primary">
                            <i class="fas fa-envelope icon"></i>
                            <p>Total arrivals</p>
                            <h3><?= $totalArrivals ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-danger">
                            <i class="fas fa-exclamation-triangle icon"></i>
                            <p>Urgent unread</p>
                            <h3><?= $urgentCount ?></h3>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card bg-success">
                            <i class="fas fa-paper-plane icon"></i>
                            <p>Sent this month</p>
                            <h3><?= $sentCount ?></h3>
                        </div>
                    </div>
                </div>
                <!-- Notifications -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-bell"></i> Notifications
                    </div>
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($notifications as $notif): ?>
                                    <li class="list-group-item">
                                        <i class="fas fa-envelope-open-text text-success"></i>
                                        <strong><?= htmlspecialchars($notif['recipient_name']) ?></strong> opened your mail
                                        "<a href="view_mail.php?id=<?= $notif['mail_id'] ?>"><?= htmlspecialchars($notif['subject']) ?></a>"
                                        (Ref: <?= htmlspecialchars($notif['ref_number']) ?>)
                                        <span class="text-muted float-end"><?= date('d/m/Y H:i', strtotime($notif['action_date'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted mb-0">No new notifications.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Recent mails -->
                <div class="card">
                    <div class="card-header bg-white">
                        <strong><i class="fas fa-clock"></i> Last received mails</strong>
                    </div>
                    <div class="card-body">
                        <?php if (count($recentMails) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr><th>Ref</th><th>Subject</th><th>Sender</th><th>Priority</th><th>Date</th><th>Status</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentMails as $mail): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($mail['ref_number']) ?></td>
                                            <td><?= htmlspecialchars($mail['subject']) ?></td>
                                            <td><?= htmlspecialchars($mail['sender_name']) ?></td>
                                            <td><span class="badge bg-<?= $mail['priority']=='urgent'?'danger':($mail['priority']=='important'?'warning':'secondary') ?>"><?= $mail['priority'] ?></span></td>
                                            <td><?= date('d/m/Y H:i', strtotime($mail['created_at'])) ?></td>
                                            <td><?= $mail['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-info">Unread</span>' ?></td>
                                            <td><a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">Open</a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No recent mails.</p>
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