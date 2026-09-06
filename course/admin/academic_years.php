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
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['academic_year_csrf'])) {
    $_SESSION['academic_year_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['academic_year_csrf'];

$error = '';
$success = '';

/* =========================================================
   CREATE / UPDATE / ACTIVATE / DELETE
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
           CREATE / UPDATE
           ===================================================== */

        if ($action === 'save_year') {

            $sessionId = (int)($_POST['session_id'] ?? 0);

            $sessionName = trim(
                (string)($_POST['session_name'] ?? '')
            );

            $startDate = trim(
                (string)($_POST['start_date'] ?? '')
            );

            $endDate = trim(
                (string)($_POST['end_date'] ?? '')
            );

            $status = trim(
                (string)($_POST['status'] ?? 'Upcoming')
            );

            /* Validation */

            if (
                $sessionName === '' ||
                $startDate === '' ||
                $endDate === ''
            ) {
                throw new RuntimeException(
                    'Academic year, start date and end date are required.'
                );
            }

            /*
             * Example:
             * 2026/2027
             */

            if (!preg_match(
                '/^\d{4}\/\d{4}$/',
                $sessionName
            )) {
                throw new RuntimeException(
                    'Academic year must use the format YYYY/YYYY. Example: 2026/2027.'
                );
            }

            $parts = explode('/', $sessionName);

            if ((int)$parts[1] !== ((int)$parts[0] + 1)) {
                throw new RuntimeException(
                    'The second year must be one year after the first year.'
                );
            }

            if ($endDate <= $startDate) {
                throw new RuntimeException(
                    'End date must be after the start date.'
                );
            }

            if (
                !in_array(
                    $status,
                    ['Upcoming', 'Active', 'Completed'],
                    true
                )
            ) {
                $status = 'Upcoming';
            }

            /* =================================================
               ACTIVE YEAR
               Only one academic year can be active.
               ================================================= */

            if ($status === 'Active') {

                $pdo->beginTransaction();

                if ($sessionId > 0) {

                    $stmt = $pdo->prepare(
                        "UPDATE academic_session
                         SET status = 'Completed'
                         WHERE status = 'Active'
                         AND session_id <> ?"
                    );

                    $stmt->execute([$sessionId]);

                } else {

                    $pdo->exec(
                        "UPDATE academic_session
                         SET status = 'Completed'
                         WHERE status = 'Active'"
                    );
                }

                /* UPDATE */

                if ($sessionId > 0) {

                    $stmt = $pdo->prepare(
                        "UPDATE academic_session
                         SET session_name = ?,
                             start_date = ?,
                             end_date = ?,
                             status = ?
                         WHERE session_id = ?"
                    );

                    $stmt->execute([
                        $sessionName,
                        $startDate,
                        $endDate,
                        $status,
                        $sessionId
                    ]);

                    $success =
                        'Academic year updated and activated successfully.';

                }

                /* CREATE */

                else {

                    $stmt = $pdo->prepare(
                        "INSERT INTO academic_session
                        (
                            session_name,
                            start_date,
                            end_date,
                            status
                        )
                        VALUES (?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $sessionName,
                        $startDate,
                        $endDate,
                        $status
                    ]);

                    $success =
                        'Academic year created and activated successfully.';
                }

                $pdo->commit();
            }

            /* =================================================
               NORMAL CREATE / UPDATE
               ================================================= */

            else {

                /* UPDATE */

                if ($sessionId > 0) {

                    $stmt = $pdo->prepare(
                        "UPDATE academic_session
                         SET session_name = ?,
                             start_date = ?,
                             end_date = ?,
                             status = ?
                         WHERE session_id = ?"
                    );

                    $stmt->execute([
                        $sessionName,
                        $startDate,
                        $endDate,
                        $status,
                        $sessionId
                    ]);

                    $success =
                        'Academic year updated successfully.';
                }

                /* CREATE */

                else {

                    $stmt = $pdo->prepare(
                        "INSERT INTO academic_session
                        (
                            session_name,
                            start_date,
                            end_date,
                            status
                        )
                        VALUES (?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $sessionName,
                        $startDate,
                        $endDate,
                        $status
                    ]);

                    $success =
                        'Academic year created successfully.';
                }
            }
        }

        /* =====================================================
           ACTIVATE YEAR
           ===================================================== */

        if ($action === 'activate_year') {

            $sessionId = (int)(
                $_POST['session_id'] ?? 0
            );

            if ($sessionId <= 0) {
                throw new RuntimeException(
                    'Invalid academic year selected.'
                );
            }

            $pdo->beginTransaction();

            /*
             * Close current active year.
             */

            $stmt = $pdo->prepare(
                "UPDATE academic_session
                 SET status = 'Completed'
                 WHERE status = 'Active'
                 AND session_id <> ?"
            );

            $stmt->execute([$sessionId]);

            /*
             * Activate selected year.
             */

            $stmt = $pdo->prepare(
                "UPDATE academic_session
                 SET status = 'Active'
                 WHERE session_id = ?"
            );

            $stmt->execute([$sessionId]);

            if ($stmt->rowCount() === 0) {

                $pdo->rollBack();

                throw new RuntimeException(
                    'Academic year was not found.'
                );
            }

            $pdo->commit();

            $success =
                'Academic year is now active.';
        }

        /* =====================================================
           DELETE
           ===================================================== */

        if ($action === 'delete_year') {

            $sessionId = (int)(
                $_POST['session_id'] ?? 0
            );

            if ($sessionId <= 0) {
                throw new RuntimeException(
                    'Invalid academic year selected.'
                );
            }

            /*
             * Check year.
             */

            $stmt = $pdo->prepare(
                "SELECT status
                 FROM academic_session
                 WHERE session_id = ?"
            );

            $stmt->execute([$sessionId]);

            $year = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$year) {

                throw new RuntimeException(
                    'Academic year was not found.'
                );
            }

            /*
             * Do not delete active year.
             */

            if (
                strtolower($year['status']) === 'active'
            ) {

                throw new RuntimeException(
                    'The active academic year cannot be deleted.'
                );
            }

            /*
             * Check course assignments.
             *
             * course_assgnment.session_id references
             * academic_session.session_id.
             */

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM course_assgnment
                 WHERE session_id = ?"
            );

            $stmt->execute([$sessionId]);

            $assignmentCount =
                (int)$stmt->fetchColumn();

            if ($assignmentCount > 0) {

                throw new RuntimeException(
                    'This academic year cannot be deleted because it is already being used by course assignments.'
                );
            }

            /*
             * Delete.
             */

            $stmt = $pdo->prepare(
                "DELETE FROM academic_session
                 WHERE session_id = ?"
            );

            $stmt->execute([$sessionId]);

            if ($stmt->rowCount() === 0) {

                throw new RuntimeException(
                    'Academic year was not deleted.'
                );
            }

            $success =
                'Academic year deleted successfully.';
        }
    }

    /* =========================================================
       GET ALL ACADEMIC YEARS
       ========================================================= */

    $stmt = $pdo->query(
        "SELECT
            s.session_id,
            s.session_name,
            s.start_date,
            s.end_date,
            s.status,

            (
                SELECT COUNT(*)
                FROM course_assgnment a
                WHERE a.session_id = s.session_id
            ) AS assignment_count

         FROM academic_session s

         ORDER BY
            s.start_date DESC,
            s.session_id DESC"
    );

    $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $ex) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $ex->getMessage();

    $years = $years ?? [];

} catch (PDOException $ex) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        (int)($ex->errorInfo[1] ?? 0) === 1062
    ) {

        $error =
            'That academic year already exists.';

    } else {

        $error =
            'A database error occurred. Please check your database connection and academic_session table.';
    }

    $years = $years ?? [];
}


