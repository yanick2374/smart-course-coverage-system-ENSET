<?php

session_start();

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['logged_in']) ||
    empty($_SESSION['user_id'])
) {
    header("Location: ../index.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK ROLE
|--------------------------------------------------------------------------
*/

$userRole = strtolower(
    trim($_SESSION['role'] ?? '')
);

if (
    $userRole !== 'hod' &&
    $userRole !== 'head of department'
) {
    header("Location: ../index.php");
    exit;
}


$hodId = (int) $_SESSION['user_id'];

$hodName =
    $_SESSION['full_name']
    ?? 'Head of Department';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


$error = '';

$success = '';

$assignments = [];

$departmentName = 'Department';


/*
|--------------------------------------------------------------------------
| GET HOD DEPARTMENT
|--------------------------------------------------------------------------
|
| The HOD's department is stored in users.department_id.
|
*/

try {

    $stmt = $pdo->prepare(
        "SELECT
            u.user_id,
            u.full_name,
            u.department_id,
            d.department_name

         FROM users u

         LEFT JOIN departments1 d
         ON d.department_id = u.department_id

         WHERE u.user_id = ?

         LIMIT 1"
    );

    $stmt->execute([
        $hodId
    ]);

    $hod = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    if (!$hod) {

        throw new RuntimeException(
            'HOD account could not be found.'
        );
    }


    $departmentId =
        (int)($hod['department_id'] ?? 0);


    if ($departmentId <= 0) {

        throw new RuntimeException(
            'No department has been assigned to your HOD account.'
        );
    }


    $departmentName =
        $hod['department_name']
        ?? 'Department';


    /*
    |--------------------------------------------------------------------------
    | FILTERS
    |--------------------------------------------------------------------------
    */

    $search =
        trim($_GET['search'] ?? '');

    $semester =
        trim($_GET['semester'] ?? '');

    $level =
        trim($_GET['level'] ?? '');

    $sessionId =
        (int)($_GET['session_id'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | BUILD QUERY
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            ca.assignment_id,

            ca.semester,

            ca.level,

            ca.session_id,

            c.course_id,

            c.course_code,

            c.course_name,

            c.credit_value,

            l.lecturer_id,

            l.staff_no,

            l.full_name AS lecturer_name,

            l.email AS lecturer_email,

            p.program_id,

            p.program_name,

            a.session_name,

            COUNT(DISTINCT ct.topic_id)
                AS total_topics,

            COALESCE(
                SUM(
                    DISTINCT cc.hours_taught
                ),
                0
            ) AS hours_taught

        FROM course_assgnment ca


        INNER JOIN courses c

            ON c.course_id =
               ca.course_id


        INNER JOIN lecturers l

            ON l.lecturer_id =
               ca.lecturer_id


        INNER JOIN programs p

            ON p.program_id =
               ca.program_id


        INNER JOIN academic_session a

            ON a.session_id =
               ca.session_id


        LEFT JOIN cousre_topics ct

            ON ct.course_id =
               c.course_id


        LEFT JOIN course_coverage cc

            ON cc.assignment_id =
               ca.assignment_id


        WHERE

            c.department_id = :department_id

            AND l.department_id = :lecturer_department_id

    ";


    $params = [

        ':department_id' =>
            $departmentId,

        ':lecturer_department_id' =>
            $departmentId

    ];


    /*
    |--------------------------------------------------------------------------
    | SEARCH
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $sql .= "

            AND (
                c.course_code LIKE :search
                OR c.course_name LIKE :search
                OR l.full_name LIKE :search
                OR l.staff_no LIKE :search
                OR p.program_name LIKE :search
            )

        ";

        $params[':search'] =
            '%' . $search . '%';
    }


    /*
    |--------------------------------------------------------------------------
    | SEMESTER FILTER
    |--------------------------------------------------------------------------
    */

    if ($semester !== '') {

        $sql .= "

            AND ca.semester = :semester

        ";

        $params[':semester'] =
            $semester;
    }


    /*
    |--------------------------------------------------------------------------
    | LEVEL FILTER
    |--------------------------------------------------------------------------
    */

    if ($level !== '') {

        $sql .= "

            AND ca.level = :level

        ";

        $params[':level'] =
            $level;
    }


    /*
    |--------------------------------------------------------------------------
    | ACADEMIC YEAR FILTER
    |--------------------------------------------------------------------------
    */

    if ($sessionId > 0) {

        $sql .= "

            AND ca.session_id = :session_id

        ";

        $params[':session_id'] =
            $sessionId;
    }


    $sql .= "

        GROUP BY

            ca.assignment_id,
            ca.semester,
            ca.level,
            ca.session_id,

            c.course_id,
            c.course_code,
            c.course_name,
            c.credit_value,

            l.lecturer_id,
            l.staff_no,
            l.full_name,
            l.email,

            p.program_id,
            p.program_name,

            a.session_name

        ORDER BY

            a.start_date DESC,
            c.course_code ASC,
            l.full_name ASC

    ";


    /*
    |--------------------------------------------------------------------------
    | EXECUTE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $assignments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | GET ACADEMIC YEARS FOR FILTER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query(
        "SELECT
            session_id,
            session_name

         FROM academic_session

         ORDER BY start_date DESC"
    );

    $academicYears =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


}
catch (RuntimeException $ex) {

    $error =
        $ex->getMessage();

    $academicYears = [];

}
catch (PDOException $ex) {

    $error =
        'Database error: ' .
        $ex->getMessage();

    $academicYears = [];
}


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalAssignments =
    count($assignments);

$totalTopics = 0;

$totalHours = 0;


foreach ($assignments as $assignment) {

    $totalTopics +=
        (int)$assignment['total_topics'];

    $totalHours +=
        (float)$assignment['hours_taught'];
}


/*
|--------------------------------------------------------------------------
| CALCULATE PROGRESS
|--------------------------------------------------------------------------
|
| Progress is based on topics that have
| coverage records.
|
*/

foreach (
    $assignments
    as &$assignment
) {

    $assignment['progress'] = 0;


    if (
        (int)$assignment['total_topics'] > 0
    ) {

        /*
        | Count coverage records for the
        | assignment.
        */

        $stmt = $pdo->prepare(
            "SELECT
                COUNT(DISTINCT topic_id)

             FROM course_coverage

             WHERE assignment_id = ?"
        );

        $stmt->execute([
            $assignment['assignment_id']
        ]);


        $coveredTopics =
            (int)$stmt->fetchColumn();


        $assignment['progress'] =
            min(
                100,
                round(
                    (
                        $coveredTopics /
                        $assignment['total_topics']
                    ) * 100
                )
            );
    }
}

unset($assignment);


/*
|--------------------------------------------------------------------------
| PAGE DATA
|--------------------------------------------------------------------------
*/

$activePage =
    'assigned_courses';

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
Assigned Courses | HOD
</title>


<style>

/* ==================================================
   GLOBAL
================================================== */

:root {

    --green: #0b5d3b;

    --green-dark: #073f2a;

    --green-light: #e8f5ed;

    --text: #18372b;

    --muted: #708078;

    --border: #e7ece9;

    --bg: #f5f8f6;

    --danger: #b42318;

    --danger-bg: #fff0ee;

    --shadow:
        0 4px 16px rgba(17,52,38,.06);
}


* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: var(--bg);

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: var(--text);
}


/* ==================================================
   SIDEBAR
================================================== */

.sidebar {

    width: 260px;

    position: fixed;

    left: 0;

    top: 0;

    bottom: 0;

    background: #fff;

    border-right:
        1px solid var(--border);

    padding: 20px 14px;

    z-index: 20;
}


.brand {

    display: flex;

    align-items: center;

    gap: 11px;

    padding:
        4px 8px 22px;
}


.brand img {

    width: 45px;

    height: 45px;

    object-fit: contain;
}


.brand-title {

    font-size: 12px;

    font-weight: 800;

    color: var(--green-dark);
}


.brand-title span {

    display: block;

    font-size: 9px;

    color: #718078;

    margin-top: 3px;

    line-height: 1.4;
}


.menu-title {

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1px;

    color: #98a39e;

    margin:
        18px 9px 8px;
}


.role {

    background: var(--green-light);

    color: var(--green);

    border-radius: 9px;

    padding: 10px;

    font-size: 11px;

    font-weight: 700;
}


.role-badge {

    display: inline-grid;

    place-items: center;

    width: 24px;

    height: 24px;

    background: #fff;

    border-radius: 7px;

    margin-right: 7px;
}


.side-link {

    display: flex;

    align-items: center;

    gap: 10px;

    text-decoration: none;

    color: #56675f;

    font-size: 12px;

    font-weight: 600;

    padding: 11px 10px;

    border-radius: 8px;

    margin: 2px 0;
}


.side-link:hover,
.side-link.active {

    background:
        var(--green-light);

    color:
        var(--green);
}


.icon {

    width: 20px;

    text-align: center;
}


.side-bottom {

    position: absolute;

    bottom: 18px;

    left: 20px;

    right: 20px;

    text-align: center;

    font-size: 9px;

    color: #89958f;
}


/* ==================================================
   MAIN
================================================== */

.main {

    margin-left: 260px;

    width:
        calc(100% - 260px);

    min-height: 100vh;
}


.topbar {

    height: 78px;

    background: #fff;

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;
}


.heading h1 {

    margin: 0;

    font-size: 22px;

    color:
        var(--green-dark);
}


.heading p {

    margin: 5px 0 0;

    font-size: 11px;

    color:
        var(--muted);
}


.profile {

    display: flex;

    align-items: center;

    gap: 10px;
}


.avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    object-fit: contain;

    border:
        1px solid var(--border);
}


.profile-text strong {

    display: block;

    font-size: 11px;
}


.profile-text span {

    display: block;

    font-size: 9px;

    color:
        var(--muted);

    margin-top: 3px;
}


/* ==================================================
   CONTENT
================================================== */

.content {

    padding:
        25px 30px;
}


.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 20px;
}


.page-title h2 {

    margin: 0;

    font-size: 19px;

    color:
        var(--green-dark);
}


.page-title p {

    margin: 5px 0 0;

    font-size: 11px;

    color:
        var(--muted);
}


.department-badge {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    background:
        var(--green-light);

    color:
        var(--green);

    padding:
        9px 13px;

    border-radius: 8px;

    font-size: 10px;

    font-weight: 800;
}


/* ==================================================
   ALERT
================================================== */

.alert {

    padding:
        12px 15px;

    border-radius: 8px;

    font-size: 11px;

    margin-bottom: 18px;
}


.alert.error {

    background:
        var(--danger-bg);

    color:
        var(--danger);
}


/* ==================================================
   STATS
================================================== */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 15px;

    margin-bottom: 18px;
}


.stat {

    background: #fff;

    border:
        1px solid var(--border);

    border-radius: 10px;

    padding: 17px;

    box-shadow:
        var(--shadow);
}


.stat-label {

    font-size: 9px;

    font-weight: 800;

    color:
        var(--muted);

    text-transform:
        uppercase;
}


.stat-value {

    margin-top: 6px;

    font-size: 23px;

    font-weight: 800;

    color:
        var(--green-dark);
}


/* ==================================================
   MAIN PANEL
================================================== */

.panel {

    background: #fff;

    border:
        1px solid var(--border);

    border-radius: 10px;

    box-shadow:
        var(--shadow);

    overflow: hidden;
}


.panel-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid var(--border);

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.panel-header h3 {

    margin: 0;

    font-size: 12px;

    color:
        var(--green-dark);
}


.panel-header p {

    margin: 4px 0 0;

    font-size: 10px;

    color:
        var(--muted);
}


/* ==================================================
   FILTERS
================================================== */

.filters {

    padding:
        15px 20px;

    border-bottom:
        1px solid var(--border);

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

    background: #fff;
}


.filters input {

    flex: 1;

    min-width: 220px;
}


.filters select {

    min-width: 150px;
}


.filters button {

    border: none;

    border-radius: 7px;

    padding:
        9px 13px;

    background:
        var(--green);

    color: white;

    font-size: 10px;

    font-weight: 700;

    cursor: pointer;
}


.clear-filter {

    text-decoration: none;

    padding:
        9px 12px;

    border-radius: 7px;

    background:
        #eef3f0;

    color:
        var(--green);

    font-size: 10px;

    font-weight: 700;
}


/* ==================================================
   TABLE
================================================== */

.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1050px;
}


