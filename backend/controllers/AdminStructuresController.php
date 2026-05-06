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
    // Check duplicate under same parent
    $check = $pdo->prepare("SELECT id FROM structures WHERE parent_id = ? AND code = ?");
    $check->execute([$data['parent_id'] ?? null, $data['code']]);
    if ($check->fetch()) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO structures (code, name, parent_id, address) VALUES (?, ?, ?, ?)");
    return $stmt->execute([
        $data['code'],
        $data['name'],
        $data['parent_id'] ?? null,
        $data['address'] ?? null
    ]);
}

function updateStructure($pdo, $id, $data) {
    // Check duplicate under same parent (excluding current id)
    $check = $pdo->prepare("SELECT id FROM structures WHERE parent_id = ? AND code = ? AND id != ?");
    $check->execute([$data['parent_id'] ?? null, $data['code'], $id]);
    if ($check->fetch()) {
        return false;
    }
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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM structures WHERE parent_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        return "Cannot delete: this structure has child structures.";
    }
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
    // Create parent name map
    $parentNames = [];
    foreach ($structures as $s) {
        $parentNames[$s['id']] = $s['name'];
    }
    foreach ($structures as $struct) {
        if ($struct['parent_id'] == $parentId) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $icon = $level == 0 ? '🏢 ' : ($level == 1 ? '📁 ' : '📄 ');
            $parentName = $struct['parent_id'] ? ($parentNames[$struct['parent_id']] ?? '-') : '-';
            $html .= '<tr>';
            $html .= '<td>' . $indent . $icon . htmlspecialchars($struct['code']) . '</td>';
            $html .= '<td>' . htmlspecialchars($struct['name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($parentName) . '</td>';
            $html .= '<td>' . htmlspecialchars($struct['address'] ?? '-') . '</td>';
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

function getAllStructuresForDropdown($pdo) {
    $structures = getAllStructures($pdo);
    if (empty($structures)) return [];

    // Build a map of id -> parent_id
    $parentMap = [];
    $nameMap = [];
    foreach ($structures as $s) {
        $parentMap[$s['id']] = $s['parent_id'];
        $nameMap[$s['id']] = $s['name'];
    }

    // For each structure, build the full path by walking up the parent chain
    $dropdown = [];
    foreach ($structures as $s) {
        $id = $s['id'];
        $path = [];
        $current = $id;
        // Walk up to root (max 100 levels to avoid infinite loop)
        $max = 100;
        while ($current !== null && $max-- > 0) {
            $path[] = $nameMap[$current];
            $current = $parentMap[$current];
        }
        $path = array_reverse($path);
        $fullPath = implode(' → ', $path);
        $dropdown[] = ['id' => $id, 'name' => $fullPath];
    }

    // Sort by the full path string
    usort($dropdown, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    return $dropdown;
}
?>