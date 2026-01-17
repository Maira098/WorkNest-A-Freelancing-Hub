<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>


<?php
// public/actions/login_process.php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid CSRF token.';
    header('Location: /worknest/public/login.php');
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Enter a valid email.';
    header('Location: /worknest/public/login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['flash_error'] = 'Invalid email or password.';
    header('Location: /worknest/public/login.php');
    exit;
}

// Login success
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['user_role'] = $user['role'];

// update last_login
$pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([(int)$user['id']]);

// Redirect based on role
if ($user['role'] === 'client') {
    header('Location: /worknest/public/dashboard.php');
} else {
    header('Location: /worknest/public/dashboard.php');
}
exit;
