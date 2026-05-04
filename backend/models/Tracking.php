<?php
class Tracking {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function add($mailId, $userId, $action, $comments = null) {
        $stmt = $this->pdo->prepare("INSERT INTO mail_tracking (mail_id, user_id, action, comments) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$mailId, $userId, $action, $comments]);
    }
    
    public function getNotifications($userId) {
        // Correction : jointure correcte pour récupérer le nom du destinataire qui a ouvert
        $stmt = $this->pdo->prepare("
            SELECT t.*, m.ref_number, m.subject,
                   (SELECT full_name FROM users WHERE id = mr.recipient_id) as recipient_name
            FROM mail_tracking t
            JOIN mails m ON t.mail_id = m.id
            JOIN mail_recipients mr ON mr.mail_id = m.id
            WHERE t.user_id = ? AND t.action = 'opened_by_recipient'
            GROUP BY t.id
            ORDER BY t.action_date DESC LIMIT 10
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function markNotificationAsRead($trackingId, $userId) {
    $stmt = $this->pdo->prepare("UPDATE mail_tracking SET is_read_notification = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$trackingId, $userId]);
}

public function getTodayNotifications($userId) {
    $stmt = $this->pdo->prepare("
        SELECT t.id, t.mail_id, m.subject, m.ref_number, t.action_date, 
               (SELECT full_name FROM users WHERE id = mr.recipient_id) as recipient_name
        FROM mail_tracking t
        JOIN mails m ON t.mail_id = m.id
        JOIN mail_recipients mr ON mr.mail_id = m.id
        WHERE t.user_id = ? 
          AND t.action = 'opened_by_recipient' 
          AND t.is_read_notification = 0
          AND DATE(t.action_date) = CURDATE()
        GROUP BY t.id
        ORDER BY t.action_date DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}
?>