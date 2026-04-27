<?php
class Structure {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAll($search = '') {
        $sql = "SELECT * FROM structures";
        if ($search) { $sql .= " WHERE name LIKE ? OR code LIKE ?"; $stmt = $this->pdo->prepare($sql); $stmt->execute(["%$search%", "%$search%"]); }
        else { $stmt = $this->pdo->prepare($sql); $stmt->execute(); }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM structures WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO structures (code, name, parent_id, address) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$data['code'], $data['name'], $data['parent_id'] ?? null, $data['address'] ?? null]);
    }
    public function update($id, $data) {
        $stmt = $this->pdo->prepare("UPDATE structures SET code=?, name=?, parent_id=?, address=? WHERE id=?");
        return $stmt->execute([$data['code'], $data['name'], $data['parent_id'] ?? null, $data['address'] ?? null, $id]);
    }
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM structures WHERE id = ?");
        return $stmt->execute([$id]);
    }
    public function hasChildren($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM structures WHERE parent_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }
    public function hasUsers($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE structure_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }
    public function buildTree($structures, $parentId = null, $level = 0) {
        $html = '';
        foreach ($structures as $s) {
            if ($s['parent_id'] == $parentId) {
                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                $icon = $level==0 ? '🏢 ' : ($level==1 ? '📁 ' : '📄 ');
                $html .= '<tr>';
                $html .= '<td>' . $indent . $icon . htmlspecialchars($s['code']) . '</td>';
                $html .= '<td>' . htmlspecialchars($s['name']) . '</td>';
                $html .= '<td>' . ($s['parent_id'] ?: '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($s['address'] ?: '-') . '</td>';
                $html .= '<td><a href="?edit=' . $s['id'] . '" class="btn btn-sm btn-primary">Edit</a> <a href="?delete=' . $s['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete?\')">Delete</a></td>';
                $html .= '</tr>';
                $html .= $this->buildTree($structures, $s['id'], $level+1);
            }
        }
        return $html;
    }
}
?>