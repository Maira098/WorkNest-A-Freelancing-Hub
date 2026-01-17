<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['user_role'];

// Route to appropriate dashboard based on role
if ($user_role === 'client') {
    // Client dashboard - shows freelancers
    if (file_exists(__DIR__ . '/dashboard-client.php')) {
        require_once __DIR__ . '/dashboard-client.php';
        exit; // Important: Stop execution here
    } else {
        die('Error: dashboard-client.php not found. Please add the client dashboard file.');
    }
} else {
    // Freelancer dashboard - shows projects
    if (file_exists(__DIR__ . '/dashboard-freelancer.php')) {
        require_once __DIR__ . '/dashboard-freelancer.php';
        exit; // Important: Stop execution here
    } else {
        die('Error: dashboard-freelancer.php not found. Please add the freelancer dashboard file.');
    }
}