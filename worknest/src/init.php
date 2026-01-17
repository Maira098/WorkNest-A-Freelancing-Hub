<?php
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function csrf_token() {
    if(!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>
