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

// Get input data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's form data (with potential file) or JSON
    if (isset($_POST['receiver_id'])) {
        $receiver_id = (int)$_POST['receiver_id'];
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    } else {
        // JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $receiver_id = isset($input['receiver_id']) ? (int)$input['receiver_id'] : 0;
        $content = isset($input['content']) ? trim($input['content']) : '';
    }
    
    // Validate input
    if (!$receiver_id) {
        echo json_encode(['success' => false, 'message' => 'Receiver ID is required']);
        exit;
    }
    
    if (empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Message content is required']);
        exit;
    }
    
    // Check if receiver exists and get their info
    $check_stmt = $pdo->prepare('SELECT u.id, u.email, p.display_name 
                                  FROM users u 
                                  LEFT JOIN profiles p ON u.id = p.user_id 
                                  WHERE u.id = ?');
    $check_stmt->execute([$receiver_id]);
    $receiver = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => 'Receiver not found']);
        exit;
    }
    
    // Get sender info
    $sender_stmt = $pdo->prepare('SELECT u.email, p.display_name 
                                   FROM users u 
                                   LEFT JOIN profiles p ON u.id = p.user_id 
                                   WHERE u.id = ?');
    $sender_stmt->execute([$user_id]);
    $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
    
    $sender_name = $sender['display_name'] ?: explode('@', $sender['email'])[0];
    
    try {
        // Insert message
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, content, is_read, created_at) 
                               VALUES (?, ?, ?, 0, NOW())');
        $stmt->execute([$user_id, $receiver_id, $content]);
        
        $message_id = $pdo->lastInsertId();
        
        // Create notification for receiver
        $preview = strlen($content) > 50 ? substr($content, 0, 50) . '...' : $content;
        createNotification(
            $pdo,
            $receiver_id,
            'message',
            'New Message from ' . $sender_name,
            $preview,
            'messages-chat.php?user=' . $user_id,
            $message_id,
            'message',
            $user_id
        );
        
        // TODO: Handle file upload if present
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // File upload handling can be added here
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Message sent successfully',
            'message_id' => $message_id
        ]);
        
    } catch (PDOException $e) {
        error_log('Error sending message: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}