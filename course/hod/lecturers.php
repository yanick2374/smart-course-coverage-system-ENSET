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
   SEARCH
========================================================= */

$search = trim($_GET['search'] ?? '');


/* =========================================================
   GET LECTURERS
========================================================= */

$lecturers = [];

try {

    $sql = "

        SELECT

            l.lecturer_id,
            l.staff_no,
            l.full_name,
            l.email,
            l.phone,
            l.department_id,

            d.department_name,

            COUNT(
                DISTINCT ca.assignment_id
            ) AS assigned_courses,

            COUNT(
                DISTINCT cc.coverage_id
            ) AS coverage_records,

            COALESCE(
                SUM(cc.hours_taught),
                0
            ) AS hours_taught

        FROM lecturers l

        LEFT JOIN departments d
            ON d.department_id = l.department_id

        LEFT JOIN course_assgnment ca
            ON ca.lecturer_id = l.lecturer_id

        LEFT JOIN course_coverage cc
            ON cc.assignment_id = ca.assignment_id

        WHERE l.department_id = :department_id

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
                l.full_name LIKE :search
                OR l.staff_no LIKE :search
                OR l.email LIKE :search
                OR l.phone LIKE :search
            )

        ";

        $params[':search'] =
            '%' . $search . '%';
    }


    $sql .= "

        GROUP BY

            l.lecturer_id,
            l.staff_no,
            l.full_name,
            l.email,
            l.phone,
            l.department_id,
            d.department_name

        ORDER BY
            l.full_name ASC

    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $lecturers =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $database_error =
        $e->getMessage();

    $lecturers = [];
}


/* =========================================================
   STATISTICS
========================================================= */

$total_lecturers =
    count($lecturers);

$total_assigned_courses = 0;

$total_coverage_records = 0;

$total_hours_taught = 0;


foreach ($lecturers as $lecturer) {

    $total_assigned_courses +=
        (int)$lecturer['assigned_courses'];

    $total_coverage_records +=
        (int)$lecturer['coverage_records'];

    $total_hours_taught +=
        (float)$lecturer['hours_taught'];
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
Lecturers | HOD
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

    display: flex;

    justify-content:
        space-between;

    align-items: center;
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
   SEARCH
========================================================= */

.search-area {

    padding:
        15px 20px;

    border-bottom:
        1px solid #e8eeeb;

    display: flex;

    gap: 8px;
}


.search-area input {

    flex: 1;

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    padding:
        10px 12px;

    font-size: 10px;

    outline: none;
}


.search-btn {

    border: none;

    background: #0b5d3b;

    color: #ffffff;

    padding:
        10px 18px;

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
        10px 14px;

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

    min-width: 1050px;
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
        14px 10px;

    font-size: 10px;

    border-bottom:
        1px solid #edf1ef;

    vertical-align: middle;
}


thead th:first-child,
tbody td:first-child {

    padding-left: 20px;
}


/* =========================================================
   LECTURER
========================================================= */

.lecturer-name {

    font-weight: 800;

    color: #073f2a;

    font-size: 11px;
}


.staff-no {

    color: #89958f;

    font-size: 9px;

    margin-top: 4px;
}


.contact {

    color: #687871;

    font-size: 9px;
}


.department-text {

    color: #687871;

    font-size: 10px;
}


/* =========================================================
   NUMBERS
========================================================= */

.number {

    font-weight: 800;

    color: #0b5d3b;
}


/* =========================================================
   ACTION
========================================================= */

.view-btn {

    display: inline-block;

    padding:
        7px 11px;

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
    class="menu-link active"
>

<span class="menu-icon">
♟
</span>

Lecturers

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
    class="menu-link"
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
     MAIN
========================================================= -->

<main class="main">


<header class="topbar">


<div>

<h1>
Lecturers
</h1>

<p>
Manage and monitor lecturers in your department
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
Department Lecturers
</h2>

<p>
View lecturers, their assigned courses and teaching activity.
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
Total Lecturers
</small>

<strong>
<?=e($total_lecturers)?>
</strong>

</div>


<div class="stat-card">

<small>
Assigned Courses
</small>

<strong>
<?=e($total_assigned_courses)?>
</strong>

</div>


<div class="stat-card">

<small>
Coverage Records
</small>

<strong>
<?=e($total_coverage_records)?>
</strong>

</div>


<div class="stat-card">

<small>
Hours Taught
</small>

<strong>
<?=e(
    number_format(
        $total_hours_taught,
        1
    )
)?>
</strong>

</div>


</div>



<!-- =====================================================
     LECTURER PANEL
===================================================== -->

<div class="panel">


<div class="panel-header">


<div>

<h3>
LECTURERS IN DEPARTMENT
</h3>

<p>
Lecturers assigned to <?=e($department_name)?>.
</p>

</div>


</div>



<!-- =====================================================
     SEARCH
===================================================== -->

<form
    method="GET"
    class="search-area"
>


<input
    type="text"
    name="search"
    placeholder="Search lecturer by name, staff number, email or phone..."
    value="<?=e($search)?>"
>


<button
    type="submit"
    class="search-btn"
>

Search

</button>


<a
    href="lecturers.php"
    class="clear-btn"
>

Clear

</a>


</form>



<!-- =====================================================
     TABLE
===================================================== -->

<div class="table-container">


<table>


<thead>

<tr>

<th>
LECTURER
</th>

<th>
CONTACT
</th>

<th>
DEPARTMENT
</th>

<th>
ASSIGNED COURSES
</th>

<th>
COVERAGE RECORDS
</th>

<th>
HOURS TAUGHT
</th>

<th>
ACTION
</th>

</tr>

</thead>


<tbody>


<?php if (
    !empty($lecturers)
): ?>


<?php foreach (
    $lecturers
    as $lecturer
): ?>


<tr>


<!-- LECTURER -->

<td>

<div class="lecturer-name">

<?=e(
    $lecturer['full_name']
)?>

</div>

<div class="staff-no">

Staff No:
<?=e(
    $lecturer['staff_no']
)?>

</div>

</td>



<!-- CONTACT -->

<td>

<div class="contact">

<?=e(
    $lecturer['email']
)?>

</div>

<div class="contact">

<?=e(
    $lecturer['phone']
)?>

</div>

</td>



<!-- DEPARTMENT -->

<td>

<div class="department-text">

<?=e(
    $lecturer['department_name']
)?>

</div>

</td>



<!-- ASSIGNED COURSES -->

<td>

<span class="number">

<?=e(
    $lecturer['assigned_courses']
)?>

</span>

</td>



<!-- COVERAGE RECORDS -->

<td>

<span class="number">

<?=e(
    $lecturer['coverage_records']
)?>

</span>

</td>



<!-- HOURS -->

<td>

<span class="number">

<?=e(
    number_format(
        (float)$lecturer['hours_taught'],
        1
    )
)?>

</span>

hrs

</td>



<!-- ACTION -->

<td>

<a
    href="lecturer_details.php?id=<?=e(
        $lecturer['lecturer_id']
    )?>"
    class="view-btn"
>

View Details

</a>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="7"
    class="empty"
>

<?php if ($search !== ''): ?>

No lecturers found matching:

<strong>
<?=e($search)?>
</strong>

<?php else: ?>

No lecturers have been registered
in your department yet.

<?php endif; ?>

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