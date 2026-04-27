<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';
require_once __DIR__ . '/../models/Structure.php';

function generateUniqueReferenceNumber($pdo, $structureCode) {
    $year = date('Y');
    $pattern = "%-$structureCode-$year";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE ref_number LIKE ?");
    $stmt->execute([$pattern]);
    $count = $stmt->fetchColumn();
    $next = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    $candidate = "$next-$structureCode-$year";
    // Ensure uniqueness (just in case of race condition)
    $check = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE ref_number = ?");
    $check->execute([$candidate]);
    if ($check->fetchColumn() == 0) return $candidate;
    // fallback: timestamp
    return date('YmdHis') . "-$structureCode-$year";
}

function handleSendMail($pdo, $userId, $allowedTypes, $replyToId) {
    $error = '';
    $success = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST['subject']);
        $content = trim($_POST['content']);
        $type = $_POST['type'];
        $priority = $_POST['priority'];
        $recipient_id = $_POST['recipient_id'];
        $target_structure_id = $_POST['target_structure_id']; // division
        $parentId = ($replyToId > 0) ? $replyToId : null;
        
        // Validate
        if (empty($target_structure_id)) {
            $error = "Please select a destination division.";
        } elseif (!in_array($type, $allowedTypes)) {
            $error = "You are not allowed to send this type of mail.";
        } elseif (empty($subject) || empty($content) || empty($recipient_id)) {
            $error = "Please fill in all fields and select a recipient.";
        } else {
            // Get target structure code
            $structModel = new Structure($pdo);
            $targetStruct = $structModel->getById($target_structure_id);
            if (!$targetStruct) {
                $error = "Invalid target division.";
            } else {
                $targetCode = $targetStruct['code'];
                $refNumber = generateUniqueReferenceNumber($pdo, $targetCode);
                
                // File upload
                $filePath = null;
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/mail_management/uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = time() . '_' . basename($_FILES['attachment']['name']);
                    $targetFile = $uploadDir . $fileName;
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                        $filePath = 'uploads/' . $fileName;
                    } else {
                        $error = "File upload failed.";
                    }
                }
                
                if (!$error) {
                    $mailModel = new Mail($pdo);
                    $trackModel = new Tracking($pdo);
                    
                    // Insert mail
                    $mailData = [
                        'ref_number' => $refNumber,
                        'subject' => $subject,
                        'content' => $content,
                        'type' => $type,
                        'priority' => $priority,
                        'sender_id' => $userId,
                        'file_path' => $filePath,
                        'parent_id' => $parentId
                    ];
                    if ($mailModel->create($mailData)) {
                        $mailId = $pdo->lastInsertId();
                        
                        // 1. Add recipient (normal inbox, not archived)
                        $mailModel->addRecipient($mailId, $recipient_id);
                        
                        // 2. Add archived copy for the sender
                        $mailModel->addArchivedCopyForSender($mailId, $userId);
                        
                        // 3. Tracking: sent by sender
                        $trackModel->add($mailId, $userId, 'sent');
                        // 4. Tracking: received by recipient
                        $trackModel->add($mailId, $recipient_id, 'received');
                        
                        // If this is a reply, archive the original mail for replier and original sender
                        if ($replyToId > 0) {
                            // Archive original for replier
                            $mailModel->archiveForUser($replyToId, $userId);
                            $trackModel->add($replyToId, $userId, 'archived_after_reply');
                            
                            // Archive original for the original sender (if different)
                            $origSenderId = $mailModel->getSenderId($replyToId);
                            if ($origSenderId && $origSenderId != $userId) {
                                $mailModel->archiveForUser($replyToId, $origSenderId);
                                $trackModel->add($replyToId, $origSenderId, 'archived_by_reply');
                            }
                        }
                        
                        $success = "Mail sent successfully. Reference: $refNumber";
                        // Reset form (by redirecting to avoid resubmission)
                        header("Location: send.php?success=1");
                        exit;
                    } else {
                        $error = "Failed to insert mail.";
                    }
                }
            }
        }
    }
    
    return ['error' => $error, 'success' => $success];
}

function getRecipients($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE is_active = 1 AND id != ? AND role != 'admin' ORDER BY full_name");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllStructuresForDropdown($pdo) {
    $stmt = $pdo->query("SELECT id, code, name FROM structures ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>