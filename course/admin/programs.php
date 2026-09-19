<?php
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));

if (!in_array($role, ['admin', 'administrator'], true)) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';


function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (empty($_SESSION['program_csrf'])) {

    $_SESSION['program_csrf'] =
        bin2hex(random_bytes(32));
}

$csrf = $_SESSION['program_csrf'];

$error = '';
$success = '';

$programs = [];
$departments = [];


/* =========================================================
   GET DEPARTMENTS

   IMPORTANT:
   Your previous system/database work may use either
   departments or departments1.

   This section first tries departments1.
   ========================================================= */

try {

    $stmt = $pdo->query(
        "SELECT department_id, department_name
         FROM departments1
         ORDER BY department_name ASC"
    );

    $departments =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $departmentTable = 'departments1';

} catch (PDOException $e) {

    try {

        $stmt = $pdo->query(
            "SELECT department_id, department_name
             FROM departments
             ORDER BY department_name ASC"
        );

        $departments =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $departmentTable = 'departments';

    } catch (PDOException $e) {

        $departmentTable = 'departments';
    }
}


/* =========================================================
   CREATE / UPDATE / DELETE PROGRAM
   ========================================================= */

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!hash_equals(
            $csrf,
            (string)($_POST['csrf_token'] ?? '')
        )) {

            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }


        $action = $_POST['action'] ?? '';


        /* =====================================================
           CREATE OR UPDATE PROGRAM
           ===================================================== */

        if ($action === 'save_program') {

            $programId =
                (int)($_POST['program_id'] ?? 0);

            $programName =
                trim(
                    (string)($_POST['program_name'] ?? '')
                );

            $departmentId =
                (int)($_POST['department_id'] ?? 0);

            $duration =
                (int)($_POST['duration'] ?? 0);


            /* Validation */

            if ($programName === '') {

                throw new RuntimeException(
                    'Program name is required.'
                );
            }


            if ($departmentId <= 0) {

                throw new RuntimeException(
                    'Please select a department.'
                );
            }


            if ($duration <= 0) {

                throw new RuntimeException(
                    'Please enter a valid program duration.'
                );
            }


            /*
             * Check that selected department exists.
             */

            $stmt = $pdo->prepare(
                "SELECT department_id
                 FROM {$departmentTable}
                 WHERE department_id = ?"
            );

            $stmt->execute([$departmentId]);

            if (!$stmt->fetch()) {

                throw new RuntimeException(
                    'The selected department does not exist.'
                );
            }


            /* =================================================
               UPDATE PROGRAM
               ================================================= */

            if ($programId > 0) {

                $stmt = $pdo->prepare(
                    "UPDATE programs
                     SET
                        program_name = ?,
                        department_id = ?,
                        duration = ?
                     WHERE program_id = ?"
                );

                $stmt->execute([
                    $programName,
                    $departmentId,
                    $duration,
                    $programId
                ]);

                $success =
                    'Program updated successfully.';
            }


            /* =================================================
               CREATE PROGRAM
               ================================================= */

            else {

                $stmt = $pdo->prepare(
                    "INSERT INTO programs
                    (
                        program_name,
                        department_id,
                        duration
                    )
                    VALUES (?, ?, ?)"
                );

                $stmt->execute([
                    $programName,
                    $departmentId,
                    $duration
                ]);

                $success =
                    'Program created successfully.';
            }
        }


        /* =====================================================
           DELETE PROGRAM
           ===================================================== */

        if ($action === 'delete_program') {

            $programId =
                (int)($_POST['program_id'] ?? 0);


            if ($programId <= 0) {

                throw new RuntimeException(
                    'Invalid program selected.'
                );
            }


            /*
             * Check whether the program
             * is already used in course assignments.
             */

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM course_assgnment
                 WHERE program_id = ?"
            );

            $stmt->execute([$programId]);

            $assignmentCount =
                (int)$stmt->fetchColumn();


            if ($assignmentCount > 0) {

                throw new RuntimeException(
                    'This program cannot be deleted because it is already being used in course assignments.'
                );
            }


            /* Delete */

            $stmt = $pdo->prepare(
                "DELETE FROM programs
                 WHERE program_id = ?"
            );

            $stmt->execute([$programId]);


            if ($stmt->rowCount() === 0) {

                throw new RuntimeException(
                    'Program was not found or could not be deleted.'
                );
            }


            $success =
                'Program deleted successfully.';
        }
    }


    /* =========================================================
       GET ALL PROGRAMS
       ========================================================= */

    $stmt = $pdo->query(
        "SELECT
            p.program_id,
            p.program_name,
            p.department_id,
            p.duration,

            d.department_name,

            (
                SELECT COUNT(*)
                FROM course_assgnment ca
                WHERE ca.program_id = p.program_id
            ) AS assignment_count

         FROM programs p

         LEFT JOIN {$departmentTable} d
            ON p.department_id = d.department_id

         ORDER BY
            p.program_name ASC"
    );


    $programs =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (RuntimeException $ex) {

    $error =
        $ex->getMessage();

} catch (PDOException $ex) {

    if (
        (int)($ex->errorInfo[1] ?? 0) === 1062
    ) {

        $error =
            'This program already exists.';

    } else {

        $error =
            'Database error: ' .
            $ex->getMessage();
    }
}


