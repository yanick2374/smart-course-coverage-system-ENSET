<?php
session_start();

if (empty($_SESSION['logged_in']) || strtolower(trim($_SESSION['role'] ?? '')) !== 'lecturer') {
    header('Location: ../index.php');
    exit;
}

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$target = 'dashboard.php#submit-progress';
if ($assignmentId > 0) {
    $target = 'dashboard.php?assignment_id=' . $assignmentId . '#submit-progress';
}

header('Location: ' . $target);
exit;
