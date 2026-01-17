<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    // Get all skills grouped by category
    $stmt = $pdo->query('SELECT id, name, category FROM skills ORDER BY category, name');
    $all_skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group skills by category
    $skills_by_category = [];
    foreach ($all_skills as $skill) {
        $category = $skill['category'] ?: 'Other';
        if (!isset($skills_by_category[$category])) {
            $skills_by_category[$category] = [];
        }
        $skills_by_category[$category][] = [
            'id' => $skill['id'],
            'name' => $skill['name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'skills' => $skills_by_category
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}