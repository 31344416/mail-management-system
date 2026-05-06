<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';

function getDescendantStructureIds($pdo, $parentId) {
    $ids = [];
    $queue = [$parentId];
    while (!empty($queue)) {
        $current = array_shift($queue);
        $ids[] = $current;
        $stmt = $pdo->prepare("SELECT id FROM structures WHERE parent_id = ?");
        $stmt->execute([$current]);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $queue = array_merge($queue, $children);
    }
    return $ids;
}

function getGroupedArchivedMails($pdo, $userId, $search = '') {
    // unchanged from your code (except I'll keep it as is)
    $sql = "
        SELECT DISTINCT 
            CASE 
                WHEN m.parent_id IS NULL THEN m.id
                ELSE m.parent_id
            END AS conversation_id
        FROM mail_recipients mr
        JOIN mails m ON mr.mail_id = m.id
        WHERE mr.recipient_id = ? AND mr.is_archived = 1
    ";
    $params = [$userId];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conversationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($conversationIds)) {
        return [];
    }
    
    $placeholders = implode(',', array_fill(0, count($conversationIds), '?'));
    $sql = "
        SELECT 
            m.id,
            m.ref_number,
            m.subject,
            m.content,
            m.type,
            m.priority,
            m.created_at,
            m.parent_id,
            u.full_name AS sender_name,
            u.role AS sender_role,
            (SELECT COUNT(*) FROM mails WHERE parent_id = m.id) AS reply_count,
            (SELECT GROUP_CONCAT(CONCAT(u2.full_name, ' (', u2.role, ')') SEPARATOR ', ')
             FROM mail_recipients mr2
             JOIN users u2 ON mr2.recipient_id = u2.id
             WHERE mr2.mail_id = m.id) AS recipients_list
        FROM mails m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id IN ($placeholders) OR m.parent_id IN ($placeholders)
    ";
    $params = array_merge($conversationIds, $conversationIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allMails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $conversations = [];
    foreach ($allMails as $mail) {
        $origId = $mail['parent_id'] ?? $mail['id'];
        if (!isset($conversations[$origId])) {
            $conversations[$origId] = $mail;
            $conversations[$origId]['reply_count'] = 0;
            $conversations[$origId]['replies'] = [];
        }
        if ($mail['id'] != $origId) {
            $conversations[$origId]['reply_count']++;
            $conversations[$origId]['replies'][] = $mail;
        }
    }
    
    $result = array_values($conversations);
    if (!empty($search)) {
        $result = array_filter($result, function($conv) use ($search) {
            return stripos($conv['ref_number'], $search) !== false ||
                   stripos($conv['subject'], $search) !== false ||
                   stripos($conv['sender_name'], $search) !== false;
        });
    }
    usort($result, function($a, $b) {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });
    return array_values($result);
}

function getConversationsForTopManager($pdo, $userId, $search = '', $type = '', $priority = '', $status = '') {
    // Get user's structure_id
    $stmt = $pdo->prepare("SELECT structure_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userStructId = $stmt->fetchColumn();
    
    // Determine allowed target structures
    $allowedStructIds = [];
    $useRestriction = false;
    if ($userStructId != 1) { // assuming 1 is root (SONELGAZ)
        $allowedStructIds = getDescendantStructureIds($pdo, $userStructId);
        $useRestriction = !empty($allowedStructIds);
    }
    
    $sql = "
        SELECT 
            m.id, m.sender_id, m.ref_number, m.subject, m.content, m.type, m.priority, m.created_at, m.parent_id,
            u.full_name AS sender_name, u.role AS sender_role,
            (SELECT COUNT(*) FROM mails WHERE parent_id = m.id) AS reply_count,
            (SELECT GROUP_CONCAT(CONCAT(u2.full_name, ' (', u2.role, ')') SEPARATOR ', ')
             FROM mail_recipients mr2
             JOIN users u2 ON mr2.recipient_id = u2.id
             WHERE mr2.mail_id = m.id) AS recipients_list,
            EXISTS(SELECT 1 FROM mail_recipients mr3 WHERE mr3.mail_id = m.id AND mr3.is_read = 1) AS is_archived_global
        FROM mails m
        JOIN users u ON m.sender_id = u.id
        WHERE m.parent_id IS NULL
    ";
    $params = [];
    
    if ($useRestriction) {
        $placeholders = implode(',', array_fill(0, count($allowedStructIds), '?'));
        $sql .= " AND m.target_structure_id IN ($placeholders)";
        $params = array_merge($params, $allowedStructIds);
    }
    
    if (!empty($search)) {
        $sql .= " AND (m.ref_number LIKE ? OR m.subject LIKE ? OR u.full_name LIKE ?)";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like;
    }
    if (!empty($type) && in_array($type, ['demande','orientation','rapport','reponse'])) {
        $sql .= " AND m.type = ?";
        $params[] = $type;
    }
    if (!empty($priority) && in_array($priority, ['normal','important','urgent'])) {
        $sql .= " AND m.priority = ?";
        $params[] = $priority;
    }
    if ($status === 'unread') {
        $sql .= " AND NOT EXISTS(SELECT 1 FROM mail_recipients mr3 WHERE mr3.mail_id = m.id AND mr3.is_read = 1)";
    } elseif ($status === 'archived') {
        $sql .= " AND EXISTS(SELECT 1 FROM mail_recipients mr3 WHERE mr3.mail_id = m.id AND mr3.is_read = 1)";
    }
    
    $sql .= " ORDER BY m.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getArchivedMails($pdo, $userId, $search = '') {
    $mailModel = new Mail($pdo);
    return $mailModel->getReceivedForUser($userId, true, $search);
}
?>