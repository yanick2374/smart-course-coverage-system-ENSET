<?php

session_start();

if (empty($_SESSION['logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

if (!in_array($role, ['admin', 'administrator'])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';


function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ==========================================
   CSRF TOKEN
========================================== */

if (empty($_SESSION['semester_csrf'])) {

    $_SESSION['semester_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf =
    $_SESSION['semester_csrf'];


$error = '';
$success = '';

$academicYears = [];
$semesters = [];


/* ==========================================
   PROCESS REQUEST
========================================== */

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!hash_equals(
            $csrf,
            $_POST['csrf_token'] ?? ''
        )) {

            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }


        $action =
            $_POST['action'] ?? '';


        /* ======================================
           CREATE / UPDATE SEMESTER
        ====================================== */

        if ($action === 'save_semester') {

            $semesterId =
                (int)($_POST['semester_id'] ?? 0);

            $semesterName =
                trim($_POST['semester_name'] ?? '');

            $semesterCode =
                strtoupper(
                    trim(
                        $_POST['semester_code'] ?? ''
                    )
                );

            $sessionId =
                (int)($_POST['session_id'] ?? 0);

            $startDate =
                $_POST['start_date'] ?? '';

            $endDate =
                $_POST['end_date'] ?? '';

            $status =
                $_POST['status'] ?? 'Active';


            /* ==================================
               VALIDATION
            ================================== */

            if (
                $semesterName === '' ||
                $semesterCode === '' ||
                $sessionId <= 0 ||
                $startDate === '' ||
                $endDate === ''
            ) {

                throw new RuntimeException(
                    'Please complete all required fields.'
                );
            }


            if ($endDate < $startDate) {

                throw new RuntimeException(
                    'The end date cannot be earlier than the start date.'
                );
            }


            if (!in_array(
                $status,
                ['Active', 'Inactive']
            )) {

                $status = 'Active';
            }


            /* ==================================
               CHECK ACADEMIC YEAR
            ================================== */

            $stmt = $pdo->prepare(
                "SELECT session_id
                 FROM academic_session
                 WHERE session_id = ?"
            );

            $stmt->execute([
                $sessionId
            ]);


            if (!$stmt->fetch()) {

                throw new RuntimeException(
                    'The selected academic year does not exist.'
                );
            }


            /* ==================================
               CHECK DUPLICATE
            ================================== */

            if ($semesterId > 0) {

                $stmt = $pdo->prepare(
                    "SELECT semester_id
                     FROM semesters
                     WHERE semester_code = ?
                     AND session_id = ?
                     AND semester_id != ?"
                );

                $stmt->execute([
                    $semesterCode,
                    $sessionId,
                    $semesterId
                ]);

            } else {

                $stmt = $pdo->prepare(
                    "SELECT semester_id
                     FROM semesters
                     WHERE semester_code = ?
                     AND session_id = ?"
                );

                $stmt->execute([
                    $semesterCode,
                    $sessionId
                ]);
            }


            if ($stmt->fetch()) {

                throw new RuntimeException(
                    'This semester code already exists for the selected academic year.'
                );
            }


            /* ==================================
               UPDATE
            ================================== */

            if ($semesterId > 0) {

                $stmt = $pdo->prepare(
                    "UPDATE semesters
                     SET
                        semester_name = ?,
                        semester_code = ?,
                        session_id = ?,
                        start_date = ?,
                        end_date = ?,
                        status = ?
                     WHERE semester_id = ?"
                );

                $stmt->execute([
                    $semesterName,
                    $semesterCode,
                    $sessionId,
                    $startDate,
                    $endDate,
                    $status,
                    $semesterId
                ]);

                $success =
                    'Semester updated successfully.';

            }


            /* ==================================
               CREATE
            ================================== */

            else {

                $stmt = $pdo->prepare(
                    "INSERT INTO semesters
                    (
                        semester_name,
                        semester_code,
                        session_id,
                        start_date,
                        end_date,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $semesterName,
                    $semesterCode,
                    $sessionId,
                    $startDate,
                    $endDate,
                    $status
                ]);

                $success =
                    'Semester created successfully.';
            }
        }


        /* ======================================
           DELETE
        ====================================== */

        if ($action === 'delete_semester') {

            $semesterId =
                (int)($_POST['semester_id'] ?? 0);


            if ($semesterId <= 0) {

                throw new RuntimeException(
                    'Invalid semester selected.'
                );
            }


            $stmt = $pdo->prepare(
                "DELETE FROM semesters
                 WHERE semester_id = ?"
            );

            $stmt->execute([
                $semesterId
            ]);


            if ($stmt->rowCount() === 0) {

                throw new RuntimeException(
                    'Semester was not found.'
                );
            }


            $success =
                'Semester deleted successfully.';
        }
    }


    /* ==========================================
       GET ACADEMIC YEARS
    ========================================== */

    $stmt = $pdo->query(
        "SELECT
            session_id,
            session_name,
            start_date,
            end_date,
            status

         FROM academic_session

         ORDER BY
            start_date DESC"
    );

    $academicYears =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* ==========================================
       GET SEMESTERS
    ========================================== */

    $stmt = $pdo->query(
        "SELECT

            s.semester_id,
            s.semester_name,
            s.semester_code,
            s.session_id,
            s.start_date,
            s.end_date,
            s.status,

            a.session_name

         FROM semesters s

         INNER JOIN academic_session a
         ON s.session_id = a.session_id

         ORDER BY
            a.start_date DESC,
            s.start_date ASC"
    );

    $semesters =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (RuntimeException $ex) {

    $error =
        $ex->getMessage();

} catch (PDOException $ex) {

    $error =
        'Database error: ' .
        $ex->getMessage();
}


/* ==========================================
   EDIT SEMESTER
========================================== */

$editId =
    (int)($_GET['edit'] ?? 0);

$editSemester = null;


foreach ($semesters as $semester) {

    if (
        (int)$semester['semester_id']
        === $editId
    ) {

        $editSemester =
            $semester;

        break;
    }
}


/* ==========================================
   STATISTICS
========================================== */

$totalSemesters =
    count($semesters);

$activeSemesters = 0;

$inactiveSemesters = 0;


foreach ($semesters as $semester) {

    if ($semester['status'] === 'Active') {

        $activeSemesters++;

    } else {

        $inactiveSemesters++;
    }
}


$adminName =
    $_SESSION['full_name']
    ?? 'Administrator';

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
Semesters | Course Coverage Management System
</title>


<style>

/* ==========================================
   GLOBAL
========================================== */

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


/* ==========================================
   SIDEBAR
========================================== */

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

    background: var(--green-light);

    color: var(--green);
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


/* ==========================================
   MAIN
========================================== */

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

    color: var(--green-dark);
}


.heading p {

    margin: 5px 0 0;

    font-size: 11px;

    color: var(--muted);
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

    color: var(--muted);

    margin-top: 3px;
}


.content {

    padding: 25px 30px;
}


/* ==========================================
   PAGE HEADER
========================================== */

.toolbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 18px;
}


.page-title h2 {

    margin: 0;

    font-size: 19px;

    color: var(--green-dark);
}


.page-title p {

    margin: 5px 0 0;

    font-size: 11px;

    color: var(--muted);
}


/* ==========================================
   BUTTON
========================================== */

.btn {

    border: none;

    border-radius: 8px;

    padding: 10px 14px;

    font-size: 11px;

    font-weight: 700;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;
}


.btn-primary {

    background: var(--green);

    color: white;
}


.btn-primary:hover {

    background: var(--green-dark);
}


.btn-secondary {

    background: #eef3f0;

    color: var(--green);
}


.btn-danger {

    background: var(--danger-bg);

    color: var(--danger);
}


/* ==========================================
   ALERT
========================================== */

.alert {

    padding: 12px 15px;

    border-radius: 8px;

    font-size: 11px;

    margin-bottom: 18px;
}


.alert.success {

    background: #eaf7ed;

    color: #26713b;
}


.alert.error {

    background: #fff0ee;

    color: #a12b22;
}


/* ==========================================
   GRID
========================================== */

.grid {

    display: grid;

    grid-template-columns:
        360px 1fr;

    gap: 18px;

    align-items: start;
}


.panel {

    background: #fff;

    border:
        1px solid var(--border);

    border-radius: 10px;

    box-shadow: var(--shadow);

    overflow: hidden;
}


.panel-head {

    padding: 17px 20px;

    border-bottom:
        1px solid var(--border);
}


.panel-head h3 {

    margin: 0;

    font-size: 12px;

    color: var(--green-dark);
}


.panel-head p {

    margin: 4px 0 0;

    font-size: 10px;

    color: var(--muted);
}


/* ==========================================
   FORM
========================================== */

.form {

    padding: 20px;
}


.field {

    margin-bottom: 14px;
}


.field label {

    display: block;

    font-size: 10px;

    font-weight: 800;

    color: #5f7068;

    margin-bottom: 6px;
}


.field input,
.field select {

    width: 100%;

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    padding: 10px;

    font-size: 11px;

    outline: none;

    background: white;
}


.field input:focus,
.field select:focus {

    border-color:
        var(--green);
}


.form-actions {

    display: flex;

    gap: 8px;
}


/* ==========================================
   STATS
========================================== */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;

    padding: 15px 20px;

    border-bottom:
        1px solid var(--border);
}


.stat {

    background: #f7faf8;

    border-radius: 8px;

    padding: 12px;
}


.stat span {

    display: block;

    font-size: 9px;

    color: var(--muted);

    font-weight: 700;
}


.stat strong {

    display: block;

    font-size: 20px;

    color: var(--green-dark);

    margin-top: 4px;
}


/* ==========================================
   FILTER
========================================== */

.filters {

    padding: 15px 20px;

    border-bottom:
        1px solid var(--border);

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}


.search,
.filter {

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    padding: 9px 10px;

    font-size: 11px;
}


.search {

    flex: 1;

    min-width: 180px;
}


.filter {

    min-width: 180px;
}


/* ==========================================
   TABLE
========================================== */

.table-wrap {

    overflow-x: auto;
}


table {

    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}


th,
td {

    padding: 12px 10px;

    text-align: left;

    border-bottom:
        1px solid #edf1ef;
}


th {

    background: #fbfcfb;

    font-size: 9px;

    color: #6c7b75;

    letter-spacing: .4px;
}


td {

    font-size: 11px;
}


th:first-child,
td:first-child {

    padding-left: 20px;
}


.semester-name {

    font-weight: 800;

    color: var(--green);

    font-size: 12px;
}


.code {

    font-weight: 700;

    color: #63736c;
}


.badge {

    display: inline-block;

    padding: 5px 8px;

    border-radius: 20px;

    background: var(--green-light);

    color: var(--green);

    font-size: 9px;

    font-weight: 700;
}


.badge.inactive {

    background: #f1f2f2;

    color: #727b77;
}


.actions {

    display: flex;

    gap: 6px;
}


.actions form {

    display: inline;
}


.empty {

    text-align: center;

    padding: 35px;

    color: var(--muted);

    font-size: 11px;
}


@media(max-width:1100px) {

    .grid {

        grid-template-columns: 1fr;
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

        padding: 18px 15px;
    }

    .topbar {

        padding: 0 15px;
    }
}

</style>

</head>


<body>


<!-- ==========================================
     SIDEBAR
========================================== -->

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

Administrator

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
href="user.php"
>

<span class="icon">
♟
</span>

Users

</a>


<a
class="side-link"
href="department.php"
>

<span class="icon">
▣
</span>

Departments

</a>


<a
class="side-link"
href="programs.php"
>

<span class="icon">
▤
</span>

Programs

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
href="academic_years.php"
>

<span class="icon">
◫
</span>

Academic Years

</a>


<a
class="side-link active"
href="semesters.php"
>

<span class="icon">
◳
</span>

Semesters

</a>


<div class="menu-title">
ACCOUNT
</div>


<a
class="side-link"
href="profile.php"
>

<span class="icon">
◉
</span>

Profile

</a>


<a
class="side-link"
href="change_password.php"
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



<!-- ==========================================
     MAIN
========================================== -->

<main class="main">


<header class="topbar">


<div class="heading">

<h1>
Semesters
</h1>

<p>
Manage academic semesters
</p>

</div>


<div class="profile">


<img
class="avatar"
src="../assets/images/ub-logo.png"
alt="Administrator"
>


<div class="profile-text">

<strong>
<?=e($adminName)?>
</strong>

<span>
Administrator
</span>

</div>

</div>


</header>



<section class="content">


<div class="toolbar">


<div class="page-title">

<h2>
Semester Management
</h2>

<p>
Create and manage semesters under each academic year.
</p>

</div>


<a
href="#semester-form"
class="btn btn-primary"
>

＋ Create Semester

</a>


</div>



<?php if ($success): ?>

<div class="alert success">

<?=e($success)?>

</div>

<?php endif; ?>


<?php if ($error): ?>

<div class="alert error">

<?=e($error)?>

</div>

<?php endif; ?>



<div class="grid">


<!-- ==========================================
     FORM
========================================== -->

<section
class="panel"
id="semester-form"
>


<div class="panel-head">

<h3>

<?= $editSemester
    ? 'EDIT SEMESTER'
    : 'CREATE SEMESTER'
?>

</h3>

<p>

Set the semester information
and academic year.

</p>

</div>



<form
class="form"
method="post"
>


<input
type="hidden"
name="csrf_token"
value="<?=e($csrf)?>"
>


<input
type="hidden"
name="action"
value="save_semester"
>


<input
type="hidden"
name="semester_id"
value="<?=e(
    $editSemester['semester_id'] ?? 0
)?>"
>


