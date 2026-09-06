<?php
session_start();
require_once "config.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$total_courses = 0;
$total_coverage = 0;
$total_notifications = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_assgnment
    WHERE lecturer_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $total_courses = $result["total"];
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM course_coverage
    WHERE lecturer_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $total_coverage = $result["total"];
}

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

if ($result) {
    $total_notifications = $result["total"];
}

$courses = [];

$stmt = $conn->prepare("
    SELECT
        c.course_id,
        c.course_code,
        c.course_title
    FROM course_assgnment ca
    INNER JOIN courses c
        ON ca.course_id = c.course_id
    WHERE ca.lecturer_id = ?
    ORDER BY c.course_code
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lecturer Dashboard | HTTTC Kumba</title>
<link rel="stylesheet" href="lecturer.css">
</head>

<body>

<div class="layout">

<aside class="sidebar">

<div class="brand">
<div class="brand-logo">
<img src="../assets/images/httc-logo.png" alt="HTTTC Kumba">
</div>

<div>
<strong>HTTTC KUMBA</strong>
<small>Course Coverage System</small>
</div>
</div>

<div class="lecturer-user">

<div class="avatar">
<?= strtoupper(substr($user["full_name"], 0, 1)); ?>
</div>

<div>
<strong><?= htmlspecialchars($user["full_name"]); ?></strong>
<small>Lecturer</small>
</div>

</div>

<nav>

<a href="dashboard.php" class="active">
<span>▦</span>
Dashboard
</a>

<a href="my_courses.php">
<span>▤</span>
My Courses
</a>

<a href="my_timetable.php">
<span>◷</span>
My Timetable
</a>

<a href="record_coverage.php">
<span>✎</span>
Record Coverage
</a>

<a href="coverage_history.php">
<span>◫</span>
Coverage History
</a>

<a href="course_progress.php">
<span>◔</span>
Course Progress
</a>

<div class="nav-title">ACCOUNT</div>

<a href="profile.php">
<span>◎</span>
My Profile
</a>

</nav>

<div class="sidebar-bottom">

<a href="../logout.php">
<span>↪</span>
Secure Logout
</a>

</div>

</aside>

<main class="main">

<header class="topbar">

<div>
<div class="breadcrumb">
Lecturer / Dashboard
</div>

<h1>
Lecturer Dashboard
</h1>
</div>

<div class="top-user">

<div class="top-avatar">
<?= strtoupper(substr($user["full_name"], 0, 1)); ?>
</div>

<div>
<strong><?= htmlspecialchars($user["full_name"]); ?></strong>
<small>Lecturer</small>
</div>

</div>

</header>

<section class="welcome">

<div>

<span>LECTURER WORKSPACE</span>

<h2>
Welcome back, <?= htmlspecialchars($user["full_name"]); ?>
</h2>

<p>
Manage your assigned courses and monitor your teaching coverage.
</p>

</div>

<div class="system-status">

<small>SYSTEM STATUS</small>

<strong>● Operational</strong>

</div>

</section>

<section class="stats">

<div class="stat-card">

<div class="stat-icon blue">
▤
</div>

<div>

<small>Assigned Courses</small>

<strong>
<?= $total_courses; ?>
</strong>

<span>
Courses assigned by HOD
</span>

</div>

</div>

<div class="stat-card">

<div class="stat-icon green">
◫
</div>

<div>

<small>Coverage Records</small>

<strong>
<?= $total_coverage; ?>
</strong>

<span>
Submitted teaching records
</span>

</div>

</div>

<div class="stat-card">

<div class="stat-icon gold">
!
</div>

<div>

<small>Notifications</small>

<strong>
<?= $total_notifications; ?>
</strong>

<span>
System notifications
</span>

</div>

</div>

</section>

<section class="content-grid">

<div class="panel">

<div class="panel-header">

<div>

<small>ACADEMIC ASSIGNMENTS</small>

<h3>
My Courses
</h3>

</div>

<a href="my_courses.php">
View All
</a>

</div>

<div class="course-list">

<?php if (count($courses) > 0): ?>

<?php foreach ($courses as $course): ?>

<div class="course-row">

<div class="course-code">
<?= htmlspecialchars($course["course_code"]); ?>
</div>

<div class="course-details">

<strong>
<?= htmlspecialchars($course["course_title"]); ?>
</strong>

<small>
Assigned by Department
</small>

</div>

<a href="course_progress.php?course_id=<?= $course["course_id"]; ?>">
View
</a>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty">

<strong>
No courses assigned
</strong>

<p>
Courses assigned by the HOD will appear here.
</p>

</div>

<?php endif; ?>

</div>

</div>

<div class="panel">

<div class="panel-header">

<div>

<small>QUICK ACCESS</small>

<h3>
Academic Actions
</h3>

</div>

</div>

<div class="quick-actions">

<a href="record_coverage.php">

<span>✎</span>

<div>

<strong>
Record Coverage
</strong>

<small>
Record today's teaching activity
</small>

</div>

</a>

<a href="my_courses.php">

<span>▤</span>

<div>

<strong>
My Courses
</strong>

<small>
View courses assigned to you
</small>

</div>

</a>

<a href="course_progress.php">

<span>◔</span>

<div>

<strong>
Course Progress
</strong>

<small>
Monitor your teaching progress
</small>

</div>

</a>

</div>

</div>

</section>

<footer>

<span>
Smart Course Coverage Management System
</span>

<span>
HTTTC Kumba © <?= date("Y"); ?>
</span>

</footer>

</main>

</div>

</body>
</html>