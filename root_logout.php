<?php
session_start();
session_destroy();
header('Location: root_login.php');
exit;
?>