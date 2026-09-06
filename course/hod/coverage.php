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

$hod_id = (int) $_SESSION['user_id'];

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'hod' && $role !== 'head of department') {
    header("Location: ../index.php");
    exit;
}


/* =========================================================
   HELPER FUNCTION
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
   GET HOD INFORMATION
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

    $stmt->execute([$hod_id]);

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

$search = trim($_GET['search'] ?? '');

$semester = trim($_GET['semester'] ?? '');

$status = trim($_GET['status'] ?? '');

$session_id = (int)($_GET['session_id'] ?? 0);


/* =========================================================
   GET ACADEMIC YEARS
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
   GET COURSE COVERAGE
========================================================= */

$coverage = [];

try {

    $sql = "

        SELECT

            cc.coverage_id,
            cc.assignment_id,
            cc.topic_id,
            cc.date_taught,
            cc.hours_taught,
            cc.coverage_status,
            cc.remarks,
            cc.updated_at,

            ca.semester,
            ca.level,

            c.course_code,
            c.course_name,

            l.lecturer_id,
            l.staff_no,
            l.full_name AS lecturer_name,

            p.program_name,

            a.session_name,

            ct.topic_number,
            ct.topic_title,
            ct.expected_hours

        FROM course_coverage cc

        INNER JOIN course_assgnment ca
            ON ca.assignment_id = cc.assignment_id

        INNER JOIN courses c
            ON c.course_id = ca.course_id

        INNER JOIN lecturers l
            ON l.lecturer_id = ca.lecturer_id

        INNER JOIN programs p
            ON p.program_id = ca.program_id

        INNER JOIN academic_session a
            ON a.session_id = ca.session_id

        INNER JOIN cousre_topics ct
            ON ct.topic_id = cc.topic_id

        WHERE c.department_id = :department_id

    ";

    $params = [
        ':department_id' => $department_id
    ];


    /* =====================================================
       SEARCH FILTER
    ===================================================== */

    if ($search !== '') {

        $sql .= "

            AND (
                c.course_code LIKE :search
                OR c.course_name LIKE :search
                OR l.full_name LIKE :search
                OR l.staff_no LIKE :search
                OR ct.topic_title LIKE :search
            )

        ";

        $params[':search'] =
            '%' . $search . '%';
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
       STATUS FILTER
    ===================================================== */

    if ($status !== '') {

        $sql .= "

            AND cc.coverage_status = :status

        ";

        $params[':status'] =
            $status;
    }


    $sql .= "

        ORDER BY
            cc.date_taught DESC,
            c.course_code ASC,
            l.full_name ASC

    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $coverage =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $database_error = $e->getMessage();

    $coverage = [];
}


/* =========================================================
   CALCULATE STATISTICS
========================================================= */

$total_records =
    count($coverage);

$total_hours = 0;

$completed = 0;

$in_progress = 0;

$pending = 0;


foreach ($coverage as $row) {

    $total_hours +=
        (float)$row['hours_taught'];

    $current_status =
        strtolower(
            trim(
                $row['coverage_status']
            )
        );


    if (
        $current_status === 'covered' ||
        $current_status === 'completed'
    ) {

        $completed++;

    } elseif (
        $current_status === 'in progress' ||
        $current_status === 'in_progress'
    ) {

        $in_progress++;

    } else {

        $pending++;
    }
}

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
Coverage Review | HOD
</title>


<style>

/* =========================================================
   GLOBAL
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
   PANEL
========================================================= */

.panel {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    box-shadow:
        0 3px 12px rgba(20,50,35,.05);

    overflow: hidden;
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


.filters input,
.filters select {

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    padding:
        9px 10px;

    font-size: 10px;

    outline: none;

    background: #ffffff;
}


.filters input {

    flex: 1;

    min-width: 230px;
}


.filters select {

    min-width: 140px;
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


.topic {

    font-weight: 700;
}


.topic-hours {

    color: #89958f;

    font-size: 9px;

    margin-top: 3px;
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


.status.covered,
.status.completed {

    background: #e8f5ed;

    color: #0b5d3b;
}


.status.in-progress {

    background: #fff8df;

    color: #946200;
}


.status.pending {

    background: #f0f2f1;

    color: #68746f;
}


/* =========================================================
   VIEW BUTTON
========================================================= */

.view-btn {

    display: inline-block;

    padding:
        7px 10px;

    border-radius: 6px;

    background: #e8f5ed;

    color: #0b5d3b;

    text-decoration: none;

    font-size: 9px;

    font-weight: 800;
}


.view-btn:hover {

    background: #0b5d3b;

    color: #ffffff;
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


</a>


<a
    href="dashboard.php#assign-course"
    class="menu-link"
>

<span class="menu-icon">
▤
</span>

Course Assignment

</a>


<a
    href="assignments.php"
    class="menu-link"
>

<span class="menu-icon">
◫
</span>

Assigned Courses

</a>


<a
    href="coverage.php"
    class="menu-link active"
>

<span class="menu-icon">
✓
</span>

Coverage Review

</a>


<a
    href="reports.php"
    class="menu-link"
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
     MAIN CONTENT
========================================================= -->

<main class="main">


<header class="topbar">


<div>

<h1>
Coverage Review
</h1>

<p>
Monitor course coverage submitted by lecturers
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
     PAGE HEADER
===================================================== -->

<div class="page-heading">


<div>

<h2>
Course Coverage Review
</h2>

<p>
Review the teaching progress of lecturers in your department.
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
Coverage Records
</small>

<strong>
<?=e($total_records)?>
</strong>

</div>


<div class="stat-card">

<small>
Covered Topics
</small>

<strong>
<?=e($completed)?>
</strong>

</div>


<div class="stat-card">

<small>
In Progress
</small>

<strong>
<?=e($in_progress)?>
</strong>

</div>


<div class="stat-card">

<small>
Hours Taught
</small>

<strong>
<?=e(
    number_format(
        $total_hours,
        1
    )
)?>
</strong>

</div>


</div>



<!-- =====================================================
     COVERAGE PANEL
===================================================== -->

<div class="panel">


<div class="panel-header">

<h3>
LECTURER COVERAGE
</h3>

<p>
All course coverage submitted within your department.
</p>

</div>



<!-- =====================================================
     FILTERS
===================================================== -->

<form
    method="GET"
    class="filters"
>


<input
    type="text"
    name="search"
    placeholder="Search lecturer, course or topic..."
    value="<?=e($search)?>"
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


<select name="status">

<option value="">
All Status
</option>


<option
    value="Covered"

    <?=(
        $status ===
        'Covered'
    )
    ? 'selected'
    : ''
    ?>
>

Covered

</option>


<option
    value="Completed"

    <?=(
        $status ===
        'Completed'
    )
    ? 'selected'
    : ''
    ?>
>

Completed

</option>


<option
    value="In Progress"

    <?=(
        $status ===
        'In Progress'
    )
    ? 'selected'
    : ''
    ?>
>

In Progress

</option>


<option
    value="Pending"

    <?=(
        $status ===
        'Pending'
    )
    ? 'selected'
    : ''
    ?>
>

Pending

</option>

</select>


<button
    type="submit"
    class="filter-btn"
>

Filter

</button>


<a
    href="coverage.php"
    class="clear-btn"
>

Clear

</a>


</form>



<!-- =====================================================
     COVERAGE TABLE
===================================================== -->

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
TOPIC
</th>

<th>
PROGRAM
</th>

<th>
SEMESTER
</th>

<th>
DATE
</th>

<th>
HOURS
</th>

<th>
STATUS
</th>

<th>
ACTION
</th>

</tr>

</thead>


<tbody>


<?php if (
    !empty($coverage)
): ?>


<?php foreach (
    $coverage
    as $row
): ?>


<?php

$current_status =
    strtolower(
        trim(
            $row['coverage_status']
        )
    );

$status_class =
    str_replace(
        ' ',
        '-',
        $current_status
    );

?>


<tr>


<!-- COURSE -->

<td>

<div class="course-code">

<?=e(
    $row['course_code']
)?>

</div>

<div class="course-name">

<?=e(
    $row['course_name']
)?>

</div>

</td>



<!-- LECTURER -->

<td>

<div class="lecturer">

<?=e(
    $row['lecturer_name']
)?>

</div>

<div class="staff">

<?=e(
    $row['staff_no']
)?>

</div>

</td>



<!-- TOPIC -->

<td>

<div class="topic">

<?=e(
    $row['topic_number']
)?>.

<?=e(
    $row['topic_title']
)?>

</div>

<div class="topic-hours">

Expected:

<?=e(
    $row['expected_hours']
)?>

hrs

</div>

</td>



<!-- PROGRAM -->

<td>

<?=e(
    $row['program_name']
)?>

<br>

<span
    style="
        color:#89958f;
        font-size:9px;
    "
>

Level

<?=e(
    $row['level']
)?>

</span>

</td>



<!-- SEMESTER -->

<td>

<?=e(
    $row['semester']
)?>

</td>



<!-- DATE -->

<td>

<?=e(
    date(
        'd M Y',
        strtotime(
            $row['date_taught']
        )
    )
)?>

</td>



<!-- HOURS -->

<td>

<strong>

<?=e(
    number_format(
        (float)$row['hours_taught'],
        2
    )
)?>

</strong>

hrs

</td>



<!-- STATUS -->

<td>

<span
    class="status <?=e(
        $status_class
    )?>"
>

<?=e(
    $row['coverage_status']
)?>

</span>

</td>



<!-- ACTION -->

<td>

<a
    href="coverage_details.php?id=<?=e(
        $row['coverage_id']
    )?>"
    class="view-btn"
>

View

</a>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="9"
    class="empty"
>

No course coverage records
found for your department.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</section>


</main>


</body>

</html>