th {

    background:
        #fbfcfb;

    color:
        #6c7b75;

    font-size: 9px;

    font-weight: 800;

    text-align: left;

    padding:
        12px 10px;

    border-bottom:
        1px solid var(--border);

    white-space: nowrap;
}


td {

    padding:
        13px 10px;

    border-bottom:
        1px solid #edf1ef;

    font-size: 10px;

    vertical-align: middle;
}


th:first-child,
td:first-child {

    padding-left: 20px;
}


.course-code {

    color:
        var(--green);

    font-weight: 800;

    font-size: 11px;
}


.course-name {

    margin-top: 3px;

    color:
        #6c7973;

    font-size: 9px;
}


.lecturer-name {

    font-weight: 700;

    color:
        var(--text);
}


.staff-no {

    margin-top: 3px;

    color:
        var(--muted);

    font-size: 9px;
}


.badge {

    display: inline-block;

    padding:
        5px 8px;

    border-radius: 20px;

    background:
        var(--green-light);

    color:
        var(--green);

    font-size: 9px;

    font-weight: 700;
}


/* ==================================================
   PROGRESS
================================================== */

.progress-container {

    width: 100px;
}


.progress-top {

    display: flex;

    justify-content: space-between;

    margin-bottom: 5px;

    font-size: 9px;

    font-weight: 700;
}


