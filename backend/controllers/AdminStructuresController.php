<?php
require_once __DIR__ . '/../models/Structure.php';

function getAllStructures($pdo, $search = '') {
    $structModel = new Structure($pdo);
    return $structModel->getAll($search);
}

function getStructureById($pdo, $id) {
    $structModel = new Structure($pdo);
    return $structModel->getById($id);
}

function addStructure($pdo, $data) {
    $structModel = new Structure($pdo);
    return $structModel->create($data);
}

function updateStructure($pdo, $id, $data) {
    $structModel = new Structure($pdo);
    return $structModel->update($id, $data);
}

function deleteStructure($pdo, $id) {
    $structModel = new Structure($pdo);
    // Check if has children or users
    if ($structModel->hasChildren($id)) {
        return "Cannot delete: structure has child structures.";
    }
    if ($structModel->hasUsers($id)) {
        return "Cannot delete: there are users assigned to this structure.";
    }
    return $structModel->delete($id) ? true : "Delete failed.";
}

function buildStructureTree($pdo, $search = '') {
    $structModel = new Structure($pdo);
    $structures = $structModel->getAll($search);
    return $structModel->buildTree($structures);
}
?>