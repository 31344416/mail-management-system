<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: root_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h1>
    <p>Role: <?= $_SESSION['role'] ?></p>
    <a href="root_logout.php">Logout</a>
</body>
</html>
