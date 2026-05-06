<?php
require_once __DIR__ . '/../models/Mail.php';
require_once __DIR__ . '/../models/Tracking.php';
require_once __DIR__ . '/../models/Structure.php';

function generateUniqueReferenceNumber($pdo, $structureCode) {
    $year = date('Y');
    $maxAttempts = 3;
    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE ref_number LIKE ? FOR UPDATE");
            $stmt->execute(["%-$structureCode-$year"]);
            $count = $stmt->fetchColumn();
            $next = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            $candidate = "$next-$structureCode-$year";
            
            $check = $pdo->prepare("SELECT id FROM mails WHERE ref_number = ? FOR UPDATE");
            $check->execute([$candidate]);
            if (!$check->fetch()) {
                $pdo->commit();
                return $candidate;
            }
            $pdo->rollBack();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    return date('YmdHis') . "-$structureCode-$year";
}

function handleSendMail($pdo, $userId, $allowedTypes, $replyToId) {
    $error = '';
    $success = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            $error = "Erreur de sécurité (CSRF). Veuillez recharger la page.";
            return ['error' => $error, 'success' => ''];
        }
        
        $subject = trim($_POST['subject']);
        $content = trim($_POST['content']);
        $type = $_POST['type'];
        $priority = $_POST['priority'];
        $recipient_id = $_POST['recipient_id'];
        $target_structure_id = $_POST['target_structure_id'];
        $parentId = ($replyToId > 0) ? $replyToId : null;
        
        if (empty($target_structure_id)) {
            $error = "Veuillez sélectionner une division destinataire.";
        } elseif (!in_array($type, $allowedTypes)) {
            $error = "Vous n'avez pas le droit d'envoyer ce type de courrier.";
        } elseif (empty($subject) || empty($content) || empty($recipient_id)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else {
            $structModel = new Structure($pdo);
            $targetStruct = $structModel->getById($target_structure_id);
            if (!$targetStruct) {
                $error = "Division invalide.";
            } else {
                $targetCode = $targetStruct['code'];
                $refNumber = generateUniqueReferenceNumber($pdo, $targetCode);
                
                $filePath = null;
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'png', 'zip'];
                    $originalName = $_FILES['attachment']['name'];
                    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) {
                        $error = "Type de fichier non autorisé (PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP uniquement).";
                    } else {
                        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/mail_management/uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        $safeName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                        $targetFile = $uploadDir . $safeName;
                        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
                            $filePath = 'uploads/' . $safeName;
                        } else {
                            $error = "Échec du téléchargement du fichier.";
                        }
                    }
                }
                
                if (!$error) {
                    $mailModel = new Mail($pdo);
                    $trackModel = new Tracking($pdo);
                    
                    $mailData = [
                        'ref_number' => $refNumber,
                        'subject' => $subject,
                        'content' => $content,
                        'type' => $type,
                        'priority' => $priority,
                        'sender_id' => $userId,
                        'file_path' => $filePath,
                        'parent_id' => $parentId,
                        'target_structure_id' => $target_structure_id
                    ];
                    if ($mailModel->create($mailData)) {
                        $mailId = $pdo->lastInsertId();
                        $mailModel->addRecipient($mailId, $recipient_id);
                        $mailModel->addArchivedCopyForSender($mailId, $userId);
                        $trackModel->add($mailId, $userId, 'sent');
                        $trackModel->add($mailId, $recipient_id, 'received');
                        
                        if ($replyToId > 0) {
                            $mailModel->archiveForUser($replyToId, $userId);
                            $trackModel->add($replyToId, $userId, 'archived_after_reply');
                            $origSenderId = $mailModel->getSenderId($replyToId);
                            if ($origSenderId && $origSenderId != $userId) {
                                $mailModel->archiveForUser($replyToId, $origSenderId);
                                $trackModel->add($replyToId, $origSenderId, 'archived_by_reply');
                            }
                        }
                        
                        $_SESSION['flash_success'] = "Mail envoyé avec succès. Réf : $refNumber";
                        header("Location: send.php?success=1");
                        exit;
                    } else {
                        $error = "Erreur lors de l'enregistrement.";
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

/**
 * Returns all structures with a dash‑separated hierarchical code path (excluding root "SONA").
 * Compatible with PHP 5.2+ (no closures, safe array access).
 */
function getAllStructuresForDropdown($pdo) {
    $stmt = $pdo->query("SELECT id, code, name, parent_id FROM structures ORDER BY name");
    $structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($structures)) return array();

    // Build lookup maps
    $parentMap = array();
    $codeMap = array();
    $nameMap = array();
    foreach ($structures as $s) {
        $parentMap[$s['id']] = $s['parent_id'];
        $codeMap[$s['id']] = $s['code'];
        $nameMap[$s['id']] = $s['name'];
    }

    $result = array();
    foreach ($structures as $s) {
        $id = $s['id'];
        $pathCodes = array();
        $current = $id;
        $max = 100;
        // Walk up the parent chain
        while ($current !== null && $max-- > 0) {
            // Ensure the current ID exists in our maps
            if (!isset($codeMap[$current])) break;
            $code = $codeMap[$current];
            // Exclude root (SONELGAZ code 'SONA') – change if your root code is different
            if ($code !== 'SONA') {
                array_unshift($pathCodes, $code);
            }
            $current = isset($parentMap[$current]) ? $parentMap[$current] : null;
        }
        $codePath = implode('-', $pathCodes);
        // If no path (e.g., root itself), just show the name
        if ($codePath === '') {
            $display = htmlspecialchars($nameMap[$id]);
        } else {
            $display = $codePath . ' (' . htmlspecialchars($nameMap[$id]) . ')';
        }
        $result[] = array('id' => $id, 'name' => $display);
    }

    // Manual sort by name (PHP 5.2 compatible – no closures)
    usort($result, 'compareStructureNames');
    return $result;
}

// Helper comparison function for usort
function compareStructureNames($a, $b) {
    return strcmp($a['name'], $b['name']);
}
?>