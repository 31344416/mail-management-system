<?php
require_once __DIR__ . '/../models/Mail.php';

function getReceivedMails($pdo, $userId, $search = '', $priority = '') {
    $mailModel = new Mail($pdo);
    return $mailModel->getReceivedForUser($userId, false, $search, $priority);
}
?>