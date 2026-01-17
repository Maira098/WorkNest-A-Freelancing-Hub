<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['other_user_id']) ? (int)$_GET['other_user_id'] : 0;
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

if (!$other_user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing other_user_id']);
    exit;
}

try {
    // Get other user info
    $user_stmt = $pdo->prepare('SELECT u.email, p.display_name 
                                 FROM users u 
                                 LEFT JOIN profiles p ON u.id = p.user_id 
                                 WHERE u.id = ? LIMIT 1');
    $user_stmt->execute([$other_user_id]);
    $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$other_user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $other_user_name = $other_user['display_name'] ?: explode('@', $other_user['email'])[0];
    $other_user_initials = strtoupper(substr($other_user_name, 0, 2));
    
    // Get messages between these two users
    $query = "SELECT id, sender_id, receiver_id, content, created_at
              FROM messages
              WHERE ((sender_id = ? AND receiver_id = ?) 
                  OR (sender_id = ? AND receiver_id = ?))
              AND id > ?
              ORDER BY created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id, $after_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format messages
    $formatted_messages = [];
    foreach ($messages as $msg) {
        // Calculate time ago
        $time_diff = time() - strtotime($msg['created_at']);
        if ($time_diff < 60) {
            $time_ago = 'Just now';
        } elseif ($time_diff < 3600) {
            $time_ago = floor($time_diff / 60) . 'm ago';
        } elseif ($time_diff < 86400) {
            $time_ago = date('g:i A', strtotime($msg['created_at']));
        } else {
            $time_ago = date('M j, g:i A', strtotime($msg['created_at']));
        }
        
        $formatted_messages[] = [
            'id' => (int)$msg['id'],
            'sender_id' => (int)$msg['sender_id'],
            'receiver_id' => (int)$msg['receiver_id'],
            'content' => $msg['content'],
            'time_ago' => $time_ago,
            'created_at' => $msg['created_at']
        ];
    }
    
    // Mark messages as read
    if (count($messages) > 0) {
        $update_stmt = $pdo->prepare('UPDATE messages 
                                       SET is_read = 1 
                                       WHERE sender_id = ? 
                                       AND receiver_id = ? 
                                       AND is_read = 0');
        $update_stmt->execute([$other_user_id, $user_id]);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'other_user' => [
            'id' => $other_user_id,
            'name' => $other_user_name,
            'initials' => $other_user_initials
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}