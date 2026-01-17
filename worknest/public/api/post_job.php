<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Only clients can post jobs
if ($user_role !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Only clients can post jobs']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$title = isset($input['title']) ? trim($input['title']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$category = isset($input['category']) ? trim($input['category']) : '';
$budget = isset($input['budget']) ? (float)$input['budget'] : 0;
$deadline = isset($input['deadline']) ? $input['deadline'] : null;
$skills_required = isset($input['skills_required']) ? trim($input['skills_required']) : '';

// Validate input
if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Job title is required']);
    exit;
}

if (empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Job description is required']);
    exit;
}

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Category is required']);
    exit;
}

if ($budget <= 0) {
    echo json_encode(['success' => false, 'message' => 'Budget must be greater than 0']);
    exit;
}

try {
    // Insert job
    $stmt = $pdo->prepare('INSERT INTO jobs 
                           (client_id, title, description, category, budget, deadline, skills_required, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, "open", NOW())');
    $stmt->execute([$user_id, $title, $description, $category, $budget, $deadline, $skills_required]);
    
    $job_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Job posted successfully',
        'job_id' => $job_id
    ]);
    
} catch (PDOException $e) {
    error_log('Error posting job: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}