.progress-bar {

    width: 100%;

    height: 6px;

    background:
        #edf1ef;

    border-radius: 10px;

    overflow: hidden;
}


.progress-fill {

    height: 100%;

    background:
        var(--green);

    border-radius: 10px;
}


/* ==================================================
   ACTION
================================================== */

.view-btn {

    display: inline-block;

    text-decoration: none;

    padding:
        7px 10px;

    border-radius: 7px;

    background:
        var(--green-light);

    color:
        var(--green);

    font-size: 9px;

    font-weight: 800;
}


.view-btn:hover {

    background:
        var(--green);

    color: #fff;
}


/* ==================================================
   EMPTY
================================================== */

.empty {

    text-align: center;

    padding: 45px 20px;

    color:
        var(--muted);

    font-size: 11px;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media(max-width:900px) {

    .stats {

        grid-template-columns:
            1fr;
    }

}


@media(max-width:760px) {

    .sidebar {

        width: 220px;
    }

    .main {

        margin-left: 220px;

        width:
            calc(100% - 220px);
    }

    .content {

        padding:
            20px 15px;
    }

    .topbar {

        padding:
            0 15px;
    }

    .page-header {

        align-items:
            flex-start;

        gap: 10px;

        flex-direction:
            column;
    }

}

</style>

</head>


<body>


<!-- ==================================================
     SIDEBAR
================================================== -->

<aside class="sidebar">


<div class="brand">

<img
src="../assets/images/ub-logo.png"
alt="Logo"
>


<div class="brand-title">

COURSE COVERAGE

<span>
MANAGEMENT SYSTEM<br>
HTTTC KUMBA
</span>

</div>

</div>


<div class="menu-title">
USER ROLE
</div>


<div class="role">

<span class="role-badge">
♙
</span>

Head of Department

</div>


<div class="menu-title">
MAIN MENU
</div>


<a
class="side-link"
href="dashboard.php"
>

<span class="icon">
⌂
</span>

Dashboard

</a>


<a
class="side-link"
href="lecturers.php"
>

<span class="icon">
♟
</span>

Lecturers

</a>


<a
class="side-link"
href="courses.php"
>

<span class="icon">
▦
</span>

Courses

</a>


<a
class="side-link"
href="assign_course.php"
>

<span class="icon">
▤
</span>

Course Assignment

</a>


<a
class="side-link active"
href="assigned_courses.php"
>

<span class="icon">
◫
</span>

Assigned Courses

</a>


<a
class="side-link"
href="coverage.php"
>

<span class="icon">
◳
</span>

Coverage Review

</a>


<a
class="side-link"
href="reports.php"
>

<span class="icon">
▥
</span>

Reports

</a>


<div class="menu-title">
ACCOUNT
</div>


<a
class="side-link"
href="../change_password.php"
>

<span class="icon">
▣
</span>

Change Password

</a>


<a
class="side-link"
href="../auth/logout.php"
>

<span class="icon">
↪
</span>

Logout

</a>


<div class="side-bottom">

HTTTC KUMBA

</div>


</aside>



<!-- ==================================================
     MAIN
================================================== -->

<main class="main">


<header class="topbar">


<div class="heading">

<h1>
Assigned Courses
</h1>

<p>
View and monitor courses assigned to lecturers.
</p>

</div>


<div class="profile">

<img
class="avatar"
src="../assets/images/ub-logo.png"
alt="HOD"
>


<div class="profile-text">

<strong>
<?=e($hodName)?>
</strong>

<span>
Head of Department
</span>

</div>

</div>


</header>



<section class="content">


<!-- PAGE HEADER -->

<div class="page-header">


<div class="page-title">

<h2>
Assigned Courses
</h2>

<p>
Courses currently assigned within your department.
</p>

</div>


<div class="department-badge">

▣

<?=e($departmentName)?>

</div>


</div>



<?php if ($error): ?>

<div class="alert error">

<?=e($error)?>

</div>

<?php endif; ?>



<!-- ==================================================
     STATISTICS
================================================== -->

<div class="stats">


<div class="stat">

<div class="stat-label">
Total Assignments
</div>

<div class="stat-value">
<?=e($totalAssignments)?>
</div>

</div>


<div class="stat">

<div class="stat-label">
Course Topics
</div>

<div class="stat-value">
<?=e($totalTopics)?>
</div>

</div>


<div class="stat">

<div class="stat-label">
Hours Reported
</div>

<div class="stat-value">
<?=e(number_format($totalHours, 1))?>
</div>

</div>


</div>



<!-- ==================================================
     TABLE
================================================== -->

<section class="panel">


<div class="panel-header">


<div>

<h3>
DEPARTMENT COURSE ASSIGNMENTS
</h3>

<p>
All course assignments belonging to
<?=e($departmentName)?>.
</p>

</div>


<a
class="view-btn"
href="assign_course.php"
>

+ Assign Course

</a>


</div>



<!-- FILTERS -->

<form
class="filters"
method="get"
>


<input
type="text"
name="search"
placeholder="Search course, lecturer or program..."
value="<?=e($search)?>"
>


<select
name="session_id"
>

<option value="">
All Academic Years
</option>


<?php foreach (
    $academicYears
    as $year
): ?>


<option
value="<?=e($year['session_id'])?>"

<?=(
    $sessionId ==
    $year['session_id']
)
? 'selected'
: ''
?>

>

<?=e($year['session_name'])?>

</option>


<?php endforeach; ?>

</select>



<select
name="semester"
>

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



<select
name="level"
>

<option value="">
All Levels
</option>

<option
value="100"

<?=(
    $level === '100'
)
? 'selected'
: ''
?>

>

100

</option>

<option
value="200"

<?=(
    $level === '200'
)
? 'selected'
: ''
?>

>

200

</option>

<option
value="300"

<?=(
    $level === '300'
)
? 'selected'
: ''
?>

>

300

</option>

<option
value="400"

<?=(
    $level === '400'
)
? 'selected'
: ''
?>

>

400

</option>

<option
value="500"

<?=(
    $level === '500'
)
? 'selected'
: ''
?>

>

500

</option>

</select>



<button
type="submit"
>

Filter

</button>


<a
class="clear-filter"
href="assigned_courses.php"
>

Clear

</a>


</form>



<!-- TABLE -->

<div class="table-wrap">


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
ACADEMIC YEAR
</th>

<th>
SEMESTER
</th>

<th>
LEVEL
</th>

<th>
PROGRESS
</th>

<th>
ACTION
</th>

</tr>

</thead>


<tbody>


<?php if (
    !empty($assignments)
): ?>


<?php foreach (
    $assignments
    as $assignment
): ?>


<tr>


<td>

<div class="course-code">

<?=e(
    $assignment['course_code']
)?>

</div>

<div class="course-name">

<?=e(
    $assignment['course_name']
)?>

</div>

</td>



<td>

<div class="lecturer-name">

<?=e(
    $assignment['lecturer_name']
)?>

</div>

<div class="staff-no">

<?=e(
    $assignment['staff_no']
)?>

</div>

</td>



<td>

<?=e(
    $assignment['program_name']
)?>

</td>



<td>

<?=e(
    $assignment['session_name']
)?>

</td>



<td>

<span class="badge">

<?=e(
    $assignment['semester']
)?>

</span>

</td>



<td>

<span class="badge">

<?=e(
    $assignment['level']
)?>

</span>

</td>



<td>


<div class="progress-container">


<div class="progress-top">

<span>
Progress
</span>

<span>
<?=e(
    $assignment['progress']
)?>%
</span>

</div>


<div class="progress-bar">

<div
class="progress-fill"
style="
width:
<?=e(
    $assignment['progress']
)?>%;
"
></div>

</div>


</div>


</td>



<td>

<a
class="view-btn"
href="assignment_details.php?id=<?=e(
    $assignment['assignment_id']
)?>"
>

View

</a>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
colspan="8"
class="empty"
>

<?php if ($error): ?>

Unable to load assigned courses.

<?php else: ?>

No courses have been assigned
to lecturers in your department yet.

<?php endif; ?>

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</section>


</section>


</main>


</body>

</html>