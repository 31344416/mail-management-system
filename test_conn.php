<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/backend/config.php';
echo "If you see this, config.php is included and \$pdo exists.";
?>