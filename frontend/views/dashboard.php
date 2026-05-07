<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/auth.php';
requireLogin();
requirePasswordChange();
require_once $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/controllers/DashboardController.php';

$data = getDashboardData($pdo, $_SESSION['user_id']);
extract($data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Mail Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if (hasRole('admin')): ?>
    <style>
        .admin-dashboard-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('/mail_management/frontend/assets/images/login.webp');
            background-repeat: no-repeat;
            background-position: right center;
            background-size: 100% auto;
            opacity: 0.4;
            z-index: 0;
            pointer-events: none;
        }
        
        .admin-content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            border-radius: 20px;
            padding: 3rem 4rem;
            text-align: center;
            box-shadow: 0 20px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(26, 188, 156, 0.3);
        }
        
        .admin-link {
            display: inline-block;
            margin: 0.5rem 0;
            color: #181a19;
            text-decoration: none;
            transition: 0.2s;
        }
        .admin-link:hover {
            color: #080808;
            text-decoration: underline;
        }
    </style>
    <?php endif; ?>
</head>
<body>
<?php if (hasRole('admin')): ?>
    <div class="admin-dashboard-bg"></div>
<?php endif; ?>

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-md-2 bg-dark sidebar vh-100 p-3">
            <h5 class="text-white text-center">MMS</h5>
            <hr class="text-secondary">
            <nav class="nav flex-column">
                <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
                <?php if (!hasRole('admin')): ?>
                    <a href="receive.php" class="nav-link"><i class="fas fa-inbox"></i> My Arrivals</a>
                    <a href="send.php" class="nav-link"><i class="fas fa-paper-plane"></i> Send Mail</a>
                    <a href="archive.php" class="nav-link"><i class="fas fa-archive"></i> Archive</a>
                <?php endif; ?>
                <a href="profile.php" class="nav-link"><i class="fas fa-user"></i> My Profile</a>
                <?php if (hasRole('admin')): ?>
                    <hr class="text-secondary">
                    <a href="admin_structures.php" class="nav-link"><i class="fas fa-building"></i> Structures</a>
                    <a href="admin_users.php" class="nav-link"><i class="fas fa-users"></i> Employees</a>
                    <a href="admin_privileges.php" class="nav-link"><i class="fas fa-key"></i> Privileges</a>
                <?php endif; ?>
                <hr class="text-secondary">
                <a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <?php if (!hasRole('admin')): ?>

                <div class="p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
                        <span class="badge bg-secondary">Role: <?= str_replace('_', ' ', ucfirst($_SESSION['role'])) ?></span>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <a href="receive.php" class="text-decoration-none">
                                <div class="stat-card bg-primary text-white">
                                    <i class="fas fa-envelope icon"></i>
                                    <p>Total Arrivals</p>
                                    <h3><?= $totalArrivals ?></h3>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="receive.php?priority=urgent" class="text-decoration-none">
                                <div class="stat-card bg-danger text-white">
                                    <i class="fas fa-exclamation-triangle icon"></i>
                                    <p>Urgent Unread</p>
                                    <h3><?= $urgentCount ?></h3>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card bg-success text-white">
                                <i class="fas fa-paper-plane icon"></i>
                                <p>Sent This Month</p>
                                <h3><?= $sentCount ?></h3>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-bell"></i> Notifications (Today)
                        </div>
                        <div class="card-body" id="notificationsList">
                            <?php if (count($notifications) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($notifications as $notif): ?>
                                        <li class="list-group-item notification-item" data-id="<?= $notif['id'] ?>">
                                            <i class="fas fa-envelope-open-text text-success"></i>
                                            <strong><?= htmlspecialchars($notif['recipient_name'] ?? 'Someone') ?></strong> opened your mail 
                                            "<strong><?= htmlspecialchars($notif['subject'] ?? '') ?></strong>"
                                            <span class="text-muted float-end"><?= date('H:i', strtotime($notif['action_date'])) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted mb-0">No new notifications today.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <strong><i class="fas fa-clock"></i> Recent Mails</strong>
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
                                                <td><span class="badge bg-<?= $mail['priority']=='urgent'?'danger':($mail['priority']=='important'?'warning':'secondary') ?>"><?= ucfirst($mail['priority']) ?></span></td>
                                                <td><?= date('d/m/Y H:i', strtotime($mail['created_at'])) ?></td>
                                                <td><?= $mail['is_read'] ? '<span class="badge bg-success">Read</span>' : '<span class="badge bg-info">Unread</span>' ?></td>
                                                <td><a href="view_mail.php?id=<?= $mail['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent mails received.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>

                <!-- Admin Centered Welcome Card with clickable links -->
                <div class="admin-content-wrapper">
                    <div class="welcome-card">
                        <i class="fas fa-user-shield fa-4x text-primary mb-3"></i>
                        <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h1>
                        <span class="role-badge badge bg-primary">Administrator</span>
                        <p class="mt-3">
                            <i class="fas fa-building"></i> 
                            <a href="admin_structures.php" class="admin-link">Manage structures</a><br>
                            <i class="fas fa-users"></i> 
                            <a href="admin_users.php" class="admin-link">Manage employees</a><br>
                            <i class="fas fa-key"></i> 
                            <a href="admin_privileges.php" class="admin-link">Manage privileges</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>

<script>
document.querySelectorAll('.notification-item').forEach(function(item) {
    const notificationId = item.dataset.id;
    if (!notificationId) return;
    
    item.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        fetch('/mail_management/backend/api/markNotificationRead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                item.remove();
                const container = document.getElementById('notificationsList');
                if (container && container.querySelectorAll('.notification-item').length === 0) {
                    container.innerHTML = '<p class="text-muted mb-0">No new notifications today.</p>';
                }
            }
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>
</body>
</html>