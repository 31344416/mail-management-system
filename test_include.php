<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$result = include 'backend/config.php';
echo "Include result: " . ($result ? "true" : "false");
?>