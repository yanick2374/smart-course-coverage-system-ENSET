<?php
session_start();

require_once __DIR__ . '/../config/database.php';

/* =========================================================
   AUTHENTICATION
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'hod' && $role !== 'head of department') {
    header("Location: ../index.php");
    exit;
}


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GET HOD DETAILS
========================================================= */

try {

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.department_id,
            d.department_name
        FROM users u
        LEFT JOIN departments d
            ON d.department_id = u.department_id
        WHERE u.user_id = ?
        LIMIT 1
    ");

    $stmt->execute([$user_id]);

    $hod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hod) {
        die("HOD account not found.");
    }

    $department_id = (int)$hod['department_id'];

    $hod_name = $hod['full_name'];

    $department_name =
        $hod['department_name'] ?? 'Department';

} catch (PDOException $e) {

    die("Database Error: " . $e->getMessage());
}


/* =========================================================
   FILTERS
========================================================= */

$session_id = (int)($_GET['session_id'] ?? 0);

$semester = trim($_GET['semester'] ?? '');

$program_id = (int)($_GET['program_id'] ?? 0);


/* =========================================================
   ACADEMIC YEARS
========================================================= */

try {

    $stmt = $pdo->query("
        SELECT
            session_id,
            session_name
        FROM academic_session
        ORDER BY start_date DESC
    ");

    $academic_sessions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $academic_sessions = [];
}


/* =========================================================
   PROGRAMS IN HOD DEPARTMENT
========================================================= */

try {

    $stmt = $pdo->prepare("
        SELECT
            program_id,
            program_name
        FROM programs
        WHERE department_id = ?
        ORDER BY program_name ASC
    ");

    $stmt->execute([
        $department_id
    ]);

    $programs =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $programs = [];
}


/* =========================================================
   COURSE COVERAGE REPORT
========================================================= */

$reports = [];

try {

    $sql = "

        SELECT

            c.course_id,
            c.course_code,
            c.course_name,

            ca.assignment_id,
            ca.semester,
            ca.level,

            l.lecturer_id,
            l.full_name AS lecturer_name,
            l.staff_no,

            p.program_id,
            p.program_name,

            a.session_id,
            a.session_name,

            COUNT(DISTINCT ct.topic_id)
                AS total_topics,

            COUNT(DISTINCT cc.coverage_id)
                AS covered_topics,

            COALESCE(
                SUM(cc.hours_taught),
                0
            ) AS hours_taught,

            COALESCE(
                SUM(ct.expected_hours),
                0
            ) AS expected_hours

        FROM courses c

        INNER JOIN course_assgnment ca
            ON ca.course_id = c.course_id

        INNER JOIN lecturers l
            ON l.lecturer_id = ca.lecturer_id

        INNER JOIN programs p
            ON p.program_id = ca.program_id

        INNER JOIN academic_session a
            ON a.session_id = ca.session_id

        LEFT JOIN cousre_topics ct
            ON ct.course_id = c.course_id

        LEFT JOIN course_coverage cc
            ON cc.assignment_id = ca.assignment_id
            AND cc.topic_id = ct.topic_id

        WHERE c.department_id = :department_id

    ";

    $params = [
        ':department_id' => $department_id
    ];


    /* =====================================================
       ACADEMIC YEAR FILTER
    ===================================================== */

    if ($session_id > 0) {

        $sql .= "
            AND ca.session_id = :session_id
        ";

        $params[':session_id'] =
            $session_id;
    }


    /* =====================================================
       SEMESTER FILTER
    ===================================================== */

    if ($semester !== '') {

        $sql .= "
            AND ca.semester = :semester
        ";

        $params[':semester'] =
            $semester;
    }


    /* =====================================================
       PROGRAM FILTER
    ===================================================== */

    if ($program_id > 0) {

        $sql .= "
            AND ca.program_id = :program_id
        ";

        $params[':program_id'] =
            $program_id;
    }


    $sql .= "

        GROUP BY

            c.course_id,
            c.course_code,
            c.course_name,

            ca.assignment_id,
            ca.semester,
            ca.level,

            l.lecturer_id,
            l.full_name,
            l.staff_no,

            p.program_id,
            p.program_name,

            a.session_id,
            a.session_name

        ORDER BY
            c.course_code ASC,
            l.full_name ASC

    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $reports =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $database_error =
        $e->getMessage();

    $reports = [];
}


/* =========================================================
   REPORT STATISTICS
========================================================= */

$total_courses =
    count($reports);

$total_topics = 0;

$total_covered = 0;

$total_expected_hours = 0;

$total_taught_hours = 0;


foreach ($reports as $report) {

    $total_topics +=
        (int)$report['total_topics'];

    $total_covered +=
        (int)$report['covered_topics'];

    $total_expected_hours +=
        (float)$report['expected_hours'];

    $total_taught_hours +=
        (float)$report['hours_taught'];
}


/* =========================================================
   OVERALL COVERAGE PERCENTAGE
========================================================= */

if ($total_topics > 0) {

    $coverage_percentage =
        ($total_covered / $total_topics) * 100;

} else {

    $coverage_percentage = 0;
}


$coverage_percentage =
    min(
        100,
        round(
            $coverage_percentage,
            1
        )
    );


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Reports | HOD
</title>


<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f8f6;

    color: #18372b;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;
    bottom: 0;

    width: 250px;

    background: #ffffff;

    border-right:
        1px solid #e5ebe7;

    padding: 20px 15px;

    overflow-y: auto;
}


.logo-area {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        5px 10px 25px;
}


.logo-area img {

    width: 43px;
    height: 43px;

    object-fit: contain;
}


.logo-text {

    font-size: 12px;

    font-weight: 800;

    color: #073f2a;
}


.logo-text span {

    display: block;

    font-size: 8px;

    color: #7b8983;

    margin-top: 4px;
}


.menu-title {

    font-size: 9px;

    font-weight: 800;

    color: #9aa49f;

    letter-spacing: 1px;

    margin:
        15px 10px 8px;
}


.menu-link {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        11px 10px;

    margin-bottom: 3px;

    border-radius: 8px;

    color: #5c6d65;

    text-decoration: none;

    font-size: 11px;

    font-weight: 600;
}


.menu-link:hover {

    background: #e8f5ed;

    color: #0b5d3b;
}


.menu-link.active {

    background: #e8f5ed;

    color: #0b5d3b;
}


.menu-icon {

    width: 20px;

    text-align: center;
}


.logout {

    color: #a42b20;
}


.logout:hover {

    background: #fff0ee;

    color: #a42b20;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 250px;

    min-height: 100vh;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    height: 75px;

    background: #ffffff;

    border-bottom:
        1px solid #e5ebe7;

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    padding:
        0 30px;
}


.topbar h1 {

    font-size: 21px;

    color: #073f2a;
}


.topbar p {

    margin-top: 4px;

    font-size: 10px;

    color: #7b8983;
}


.user-info {

    display: flex;

    align-items: center;

    gap: 10px;
}


.user-avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #e8f5ed;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #0b5d3b;

    font-weight: 800;
}