/* =========================================================
   EDIT PROGRAM
   ========================================================= */

$editId =
    (int)($_GET['edit'] ?? 0);

$editProgram = null;


foreach ($programs as $program) {

    if (
        (int)$program['program_id'] === $editId
    ) {

        $editProgram = $program;

        break;
    }
}


/* =========================================================
   STATISTICS
   ========================================================= */

$totalPrograms =
    count($programs);


$departmentIds = [];

$totalAssignments = 0;


foreach ($programs as $program) {

    if (!empty($program['department_id'])) {

        $departmentIds[] =
            $program['department_id'];
    }

    $totalAssignments +=
        (int)$program['assignment_count'];
}


$totalProgramDepartments =
    count(
        array_unique($departmentIds)
    );


$adminName =
    $_SESSION['full_name'] ??
    'Administrator';

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
Programs | Administrator
</title>


<style>

:root {

    --green:#0b5d3b;

    --green-dark:#073f2a;

    --green-light:#e8f5ed;

    --text:#18372b;

    --muted:#708078;

    --border:#e7ece9;

    --bg:#f5f8f6;

    --danger:#b42318;

    --danger-bg:#fff0ee;

    --shadow:
        0 4px 16px rgba(17,52,38,.06);
}


* {

    box-sizing:border-box;
}


body {

    margin:0;

    background:var(--bg);

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color:var(--text);
}


.layout {

    display:flex;

    min-height:100vh;
}


/* =====================================================
   SIDEBAR
   ===================================================== */

.sidebar {

    width:260px;

    background:#ffffff;

    border-right:
        1px solid var(--border);

    padding:20px 14px;

    position:fixed;

    top:0;

    bottom:0;

    left:0;
}


.brand {

    display:flex;

    gap:11px;

    align-items:center;

    padding:
        4px 8px 22px;
}


.brand img {

    width:45px;

    height:45px;

    object-fit:contain;
}


.brand-title {

    font-size:12px;

    font-weight:800;

    color:var(--green-dark);

    line-height:1.35;
}


.brand-title span {

    display:block;

    font-size:9px;

    color:#718078;

    margin-top:3px;
}


.menu-title {

    font-size:9px;

    font-weight:800;

    color:#98a39e;

    letter-spacing:1px;

    margin:
        18px 9px 8px;
}


.role {

    background:var(--green-light);

    color:var(--green);

    border-radius:9px;

    padding:10px;

    font-size:11px;

    font-weight:700;
}


.side-link {

    display:flex;

    align-items:center;

    gap:10px;

    text-decoration:none;

    color:#56675f;

    font-size:12px;

    font-weight:600;

    padding:11px 10px;

    border-radius:8px;

    margin:2px 0;
}


.side-link:hover,
.side-link.active {

    background:var(--green-light);

    color:var(--green);
}


.icon {

    width:20px;

    text-align:center;
}


/* =====================================================
   MAIN CONTENT
   ===================================================== */

.main {

    margin-left:260px;

    width:
        calc(100% - 260px);

    min-height:100vh;
}


.topbar {

    height:78px;

    background:#ffffff;

    border-bottom:
        1px solid var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;
}


.heading h1 {

    margin:0;

    font-size:22px;

    color:var(--green-dark);
}


.heading p {

    margin:5px 0 0;

    color:var(--muted);

    font-size:11px;
}


.profile {

    display:flex;

    align-items:center;

    gap:10px;
}


.avatar {

    width:38px;

    height:38px;

    border-radius:50%;

    object-fit:contain;

    border:
        1px solid var(--border);
}


.profile-text {

    display:flex;

    flex-direction:column;

    gap:3px;
}