<div class="field">

<label>
SEMESTER NAME
</label>


<select
name="semester_name"
required
>


<option value="">
Select Semester
</option>


<option
value="First Semester"

<?=(
    ($editSemester['semester_name'] ?? '')
    ===
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
    ($editSemester['semester_name'] ?? '')
    ===
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
    ($editSemester['semester_name'] ?? '')
    ===
    'Summer Semester'
)
? 'selected'
: ''
?>

>

Summer Semester

</option>


</select>

</div>



<div class="field">

<label>
SEMESTER CODE
</label>


<input
type="text"
name="semester_code"
placeholder="Example: SEM1"
maxlength="20"
value="<?=e(
    $editSemester['semester_code'] ?? ''
)?>"
required
>

</div>



<div class="field">

<label>
ACADEMIC YEAR
</label>


<select
name="session_id"
required
>


<option value="">
Select Academic Year
</option>


<?php foreach (
    $academicYears
    as $year
): ?>


<option
value="<?=e(
    $year['session_id']
)?>"

<?=(
    (int)($editSemester['session_id'] ?? 0)
    ===
    (int)$year['session_id']
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

</div>



<div class="field">

<label>
START DATE
</label>


<input
type="date"
name="start_date"
value="<?=e(
    $editSemester['start_date'] ?? ''
)?>"
required
>