.user-info strong {

    display: block;

    font-size: 11px;
}


.user-info span {

    display: block;

    font-size: 9px;

    color: #7b8983;

    margin-top: 3px;
}


/* =========================================================
   CONTENT
========================================================= */

.content {

    padding:
        25px 30px;
}


.page-heading {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 20px;
}


.page-heading h2 {

    font-size: 18px;

    color: #073f2a;
}


.page-heading p {

    margin-top: 5px;

    font-size: 10px;

    color: #7b8983;
}


.department {

    background: #e8f5ed;

    color: #0b5d3b;

    padding:
        9px 13px;

    border-radius: 8px;

    font-size: 10px;

    font-weight: 800;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-bottom: 20px;
}


.stat-card {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    padding: 18px;

    box-shadow:
        0 3px 12px rgba(20,50,35,.05);
}


.stat-card small {

    display: block;

    color: #7b8983;

    font-size: 9px;

    font-weight: 800;

    text-transform: uppercase;
}


.stat-card strong {

    display: block;

    margin-top: 7px;

    font-size: 23px;

    color: #073f2a;
}


/* =========================================================
   REPORT PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    box-shadow:
        0 3px 12px rgba(20,50,35,.05);

    overflow: hidden;

    margin-bottom: 20px;
}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid #e8eeeb;
}


.panel-header h3 {

    font-size: 12px;

    color: #073f2a;
}


.panel-header p {

    margin-top: 4px;

    font-size: 9px;

    color: #7b8983;
}


/* =========================================================
   FILTERS
========================================================= */

