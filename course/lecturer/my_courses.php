<?php

session_start();

/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
|--------------------------------------------------------------------------
|
| We only use the session role for access control.
| We DO NOT use lecturers.user_id to identify the lecturer.
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['logged_in']) ||
    strtolower(trim($_SESSION['role'] ?? '')) !== 'lecturer'
) {
    header('Location: ../index.php');
    exit;
}


require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$userId = (int)(
    $_SESSION['user_id'] ?? 0
);

$loginEmail = trim(
    $_SESSION['email'] ?? ''
);

$lecturerName =
    $_SESSION['full_name'] ?? 'Lecturer';


/*
|--------------------------------------------------------------------------
| LECTURER VARIABLES
|--------------------------------------------------------------------------
*/

$lecturerId = 0;
$staffNo = '';
$departmentName = 'Department not assigned';
$lecturerEmail = $loginEmail;

$error = '';


/*
|--------------------------------------------------------------------------
| SEARCH / FILTERS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET['search'] ?? ''
);

$sessionFilter = (int)(
    $_GET['session_id'] ?? 0
);

$semesterFilter = trim(
    $_GET['semester'] ?? ''
);

$levelFilter = trim(
    $_GET['level'] ?? ''
);


/*
|--------------------------------------------------------------------------
| DATA ARRAYS
|--------------------------------------------------------------------------
*/

$sessions = [];
$semesters = [];
$levels = [];
$courses = [];


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$stats = [
    'courses' => 0,
    'credits' => 0,
    'topics' => 0,
    'hours_expected' => 0,
    'hours_taught' => 0
];


/*
|--------------------------------------------------------------------------
| FIND LECTURER USING EMAIL
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We intentionally do NOT do:
|
|     l.user_id = ?
|
| Instead:
|
|     l.email = logged-in user's email
|
|--------------------------------------------------------------------------
*/

