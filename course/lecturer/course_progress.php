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
$lecturer_email = trim($_SESSION['email'] ?? '');

$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'lecturer') {
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
   GET LECTURER
========================================================= */

try {

    /*
     * We first get the lecturer using the logged-in
     * user's ID.
     *
     * If your users table stores the lecturer information
     * directly, this query can be adjusted later.
     */

    $stmt = $pdo->prepare("
        SELECT
            l.lecturer_id,
            l.staff_no,
            l.full_name,
            l.email,
            l.department_id,
            d.department_name
        FROM lecturers l
        LEFT JOIN departments d
            ON d.department_id = l.department_id
        WHERE LOWER(TRIM(l.email)) = LOWER(TRIM(?))
        LIMIT 1
    ");

    $stmt->execute([$lecturer_email]);

    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        die("Lecturer account not found.");
    }

    $lecturer_id =
        (int)$lecturer['lecturer_id'];

    $lecturer_name =
        $lecturer['full_name'];

    $department_name =
        $lecturer['department_name']
        ?? 'Department';

} catch (PDOException $e) {

    die(
        "Database Error: " .
        e($e->getMessage())
    );
}


/* =========================================================
   GET COURSE PROGRESS
========================================================= */

$courses = [];

try {

    $sql = "

        SELECT

            ca.assignment_id,

            ca.course_id,

            ca.program_id,

            ca.session_id,

            ca.semester,

            ca.level,

            c.course_code,

            c.course_name,

            p.program_name,

            a.session_name,
            a.start_date,
            a.end_date,

            COALESCE((SELECT COUNT(*)
                      FROM cousre_topics ct
                      WHERE ct.course_id = ca.course_id), 0) AS total_topics,

            COALESCE((SELECT COUNT(*)
                      FROM cousre_topics ct
                      WHERE ct.course_id = ca.course_id
                        AND ct.expected_hours > 0
                        AND (SELECT COALESCE(SUM(cc.hours_taught), 0)
                             FROM course_coverage cc
                             WHERE cc.assignment_id = ca.assignment_id
                               AND cc.topic_id = ct.topic_id) >= ct.expected_hours), 0) AS completed_topics,

            COALESCE((SELECT COUNT(*)
                      FROM cousre_topics ct
                      WHERE ct.course_id = ca.course_id
                        AND (SELECT COALESCE(SUM(cc.hours_taught), 0)
                             FROM course_coverage cc
                             WHERE cc.assignment_id = ca.assignment_id
                               AND cc.topic_id = ct.topic_id) > 0
                        AND (SELECT COALESCE(SUM(cc.hours_taught), 0)
                             FROM course_coverage cc
                             WHERE cc.assignment_id = ca.assignment_id
                               AND cc.topic_id = ct.topic_id) < ct.expected_hours), 0) AS in_progress_topics,

            COALESCE((SELECT SUM(ct.expected_hours)
                      FROM cousre_topics ct
                      WHERE ct.course_id = ca.course_id), 0) AS expected_hours,

            COALESCE((SELECT SUM(cc.hours_taught)
                      FROM course_coverage cc
                      WHERE cc.assignment_id = ca.assignment_id), 0) AS hours_taught

        FROM course_assgnment ca

        INNER JOIN courses c
            ON c.course_id = ca.course_id

        LEFT JOIN programs p
            ON p.program_id = ca.program_id

        LEFT JOIN academic_session a
            ON a.session_id = ca.session_id

        WHERE ca.lecturer_id = :lecturer_id

        ORDER BY
            c.course_code ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':lecturer_id' => $lecturer_id
    ]);

    $courses =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $database_error =
        $e->getMessage();

    $courses = [];
}


/* =========================================================
   CALCULATE OVERALL STATISTICS
========================================================= */

$total_courses = count($courses);

$total_topics = 0;

$total_completed = 0;

$total_in_progress = 0;

$total_hours = 0;

$total_expected_hours = 0;


foreach ($courses as $course) {

    $total_topics +=
        (int)$course['total_topics'];

    $total_completed +=
        (int)$course['completed_topics'];

    $total_in_progress +=
        (int)$course['in_progress_topics'];

    $total_hours +=
        (float)$course['hours_taught'];

    $total_expected_hours +=
        (float)$course['expected_hours'];

    $course['remaining_topics'] = max(
        0,
        (int)$course['total_topics'] - (int)$course['completed_topics']
    );

    $course['remaining_hours'] = max(
        0,
        (float)$course['expected_hours'] - (float)$course['hours_taught']
    );

    $course['progress'] = (float)$course['expected_hours'] > 0
        ? min(100, max(0, ((float)$course['hours_taught'] / (float)$course['expected_hours']) * 100))
        : 0;
}


