<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';

function getMailDetails($pdo, $mailId) {
    $mailModel = new Mail($pdo);
    return $mailModel->findById($mailId);
}

function markAsReadAndArchive($pdo, $mailId, $userId) {
    $mailModel = new Mail($pdo);
    return $mailModel->markAsReadAndArchive($mailId, $userId);
}

function notifySenderOpened($pdo, $mailId, $recipientId, $recipientName) {
    $mailModel = new Mail($pdo);
    $senderId = $mailModel->getSenderId($mailId);
    if ($senderId && $senderId != $recipientId) {
        $trackModel = new Tracking($pdo);
        $comment = "Your mail was opened by " . $recipientName;
        $trackModel->add($mailId, $senderId, 'opened_by_recipient', $comment);
    }
}

function getMailReplies($pdo, $mailId) {
    $mailModel = new Mail($pdo);
    return $mailModel->getReplies($mailId);
}

function getOriginalMail($pdo, $mailId) {
    $mailModel = new Mail($pdo);
    return $mailModel->getOriginal($mailId);
}
?>