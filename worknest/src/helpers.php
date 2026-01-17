<?php

function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getAllJobs($pdo) {
    return $pdo->query("SELECT * FROM jobs ORDER BY created_at DESC")->fetchAll();
}

function getJobById($pdo, $jobId) {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$jobId]);
    return $stmt->fetch();
}
