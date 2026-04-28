<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';

// ---- Existing functions for normal users (no manual archive) ----
function getArchivedMails($pdo, $userId, $search = '') {
    $mailModel = new Mail($pdo);
    return $mailModel->getReceivedForUser($userId, true, $search);
}

// ---- Global archive for Top Manager ----
function getGlobalMailsForTopManager($pdo, $userId, $search = '', $type = '', $priority = '', $status = '') {
    // Base query: all mails with sender info, aggregated recipients, and archive/read info
    $sql = "
        SELECT 
            m.*,
            u.full_name AS sender_name,
            u.role AS sender_role,
            (
                SELECT GROUP_CONCAT(
                    CONCAT(u2.full_name, ' (', u2.role, ')') 
                    SEPARATOR ', '
                )
                FROM mail_recipients mr2
                JOIN users u2 ON mr2.recipient_id = u2.id
                WHERE mr2.mail_id = m.id
            ) AS recipients_list,
            -- Global archive status: true if at least one recipient has opened it
            EXISTS(
                SELECT 1 FROM mail_recipients mr3
                WHERE mr3.mail_id = m.id AND mr3.is_read = 1
            ) AS is_archived_global,
            -- Date when this top manager opened the mail (if ever)
            (
                SELECT action_date FROM mail_tracking
                WHERE mail_id = m.id 
                  AND user_id = ? 
                  AND action = 'opened_by_recipient'
                LIMIT 1
            ) AS opened_by_you_date
        FROM mails m
        JOIN users u ON m.sender_id = u.id
        WHERE 1=1
    ";
    $params = [$userId]; // first param for the opened_by_you_date subquery

    // Search: ref_number, subject, content, sender name, or any recipient name
    if (!empty($search)) {
        $sql .= " AND (
            m.ref_number LIKE ? 
            OR m.subject LIKE ? 
            OR m.content LIKE ? 
            OR u.full_name LIKE ?
            OR EXISTS (
                SELECT 1 FROM mail_recipients mr4
                JOIN users u3 ON mr4.recipient_id = u3.id
                WHERE mr4.mail_id = m.id AND u3.full_name LIKE ?
            )
        )";
        $like = "%$search%";
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }

    // Filter by type
    if (!empty($type) && in_array($type, ['demande', 'orientation', 'rapport', 'reponse'])) {
        $sql .= " AND m.type = ?";
        $params[] = $type;
    }

    // Filter by priority
    if (!empty($priority) && in_array($priority, ['normal', 'important', 'urgent'])) {
        $sql .= " AND m.priority = ?";
        $params[] = $priority;
    }

    // Filter by archive status (unread = no recipient has opened it; archived = at least one opened)
    if ($status === 'unread') {
        $sql .= " AND NOT EXISTS(
            SELECT 1 FROM mail_recipients mr5
            WHERE mr5.mail_id = m.id AND mr5.is_read = 1
        )";
    } elseif ($status === 'archived') {
        $sql .= " AND EXISTS(
            SELECT 1 FROM mail_recipients mr6
            WHERE mr6.mail_id = m.id AND mr6.is_read = 1
        )";
    }

    // Order by newest first
    $sql .= " ORDER BY m.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>