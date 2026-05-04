<?php
class Mail {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        $sql = "INSERT INTO mails (ref_number, subject, content, type, priority, sender_id, file_path, parent_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'sent')";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['ref_number'], $data['subject'], $data['content'],
            $data['type'], $data['priority'], $data['sender_id'],
            $data['file_path'], $data['parent_id']
        ]);
    }
    
    public function findById($id) {
        $stmt = $this->pdo->prepare("SELECT m.*, u.full_name as sender_name, u.role as sender_role
                                     FROM mails m 
                                     JOIN users u ON m.sender_id = u.id 
                                     WHERE m.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getReceivedForUser($userId, $archived = false, $search = '', $priority = '') {
        $sql = "SELECT m.*, u.full_name as sender_name, u.role as sender_role, mr.is_read, mr.is_archived 
                FROM mail_recipients mr
                JOIN mails m ON mr.mail_id = m.id
                JOIN users u ON m.sender_id = u.id
                WHERE mr.recipient_id = ? AND mr.is_archived = ?";
        $params = [$userId, $archived ? 1 : 0];
        if ($priority) {
            $sql .= " AND m.priority = ?";
            $params[] = $priority;
        }
        if ($search) {
            $sql .= " AND (m.ref_number LIKE ? OR m.subject LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY m.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all archived mails for a user (both as sender and as recipient)
     * Useful for a unified archive view.
     */
    public function getArchivedForUser($userId, $search = '') {
        $sql = "SELECT DISTINCT m.*, u.full_name as sender_name, u.role as sender_role,
                       mr.is_read, mr.is_archived
                FROM mails m
                JOIN users u ON m.sender_id = u.id
                LEFT JOIN mail_recipients mr ON m.id = mr.mail_id AND mr.recipient_id = ?
                WHERE (mr.id IS NOT NULL AND mr.is_archived = 1)
                   OR (m.sender_id = ? AND EXISTS (
                         SELECT 1 FROM mail_recipients mr2 
                         WHERE mr2.mail_id = m.id AND mr2.recipient_id = m.sender_id AND mr2.is_archived = 1
                       ))
                ";
        $params = [$userId, $userId];
        if ($search) {
            $sql .= " AND (m.ref_number LIKE ? OR m.subject LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= " ORDER BY m.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function markAsReadAndArchive($mailId, $userId) {
        $stmt = $this->pdo->prepare("UPDATE mail_recipients SET is_read = 1, is_archived = 1 WHERE mail_id = ? AND recipient_id = ?");
        return $stmt->execute([$mailId, $userId]);
    }
    
    public function archiveForUser($mailId, $userId) {
        $stmt = $this->pdo->prepare("UPDATE mail_recipients SET is_archived = 1 WHERE mail_id = ? AND recipient_id = ?");
        return $stmt->execute([$mailId, $userId]);
    }
    
    public function restoreForUser($mailId, $userId) {
        $stmt = $this->pdo->prepare("UPDATE mail_recipients SET is_archived = 0 WHERE mail_id = ? AND recipient_id = ?");
        return $stmt->execute([$mailId, $userId]);
    }
    
    public function addRecipient($mailId, $recipientId) {
        $stmt = $this->pdo->prepare("INSERT INTO mail_recipients (mail_id, recipient_id, is_read, is_archived) VALUES (?, ?, 0, 0)");
        return $stmt->execute([$mailId, $recipientId]);
    }
    
    public function addArchivedCopyForSender($mailId, $senderId) {
        $stmt = $this->pdo->prepare("INSERT INTO mail_recipients (mail_id, recipient_id, is_read, is_archived) VALUES (?, ?, 1, 1)");
        return $stmt->execute([$mailId, $senderId]);
    }
    
    public function getReplies($mailId) {
    $stmt = $this->pdo->prepare("SELECT m.*, u.full_name as sender_name, u.role as sender_role
                                 FROM mails m 
                                 JOIN users u ON m.sender_id = u.id 
                                 WHERE m.parent_id = ? 
                                 ORDER BY m.created_at ASC");
    $stmt->execute([$mailId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    
    public function getOriginal($mailId) {
        $stmt = $this->pdo->prepare("SELECT m.*, u.full_name as sender_name 
                                     FROM mails m 
                                     JOIN users u ON m.sender_id = u.id 
                                     WHERE m.id = ?");
        $stmt->execute([$mailId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getSenderId($mailId) {
        $stmt = $this->pdo->prepare("SELECT sender_id FROM mails WHERE id = ?");
        $stmt->execute([$mailId]);
        return $stmt->fetchColumn();
    }
    
    public function generateReferenceNumber($structureCode, $year) {
        $pattern = "%-$structureCode-$year";
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM mails WHERE ref_number LIKE ?");
        $stmt->execute([$pattern]);
        $count = $stmt->fetchColumn();
        $next = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $candidate = "$next-$structureCode-$year";
        // Ensure uniqueness (just in case)
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM mails WHERE ref_number = ?");
        $check->execute([$candidate]);
        if ($check->fetchColumn() == 0) return $candidate;
        // fallback: timestamp
        return date('YmdHis') . "-$structureCode-$year";
    }
}
?>