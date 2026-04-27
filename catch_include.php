<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$configPath = $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';

// Capture any error that occurs during include
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    include $configPath;
    echo "Include succeeded.<br>";
    if (isset($pdo)) {
        echo "PDO is set.<br>";
        // Test a simple query
        $stmt = $pdo->query("SELECT 1");
        echo "Database query works.<br>";
    } else {
        echo "PDO is NOT set after include.<br>";
    }
} catch (Throwable $e) {
    echo "Error during include: " . $e->getMessage() . "<br>";
    echo "In file: " . $e->getFile() . " on line " . $e->getLine() . "<br>";
}
?>