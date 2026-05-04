<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';

function getDashboardData($pdo, $userId) {
    // Total arrivals (non-archived)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients WHERE recipient_id = ? AND is_archived = 0");
    $stmt->execute([$userId]);
    $totalArrivals = $stmt->fetchColumn();
    
    // Urgent unread
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_recipients mr 
                           JOIN mails m ON mr.mail_id = m.id 
                           WHERE mr.recipient_id = ? AND m.priority = 'urgent' AND mr.is_read = 0 AND mr.is_archived = 0");
    $stmt->execute([$userId]);
    $urgentCount = $stmt->fetchColumn();
    
    // Sent this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE sender_id = ? AND MONTH(created_at) = MONTH(NOW())");
    $stmt->execute([$userId]);
    $sentCount = $stmt->fetchColumn();
    
    // Notifications (TODAY only, unread)
    $trackModel = new Tracking($pdo);
    $notifications = $trackModel->getTodayNotifications($userId);
    
    // Recent mails (last 10)
    $sql = "SELECT m.*, u.full_name as sender_name, mr.is_read 
            FROM mail_recipients mr 
            JOIN mails m ON mr.mail_id = m.id 
            JOIN users u ON m.sender_id = u.id 
            WHERE mr.recipient_id = ? AND mr.is_archived = 0 
            ORDER BY m.created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $recentMails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return compact('totalArrivals', 'urgentCount', 'sentCount', 'notifications', 'recentMails');
}
?>