/* =========================================================
   EDIT DATA
   ========================================================= */

$editId = (int)(
    $_GET['edit'] ?? 0
);

$editYear = null;

foreach ($years as $year) {

    if (
        (int)$year['session_id'] === $editId
    ) {

        $editYear = $year;

        break;
    }
}


/* =========================================================
   STATISTICS
   ========================================================= */

$totalYears = count($years);

$activeYear = null;

$completedYears = 0;

$upcomingYears = 0;

foreach ($years as $year) {

    $status =
        strtolower(
            (string)$year['status']
        );

    if ($status === 'active') {

        $activeYear = $year;
    }

    if ($status === 'completed') {

        $completedYears++;
    }

    if ($status === 'upcoming') {

        $upcomingYears++;
    }
}

$adminName =
    $_SESSION['full_name'] ??
    'Administrator';

?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
Academic Years | Administrator
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

    --white:#ffffff;

    --danger:#b42318;

    --danger-bg:#fff0ee;

    --warning:#8a5a00;

    --warning-bg:#fff7e6;

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


/* SIDEBAR */

.sidebar {

    width:260px;

    background:#fff;

    border-right:
        1px solid var(--border);

    padding:20px 14px;

    position:fixed;

    top:0;

    bottom:0;

    left:0;

    z-index:20;
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

    font-size:9px;

    color:#718078;

    letter-spacing:.4px;
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

.role-badge {

    display:inline-grid;

    place-items:center;

    width:24px;

    height:24px;

    border-radius:7px;

    background:#fff;

    margin-right:7px;
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

    font-size:14px;
}

.side-bottom {

    position:absolute;

    bottom:18px;

    left:20px;

    right:20px;

    color:#89958f;

    font-size:9px;

    text-align:center;
}

.building {

    font-size:24px;

    color:var(--green);

    margin-bottom:4px;
}


/* MAIN */

.main {

    margin-left:260px;

    width:
        calc(100% - 260px);

    min-height:100vh;
}

.topbar {

    height:78px;

    background:#fff;

    border-bottom:
        1px solid var(--border);

    display:flex;

    align-items:center;

    justify-content:space-between;

    padding:0 30px;
}

.top-left {

    display:flex;

    align-items:center;
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

    object-fit:contain;

    border-radius:50%;

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


/* CONTENT */

.content {

    padding:25px 30px 30px;
}

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


/* BUTTONS */

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

    color:#fff;
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

.btn-warning {

    background:var(--warning-bg);

    color:var(--warning);
}


/* ALERTS */

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


/* GRID */

.grid {

    display:grid;

    grid-template-columns:
        360px 1fr;

    gap:18px;

    align-items:start;
}

.panel {

    background:#fff;

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


/* FORM */

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

    background:#fff;
}

.field input:focus,
.field select:focus {

    border-color:var(--green);
}

.form-actions {

    display:flex;

    gap:8px;

    margin-top:6px;
}


/* STATS */

.stats {

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

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

    color:var(--muted);

    font-size:9px;

    font-weight:700;
}

.stat strong {

    display:block;

    color:var(--green-dark);

    font-size:20px;

    margin-top:4px;
}


/* ACTIVE YEAR */

.active-box {

    margin:
        15px 20px 0;

    padding:12px;

    border-radius:8px;

    background:var(--green-light);

    border:
        1px solid #cce7d7;
}

.active-box small {

    display:block;

    color:var(--green);

    font-weight:700;

    font-size:9px;

    text-transform:uppercase;
}

.active-box strong {

    display:block;

    margin-top:4px;

    color:var(--green-dark);

    font-size:13px;
}

.active-box span {

    display:block;

    margin-top:4px;

    color:#61736a;

    font-size:9px;
}


/* FILTER */

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

    background:#fff;
}

.search {

    flex:1;

    min-width:180px;
}

.filter {

    min-width:150px;
}


/* TABLE */

.table-wrap {

    overflow:auto;
}

table {

    width:100%;

    border-collapse:collapse;

    min-width:850px;
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

.year-name {

    font-weight:800;

    color:var(--green);

    font-size:12px;
}

.date {

    font-size:10px;

    color:#697970;
}


/* BADGES */

.badge {

    display:inline-block;

    border-radius:20px;

    padding:5px 8px;

    font-size:9px;

    font-weight:700;

    background:#eaf6ed;

    color:#27713b;
}

.badge.upcoming {

    background:#fff7e6;

    color:#8a5a00;
}

.badge.completed {

    background:#f1f3f2;

    color:#66736d;
}

.actions {

    display:flex;

    gap:6px;

    align-items:center;
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

.mobile-menu {

    display:none;
}


/* RESPONSIVE */

@media(max-width:1150px) {

    .grid {

        grid-template-columns:1fr;
    }

    .sidebar {

        width:220px;
    }

    .main {

        margin-left:220px;

        width:
            calc(100% - 220px);
    }
}

@media(max-width:760px) {

    .sidebar {

        transform:
            translateX(-100%);

        transition:.2s;

        width:260px;
    }

    .sidebar.open {

        transform:
            translateX(0);
    }

    .main {

        margin-left:0;

        width:100%;
    }

    .topbar {

        padding:0 15px;
    }

    .content {

        padding:
            18px 15px;
    }

    .mobile-menu {

        display:block;

        border:0;

        background:transparent;

        font-size:22px;

        color:var(--green);

        margin-right:10px;
    }

    .profile-text {

        display:none;
    }

    .stats {

        grid-template-columns:
            repeat(2,1fr);
    }
}

</style>

</head>

<body>

<div class="layout">


<!-- ======================================================
     SIDEBAR
     ====================================================== -->

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <img
            src="../assets/images/ub-logo.png"
            alt="University of Buea"
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
        class="side-link active"
        href="academic_years.php"
    >

        <span class="icon">
            ◫
        </span>

        Academic Years

    </a>


    <a
        class="side-link"
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

        <div class="building">
            ⌂
        </div>

        <div>
            HTTTC KUMBA
        </div>

    </div>

</aside>


<!-- ======================================================
     MAIN
     ====================================================== -->

<main class="main">


<header class="topbar">

    <div class="top-left">

        <button
            class="mobile-menu"
            onclick="
                document
                .getElementById('sidebar')
                .classList
                .toggle('open')
            "
        >
            ☰
        </button>

        <div class="heading">

            <h1>
                Academic Years
            </h1>

            <p>
                Manage academic sessions used by the system
            </p>

        </div>

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
            Academic Year Management
        </h2>

        <p>
            Create and manage academic years directly in the database.
        </p>

    </div>


    <a
        href="#year-form"
        class="btn btn-primary"
    >

        ＋

        <?= $editYear
            ? 'Edit Academic Year'
            : 'Create Academic Year'
        ?>

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


<!-- ======================================================
     FORM
     ====================================================== -->

<section
    class="panel"
    id="year-form"
>

    <div class="panel-head">

        <h3>

            <?= $editYear
                ? 'EDIT ACADEMIC YEAR'
                : 'CREATE ACADEMIC YEAR'
            ?>

        </h3>

        <p>

            <?= $editYear
                ? 'Update the selected academic session.'
                : 'The information is saved directly to academic_session.'
            ?>

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
            value="save_year"
        >


        <input
            type="hidden"
            name="session_id"
            value="<?=e($editYear['session_id'] ?? 0)?>"
        >


        <div class="field">

            <label>
                ACADEMIC YEAR
            </label>

            <input
                type="text"
                name="session_name"
                maxlength="20"
                placeholder="e.g. 2026/2027"
                value="<?=e($editYear['session_name'] ?? '')?>"
                required
            >

        </div>


        <div class="field">

            <label>
                START DATE
            </label>

            <input
                type="date"
                name="start_date"
                value="<?=e($editYear['start_date'] ?? '')?>"
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
                value="<?=e($editYear['end_date'] ?? '')?>"
                required
            >

        </div>


        <div class="field">

            <label>
                STATUS
            </label>

            <select name="status">

                <option
                    value="Upcoming"
                    <?=(
                        ($editYear['status'] ?? 'Upcoming')
                        === 'Upcoming'
                    )
                    ? 'selected'
                    : ''
                    ?>
                >
                    Upcoming
                </option>


                <option
                    value="Active"
                    <?=(
                        ($editYear['status'] ?? '')
                        === 'Active'
                    )
                    ? 'selected'
                    : ''
                    ?>
                >
                    Active
                </option>


                <option
                    value="Completed"
                    <?=(
                        ($editYear['status'] ?? '')
                        === 'Completed'
                    )
                    ? 'selected'
                    : ''
                    ?>
                >
                    Completed
                </option>

            </select>

        </div>


        <div class="form-actions">

            <button
                class="btn btn-primary"
                type="submit"
            >

                <?= $editYear
                    ? 'Save Changes'
                    : 'Create Academic Year'
                ?>

            </button>


            <?php if ($editYear): ?>

                <a
                    class="btn btn-secondary"
                    href="academic_years.php"
                >
                    Cancel
                </a>

            <?php else: ?>

                <button
                    class="btn btn-secondary"
                    type="reset"
                >
                    Clear
                </button>

            <?php endif; ?>

        </div>

    </form>

</section>


<!-- ======================================================
     LIST
     ====================================================== -->

<section class="panel">

    <div class="panel-head">

        <h3>
            ACADEMIC YEARS
        </h3>

        <p>
            Academic sessions retrieved from the database.
        </p>

    </div>


    <div class="stats">


        <div class="stat">

            <span>
                TOTAL YEARS
            </span>

            <strong>
                <?=e($totalYears)?>
            </strong>

        </div>


        <div class="stat">

            <span>
                ACTIVE
            </span>

            <strong>
                <?=e($activeYear ? 1 : 0)?>
            </strong>

        </div>


        <div class="stat">

            <span>
                UPCOMING
            </span>

            <strong>
                <?=e($upcomingYears)?>
            </strong>

        </div>


        <div class="stat">

            <span>
                COMPLETED
            </span>

            <strong>
                <?=e($completedYears)?>
            </strong>

        </div>


    </div>


    <?php if ($activeYear): ?>

    <div class="active-box">

        <small>
            Current Active Academic Year
        </small>

        <strong>
            <?=e($activeYear['session_name'])?>
        </strong>

        <span>

            <?=e(
                date(
                    'd M Y',
                    strtotime(
                        $activeYear['start_date']
                    )
                )
            )?>

            —

            <?=e(
                date(
                    'd M Y',
                    strtotime(
                        $activeYear['end_date']
                    )
                )
            )?>

        </span>

    </div>

    <?php endif; ?>


    <div class="filters">

        <input
            class="search"
            id="yearSearch"
            type="search"
            placeholder="Search academic year..."
        >


        <select
            class="filter"
            id="statusFilter"
        >

            <option value="">
                All Statuses
            </option>

            <option value="active">
                Active
            </option>

            <option value="upcoming">
                Upcoming
            </option>

            <option value="completed">
                Completed
            </option>

        </select>

    </div>


    <div class="table-wrap">

        <table id="yearsTable">

            <thead>

            <tr>

                <th>
                    ACADEMIC YEAR
                </th>

                <th>
                    START DATE
                </th>

                <th>
                    END DATE
                </th>

                <th>
                    STATUS
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


            <?php if ($years): ?>


                <?php foreach ($years as $year): ?>

                    <?php

                    $statusClass =
                        strtolower(
                            (string)$year['status']
                        );

                    ?>


                    <tr
                        data-status="<?=e($statusClass)?>"
                    >


                        <td>

                            <div class="year-name">

                                <?=e(
                                    $year['session_name']
                                )?>

                            </div>

                        </td>


                        <td class="date">

                            <?=e(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $year['start_date']
                                    )
                                )
                            )?>

                        </td>


                        <td class="date">

                            <?=e(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $year['end_date']
                                    )
                                )
                            )?>

                        </td>


                        <td>

                            <span
                                class="
                                badge
                                <?=in_array(
                                    $statusClass,
                                    [
                                        'upcoming',
                                        'completed'
                                    ],
                                    true
                                )
                                ? $statusClass
                                : ''
                                ?>
                                "
                            >

                                <?=e(
                                    $year['status']
                                )?>

                            </span>

                        </td>


                        <td>

                            <?=e(
                                $year['assignment_count']
                            )?>

                        </td>


                        <td>

                            <div class="actions">


                                <a
                                    class="btn btn-secondary"
                                    href="
                                    academic_years.php
                                    ?edit=
                                    <?=e(
                                        $year['session_id']
                                    )?>
                                    #year-form
                                    "
                                >
                                    Edit
                                </a>


                                <?php if (
                                    $statusClass !== 'active'
                                ): ?>


                                    <form
                                        method="post"
                                        onsubmit="
                                        return confirm(
                                        'Make this the active academic year? The current active year will be marked Completed.'
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
                                            value="activate_year"
                                        >

                                        <input
                                            type="hidden"
                                            name="session_id"
                                            value="<?=e(
                                                $year['session_id']
                                            )?>"
                                        >

                                        <button
                                            class="btn btn-warning"
                                            type="submit"
                                        >
                                            Activate
                                        </button>

                                    </form>


                                    <form
                                        method="post"
                                        onsubmit="
                                        return confirm(
                                        'Delete this academic year?'
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
                                            value="delete_year"
                                        >

                                        <input
                                            type="hidden"
                                            name="session_id"
                                            value="<?=e(
                                                $year['session_id']
                                            )?>"
                                        >

                                        <button
                                            class="btn btn-danger"
                                            type="submit"
                                        >
                                            Delete
                                        </button>

                                    </form>


                                <?php endif; ?>


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

                        No academic years found.

                        Create your first academic year
                        using the form.

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
        'yearSearch'
    );

const status =
    document.getElementById(
        'statusFilter'
    );

const rows =
    document.querySelectorAll(
        '#yearsTable tbody tr'
    );


function filterYears()
{
    const q =
        (search.value || '')
        .toLowerCase()
        .trim();

    const s =
        (status.value || '')
        .toLowerCase();


    rows.forEach(row => {

        const text =
            row.textContent
            .toLowerCase();

        const rowStatus =
            row.dataset.status || '';


        const matchesSearch =
            !q ||
            text.includes(q);

        const matchesStatus =
            !s ||
            rowStatus === s;


        row.style.display =
            matchesSearch &&
            matchesStatus
            ? ''
            : 'none';

    });
}


search.addEventListener(
    'input',
    filterYears
);

status.addEventListener(
    'change',
    filterYears
);

</script>

</body>

</html>