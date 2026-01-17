<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$skills = isset($input['skills']) ? $input['skills'] : [];

if (empty($skills)) {
    echo json_encode(['success' => false, 'message' => 'No skills provided']);
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Add each skill
    $stmt = $pdo->prepare('INSERT IGNORE INTO user_skills (user_id, skill_id, proficiency) VALUES (?, ?, ?)');
    
    foreach ($skills as $skill) {
        $skill_id = (int)$skill['id'];
        // Default proficiency is intermediate
        $stmt->execute([$user_id, $skill_id, 'intermediate']);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Skills added successfully'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error adding skills: ' . $e->getMessage()
    ]);
}