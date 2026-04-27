<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';

function handleArchiveAction($pdo, $userId) {
    if (isset($_GET['action']) && $_GET['action'] == 'archive' && isset($_GET['id'])) {
        $mailId = intval($_GET['id']);
        $mailModel = new Mail($pdo);
        if ($mailModel->archiveForUser($mailId, $userId)) {
            $trackModel = new Tracking($pdo);
            $trackModel->add($mailId, $userId, 'archived');
            header('Location: archive.php?msg=archived');
            exit;
        }
    }
}

function getArchivedMails($pdo, $userId, $search = '') {
    $mailModel = new Mail($pdo);
    return $mailModel->getReceivedForUser($userId, true, $search);
}
?>