<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_role = $_SESSION['user_role'];

// Route based on user role
if ($user_role === 'client') {
    // Clients browse freelancers
    if (file_exists('browse-freelancers.php')) {
        require_once 'browse-freelancers.php';
    } else {
        // Fallback to projects if freelancers page doesn't exist yet
        require_once 'browse-projects.php';
    }
} else {
    // Freelancers browse projects
    require_once 'browse-projects.php';
}