.profile-text strong {

    font-size:11px;
}


.profile-text span {

    font-size:9px;

    color:var(--muted);
}


.content {

    padding:25px 30px 30px;
}


/* =====================================================
   PAGE HEADER
   ===================================================== */

.toolbar {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:18px;
}


.page-title h2 {

    margin:0;

    font-size:19px;

    color:var(--green-dark);
}


.page-title p {

    margin:5px 0 0;

    font-size:11px;

    color:var(--muted);
}


/* =====================================================
   BUTTONS
   ===================================================== */

.btn {

    border:0;

    border-radius:8px;

    padding:10px 14px;

    font-size:11px;

    font-weight:700;

    cursor:pointer;

    text-decoration:none;

    display:inline-block;
}


.btn-primary {

    background:var(--green);

    color:#ffffff;
}


.btn-primary:hover {

    background:var(--green-dark);
}


.btn-secondary {

    background:#eef3f0;

    color:var(--green);
}


.btn-danger {

    background:var(--danger-bg);

    color:var(--danger);
}


/* =====================================================
   ALERTS
   ===================================================== */

.alert {

    padding:12px 15px;

    border-radius:8px;

    font-size:11px;

    margin-bottom:18px;
}


.alert.success {

    background:#eaf7ed;

    color:#26713b;

    border:
        1px solid #ccebd4;
}


.alert.error {

    background:#fff0ee;

    color:#a12b22;

    border:
        1px solid #f2cbc6;
}


/* =====================================================
   GRID
   ===================================================== */

.grid {

    display:grid;

    grid-template-columns:
        360px 1fr;

    gap:18px;

    align-items:start;
}


.panel {

    background:#ffffff;

    border:
        1px solid var(--border);

    border-radius:10px;

    box-shadow:var(--shadow);

    overflow:hidden;
}


.panel-head {

    padding:17px 20px;

    border-bottom:
        1px solid var(--border);
}


.panel-head h3 {

    margin:0;

    font-size:12px;

    color:var(--green-dark);
}


.panel-head p {

    margin:4px 0 0;

    font-size:10px;

    color:var(--muted);
}


/* =====================================================
   FORM
   ===================================================== */

.form {

    padding:20px;
}


.field {

    margin-bottom:14px;
}


.field label {

    display:block;

    font-size:10px;

    font-weight:800;

    color:#5f7068;

    margin-bottom:6px;
}


.field input,
.field select {

    width:100%;

    border:
        1px solid #dce5e0;

    border-radius:7px;

    padding:10px;

    font-size:11px;

    outline:none;
}


.field input:focus,
.field select:focus {

    border-color:var(--green);
}


.form-actions {

    display:flex;

    gap:8px;
}


/* =====================================================
   STATS
   ===================================================== */

.stats {

    display:grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:10px;

    padding:15px 20px;

    border-bottom:
        1px solid var(--border);
}


.stat {

    background:#f7faf8;

    border-radius:8px;

    padding:12px;
}


.stat span {

    display:block;

    font-size:9px;

    color:var(--muted);

    font-weight:700;
}


.stat strong {

    display:block;

    margin-top:4px;

    font-size:20px;

    color:var(--green-dark);
}


/* =====================================================
   SEARCH
   ===================================================== */

.filters {

    padding:15px 20px;

    border-bottom:
        1px solid var(--border);

    display:flex;

    gap:8px;

    flex-wrap:wrap;
}


.search,
.filter {

    border:
        1px solid #dce5e0;

    border-radius:7px;

    padding:9px 10px;

    font-size:11px;
}


.search {

    flex:1;

    min-width:180px;
}


.filter {

    min-width:180px;
}


/* =====================================================
   TABLE
   ===================================================== */

.table-wrap {

    overflow:auto;
}


table {

    width:100%;

    border-collapse:collapse;

    min-width:750px;
}


th,
td {

    padding:12px 10px;

    border-bottom:
        1px solid #edf1ef;

    text-align:left;
}


th {

    background:#fbfcfb;

    color:#6c7b75;

    font-size:9px;

    letter-spacing:.4px;
}


td {

    font-size:11px;
}


td:first-child,
th:first-child {

    padding-left:20px;
}


.program-name {

    font-weight:800;

    color:var(--green);
}


.assignment-badge {

    display:inline-block;

    padding:5px 8px;

    border-radius:20px;

    background:var(--green-light);

    color:var(--green);

    font-size:9px;

    font-weight:700;
}


