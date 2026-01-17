<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get all conversations (users with whom current user has exchanged messages)
    $query = "
        SELECT DISTINCT
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id
                ELSE m.sender_id
            END as other_user_id,
            CASE 
                WHEN m.sender_id = ? THEN receiver.email
                ELSE sender.email
            END as other_user_email,
            CASE 
                WHEN m.sender_id = ? THEN receiver_profile.display_name
                ELSE sender_profile.display_name
            END as other_user_name,
            (SELECT content FROM messages m2 
             WHERE (m2.sender_id = other_user_id AND m2.receiver_id = ?) 
                OR (m2.sender_id = ? AND m2.receiver_id = other_user_id)
             ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages m2 
             WHERE (m2.sender_id = other_user_id AND m2.receiver_id = ?) 
                OR (m2.sender_id = ? AND m2.receiver_id = other_user_id)
             ORDER BY m2.created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages m2 
             WHERE m2.sender_id = other_user_id 
               AND m2.receiver_id = ? 
               AND m2.is_read = 0) as unread_count
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN users receiver ON m.receiver_id = receiver.id
        LEFT JOIN profiles sender_profile ON sender.id = sender_profile.user_id
        LEFT JOIN profiles receiver_profile ON receiver.id = receiver_profile.user_id
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY last_message_time DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format conversations
    $formatted_conversations = [];
    foreach ($conversations as $conv) {
        $name = $conv['other_user_name'] ?: explode('@', $conv['other_user_email'])[0];
        $initials = strtoupper(substr($name, 0, 2));
        
        // Calculate time ago
        $time_diff = time() - strtotime($conv['last_message_time']);
        if ($time_diff < 60) {
            $time_ago = 'Just now';
        } elseif ($time_diff < 3600) {
            $time_ago = floor($time_diff / 60) . 'm ago';
        } elseif ($time_diff < 86400) {
            $time_ago = floor($time_diff / 3600) . 'h ago';
        } elseif ($time_diff < 172800) {
            $time_ago = 'Yesterday';
        } else {
            $days = floor($time_diff / 86400);
            $time_ago = $days . ' days ago';
        }
        
        $formatted_conversations[] = [
            'other_user_id' => $conv['other_user_id'],
            'name' => $name,
            'initials' => $initials,
            'last_message' => substr($conv['last_message'], 0, 50) . (strlen($conv['last_message']) > 50 ? '...' : ''),
            'time_ago' => $time_ago,
            'unread_count' => (int)$conv['unread_count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'conversations' => $formatted_conversations
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}