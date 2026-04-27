<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/backend/config.php';
echo "<h1>Config test</h1>";
echo "If you see this, config.php was included.<br>";
echo "PDO object exists: " . (isset($pdo) ? "YES" : "NO") . "<br>";
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "Number of users: $count";
}
?>