<?php
require_once __DIR__ . '/../models/Structure.php';

function getAllStructures($pdo, $search = '') {
    $sql = "SELECT * FROM structures";
    $params = [];
    if (!empty($search)) {
        $sql .= " WHERE name LIKE ? OR code LIKE ?";
        $params = ["%$search%", "%$search%"];
    }
    $sql .= " ORDER BY name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStructureForEdit($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM structures WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function addStructure($pdo, $data) {
    $stmt = $pdo->prepare("INSERT INTO structures (code, name, parent_id, address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([
        $data['code'],
        $data['name'],
        $data['parent_id'] ?? null,
        $data['address'] ?? null
    ]);
}

function updateStructure($pdo, $id, $data) {
    $stmt = $pdo->prepare("UPDATE structures SET code = ?, name = ?, parent_id = ?, address = ? WHERE id = ?");
    return $stmt->execute([
        $data['code'],
        $data['name'],
        $data['parent_id'] ?? null,
        $data['address'] ?? null,
        $id
    ]);
}

function deleteStructure($pdo, $id) {
    // Check children
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM structures WHERE parent_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        return "Cannot delete: this structure has child structures.";
    }
    // Check users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE structure_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        return "Cannot delete: users are assigned to this structure.";
    }
    $stmt = $pdo->prepare("DELETE FROM structures WHERE id = ?");
    return $stmt->execute([$id]) ? true : "Delete failed.";
}

function buildStructureTreeHtml($structures, $parentId = null, $level = 0) {
    $html = '';
    foreach ($structures as $struct) {
        if ($struct['parent_id'] == $parentId) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $icon = $level == 0 ? '🏢 ' : ($level == 1 ? '📁 ' : '📄 ');
            $html .= '<tr>';
            $html .= '<td>' . $indent . $icon . htmlspecialchars($struct['code']) . '</td>';
            $html .= '<td>' . htmlspecialchars($struct['name']) . '</td>';
            $html .= '<td>' . ($struct['parent_id'] ?: '-') . '</td>';
            $html .= '<td>' . htmlspecialchars($struct['address'] ?: '-') . '</td>';
            $html .= '<td>
                        <a href="?edit=' . $struct['id'] . '" class="btn btn-sm btn-primary">Edit</a>
                        <a href="?delete=' . $struct['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this structure?\')">Delete</a>
                       </div>';
            $html .= '</tr>';
            $html .= buildStructureTreeHtml($structures, $struct['id'], $level + 1);
        }
    }
    return $html;
}

// ========== MISSING FUNCTION – now added ==========
function getAllStructuresForDropdown($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM structures ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>