/* =========================================================
   OVERALL PROGRESS
========================================================= */

if ($total_expected_hours > 0) {

    $overall_progress =
        ($total_hours / $total_expected_hours) * 100;

} else {

    $overall_progress = 0;
}

$overall_progress =
    min(
        100,
        round(
            $overall_progress,
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
Course Progress | Lecturer
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
   TOPBAR
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
   OVERALL PROGRESS
========================================================= */

.overall-card {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    padding: 20px;

    margin-bottom: 20px;

    box-shadow:
        0 3px 12px rgba(20,50,35,.05);
}


.overall-top {

    display: flex;

    justify-content:
        space-between;

    align-items: center;

    margin-bottom: 10px;
}


.overall-top h3 {

    font-size: 12px;

    color: #073f2a;
}


.overall-percent {

    font-size: 20px;

    font-weight: 800;

    color: #0b5d3b;
}


.progress {

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
}


/* =========================================================
   COURSE GRID
========================================================= */

.course-grid {

    display: grid;

    grid-template-columns:
        repeat(2, 1fr);

    gap: 18px;
}


/* =========================================================
   COURSE CARD
========================================================= */

.course-card {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    padding: 20px;

    box-shadow:
        0 3px 12px rgba(20,50,35,.05);
}


.course-code {

    display: inline-block;

    background: #e8f5ed;

    color: #0b5d3b;

    padding:
        6px 9px;

    border-radius: 6px;

    font-size: 10px;

    font-weight: 800;
}


.course-name {

    margin-top: 9px;

    font-size: 14px;

    font-weight: 800;

    color: #073f2a;
}


.course-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    margin-top: 10px;
}


.meta {

    background: #f5f8f6;

    padding:
        6px 8px;

    border-radius: 5px;

    font-size: 8px;

    color: #687871;
}


/* =========================================================
   COURSE PROGRESS
========================================================= */

.course-progress {

    margin-top: 20px;
}


.progress-header {

    display: flex;

    justify-content:
        space-between;

    margin-bottom: 7px;
}


.progress-header span {

    font-size: 9px;

    color: #7b8983;
}


.progress-header strong {

    font-size: 10px;

    color: #0b5d3b;
}


/* =========================================================
   COURSE STATS
========================================================= */

.course-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 8px;

    margin-top: 18px;
}


.course-stat {

    background: #fafcfb;

    border:
        1px solid #edf1ef;

    padding: 10px;

    border-radius: 7px;

    text-align: center;
}


.course-stat small {

    display: block;

    font-size: 7px;

    color: #89958f;

    text-transform: uppercase;

    font-weight: 700;
}


.course-stat strong {

    display: block;

    margin-top: 5px;

    font-size: 13px;

    color: #073f2a;
}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-block;

    margin-top: 15px;

    padding:
        6px 9px;

    border-radius: 6px;

    font-size: 8px;

    font-weight: 800;
}


.status.completed {

    background: #e8f5ed;

    color: #0b5d3b;
}


.status.progressing {

    background: #fff5dd;

    color: #946a00;
}


.status.not-started {

    background: #f1f3f2;

    color: #687871;
}


/* =========================================================
   BUTTON
========================================================= */

.course-footer {

    display: flex;

    justify-content:
        flex-end;

    margin-top: 18px;
}


.view-btn {

    display: inline-block;

    background: #0b5d3b;

    color: #ffffff;

    text-decoration: none;

    padding:
        9px 14px;

    border-radius: 7px;

    font-size: 9px;

    font-weight: 800;
}


