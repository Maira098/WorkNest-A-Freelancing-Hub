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

$reviewee_id = isset($input['reviewee_id']) ? (int)$input['reviewee_id'] : 0;
$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;
$rating = isset($input['rating']) ? (int)$input['rating'] : 0;
$comment = isset($input['comment']) ? trim($input['comment']) : '';

// Validate input
if ($reviewee_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid freelancer']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Please write a comment']);
    exit;
}

try {
    // Check if review already exists
    $check_stmt = $pdo->prepare('SELECT id FROM reviews 
                                  WHERE reviewer_id = ? AND reviewee_id = ? AND job_id = ?');
    $check_stmt->execute([$user_id, $reviewee_id, $job_id]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this freelancer for this project']);
        exit;
    }

    // Insert review
    $stmt = $pdo->prepare('INSERT INTO reviews 
                           (reviewer_id, reviewee_id, job_id, rating, comment, created_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$user_id, $reviewee_id, $job_id, $rating, $comment]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);
    
} catch (PDOException $e) {
    error_log('Error submitting review: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
EOFAPI
echo "Created give_review.php API endpoint"