</div>



<div class="field">

<label>
END DATE
</label>


<input
type="date"
name="end_date"
value="<?=e(
    $editSemester['end_date'] ?? ''
)?>"
required
>

</div>



<div class="field">

<label>
STATUS
</label>


<select
name="status"
>


<option
value="Active"

<?=(
    ($editSemester['status'] ?? 'Active')
    ===
    'Active'
)
? 'selected'
: ''
?>

>

Active

</option>


<option
value="Inactive"

<?=(
    ($editSemester['status'] ?? '')
    ===
    'Inactive'
)
? 'selected'
: ''
?>

>

Inactive

</option>


</select>

</div>



<div class="form-actions">


<button
class="btn btn-primary"
type="submit"
>

<?= $editSemester
    ? 'Save Changes'
    : 'Create Semester'
?>

</button>


<?php if ($editSemester): ?>

<a
href="semesters.php"
class="btn btn-secondary"
>

Cancel

</a>

<?php else: ?>

<button
type="reset"
class="btn btn-secondary"
>

Clear

</button>

<?php endif; ?>


</div>


</form>


</section>



<!-- ==========================================
     SEMESTER LIST
========================================== -->

<section class="panel">


<div class="panel-head">

<h3>
ALL SEMESTERS
</h3>

<p>
Semesters retrieved from the database.
</p>

