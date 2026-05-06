<?php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function findByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findById($id) {
        $stmt = $this->pdo->prepare("
            SELECT u.*, s.name as structure_name 
            FROM users u 
            LEFT JOIN structures s ON u.structure_id = s.id 
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getById($id) {
        return $this->findById($id);
    }
    
    public function updateLastLogin($id) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getAllNonAdmin($search = '') {
        $sql = "SELECT u.*, s.name as structure_name,
                (SELECT COUNT(*) FROM mails WHERE sender_id = u.id) as sent_count,
                (SELECT COUNT(*) FROM mail_recipients WHERE recipient_id = u.id) as received_count
                FROM users u 
                LEFT JOIN structures s ON u.structure_id = s.id 
                WHERE u.role != 'admin'";
        $params = [];

        if (!empty($search)) {
            // Split search into individual words
            $words = preg_split('/\s+/', trim($search));
            $fullNameConditions = [];
            foreach ($words as $word) {
                $fullNameConditions[] = "LOWER(u.full_name) LIKE LOWER(?)";
                $params[] = "%$word%";
            }
            $fullNameSql = implode(' OR ', $fullNameConditions);

            // Match username, email, or any word in full_name
            $sql .= " AND (LOWER(u.username) LIKE LOWER(?) 
                        OR LOWER(u.email) LIKE LOWER(?) 
                        OR ($fullNameSql))";
            $params[] = "%$search%";  // for username
            $params[] = "%$search%";  // for email
            // full_name word parameters already added
        }

        $sql .= " ORDER BY SUBSTRING_INDEX(u.full_name, ' ', -1) ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function create($data, $passwordHash) {
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, email, full_name, structure_id, role, is_active, password_changed) VALUES (?, ?, ?, ?, ?, ?, 1, 0)");
        return $stmt->execute([
            $data['username'], $passwordHash, $data['email'],
            $data['full_name'], $data['structure_id'], $data['role']
        ]);
    }
    
    public function update($id, $data) {
        $stmt = $this->pdo->prepare("UPDATE users SET username=?, full_name=?, email=?, structure_id=?, role=? WHERE id = ? AND role != 'admin'");
        return $stmt->execute([
            $data['username'], $data['full_name'], $data['email'],
            $data['structure_id'], $data['role'], $id
        ]);
    }
    
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        return $stmt->execute([$id]);
    }
    
    public function toggleActive($id) {
        $stmt = $this->pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role != 'admin'");
        return $stmt->execute([$id]);
    }
    
    public function resetPassword($id, $newPasswordHash) {
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role != 'admin'");
        return $stmt->execute([$newPasswordHash, $id]);
    }
}
?>