.view-btn:hover {

    background: #073f2a;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    background: #ffffff;

    border:
        1px solid #e5ebe7;

    border-radius: 10px;

    padding: 50px 20px;

    text-align: center;

    color: #89958f;

    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px) {

    .course-grid {

        grid-template-columns: 1fr;
    }

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

    .page-heading {

        align-items: flex-start;

        flex-direction: column;

        gap: 10px;
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
    href="my_courses.php"
    class="menu-link"
>

<span class="menu-icon">
▦
</span>

My Courses

</a>


<a
    href="course_progress.php"
    class="menu-link active"
>

<span class="menu-icon">
▥
</span>

Course Progress

</a>


<a
    href="coverage.php"
    class="menu-link"
>

<span class="menu-icon">
✓
</span>

My Coverage

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
Course Progress
</h1>

<p>
Monitor your teaching progress for assigned courses
</p>

</div>


<div class="user-info">


<div class="user-avatar">

<?=e(
    strtoupper(
        substr(
            $lecturer_name,
            0,
            1
        )
    )
)?>

</div>


<div>

<strong>
<?=e($lecturer_name)?>
</strong>

<span>
Lecturer
</span>

</div>

</div>


</header>



<section class="content">


<!-- =====================================================
     HEADING
===================================================== -->

<div class="page-heading">


<div>

<h2>
My Course Progress
</h2>

<p>
Track topics covered and teaching hours for your assigned courses.
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
Completed Topics
</small>

<strong>
<?=e($total_completed)?>
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
     OVERALL PROGRESS
===================================================== -->

<div class="overall-card">


<div class="overall-top">

<h3>
OVERALL COURSE COVERAGE
</h3>

<div class="overall-percent">

<?=e($overall_progress)?>%

</div>

</div>


<div class="progress">

<div
    class="progress-fill"
    style="width: <?=e($overall_progress)?>%;"
></div>

</div>


</div>



<!-- =====================================================
     COURSE LIST
===================================================== -->

<?php if (!empty($courses)): ?>


<div class="course-grid">


<?php foreach ($courses as $course): ?>


<?php

$total =
    (int)$course['total_topics'];

$completed =
    (int)$course['completed_topics'];

$in_progress =
    (int)$course['in_progress_topics'];


if ($total > 0) {

    $percentage =
        (float)($course['progress'] ?? 0);

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


if ($percentage >= 100) {

    $status_text =
        "Completed";

    $status_class =
        "completed";

} elseif ($percentage <= 0) {

    $status_text =
        "Not Started";

    $status_class =
        "not-started";

} elseif (
    !empty($course['end_date']) &&
    strtotime($course['end_date']) < time()
) {

    $status_text =
        "Behind Schedule";

    $status_class =
        "progressing";

} elseif ($percentage > 0) {

    $status_text =
        "On Track";

    $status_class =
        "progressing";

}

?>


<div class="course-card">


<div class="course-code">

<?=e(
    $course['course_code']
)?>

</div>


<div class="course-name">

<?=e(
    $course['course_name']
)?>

</div>



<!-- COURSE META -->

<div class="course-meta">


<div class="meta">

Program:
<?=e(
    $course['program_name']
    ?? 'N/A'
)?>

</div>


<div class="meta">

Level:
<?=e(
    $course['level']
    ?? 'N/A'
)?>

</div>


<div class="meta">

Semester:
<?=e(
    $course['semester']
    ?? 'N/A'
)?>

</div>


<div class="meta">

Academic Year:
<?=e(
    $course['session_name']
    ?? 'N/A'
)?>

</div>


</div>



<!-- PROGRESS -->

<div class="course-progress">


<div class="progress-header">

<span>
Course Coverage
</span>

<strong>
<?=e($percentage)?>%
</strong>

</div>


<div class="progress">

<div
    class="progress-fill"
    style="width: <?=e($percentage)?>%;"
></div>

</div>


</div>



<!-- COURSE STATS -->

<div class="course-stats">


<div class="course-stat">

<small>
Topics
</small>

<strong>
<?=e($total)?>
</strong>

</div>


<div class="course-stat">

<small>
Remaining
</small>

<strong>
<?=e($course['remaining_topics'])?>
</strong>

</div>


<div class="course-stat">

<small>
Completed
</small>

<strong>
<?=e($completed)?>
</strong>

</div>


<div class="course-stat">

<small>
Hours
</small>

<strong>
<?=e(
        number_format(
        (float)$course['hours_taught'],
        1
    )
)?>
</strong>

</div>


<div class="course-stat">

<small>
Hours Left
</small>

<strong>
<?=e(number_format((float)$course['remaining_hours'], 1))?>
</strong>

</div>


</div>



<!-- STATUS -->

<div class="status <?=e($status_class)?>">

<?=e($status_text)?>

</div>



<!-- FOOTER -->

<div class="course-footer">

<a
    href="coverage.php?assignment_id=<?=e(
        $course['assignment_id']
    )?>"
    class="view-btn"
>

View Progress

</a>

</div>


</div>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="empty">

You currently have no courses assigned to you.

Your courses will appear here after the HOD
assigns them to you.

</div>


<?php endif; ?>


</section>


</main>


</body>

</html>