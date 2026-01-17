<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// public/actions/signup_process.php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Basic CSRF
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    $_SESSION['flash_error'] = 'Invalid CSRF token.';
    header('Location: /worknest/public/signup.php');
    exit;
}

$firstName = trim((string)($_POST['firstName'] ?? ''));
$lastName = trim((string)($_POST['lastName'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = in_array($_POST['role'] ?? 'freelancer', ['freelancer','client'], true) ? $_POST['role'] : 'freelancer';

// Validation
if (empty($firstName) || empty($lastName)) {
    $_SESSION['flash_error'] = 'First name and last name are required.';
    header('Location: /worknest/public/signup.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_error'] = 'Enter a valid email.';
    header('Location: /worknest/public/signup.php');
    exit;
}

if (strlen($password) < 6) {
    $_SESSION['flash_error'] = 'Password must be at least 6 characters.';
    header('Location: /worknest/public/signup.php');
    exit;
}

// Check email exists
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $_SESSION['flash_error'] = 'Email already registered.';
    header('Location: /worknest/public/signup.php');
    exit;
}

// Insert user
$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $pdo->prepare('INSERT INTO users (email, password_hash, role, created_at) VALUES (?, ?, ?, NOW())');
$ins->execute([$email, $hash, $role]);
$user_id = (int)$pdo->lastInsertId();

// Create profile with display_name (first name + last name)
$displayName = $firstName . ' ' . $lastName;
$pdo->prepare('INSERT INTO profiles (user_id, display_name) VALUES (?, ?)')
    ->execute([$user_id, $displayName]);

$_SESSION['flash_success'] = 'Account created successfully! Please login.';
header('Location: /worknest/public/login.php');
exit;