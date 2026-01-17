<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

// Notification helper function
function createNotification($pdo, $user_id, $type, $title, $message, $link = null, $related_id = null, $related_type = null, $sender_id = null) {
    try {
        $stmt = $pdo->prepare('INSERT INTO notifications 
                               (user_id, type, title, message, link, related_id, related_type, sender_id, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        return $stmt->execute([$user_id, $type, $title, $message, $link, $related_id, $related_type, $sender_id]);
    } catch (PDOException $e) {
        error_log('Error creating notification: ' . $e->getMessage());
        return false;
    }
}

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Only freelancers can submit proposals
if ($user_role !== 'freelancer') {
    echo json_encode(['success' => false, 'message' => 'Only freelancers can submit proposals']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$job_id = isset($input['job_id']) ? (int)$input['job_id'] : 0;
$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$duration = isset($input['duration']) ? (int)$input['duration'] : 0;
$cover_letter = isset($input['cover_letter']) ? trim($input['cover_letter']) : '';

// Validate input
if (!$job_id || !$amount || !$duration || empty($cover_letter)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

try {
    // Get job details and client info
    $job_stmt = $pdo->prepare('SELECT j.*, u.email as client_email, p.display_name as client_name
                               FROM jobs j
                               INNER JOIN users u ON j.client_id = u.id
                               LEFT JOIN profiles p ON u.id = p.user_id
                               WHERE j.id = ?');
    $job_stmt->execute([$job_id]);
    $job = $job_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job) {
        echo json_encode(['success' => false, 'message' => 'Job not found']);
        exit;
    }
    
    // Check if user already submitted a proposal
    $check_stmt = $pdo->prepare('SELECT id FROM proposals WHERE job_id = ? AND freelancer_id = ?');
    $check_stmt->execute([$job_id, $user_id]);
    
    if ($check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You have already submitted a proposal for this job']);
        exit;
    }
    
    // Get freelancer info
    $freelancer_stmt = $pdo->prepare('SELECT u.email, p.display_name
                                      FROM users u
                                      LEFT JOIN profiles p ON u.id = p.user_id
                                      WHERE u.id = ?');
    $freelancer_stmt->execute([$user_id]);
    $freelancer = $freelancer_stmt->fetch(PDO::FETCH_ASSOC);
    $freelancer_name = $freelancer['display_name'] ?: explode('@', $freelancer['email'])[0];
    
    // Insert proposal
    $stmt = $pdo->prepare('INSERT INTO proposals 
                           (job_id, freelancer_id, amount, duration, cover_letter, status, created_at) 
                           VALUES (?, ?, ?, ?, ?, "pending", NOW())');
    $stmt->execute([$job_id, $user_id, $amount, $duration, $cover_letter]);
    
    $proposal_id = $pdo->lastInsertId();
    
    // Create notification for client
    createNotification(
        $pdo,
        $job['client_id'],
        'proposal',
        'New Proposal Received',
        $freelancer_name . ' submitted a proposal for "' . $job['title'] . '" - $' . number_format($amount),
        'job-details.php?id=' . $job_id,
        $proposal_id,
        'proposal',
        $user_id
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Proposal submitted successfully',
        'proposal_id' => $proposal_id
    ]);
    
} catch (PDOException $e) {
    error_log('Error submitting proposal: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}