try {

    if ($loginEmail === '') {

        $error =
            'Your login session does not contain an email address.';

    } else {

        $stmt = $pdo->prepare("
            SELECT
                l.lecturer_id,
                l.staff_no,
                l.full_name,
                l.email,
                l.department_id,
                l.phone,
                l.status,

                d.department_name

            FROM lecturers l

            LEFT JOIN departments d
                ON d.department_id = l.department_id

            WHERE LOWER(TRIM(l.email))
                  = LOWER(TRIM(?))

            LIMIT 1
        ");

        $stmt->execute([
            $loginEmail
        ]);

        $lecturer =
            $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | LECTURER FOUND
        |--------------------------------------------------------------------------
        */

        if ($lecturer) {

            $lecturerId =
                (int)$lecturer['lecturer_id'];

            $staffNo =
                $lecturer['staff_no'] ?? '';

            $lecturerName =
                $lecturer['full_name']
                ?: $lecturerName;

            $lecturerEmail =
                $lecturer['email']
                ?: $loginEmail;

            $departmentName =
                $lecturer['department_name']
                ?: 'Department not assigned';

        } else {

            $error =
                'Unable to load lecturer information. ' .
                'No lecturer record matches the login email: '
                . $loginEmail;
        }
    }

} catch (PDOException $e) {

    $error =
        'Database error while loading lecturer information.';
}


/*
|--------------------------------------------------------------------------
| LOAD ACADEMIC SESSIONS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            session_id,
            session_name,
            start_date,
            end_date,
            status

        FROM academic_session

        ORDER BY
            start_date DESC,
            session_id DESC
    ");

    $sessions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $sessions = [];
}


/*
|--------------------------------------------------------------------------
| LOAD LECTURER'S COURSES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We identify the lecturer using:
|
|     lecturers.email
|
| Then:
|
|     lecturers.lecturer_id
|             ↓
|     course_assgnment.lecturer_id
|
|--------------------------------------------------------------------------
*/

if ($lecturerId > 0) {

    try {

        $sql = "
            SELECT

                ca.assignment_id,
                ca.course_id,
                ca.lecturer_id,
                ca.program_id,
                ca.session_id,
                ca.semester AS assignment_semester,
                ca.level AS assignment_level,

                c.course_code,
                c.course_name,
                c.credit_value,
                c.department_id AS course_department_id,
                c.Semester AS course_semester,
                c.Level AS course_level,
                c.status AS course_status,
                c.description,

                p.program_name,

                s.session_name,
                s.start_date,
                s.end_date,
                s.status AS session_status,

                d.department_name,

                /*
                --------------------------------------------------------------
                TOPIC COUNT
                --------------------------------------------------------------
                */

                (
                    SELECT COUNT(*)
                    FROM cousre_topics ct
                    WHERE ct.course_id = ca.course_id
                ) AS topic_count,


                /*
                --------------------------------------------------------------
                EXPECTED HOURS
                --------------------------------------------------------------
                */

                (
                    SELECT COALESCE(
                        SUM(ct.expected_hours),
                        0
                    )

                    FROM cousre_topics ct

                    WHERE ct.course_id = ca.course_id
                ) AS expected_hours,


                /*
                --------------------------------------------------------------
                TAUGHT HOURS
                --------------------------------------------------------------
                */

                (
                    SELECT COALESCE(
                        SUM(cc.hours_taught),
                        0
                    )

                    FROM course_coverage cc

                    WHERE cc.assignment_id =
                          ca.assignment_id
                ) AS taught_hours,


                /*
                --------------------------------------------------------------
                COVERAGE ENTRIES
                --------------------------------------------------------------
                */

                (
                    SELECT COUNT(*)

                    FROM course_coverage cc

                    WHERE cc.assignment_id =
                          ca.assignment_id
                ) AS coverage_entries


            FROM course_assgnment ca


            /*
            ------------------------------------------------------------------
            COURSE
            ------------------------------------------------------------------
            */

            INNER JOIN courses c
                ON c.course_id = ca.course_id


            /*
            ------------------------------------------------------------------
            LECTURER
            ------------------------------------------------------------------
            */

            INNER JOIN lecturers l
                ON l.lecturer_id = ca.lecturer_id


            /*
            ------------------------------------------------------------------
            PROGRAM
            ------------------------------------------------------------------
            */

            LEFT JOIN programs p
                ON p.program_id = ca.program_id


            /*
            ------------------------------------------------------------------
            ACADEMIC SESSION
            ------------------------------------------------------------------
            */

            LEFT JOIN academic_session s
                ON s.session_id = ca.session_id


            /*
            ------------------------------------------------------------------
            DEPARTMENT
            ------------------------------------------------------------------
            */

            LEFT JOIN departments d
                ON d.department_id = c.department_id


            WHERE
                ca.lecturer_id = ?


        ";

        $params = [
            $lecturerId
        ];


        /*
        |--------------------------------------------------------------------------
        | SESSION FILTER
        |--------------------------------------------------------------------------
        */

        if ($sessionFilter > 0) {

            $sql .= "
                AND ca.session_id = ?
            ";

            $params[] =
                $sessionFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | SEMESTER FILTER
        |--------------------------------------------------------------------------
        */

        if ($semesterFilter !== '') {

            $sql .= "
                AND ca.semester = ?
            ";

            $params[] =
                $semesterFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | LEVEL FILTER
        |--------------------------------------------------------------------------
        */

        if ($levelFilter !== '') {

            $sql .= "
                AND ca.level = ?
            ";

            $params[] =
                $levelFilter;
        }


        /*
        |--------------------------------------------------------------------------
        | SEARCH
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    c.course_code LIKE ?
                    OR c.course_name LIKE ?
                    OR p.program_name LIKE ?
                )
            ";

            $searchValue =
                '%' . $search . '%';

            $params[] =
                $searchValue;

            $params[] =
                $searchValue;

            $params[] =
                $searchValue;
        }


        /*
        |--------------------------------------------------------------------------
        | ORDER
        |--------------------------------------------------------------------------
        */

        $sql .= "
            ORDER BY
                s.start_date DESC,
                c.course_code ASC
        ";


        /*
        |--------------------------------------------------------------------------
        | EXECUTE
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare($sql);

        $stmt->execute(
            $params
        );

        $courses =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | CALCULATE COURSE COVERAGE
        |--------------------------------------------------------------------------
        */

        foreach ($courses as &$course) {

            $expected =
                (float)$course['expected_hours'];

            $taught =
                (float)$course['taught_hours'];


            if ($expected > 0) {

                $course['coverage'] =
                    min(
                        100,
                        ($taught / $expected) * 100
                    );

            } else {

                $course['coverage'] = 0;
            }


            /*
            --------------------------------------------------------------
            REMAINING HOURS
            --------------------------------------------------------------
            */

            $course['remaining_hours'] =
                max(
                    0,
                    $expected - $taught
                );


            /*
            --------------------------------------------------------------
            GLOBAL STATISTICS
            --------------------------------------------------------------
            */

            $stats['courses']++;

            $stats['credits'] +=
                (int)$course['credit_value'];

            $stats['topics'] +=
                (int)$course['topic_count'];

            $stats['hours_expected'] +=
                $expected;

            $stats['hours_taught'] +=
                $taught;
        }

        unset($course);


    } catch (PDOException $e) {

        $error =
            'Unable to load your assigned courses.';

    }
}


/*
|--------------------------------------------------------------------------
| OVERALL COVERAGE
|--------------------------------------------------------------------------
*/

$overallCoverage = 0;

if ($stats['hours_expected'] > 0) {

    $overallCoverage =
        min(
            100,
            (
                $stats['hours_taught']
                /
                $stats['hours_expected']
            ) * 100
        );
}


/*
|--------------------------------------------------------------------------
| BUILD FILTER OPTIONS FROM CURRENT COURSES
|--------------------------------------------------------------------------
*/

foreach ($courses as $course) {

    $semester =
        $course['assignment_semester'];

    $level =
        $course['assignment_level'];


    if (
        $semester !== ''
        &&
        !in_array(
            $semester,
            $semesters,
            true
        )
    ) {

        $semesters[] =
            $semester;
    }


    if (
        $level !== ''
        &&
        !in_array(
            $level,
            $levels,
            true
        )
    ) {

        $levels[] =
            $level;
    }
}


/*
|--------------------------------------------------------------------------
| SORT FILTER OPTIONS
|--------------------------------------------------------------------------
*/

sort($semesters);
sort($levels);

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
    My Courses | Course Coverage Management System
</title>


<style>

/* ==========================================================
   RESET
========================================================== */

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:Arial,Helvetica,sans-serif;
    background:#f5f8f6;
    color:#18382d;
    font-size:13px;
}

a{
    text-decoration:none;
}

button,
input,
select{
    font-family:inherit;
}


/* ==========================================================
   APP
========================================================== */

.app{
    display:flex;
    min-height:100vh;
}


/* ==========================================================
   SIDEBAR
========================================================== */

.sidebar{
    width:245px;
    position:fixed;
    left:0;
    top:0;
    bottom:0;
    background:#0d5b3f;
    color:#fff;
    padding:22px 15px;
    display:flex;
    flex-direction:column;
    z-index:50;
}

.brand{
    display:flex;
    align-items:center;
    gap:11px;
    padding:4px 8px 24px;
    border-bottom:1px solid
        rgba(255,255,255,.15);
}

.brand-logo{
    width:45px;
    height:45px;
    border-radius:50%;
    background:#fff;
    display:grid;
    place-items:center;
    color:#0d5b3f;
    font-weight:800;
    font-size:14px;
}

.brand strong{
    display:block;
    font-size:12px;
    line-height:1.3;
}

.brand span{
    display:block;
    font-size:9px;
    opacity:.72;
    margin-top:3px;
}

.menu-title{
    font-size:9px;
    letter-spacing:1.2px;
    opacity:.55;
    margin:25px 10px 9px;
}

.side-link{
    color:#eaf7f1;
    display:flex;
    align-items:center;
    gap:11px;
    padding:11px 12px;
    border-radius:7px;
    margin:3px 0;
    font-size:12px;
}

.side-link:hover,
.side-link.active{
    background:
        rgba(255,255,255,.13);
}

.side-icon{
    width:18px;
    text-align:center;
}

.side-bottom{
    margin-top:auto;
    border-top:1px solid
        rgba(255,255,255,.14);
    padding-top:18px;
    text-align:center;
    font-size:9px;
    opacity:.6;
}


/* ==========================================================
   MAIN
========================================================== */

.main{
    margin-left:245px;
    flex:1;
    min-width:0;
}

.topbar{
    height:82px;
    background:#fff;
    border-bottom:1px solid #e4ebe7;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 30px;
}

.top-left{
    display:flex;
    align-items:center;
    gap:13px;
}

.mobile-menu{
    display:none;
    border:0;
    background:none;
    font-size:22px;
    cursor:pointer;
}

.heading h1{
    margin:0;
    font-size:21px;
    color:#18382d;
}

.heading p{
    margin:5px 0 0;
    font-size:11px;
    color:#7e8e87;
}

.profile{
    display:flex;
    align-items:center;
    gap:10px;
}

.avatar{
    width:39px;
    height:39px;
    border-radius:50%;
    background:#e8f4ee;
    display:grid;
    place-items:center;
    color:#0d6848;
    font-weight:700;
}

.profile-text strong{
    display:block;
    font-size:11px;
}

.profile-text span{
    display:block;
    margin-top:3px;
    color:#899790;
    font-size:10px;
}

.content{
    padding:25px 30px 35px;
}


/* ==========================================================
   NOTICE
========================================================== */

.notice{
    border-radius:8px;
    padding:13px 15px;
    margin-bottom:18px;
    font-size:11px;
}

.notice.error{
    background:#fff0ef;
    color:#a13b32;
    border:1px solid #f0d3cf;
}


/* ==========================================================
   PAGE INTRO
========================================================== */

.page-intro{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:20px;
    margin-bottom:20px;
}

.page-intro h2{
    margin:0;
    font-size:16px;
    color:#204238;
}

.page-intro p{
    margin:6px 0 0;
    color:#82918b;
    font-size:10px;
}

.department{
    font-size:10px;
    color:#7d8c86;
}

.department strong{
    color:#31564a;
}


/* ==========================================================
   STATISTICS
========================================================== */

.stats{
    display:grid;
    grid-template-columns:
        repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.stat{
    background:#fff;
    border:1px solid #e3ebe6;
    border-radius:9px;
    padding:17px;
    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.stat-label{
    color:#87958f;
    font-size:9px;
    letter-spacing:.5px;
}

.stat-number{
    color:#183b2f;
    font-size:23px;
    font-weight:700;
    margin-top:6px;
}

.stat-sub{
    color:#8a9992;
    font-size:9px;
    margin-top:4px;
}


/* ==========================================================
   FILTER
========================================================== */

.filter-panel{
    background:#fff;
    border:1px solid #e3ebe6;
    border-radius:9px;
    padding:16px;
    margin-bottom:20px;
}

.filter-grid{
    display:grid;
    grid-template-columns:
        1.7fr
        1fr
        1fr
        1fr
        auto;
    gap:10px;
    align-items:end;
}

.field label{
    display:block;
    color:#778980;
    font-size:8px;
    font-weight:700;
    letter-spacing:.5px;
    margin-bottom:6px;
}

.field input,
.field select{
    width:100%;
    padding:10px 11px;
    border:1px solid #dce6e1;
    border-radius:7px;
    outline:none;
    background:#fff;
    color:#304c42;
    font-size:10px;
}

.field input:focus,
.field select:focus{
    border-color:#0d6848;
}

.filter-button{
    border:0;
    background:#0d6848;
    color:#fff;
    padding:10px 15px;
    border-radius:7px;
    font-size:10px;
    font-weight:700;
    cursor:pointer;
}

.clear-filter{
    display:inline-block;
    margin-top:8px;
    font-size:9px;
    color:#0d6848;
}


/* ==========================================================
   COURSES PANEL
========================================================== */

.panel{
    background:#fff;
    border:1px solid #e3ebe6;
    border-radius:9px;
    overflow:hidden;
    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.panel-head{
    padding:17px 20px;
    border-bottom:1px solid #edf2ef;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.panel-head h2{
    margin:0;
    font-size:11px;
    letter-spacing:.5px;
    color:#23453a;
}

.panel-head p{
    margin:5px 0 0;
    color:#84928c;
    font-size:10px;
}


/* ==========================================================
   COURSE GRID
========================================================== */

.course-grid{
    padding:18px;
    display:grid;
    grid-template-columns:
        repeat(2,1fr);
    gap:16px;
}

.course-card{
    border:1px solid #e0e9e4;
    border-radius:9px;
    padding:18px;
    transition:
        transform .15s,
        box-shadow .15s;
}

.course-card:hover{
    transform:translateY(-2px);
    box-shadow:
        0 5px 15px
        rgba(25,70,53,.08);
}

.course-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
}

.course-code{
    color:#0d6848;
    font-weight:700;
    font-size:11px;
}

.course-name{
    margin-top:5px;
    font-size:16px;
    font-weight:700;
    color:#203f34;
}

.course-description{
    margin-top:7px;
    color:#84928c;
    font-size:9px;
    line-height:1.5;
}

.course-status{
    display:inline-block;
    background:#e8f6ee;
    color:#287448;
    padding:5px 8px;
    border-radius:20px;
    font-size:8px;
    font-weight:700;
}


/* ==========================================================
   COURSE META
========================================================== */

.course-meta{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-top:14px;
}

.meta{
    background:#f3f7f5;
    border:1px solid #e3ebe6;
    color:#536960;
    padding:6px 8px;
    border-radius:6px;
    font-size:8px;
}


/* ==========================================================
   COURSE NUMBERS
========================================================== */

.course-info{
    display:grid;
    grid-template-columns:
        repeat(4,1fr);
    gap:8px;
    margin-top:16px;
}

.info{
    background:#f8faf9;
    padding:10px;
    border-radius:6px;
}

.info-label{
    font-size:8px;
    color:#8a9892;
}

.info-value{
    margin-top:5px;
    font-size:11px;
    color:#304e43;
    font-weight:700;
}


/* ==========================================================
   PROGRESS
========================================================== */

.progress-section{
    margin-top:16px;
    padding-top:15px;
    border-top:1px solid #edf2ef;
}

.progress-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.progress-label{
    color:#71837b;
    font-size:9px;
}

.progress-percent{
    color:#0d6848;
    font-weight:700;
    font-size:11px;
}

.progress-bar{
    height:7px;
    background:#edf2ef;
    border-radius:20px;
    overflow:hidden;
    margin-top:7px;
}

.progress-bar span{
    display:block;
    height:100%;
    background:#0d704b;
    border-radius:20px;
}


/* ==========================================================
   COURSE FOOTER
========================================================== */

.course-footer{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-top:15px;
    padding-top:13px;
    border-top:1px solid #edf2ef;
}

.remaining{
    font-size:9px;
    color:#819089;
}

.course-buttons{
    display:flex;
    gap:7px;
}

.btn{
    display:inline-block;
    padding:8px 11px;
    border-radius:6px;
    font-size:9px;
    font-weight:700;
}

.btn-secondary{
    background:#edf5f1;
    color:#0d6848;
}

.btn-primary{
    background:#0d6848;
    color:#fff;
}


/* ==========================================================
   EMPTY
========================================================== */

.empty{
    padding:50px 20px;
    text-align:center;
    color:#899791;
    font-size:11px;
}

.empty strong{
    display:block;
    color:#526960;
    margin-bottom:6px;
}


/* ==========================================================
   FOOTER
========================================================== */

.footer{
    padding:17px 30px;
    border-top:1px solid #e3ebe6;
    color:#899791;
    font-size:9px;
    display:flex;
    justify-content:space-between;
    gap:20px;
}

.footer strong{
    color:#60776d;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media(max-width:1150px){

    .stats{
        grid-template-columns:
            repeat(2,1fr);
    }

    .filter-grid{
        grid-template-columns:
            repeat(2,1fr);
    }

    .course-grid{
        grid-template-columns:1fr;
    }

}


@media(max-width:800px){

    .sidebar{
        transform:translateX(-100%);
        transition:.2s;
    }

    .sidebar.open{
        transform:translateX(0);
    }

    .main{
        margin-left:0;
    }

    .mobile-menu{
        display:block;
    }

    .topbar{
        padding:0 16px;
    }

    .content{
        padding:18px 15px 25px;
    }

    .profile-text{
        display:none;
    }

    .page-intro{
        flex-direction:column;
        align-items:flex-start;
    }

    .filter-grid{
        grid-template-columns:1fr;
    }

    .course-info{
        grid-template-columns:
            repeat(2,1fr);
    }

    .footer{
        flex-direction:column;
        padding:16px 15px;
    }

}


@media(max-width:500px){

    .stats{
        grid-template-columns:1fr;
    }

    .course-info{
        grid-template-columns:1fr;
    }

    .course-footer{
        flex-direction:column;
        align-items:flex-start;
    }

    .course-buttons{
        width:100%;
        flex-direction:column;
    }

    .course-buttons .btn{
        text-align:center;
    }

}

</style>

</head>


<body>


<div class="app">


<!-- ======================================================
     SIDEBAR
======================================================= -->

<aside
    class="sidebar"
    id="sidebar"
>

    <div class="brand">

        <div class="brand-logo">
            UB
        </div>

        <div>

            <strong>
                COURSE COVERAGE
            </strong>

            <span>
                University Administration
            </span>

        </div>

    </div>


    <div class="menu-title">
        LECTURER MENU
    </div>


    <a
        class="side-link"
        href="dashboard.php"
    >

        <span class="side-icon">
            ⌂
        </span>

        Dashboard

    </a>


    <a
        class="side-link active"
        href="my_courses.php"
    >

        <span class="side-icon">
            ▤
        </span>

        My Courses

    </a>


    <a
        class="side-link"
        href="coverage.php"
    >

        <span class="side-icon">
            ◫
        </span>

        Course Progress

    </a>


    <a
        class="side-link"
        href="record_coverage.php"
    >

        <span class="side-icon">
            ＋
        </span>

        Record Coverage

    </a>


    <a
        class="side-link"
        href="coverage.php"
    >

        <span class="side-icon">
            ◷
        </span>

        Coverage History

    </a>


    <a
        class="side-link"
        href="profile.php"
    >

        <span class="side-icon">
            ◉
        </span>

        Profile

    </a>


    <a
        class="side-link"
        href="../change_password.php"
    >

        <span class="side-icon">
            ▣
        </span>

        Change Password

    </a>


    <a
        class="side-link"
        href="../auth/logout.php"
    >

        <span class="side-icon">
            ↪
        </span>

        Logout

    </a>


    <div class="side-bottom">

        HTTTC KUMBA

    </div>

</aside>



<!-- ======================================================
     MAIN
======================================================= -->

<main class="main">


<!-- ======================================================
     TOPBAR
======================================================= -->

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
                My Courses
            </h1>

            <p>
                View and manage the courses assigned to you
            </p>

        </div>

    </div>


    <div class="profile">


        <div class="avatar">

            <?= e(
                strtoupper(
                    substr(
                        $lecturerName,
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div class="profile-text">

            <strong>
                <?= e(
                    $lecturerName
                ) ?>
            </strong>

            <span>

                Lecturer

                <?php if ($staffNo): ?>

                    · <?= e($staffNo) ?>

                <?php endif; ?>

            </span>

        </div>


    </div>

</header>



<!-- ======================================================
     CONTENT
======================================================= -->

<section class="content">


<?php if ($error): ?>

    <div class="notice error">

        <?= e($error) ?>

    </div>

<?php endif; ?>



<!-- ======================================================
     INTRO
======================================================= -->

<div class="page-intro">


    <div>

        <h2>
            My Assigned Courses
        </h2>

        <p>
            Courses assigned to you for teaching and course coverage.
        </p>

    </div>


    <div class="department">

        Department:

        <strong>
            <?= e(
                $departmentName
            ) ?>
        </strong>

    </div>


</div>



<!-- ======================================================
     STATISTICS
======================================================= -->

<div class="stats">


    <div class="stat">

        <div class="stat-label">
            ASSIGNED COURSES
        </div>

        <div class="stat-number">
            <?= number_format(
                $stats['courses']
            ) ?>
        </div>

        <div class="stat-sub">
            Courses currently assigned
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            TOTAL CREDITS
        </div>

        <div class="stat-number">
            <?= number_format(
                $stats['credits']
            ) ?>
        </div>

        <div class="stat-sub">
            Credit value of assignments
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            COURSE TOPICS
        </div>

        <div class="stat-number">
            <?= number_format(
                $stats['topics']
            ) ?>
        </div>

        <div class="stat-sub">
            Topics across your courses
        </div>

    </div>


    <div class="stat">

        <div class="stat-label">
            OVERALL COVERAGE
        </div>

        <div class="stat-number">

            <?= number_format(
                $overallCoverage,
                1
            ) ?>%

        </div>

        <div class="stat-sub">
            Based on recorded teaching hours
        </div>

    </div>


</div>



<!-- ======================================================
     FILTERS
======================================================= -->

<form
    method="get"
    class="filter-panel"
>


    <div class="filter-grid">


        <div class="field">

            <label>
                SEARCH
            </label>

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Course code, course name or program..."
            >

        </div>


        <div class="field">

            <label>
                ACADEMIC SESSION
            </label>

            <select name="session_id">

                <option value="">
                    All Sessions
                </option>


                <?php foreach (
                    $sessions
                    as $session
                ): ?>

                    <option
                        value="<?= (int)$session['session_id'] ?>"
                        <?= (
                            $sessionFilter
                            ===
                            (int)$session['session_id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $session['session_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="field">

            <label>
                SEMESTER
            </label>

            <select name="semester">

                <option value="">
                    All Semesters
                </option>


                <?php foreach (
                    $semesters
                    as $semester
                ): ?>

                    <option
                        value="<?= e($semester) ?>"
                        <?= (
                            $semesterFilter
                            ===
                            $semester
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $semester
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="field">

            <label>
                LEVEL
            </label>

            <select name="level">

                <option value="">
                    All Levels
                </option>


                <?php foreach (
                    $levels
                    as $level
                ): ?>

                    <option
                        value="<?= e($level) ?>"
                        <?= (
                            $levelFilter
                            ===
                            $level
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $level
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div>

            <button
                type="submit"
                class="filter-button"
            >
                Filter
            </button>

        </div>


    </div>


    <?php if (
        $search !== ''
        ||
        $sessionFilter > 0
        ||
        $semesterFilter !== ''
        ||
        $levelFilter !== ''
    ): ?>

        <a
            class="clear-filter"
            href="my_courses.php"
        >
            Clear all filters
        </a>

    <?php endif; ?>


</form>



<!-- ======================================================
     COURSES
======================================================= -->

<div class="panel">


    <div class="panel-head">


        <div>

            <h2>
                ASSIGNED COURSES
            </h2>

            <p>
                <?= number_format(
                    count($courses)
                ) ?>

                course(s) found
            </p>

        </div>


    </div>



    <?php if ($courses): ?>


        <div class="course-grid">


            <?php foreach (
                $courses
                as $course
            ): ?>


                <?php

                $coverage =
                    (float)$course['coverage'];


                if (
                    $coverage >= 75
                ) {

                    $coverageText =
                        'Good progress';

                } elseif (
                    $coverage >= 50
                ) {

                    $coverageText =
                        'Moderate progress';

                } elseif (
                    $coverage > 0
                ) {

                    $coverageText =
                        'Needs attention';

                } else {

                    $coverageText =
                        'Not started';
                }

                ?>


                <div class="course-card">


                    <!-- COURSE HEADER -->

                    <div class="course-header">


                        <div>

                            <div class="course-code">

                                <?= e(
                                    $course['course_code']
                                ) ?>

                            </div>


                            <div class="course-name">

                                <?= e(
                                    $course['course_name']
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $course['description']
                                )
                            ): ?>

                                <div
                                    class="course-description"
                                >

                                    <?= e(
                                        $course['description']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <span
                            class="course-status"
                        >

                            <?= e(
                                $course['course_status']
                            ) ?>

                        </span>


                    </div>



                    <!-- COURSE META -->

                    <div class="course-meta">


                        <span class="meta">

                            Program:

                            <?= e(
                                $course['program_name']
                                ?: 'Not specified'
                            ) ?>

                        </span>


                        <span class="meta">

                            <?= e(
                                $course['assignment_level']
                            ) ?>

                            Level

                        </span>


                        <span class="meta">

                            <?= e(
                                $course['assignment_semester']
                            ) ?>

                        </span>


                        <span class="meta">

                            <?= e(
                                $course['session_name']
                                ?: 'Session not specified'
                            ) ?>

                        </span>


                    </div>



                    <!-- COURSE INFO -->

                    <div class="course-info">


                        <div class="info">

                            <div class="info-label">
                                CREDITS
                            </div>

                            <div class="info-value">

                                <?= (int)$course[
                                    'credit_value'
                                ] ?>

                            </div>

                        </div>


                        <div class="info">

                            <div class="info-label">
                                TOPICS
                            </div>

                            <div class="info-value">

                                <?= (int)$course[
                                    'topic_count'
                                ] ?>

                            </div>

                        </div>


                        <div class="info">

                            <div class="info-label">
                                EXPECTED
                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    (float)$course[
                                        'expected_hours'
                                    ],
                                    1
                                ) ?>

                                h

                            </div>

                        </div>


                        <div class="info">

                            <div class="info-label">
                                TAUGHT
                            </div>

                            <div class="info-value">

                                <?= number_format(
                                    (float)$course[
                                        'taught_hours'
                                    ],
                                    1
                                ) ?>

                                h

                            </div>

                        </div>


                    </div>



                    <!-- PROGRESS -->

                    <div class="progress-section">


                        <div class="progress-top">


                            <div class="progress-label">

                                Course Coverage

                            </div>


                            <div class="progress-percent">

                                <?= number_format(
                                    $coverage,
                                    1
                                ) ?>%

                            </div>


                        </div>


                        <div class="progress-bar">

                            <span
                                style="
                                    width:<?= min(
                                        100,
                                        max(
                                            0,
                                            $coverage
                                        )
                                    ) ?>%;
                                "
                            ></span>

                        </div>


                    </div>



                    <!-- FOOTER -->

                    <div class="course-footer">


                        <div class="remaining">

                            <?php if (
                                $course[
                                    'remaining_hours'
                                ] > 0
                            ): ?>

                                <?= number_format(
                                    (float)$course[
                                        'remaining_hours'
                                    ],
                                    1
                                ) ?>

                                hours remaining

                            <?php else: ?>

                                Course hours completed

                            <?php endif; ?>

                        </div>


                        <div class="course-buttons">


                            <a
                                class="btn btn-secondary"
                                href="coverage.php?assignment_id=<?= (int)$course['assignment_id'] ?>"
                            >
                                View Progress
                            </a>


                            <a
                                class="btn btn-primary"
                                href="record_coverage.php?assignment_id=<?= (int)$course['assignment_id'] ?>"
                            >
                                Record Coverage
                            </a>


                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            <?php if ($lecturerId > 0): ?>

                <strong>
                    No courses assigned
                </strong>

                There are currently no courses assigned
                to your lecturer profile matching the selected filters.

            <?php else: ?>

                <strong>
                    Lecturer profile not found
                </strong>

                The system could not find a lecturer record
                with the email used for this login.

            <?php endif; ?>

        </div>


    <?php endif; ?>


</div>


</section>



<!-- ======================================================
     FOOTER
======================================================= -->

<footer class="footer">

    <span>

        © <?= date('Y') ?>

        Course Coverage Management System.
        All Rights Reserved.

    </span>


    <strong>

        HTTTC KUMBA -
        Excellence in Professional Training

    </strong>

</footer>


</main>

</div>


</body>

</html>