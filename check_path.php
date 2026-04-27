<?php
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
$absPath = $_SERVER['DOCUMENT_ROOT'] . '/mail_management/backend/config.php';
echo "Absolute path: $absPath<br>";
echo "File exists: " . (file_exists($absPath) ? "YES" : "NO") . "<br>";
?>