</div>



<div class="stats">


<div class="stat">

<span>
TOTAL SEMESTERS
</span>

<strong>
<?=e($totalSemesters)?>
</strong>

</div>


<div class="stat">

<span>
ACTIVE
</span>

<strong>
<?=e($activeSemesters)?>
</strong>

</div>


<div class="stat">

<span>
INACTIVE
</span>

<strong>
<?=e($inactiveSemesters)?>
</strong>

</div>


</div>



<div class="filters">


<input
type="search"
class="search"
id="semesterSearch"
placeholder="Search semester..."
>


<select
class="filter"
id="yearFilter"
>


<option value="">
All Academic Years
</option>


<?php foreach (
    $academicYears
    as $year
): ?>


<option
value="<?=e(
    strtolower(
        $year['session_name']
    )
)?>"
>

<?=e(
    $year['session_name']
)?>

</option>


<?php endforeach; ?>


</select>


<select
class="filter"
id="statusFilter"
>

<option value="">
All Status
</option>

<option value="active">
Active
</option>

<option value="inactive">
Inactive
</option>

</select>


</div>



<div class="table-wrap">


<table id="semesterTable">


<thead>

<tr>

<th>
SEMESTER
</th>

<th>
CODE
</th>

<th>
ACADEMIC YEAR
</th>