.actions {

    display:flex;

    gap:6px;
}


.actions form {

    display:inline;
}


.empty {

    text-align:center;

    padding:35px;

    color:var(--muted);

    font-size:11px;
}


/* =====================================================
   RESPONSIVE
   ===================================================== */

@media(max-width:1100px) {

    .grid {

        grid-template-columns:1fr;
    }
}


@media(max-width:800px) {

    .sidebar {

        width:220px;
    }

    .main {

        margin-left:220px;

        width:
            calc(100% - 220px);
    }
}


@media(max-width:650px) {

    .sidebar {

        display:none;
    }

    .main {

        margin-left:0;

        width:100%;
    }

    .topbar {

        padding:0 15px;
    }

    .content {

        padding:18px 15px;
    }

    .profile-text {

        display:none;
    }

    .stats {

        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>


<div class="layout">


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

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
Administrator
</div>


<div class="menu-title">
MAIN MENU
</div>


<a
    class="side-link"
    href="dashboard.php"
>
    <span class="icon">⌂</span>
    Dashboard
</a>


<a
    class="side-link"
    href="users.php"
>
    <span class="icon">♟</span>
    Users
</a>


<a
    class="side-link"
    href="departments.php"
>
    <span class="icon">▣</span>
    Departments
</a>


<a
    class="side-link active"
    href="programs.php"
>
    <span class="icon">▤</span>
    Programs
</a>


<a
    class="side-link"
    href="courses.php"
>
    <span class="icon">▦</span>
    Courses
</a>


<a
    class="side-link"
    href="academic_years.php"
>
    <span class="icon">◫</span>
    Academic Years
</a>


<a
    class="side-link"
    href="semesters.php"
>
    <span class="icon">◳</span>
    Semesters
</a>


<div class="menu-title">
ACCOUNT
</div>


<a
    class="side-link"
    href="profile.php"
>
    <span class="icon">◉</span>
    Profile
</a>


<a
    class="side-link"
    href="../auth/logout.php"
>
    <span class="icon">↪</span>
    Logout
</a>


</aside>


<!-- =====================================================
     MAIN CONTENT
     ===================================================== -->

<main class="main">


<header class="topbar">


<div class="heading">

    <h1>
        Programs
    </h1>

    <p>
        Manage academic programs and their departments
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
        Program Management
    </h2>

    <p>
        Create and manage programs available in each department.
    </p>

</div>


<a
    href="#program-form"
    class="btn btn-primary"
>
    + Create Program
</a>


</div>


<!-- SUCCESS -->

<?php if ($success): ?>

<div class="alert success">

    <?=e($success)?>

</div>

<?php endif; ?>


<!-- ERROR -->

<?php if ($error): ?>

<div class="alert error">

    <?=e($error)?>

</div>

<?php endif; ?>


<div class="grid">


<!-- =====================================================
     CREATE PROGRAM FORM
     ===================================================== -->

<section
    class="panel"
    id="program-form"
>


<div class="panel-head">

    <h3>

        <?= $editProgram
            ? 'EDIT PROGRAM'
            : 'CREATE PROGRAM'
        ?>

    </h3>

    <p>
        Assign every program to a department.
    </p>

</div>


<form
    method="post"
    class="form"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?=e($csrf)?>"
>


<input
    type="hidden"
    name="action"
    value="save_program"
>


<input
    type="hidden"
    name="program_id"
    value="<?=e($editProgram['program_id'] ?? 0)?>"
>


<!-- PROGRAM NAME -->

<div class="field">

    <label>
        PROGRAM NAME
    </label>

    <input
        type="text"
        name="program_name"
        placeholder="Enter program name"
        value="<?=e(
            $editProgram['program_name'] ?? ''
        )?>"
        required
    >

</div>


<!-- DEPARTMENT -->

<div class="field">

    <label>
        DEPARTMENT
    </label>

    <select
        name="department_id"
        required
    >

        <option value="">
            Select Department
        </option>


        <?php foreach ($departments as $department): ?>


        <option
            value="<?=e(
                $department['department_id']
            )?>"

            <?=
            (
                (int)(
                    $editProgram['department_id'] ?? 0
                )
                ===
                (int)$department['department_id']
            )
            ? 'selected'
            : ''
            ?>

        >

            <?=e(
                $department['department_name']
            )?>

        </option>


        <?php endforeach; ?>


    </select>

</div>


<!-- DURATION -->

