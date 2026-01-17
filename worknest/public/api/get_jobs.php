<?php
require_once "../src/config.php";
$data = $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC")->fetchAll();
echo json_encode($data);