<th>
START
</th>

<th>
END
</th>

<th>
STATUS
</th>

<th>
ACTIONS
</th>

</tr>

</thead>


<tbody>


<?php if ($semesters): ?>


<?php foreach (
    $semesters
    as $semester
): ?>


<tr
data-year="<?=e(
    strtolower(
        $semester['session_name']
    )
)?>"

data-status="<?=e(
    strtolower(
        $semester['status']
    )
)?>"
>


<td>

<div class="semester-name">

<?=e(
    $semester['semester_name']
)?>

</div>

</td>


<td>

<span class="code">

<?=e(
    $semester['semester_code']
)?>

</span>

</td>


<td>

<?=e(
    $semester['session_name']
)?>

</td>


<td>

<?=e(
    date(
        'd M Y',
        strtotime(
            $semester['start_date']
        )
    )
)?>

</td>


<td>

<?=e(
    date(
        'd M Y',
        strtotime(
            $semester['end_date']
        )
    )
)?>

</td>


<td>


<?php if (
    $semester['status']
    ===
    'Active'
): ?>

<span class="badge">

Active

</span>

<?php else: ?>

<span class="badge inactive">

Inactive

</span>

<?php endif; ?>


</td>


<td>


<div class="actions">


<a
class="btn btn-secondary"
href="semesters.php?edit=<?=e(
    $semester['semester_id']
)?>#semester-form"
>

Edit

</a>



<form
method="post"
onsubmit="
return confirm(
'Are you sure you want to delete this semester?'
);
"
>


<input
type="hidden"
name="csrf_token"
value="<?=e($csrf)?>"
>


<input
type="hidden"
name="action"
value="delete_semester"
>


<input
type="hidden"
name="semester_id"
value="<?=e(
    $semester['semester_id']
)?>"
>


<button
type="submit"
class="btn btn-danger"
>

Delete

</button>


</form>


</div>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
colspan="7"
class="empty"
>

No semesters have been created yet.

</td>

</tr>


<?php endif; ?>


</tbody>

</table>

</div>


</section>


</div>


</section>


</main>



<script>

const search =
    document.getElementById(
        'semesterSearch'
    );

const yearFilter =
    document.getElementById(
        'yearFilter'
    );

const statusFilter =
    document.getElementById(
        'statusFilter'
    );

const rows =
    document.querySelectorAll(
        '#semesterTable tbody tr'
    );


function filterSemesters()
{

    const searchValue =
        search.value
        .toLowerCase()
        .trim();


    const yearValue =
        yearFilter.value
        .toLowerCase();


    const statusValue =
        statusFilter.value
        .toLowerCase();


    rows.forEach(row => {

        const text =
            row.textContent
            .toLowerCase();


        const year =
            row.dataset.year || '';


        const status =
            row.dataset.status || '';


        const matchesSearch =
            !searchValue ||
            text.includes(searchValue);


        const matchesYear =
            !yearValue ||
            year === yearValue;


        const matchesStatus =
            !statusValue ||
            status === statusValue;


        row.style.display =
            matchesSearch &&
            matchesYear &&
            matchesStatus
                ? ''
                : 'none';

    });

}


search.addEventListener(
    'input',
    filterSemesters
);


yearFilter.addEventListener(
    'change',
    filterSemesters
);


statusFilter.addEventListener(
    'change',
    filterSemesters
);

</script>


</body>

</html>