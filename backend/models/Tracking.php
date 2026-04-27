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
        $stmt = $this->pdo->prepare("SELECT t.*, m.ref_number, m.subject, u.full_name as recipient_name 
                                     FROM mail_tracking t
                                     JOIN mails m ON t.mail_id = m.id
                                     JOIN users u ON u.id = (SELECT recipient_id FROM mail_recipients WHERE mail_id = m.id LIMIT 1)
                                     WHERE t.user_id = ? AND t.action = 'opened_by_recipient'
                                     ORDER BY t.action_date DESC LIMIT 10");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>