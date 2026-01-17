<?php
$host = "localhost";
$dbname = "worknest_db";
$user = "root";   // or your user
$pass = "";       // DB password if any

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4",$user,$pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