.filters {

    padding:
        15px 20px;

    border-bottom:
        1px solid #e8eeeb;

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.filters select {

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    padding:
        9px 10px;

    font-size: 10px;

    outline: none;

    background: #ffffff;

    min-width: 160px;
}


.filter-btn {

    border: none;

    background: #0b5d3b;

    color: #ffffff;

    padding:
        9px 15px;

    border-radius: 7px;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}


.clear-btn {

    background: #eef3f0;

    color: #0b5d3b;

    text-decoration: none;

    padding:
        9px 13px;

    border-radius: 7px;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   COVERAGE PROGRESS
========================================================= */

.progress-wrapper {

    padding: 20px;
}


.progress-top {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 8px;
}


.progress-top span {

    font-size: 10px;

    color: #687871;
}


.progress-top strong {

    font-size: 12px;

    color: #0b5d3b;
}


.progress-bar {

    width: 100%;

    height: 10px;

    background: #edf1ef;

    border-radius: 20px;

    overflow: hidden;
}


.progress-fill {

    height: 100%;

    background: #0b5d3b;

    border-radius: 20px;

    width:
        <?=e($coverage_percentage)?>%;
}


/* =========================================================
   TABLE
========================================================= */

.table-container {

    width: 100%;

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1100px;
}


thead th {

    background: #fafcfb;

    color: #687871;

    font-size: 9px;

    text-align: left;

    padding:
        12px 10px;

    border-bottom:
        1px solid #e5ebe7;
}


tbody td {

    padding:
        13px 10px;

    font-size: 10px;

    border-bottom:
        1px solid #edf1ef;

    vertical-align: middle;
}


thead th:first-child,
tbody td:first-child {

    padding-left: 20px;
}


.course-code {

    color: #0b5d3b;

    font-weight: 800;

    font-size: 11px;
}


.course-name {

    color: #7b8983;

    font-size: 9px;

    margin-top: 3px;
}


.lecturer {

    font-weight: 700;
}


.staff {

    color: #89958f;

    font-size: 9px;

    margin-top: 3px;
}


.percentage {

    font-weight: 800;

    color: #0b5d3b;
}


.hours {

    font-weight: 700;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    padding:
        5px 9px;

    border-radius: 20px;

    font-size: 9px;

    font-weight: 800;
}


.status.good {

    background: #e8f5ed;

    color: #0b5d3b;
}


.status.medium {

    background: #fff8df;

    color: #946200;
}


.status.low {

    background: #fff0ee;

    color: #a42b20;
}


/* =========================================================
   PRINT BUTTON
========================================================= */

.actions {

    display: flex;

    justify-content:
        flex-end;

    gap: 8px;

    margin-bottom: 15px;
}


.print-btn {

    border: none;

    background: #0b5d3b;

    color: #ffffff;

    padding:
        10px 16px;

    border-radius: 7px;

    cursor: pointer;

    font-size: 10px;

    font-weight: 700;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 45px 20px;

    color: #89958f;

    font-size: 11px;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .sidebar,
    .topbar,
    .filters,
    .actions {

        display: none !important;
    }

    .main {

        margin-left: 0;
    }

    .content {

        padding: 10px;
    }

    body {

        background: #ffffff;
    }

    .panel {

        box-shadow: none;
    }
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .stats {

        grid-template-columns:
            repeat(2, 1fr);
    }
}


@media(max-width:700px) {

    .sidebar {

        width: 210px;
    }

    .main {

        margin-left: 210px;
    }

    .topbar {

        padding:
            0 15px;
    }

    .content {

        padding:
            20px 15px;
    }

    .stats {

        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


<div class="logo-area">

<img
    src="../assets/images/ub-logo.png"
    alt="Logo"
>

<div class="logo-text">

COURSE COVERAGE

<span>
MANAGEMENT SYSTEM<br>
HTTTC KUMBA
</span>

</div>

</div>


<div class="menu-title">
MAIN MENU
</div>


<a
    href="dashboard.php"
    class="menu-link"
>

<span class="menu-icon">
⌂
</span>

Dashboard

</a>


<a
    href="lecturers.php"
    class="menu-link"
>

<span class="menu-icon">
♟
</span>

Lecturers

</a>


<a
    href="courses.php"
    class="menu-link"
>

<span class="menu-icon">
▦
</span>

Courses

</a>


<a
    href="assign_course.php"
    class="menu-link"
>

<span class="menu-icon">
▤
</span>

Course Assignment

</a>


<a
    href="assigned_courses.php"
    class="menu-link"
>

<span class="menu-icon">
◫
</span>

Assigned Courses

</a>


<a
    href="coverage.php"
    class="menu-link"
>

<span class="menu-icon">
✓
</span>

Coverage Review

</a>


<a
    href="reports.php"
    class="menu-link active"
>

<span class="menu-icon">
▥
</span>

Reports

</a>


<div class="menu-title">
ACCOUNT
</div>


<a
    href="../change_password.php"
    class="menu-link"
>

<span class="menu-icon">
⚙
</span>

Change Password

</a>


<a
    href="../auth/logout.php"
    class="menu-link logout"
>

<span class="menu-icon">
↪
</span>

Logout

</a>


</aside>



<!-- =========================================================
     MAIN
========================================================= -->

<main class="main">


<header class="topbar">


<div>

<h1>
Reports
</h1>

<p>
Course coverage and teaching progress reports
</p>

</div>


<div class="user-info">


<div class="user-avatar">

<?=e(
    strtoupper(
        substr(
            $hod_name,
            0,
            1
        )
    )
)?>

</div>


<div>

<strong>
<?=e($hod_name)?>
</strong>

<span>
Head of Department
</span>

</div>

</div>


</header>



<section class="content">


<!-- =====================================================
     PAGE HEADING
===================================================== -->

<div class="page-heading">


<div>

<h2>
Department Reports
</h2>

<p>
View the overall course coverage performance of your department.
</p>

</div>


<div class="department">

<?=e($department_name)?>

</div>


</div>



<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="stats">


<div class="stat-card">

<small>
Assigned Courses
</small>

<strong>
<?=e($total_courses)?>
</strong>

</div>


<div class="stat-card">

<small>
Total Topics
</small>

<strong>
<?=e($total_topics)?>
</strong>

</div>


<div class="stat-card">

<small>
Covered Topics
</small>

<strong>
<?=e($total_covered)?>
</strong>

</div>


<div class="stat-card">

<small>
Coverage Rate
</small>

<strong>
<?=e($coverage_percentage)?>%
</strong>

</div>


</div>



<!-- =====================================================
     REPORT FILTERS
===================================================== -->

<div class="panel">


<div class="panel-header">

<h3>
GENERATE REPORT
</h3>

<p>
Select the academic year, semester or program you want to review.
</p>

</div>


<form
    method="GET"
    class="filters"
>


<select name="session_id">

<option value="">
All Academic Years
</option>


<?php foreach (
    $academic_sessions
    as $year
): ?>

<option
    value="<?=e(
        $year['session_id']
    )?>"

    <?=(
        $session_id ==
        $year['session_id']
    )
    ? 'selected'
    : ''
    ?>
>

<?=e(
    $year['session_name']
)?>

</option>

<?php endforeach; ?>

</select>


<select name="semester">

<option value="">
All Semesters
</option>


<option
    value="First Semester"

    <?=(
        $semester ===
        'First Semester'
    )
    ? 'selected'
    : ''
    ?>
>

First Semester

</option>


<option
    value="Second Semester"

    <?=(
        $semester ===
        'Second Semester'
    )
    ? 'selected'
    : ''
    ?>
>

Second Semester

</option>


<option
    value="Summer Semester"

    <?=(
        $semester ===
        'Summer Semester'
    )
    ? 'selected'
    : ''
    ?>
>

Summer Semester

</option>

</select>


<select name="program_id">

<option value="">
All Programs
</option>


<?php foreach (
    $programs
    as $program
): ?>

<option
    value="<?=e(
        $program['program_id']
    )?>"

    <?=(
        $program_id ==
        $program['program_id']
    )
    ? 'selected'
    : ''
    ?>
>

<?=e(
    $program['program_name']
)?>

</option>

<?php endforeach; ?>

</select>


<button
    type="submit"
    class="filter-btn"
>

Generate Report

</button>


<a
    href="reports.php"
    class="clear-btn"
>

Clear

</a>


</form>


</div>



<!-- =====================================================
     OVERALL PROGRESS
===================================================== -->

<div class="panel">


<div class="panel-header">

<h3>
OVERALL COURSE COVERAGE
</h3>

<p>
Percentage of topics covered by lecturers in this department.
</p>

</div>


<div class="progress-wrapper">


<div class="progress-top">

<span>
Department Coverage
</span>

<strong>
<?=e($coverage_percentage)?>%
</strong>

</div>


<div class="progress-bar">

<div class="progress-fill"></div>

</div>


</div>


</div>



<!-- =====================================================
     PRINT ACTION
===================================================== -->

<div class="actions">

<button
    onclick="window.print()"
    class="print-btn"
>

Print Report

</button>

</div>



<!-- =====================================================
     COURSE REPORT
===================================================== -->

<div class="panel">


<div class="panel-header">

<h3>
COURSE COVERAGE REPORT
</h3>

<p>
Detailed coverage report for courses assigned within your department.
</p>

</div>


<div class="table-container">


<table>


<thead>

<tr>

<th>
COURSE
</th>

<th>
LECTURER
</th>

<th>
PROGRAM
</th>

<th>
LEVEL
</th>

<th>
SEMESTER
</th>

<th>
TOPICS
</th>

<th>
COVERED
</th>

<th>
HOURS
</th>

<th>
COVERAGE
</th>

</tr>

</thead>


<tbody>


<?php if (
    !empty($reports)
): ?>


<?php foreach (
    $reports
    as $report
): ?>


<?php

$total =
    (int)$report['total_topics'];

$covered =
    (int)$report['covered_topics'];


if ($total > 0) {

    $percentage =
        ($covered / $total) * 100;

} else {

    $percentage = 0;
}


$percentage =
    min(
        100,
        round(
            $percentage,
            1
        )
    );


if ($percentage >= 75) {

    $status_class = 'good';

} elseif ($percentage >= 40) {

    $status_class = 'medium';

} else {

    $status_class = 'low';
}

?>


<tr>


<!-- COURSE -->

<td>

<div class="course-code">

<?=e(
    $report['course_code']
)?>

</div>

<div class="course-name">

<?=e(
    $report['course_name']
)?>

</div>

</td>



<!-- LECTURER -->

<td>

<div class="lecturer">

<?=e(
    $report['lecturer_name']
)?>

</div>

<div class="staff">

<?=e(
    $report['staff_no']
)?>

</div>

</td>



<!-- PROGRAM -->

<td>

<?=e(
    $report['program_name']
)?>

</td>



<!-- LEVEL -->

<td>

<?=e(
    $report['level']
)?>

</td>



<!-- SEMESTER -->

<td>

<?=e(
    $report['semester']
)?>

</td>



<!-- TOTAL TOPICS -->

<td>

<?=e(
    $total
)?>

</td>



<!-- COVERED -->

<td>

<?=e(
    $covered
)?>

</td>



<!-- HOURS -->

<td>

<div class="hours">

<?=e(
    number_format(
        (float)$report['hours_taught'],
        1
    )
)?>

hrs

</div>

</td>



<!-- COVERAGE -->

<td>

<span
    class="status <?=e(
        $status_class
    )?>"
>

<?=e(
    $percentage
)?>%

</span>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="9"
    class="empty"
>

No report data found for the selected filters.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>



<!-- =====================================================
     HOURS SUMMARY
===================================================== -->

<div class="stats">


<div class="stat-card">

<small>
Expected Teaching Hours
</small>

<strong>
<?=e(
    number_format(
        $total_expected_hours,
        1
    )
)?>
</strong>

</div>


<div class="stat-card">

<small>
Actual Hours Taught
</small>

<strong>
<?=e(
    number_format(
        $total_taught_hours,
        1
    )
)?>
</strong>

</div>


<div class="stat-card">

<small>
Remaining Hours
</small>

<strong>

<?=e(
    number_format(
        max(
            0,
            $total_expected_hours -
            $total_taught_hours
        ),
        1
    )
)?>

</strong>

</div>


<div class="stat-card">

<small>
Report Status
</small>

<strong style="font-size:16px;">

<?php

if ($coverage_percentage >= 75) {

    echo "Good";

} elseif ($coverage_percentage >= 40) {

    echo "Moderate";

} else {

    echo "Needs Attention";
}

?>

</strong>

</div>


</div>


</section>


</main>


</body>

</html>