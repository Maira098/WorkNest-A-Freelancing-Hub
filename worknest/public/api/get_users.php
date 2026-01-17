<?php
require_once "../../src/init.php";
require_once "../../src/config.php";

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output, log them instead

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get all users except current user
    $query = "SELECT u.id, u.email, u.role, p.display_name
              FROM users u
              LEFT JOIN profiles p ON u.id = p.user_id
              WHERE u.id != ?
              ORDER BY p.display_name ASC, u.email ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format users
    $formatted_users = [];
    foreach ($users as $user) {
        $name = !empty($user['display_name']) ? $user['display_name'] : explode('@', $user['email'])[0];
        $initials = strtoupper(substr($name, 0, 2));
        
        $formatted_users[] = [
            'id' => (int)$user['id'],
            'name' => $name,
            'email' => $user['email'],
            'role' => ucfirst($user['role']),
            'initials' => $initials
        ];
    }
    
    echo json_encode([
        'success' => true,
        'users' => $formatted_users,
        'count' => count($formatted_users)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}