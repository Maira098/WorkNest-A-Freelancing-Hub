<?php
// public/actions/logout.php
require_once __DIR__ . '/../../src/init.php';
require_once __DIR__ . '/../../src/config.php';

// Destroy the session
session_unset();
session_destroy();

// Redirect to landing page
header('Location: /worknest/public/index.php');
exit;