<div class="field">

    <label>
        DURATION (YEARS)
    </label>

    <input
        type="number"
        name="duration"
        min="1"
        max="10"
        placeholder="Example: 2"
        value="<?=e(
            $editProgram['duration'] ?? ''
        )?>"
        required
    >

</div>


<div class="form-actions">


<button
    type="submit"
    class="btn btn-primary"
>

    <?= $editProgram
        ? 'Save Changes'
        : 'Create Program'
    ?>

</button>


<?php if ($editProgram): ?>


<a
    href="programs.php"
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


<!-- =====================================================
     PROGRAM LIST
     ===================================================== -->

<section class="panel">


<div class="panel-head">

    <h3>
        ALL PROGRAMS
    </h3>

    <p>
        Programs retrieved from the database.
    </p>

</div>


<!-- STATS -->

<div class="stats">


<div class="stat">

    <span>
        TOTAL PROGRAMS
    </span>

    <strong>
        <?=e($totalPrograms)?>
    </strong>

</div>


<div class="stat">

    <span>
        DEPARTMENTS
    </span>

    <strong>
        <?=e($totalProgramDepartments)?>
    </strong>

</div>


<div class="stat">

    <span>
        COURSE ASSIGNMENTS
    </span>

    <strong>
        <?=e($totalAssignments)?>
    </strong>

</div>


</div>


<!-- FILTERS -->

<div class="filters">


<input
    class="search"
    id="programSearch"
    type="search"
    placeholder="Search program..."
>


<select
    class="filter"
    id="departmentFilter"
>

    <option value="">
        All Departments
    </option>


    <?php foreach ($departments as $department): ?>


    <option
        value="<?=e(
            $department['department_id']
        )?>"
    >

        <?=e(
            $department['department_name']
        )?>

    </option>


    <?php endforeach; ?>


</select>


</div>


<!-- TABLE -->

<div class="table-wrap">


<table id="programsTable">


<thead>

<tr>

    <th>
        ID
    </th>

    <th>
        PROGRAM
    </th>

    <th>
        DEPARTMENT
    </th>

    <th>
        DURATION
    </th>

    <th>
        ASSIGNMENTS
    </th>

    <th>
        ACTIONS
    </th>

</tr>

</thead>


<tbody>


<?php if ($programs): ?>


<?php foreach ($programs as $program): ?>


<tr
    data-department="<?=e(
        $program['department_id']
    )?>"
>


<td>

    <?=e(
        $program['program_id']
    )?>

</td>


<td>

    <div class="program-name">

        <?=e(
            $program['program_name']
        )?>

    </div>

</td>


<td>

    <?=e(
        $program['department_name']
        ?? 'No Department'
    )?>

</td>


<td>

    <?=e(
        $program['duration']
    )?>

    Year(s)

</td>


<td>

    <span class="assignment-badge">

        <?=e(
            $program['assignment_count']
        )?>

        Assignment(s)

    </span>

</td>


<td>


<div class="actions">


<a
    href="
    programs.php
    ?edit=
    <?=e(
        $program['program_id']
    )?>
    #program-form
    "
    class="btn btn-secondary"
>
    Edit
</a>


<form
    method="post"
    onsubmit="
    return confirm(
    'Are you sure you want to delete this program?'
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
    value="delete_program"
>


<input
    type="hidden"
    name="program_id"
    value="<?=e(
        $program['program_id']
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
    colspan="6"
    class="empty"
>

    No programs have been created yet.

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


</div>


<script>

const search =
    document.getElementById(
        'programSearch'
    );

const departmentFilter =
    document.getElementById(
        'departmentFilter'
    );

const rows =
    document.querySelectorAll(
        '#programsTable tbody tr'
    );


function filterPrograms()
{

    const searchValue =
        search.value
        .toLowerCase()
        .trim();

    const departmentValue =
        departmentFilter.value;


    rows.forEach(row => {

        const rowText =
            row.textContent
            .toLowerCase();

        const rowDepartment =
            row.dataset.department || '';


        const matchesSearch =
            !searchValue ||
            rowText.includes(
                searchValue
            );


        const matchesDepartment =
            !departmentValue ||
            rowDepartment ===
            departmentValue;


        row.style.display =
            matchesSearch &&
            matchesDepartment
            ? ''
            : 'none';

    });

}


search.addEventListener(
    'input',
    filterPrograms
);


departmentFilter.addEventListener(
    'change',
    filterPrograms
);

</script>


</body>

</html>