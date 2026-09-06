<?php
session_start();

/*
|--------------------------------------------------------------------------
| Lecturer Authentication
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
| Helper
|--------------------------------------------------------------------------
*/
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| Logged-in User
|--------------------------------------------------------------------------
*/
$userId = (int)($_SESSION['user_id'] ?? 0);
$lecturerEmail = $_SESSION['email'] ?? '';
$lecturerName = $_SESSION['full_name'] ?? 'Lecturer';

$lecturerId = 0;
$staffNo = '';
$departmentId = 0;
$departmentName = 'Department not assigned';

$error = '';


/*
|--------------------------------------------------------------------------
| Find Lecturer Profile
|--------------------------------------------------------------------------
|
| The supplied dashboard currently resolves the lecturer through email
| and then falls back through the users table. We retain that approach.
|--------------------------------------------------------------------------
*/
try {

    $stmt = $pdo->prepare("
        SELECT
            l.lecturer_id,
            l.staff_no,
            l.full_name,
            l.email,
            l.department_id,
            l.phone,
            d.department_name
        FROM lecturers l
        LEFT JOIN departments d
            ON d.department_id = l.department_id
        WHERE LOWER(l.email) = LOWER(?)
        LIMIT 1
    ");

    $stmt->execute([$lecturerEmail]);

    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | Fallback using logged-in user
    |--------------------------------------------------------------------------
    */
    if (!$lecturer && $userId) {

        $stmt = $pdo->prepare("
            SELECT
                l.lecturer_id,
                l.staff_no,
                l.full_name,
                l.email,
                l.department_id,
                l.phone,
                d.department_name
            FROM users u
            INNER JOIN lecturers l
                ON LOWER(l.email) = LOWER(u.email)
            LEFT JOIN departments d
                ON d.department_id = l.department_id
            WHERE u.user_id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    }


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
            ?: $lecturerEmail;

        $departmentId =
            (int)($lecturer['department_id'] ?? 0);

        $departmentName =
            $lecturer['department_name']
            ?: 'Department not assigned';
    }

} catch (PDOException $e) {

    $error =
        'Unable to load lecturer information.';
}


/*
|--------------------------------------------------------------------------
| Filter Values
|--------------------------------------------------------------------------
*/
$sessionFilter =
    (int)($_GET['session_id'] ?? 0);

$semesterFilter =
    trim($_GET['semester'] ?? '');

$levelFilter =
    trim($_GET['level'] ?? '');

$search =
    trim($_GET['search'] ?? '');

$selectedAssignmentId =
    (int)($_GET['assignment_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| Page Data
|--------------------------------------------------------------------------
*/
$sessions = [];
$semesters = [];
$levels = [];

$assignments = [];
$selectedCourse = null;
$selectedTopics = [];

$stats = [
    'courses' => 0,
    'topics' => 0,
    'completed_topics' => 0,
    'expected_hours' => 0,
    'taught_hours' => 0,
    'coverage' => 0
];


/*
|--------------------------------------------------------------------------
| Academic Sessions
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
        ORDER BY start_date DESC, session_id DESC
    ");

    $sessions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $sessions = [];
}


/*
|--------------------------------------------------------------------------
| Main Course Progress Query
|--------------------------------------------------------------------------
*/
if ($lecturerId > 0) {

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
                c.credit_value,
                c.description,
                c.status AS course_status,

                p.program_name,

                s.session_name,
                s.start_date,
                s.end_date,

                d.department_name,

                COALESCE(
                    (
                        SELECT COUNT(*)
                        FROM cousre_topics ct
                        WHERE ct.course_id = ca.course_id
                    ),
                    0
                ) AS total_topics,

                COALESCE(
                    (
                        SELECT SUM(ct.expected_hours)
                        FROM cousre_topics ct
                        WHERE ct.course_id = ca.course_id
                    ),
                    0
                ) AS expected_hours,

                COALESCE(
                    (
                        SELECT SUM(cc.hours_taught)
                        FROM course_coverage cc
                        WHERE cc.assignment_id = ca.assignment_id
                    ),
                    0
                ) AS taught_hours

            FROM course_assgnment ca

            INNER JOIN courses c
                ON c.course_id = ca.course_id

            LEFT JOIN programs p
                ON p.program_id = ca.program_id

            LEFT JOIN academic_session s
                ON s.session_id = ca.session_id

            LEFT JOIN departments d
                ON d.department_id = c.department_id

            WHERE ca.lecturer_id = ?
        ";

        $params = [$lecturerId];


        /*
        |--------------------------------------------------------------------------
        | Session Filter
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
        | Semester Filter
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
        | Level Filter
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
        | Search Filter
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


        $sql .= "
            ORDER BY
                s.start_date DESC,
                ca.assignment_id DESC
        ";


        $stmt =
            $pdo->prepare($sql);

        $stmt->execute($params);

        $assignments =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Topic Progress Query
        |--------------------------------------------------------------------------
        */
        $topicStmt = $pdo->prepare("
            SELECT

                ct.topic_id,
                ct.topic_number,
                ct.topic_title,
                ct.description,
                ct.expected_hours,

                COALESCE(
                    (
                        SELECT SUM(cc.hours_taught)
                        FROM course_coverage cc
                        WHERE cc.assignment_id = ?
                          AND cc.topic_id = ct.topic_id
                    ),
                    0
                ) AS taught_hours,

                COALESCE(
                    (
                        SELECT COUNT(*)
                        FROM course_coverage cc
                        WHERE cc.assignment_id = ?
                          AND cc.topic_id = ct.topic_id
                    ),
                    0
                ) AS coverage_entries

            FROM cousre_topics ct

            WHERE ct.course_id = ?

            ORDER BY
                ct.topic_number ASC,
                ct.topic_id ASC
        ");


        /*
        |--------------------------------------------------------------------------
        | Process Courses
        |--------------------------------------------------------------------------
        */
        foreach ($assignments as &$course) {

            $assignmentId =
                (int)$course['assignment_id'];

            $courseId =
                (int)$course['course_id'];


            /*
            | Get topics for this assignment
            */
            $topicStmt->execute([
                $assignmentId,
                $assignmentId,
                $courseId
            ]);

            $topics =
                $topicStmt->fetchAll(PDO::FETCH_ASSOC);


            $completedTopics = 0;


            foreach ($topics as &$topic) {

                $expected =
                    (float)$topic['expected_hours'];

                $taught =
                    (float)$topic['taught_hours'];


                if ($expected > 0) {

                    $topic['coverage'] =
                        min(
                            100,
                            ($taught / $expected) * 100
                        );

                } else {

                    $topic['coverage'] = 0;
                }


                /*
                |--------------------------------------------------------------------------
                | Determine Topic Status
                |--------------------------------------------------------------------------
                */
                if (
                    $expected > 0 &&
                    $taught >= $expected
                ) {

                    $topic['status'] =
                        'Completed';

                    $topic['status_class'] =
                        'good';

                    $completedTopics++;

                } elseif ($taught > 0) {

                    $topic['status'] =
                        'In Progress';

                    $topic['status_class'] =
                        'average';

                } else {

                    $topic['status'] =
                        'Not Started';

                    $topic['status_class'] =
                        'low';
                }
            }

            unset($topic);


            /*
            |--------------------------------------------------------------------------
            | Course Coverage
            |--------------------------------------------------------------------------
            */
            $expectedHours =
                (float)$course['expected_hours'];

            $taughtHours =
                (float)$course['taught_hours'];


            if ($expectedHours > 0) {

                $course['coverage'] =
                    min(
                        100,
                        ($taughtHours / $expectedHours) * 100
                    );

            } else {

                $course['coverage'] = 0;
            }


            $course['completed_topics'] =
                $completedTopics;

            $course['topics'] =
                $topics;


            /*
            |--------------------------------------------------------------------------
            | Global Statistics
            |--------------------------------------------------------------------------
            */
            $stats['courses']++;

            $stats['topics'] +=
                count($topics);

            $stats['completed_topics'] +=
                $completedTopics;

            $stats['expected_hours'] +=
                $expectedHours;

            $stats['taught_hours'] +=
                $taughtHours;


            /*
            |--------------------------------------------------------------------------
            | Selected Course
            |--------------------------------------------------------------------------
            */
            if (
                $selectedAssignmentId > 0 &&
                $selectedAssignmentId === $assignmentId
            ) {

                $selectedCourse =
                    $course;

                $selectedTopics =
                    $topics;
            }


            /*
            |--------------------------------------------------------------------------
            | Filter Options
            |--------------------------------------------------------------------------
            */
            if (
                !empty($course['semester']) &&
                !in_array(
                    $course['semester'],
                    $semesters,
                    true
                )
            ) {

                $semesters[] =
                    $course['semester'];
            }


            if (
                !empty($course['level']) &&
                !in_array(
                    $course['level'],
                    $levels,
                    true
                )
            ) {

                $levels[] =
                    $course['level'];
            }
        }

        unset($course);


        /*
        |--------------------------------------------------------------------------
        | Overall Coverage
        |--------------------------------------------------------------------------
        */
        if ($stats['expected_hours'] > 0) {

            $stats['coverage'] =
                min(
                    100,
                    (
                        $stats['taught_hours']
                        /
                        $stats['expected_hours']
                    ) * 100
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Automatically select first course
        |--------------------------------------------------------------------------
        */
        if (
            !$selectedCourse &&
            !empty($assignments)
        ) {

            $selectedCourse =
                $assignments[0];

            $selectedTopics =
                $selectedCourse['topics'];
        }

    } catch (PDOException $e) {

        $error =
            'Unable to load course progress. ' .
            'Please check your database connection and table structure.';
    }
}


/*
|--------------------------------------------------------------------------
| Topic Status Counts for Selected Course
|--------------------------------------------------------------------------
*/
$selectedCounts = [
    'completed' => 0,
    'progress' => 0,
    'not_started' => 0
];

if ($selectedTopics) {

    foreach ($selectedTopics as $topic) {

        if ($topic['status'] === 'Completed') {

            $selectedCounts['completed']++;

        } elseif ($topic['status'] === 'In Progress') {

            $selectedCounts['progress']++;

        } else {

            $selectedCounts['not_started']++;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Current URL Query Builder
|--------------------------------------------------------------------------
*/
function buildQuery(array $changes = []): string
{
    $values = [
        'session_id' =>
            $_GET['session_id'] ?? '',

        'semester' =>
            $_GET['semester'] ?? '',

        'level' =>
            $_GET['level'] ?? '',

        'search' =>
            $_GET['search'] ?? ''
    ];

    foreach ($changes as $key => $value) {
        $values[$key] = $value;
    }

    $values =
        array_filter(
            $values,
            fn($value) =>
                $value !== ''
                &&
                $value !== null
        );

    return
        http_build_query($values);
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
    Course Progress | Course Coverage Management System
</title>


<style>

/* ==========================================================
   GLOBAL
========================================================== */

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:Arial,Helvetica,sans-serif;
    background:#f5f8f6;
    color:#173128;
    font-size:13px;
}

.app{
    display:flex;
    min-height:100vh;
}


/* ==========================================================
   SIDEBAR
========================================================== */

.sidebar{
    width:245px;
    background:#0d5b3f;
    color:#fff;
    position:fixed;
    left:0;
    top:0;
    bottom:0;
    padding:22px 15px;
    display:flex;
    flex-direction:column;
    z-index:20;
}

.brand{
    display:flex;
    align-items:center;
    gap:11px;
    padding:4px 8px 24px;
    border-bottom:1px solid rgba(255,255,255,.16);
}

.brand img{
    width:48px;
    height:48px;
    object-fit:contain;
    background:#fff;
    border-radius:50%;
    padding:4px;
}

.brand strong{
    font-size:12px;
    line-height:1.35;
}

.brand span{
    display:block;
    font-size:10px;
    opacity:.75;
    margin-top:2px;
}

.menu-title{
    font-size:9px;
    letter-spacing:1.2px;
    opacity:.55;
    margin:25px 10px 9px;
}

.side-link{
    display:flex;
    align-items:center;
    gap:11px;
    text-decoration:none;
    color:#eaf7f1;
    padding:11px 12px;
    border-radius:7px;
    margin:3px 0;
    font-size:12px;
}

.side-link:hover,
.side-link.active{
    background:rgba(255,255,255,.13);
}

.icon{
    width:18px;
    text-align:center;
    opacity:.9;
}

.side-bottom{
    margin-top:auto;
    border-top:1px solid rgba(255,255,255,.14);
    padding:18px 8px 4px;
    font-size:9px;
    opacity:.65;
    text-align:center;
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
    position:sticky;
    top:0;
    z-index:10;
}

.top-left{
    display:flex;
    align-items:center;
    gap:14px;
}

.mobile-menu{
    display:none;
    border:0;
    background:none;
    font-size:22px;
}

.heading h1{
    font-size:21px;
    margin:0;
    color:#18382d;
}

.heading p{
    margin:5px 0 0;
    color:#7c8d86;
    font-size:11px;
}

.profile{
    display:flex;
    align-items:center;
    gap:10px;
}

.bell{
    font-size:18px;
    color:#547168;
    margin-right:7px;
}

.avatar{
    width:38px;
    height:38px;
    border-radius:50%;
    object-fit:contain;
    background:#f1f5f2;
    padding:4px;
}

.profile-text strong{
    display:block;
    font-size:11px;
}

.profile-text span{
    display:block;
    font-size:10px;
    color:#8a9893;
    margin-top:3px;
}

.content{
    padding:25px 30px 35px;
}


/* ==========================================================
   FILTERS
========================================================== */

.filter-panel{
    background:#fff;
    border:1px solid #e4ebe7;
    border-radius:9px;
    padding:16px;
    margin-bottom:20px;
}

.filter-grid{
    display:grid;
    grid-template-columns:1.5fr 1fr 1fr 1.5fr auto;
    gap:10px;
    align-items:end;
}

.filter-field label{
    display:block;
    font-size:8px;
    font-weight:700;
    letter-spacing:.5px;
    color:#75877f;
    margin-bottom:6px;
}

.filter-field input,
.filter-field select{
    width:100%;
    border:1px solid #dce6e1;
    border-radius:7px;
    background:#fff;
    padding:10px 11px;
    font-size:11px;
    color:#29483d;
    outline:none;
}

.filter-button{
    border:0;
    background:#0d6848;
    color:#fff;
    border-radius:7px;
    padding:10px 15px;
    font-size:10px;
    font-weight:700;
    cursor:pointer;
}

.clear-button{
    display:inline-block;
    margin-top:8px;
    font-size:9px;
    color:#0d6848;
    text-decoration:none;
}


/* ==========================================================
   CARDS
========================================================== */

.cards{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:15px;
    margin-bottom:20px;
}

.card{
    background:#fff;
    border:1px solid #e4ebe7;
    border-radius:9px;
    padding:16px;
    box-shadow:0 2px 8px rgba(25,70,53,.03);
}

.card-top{
    display:flex;
    gap:12px;
    align-items:center;
}

.card-icon{
    width:42px;
    height:42px;
    border-radius:8px;
    background:#e8f4ee;
    color:#0b704a;
    display:grid;
    place-items:center;
    font-size:19px;
}

.card-label{
    font-size:9px;
    color:#87968f;
    letter-spacing:.5px;
}

.card-number{
    font-size:23px;
    font-weight:700;
    color:#183b2f;
    margin-top:5px;
}


/* ==========================================================
   PANEL
========================================================== */

.panel{
    background:#fff;
    border:1px solid #e4ebe7;
    border-radius:9px;
    box-shadow:0 2px 8px rgba(25,70,53,.03);
    overflow:hidden;
    margin-bottom:20px;
}

.panel-head{
    padding:17px 20px;
    border-bottom:1px solid #edf2ef;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
}

.panel-head h2{
    margin:0;
    font-size:11px;
    letter-spacing:.5px;
    color:#23453a;
}

.panel-head p{
    margin:5px 0 0;
    font-size:10px;
    color:#84928c;
}


/* ==========================================================
   COURSE PROGRESS CARD
========================================================== */

.course-list{
    padding:18px;
    display:grid;
    gap:15px;
}

.course-progress{
    border:1px solid #e2ebe6;
    border-radius:9px;
    padding:18px;
}

.course-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
}

.course-code{
    font-size:12px;
    color:#0d6848;
    font-weight:700;
}

.course-name{
    font-size:15px;
    font-weight:700;
    color:#1d3d32;
    margin-top:4px;
}

.course-sub{
    font-size:10px;
    color:#84938c;
    margin-top:7px;
}

.coverage-number{
    font-size:23px;
    font-weight:700;
    color:#0d6848;
    text-align:right;
}

.coverage-label{
    font-size:8px;
    color:#8a9892;
    text-align:right;
    margin-top:3px;
}

.big-progress{
    height:10px;
    background:#edf2ef;
    border-radius:99px;
    overflow:hidden;
    margin-top:17px;
}

.big-progress span{
    display:block;
    height:100%;
    background:#0d704b;
    border-radius:99px;
}

.course-stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
    margin-top:17px;
}

.course-stat{
    background:#f8fbf9;
    border-radius:7px;
    padding:11px;
}

.course-stat-label{
    font-size:8px;
    color:#899791;
}

.course-stat-value{
    font-size:12px;
    font-weight:700;
    color:#29483d;
    margin-top:5px;
}

.course-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:15px;
    padding-top:13px;
    border-top:1px solid #edf2ef;
}

.btn{
    display:inline-block;
    text-decoration:none;
    border:0;
    border-radius:7px;
    padding:9px 13px;
    font-size:10px;
    font-weight:700;
}

.btn-primary{
    background:#0d6848;
    color:#fff;
}

.btn-secondary{
    background:#edf5f1;
    color:#0d6848;
}


/* ==========================================================
   SELECTED COURSE
========================================================== */

.selected{
    padding:20px;
}

.selected-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    padding-bottom:18px;
    border-bottom:1px solid #edf2ef;
}

.selected-code{
    font-size:12px;
    color:#0d6848;
    font-weight:700;
}

.selected-name{
    font-size:18px;
    font-weight:700;
    margin-top:5px;
    color:#193b30;
}

.selected-meta{
    display:flex;
    flex-wrap:wrap;
    gap:7px;
    margin-top:9px;
}

.meta-pill{
    background:#f0f6f3;
    border:1px solid #e0ebe5;
    padding:5px 8px;
    border-radius:20px;
    font-size:9px;
    color:#506960;
}

.selected-coverage{
    min-width:180px;
    text-align:right;
}

.selected-coverage strong{
    display:block;
    font-size:28px;
    color:#0d6848;
}

.selected-coverage span{
    font-size:9px;
    color:#82918b;
}


/* ==========================================================
   STATUS SUMMARY
========================================================== */

.status-summary{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:12px;
    margin:18px 0;
}

.status-box{
    border:1px solid #e5ece8;
    border-radius:8px;
    padding:14px;
}

.status-box strong{
    display:block;
    font-size:19px;
}

.status-box span{
    display:block;
    font-size:9px;
    color:#82918b;
    margin-top:4px;
}

.status-box.completed strong{
    color:#287448;
}

.status-box.progressing strong{
    color:#a1761d;
}

.status-box.pending strong{
    color:#a23c33;
}


/* ==========================================================
   TOPIC TABLE
========================================================== */

.table-wrap{
    overflow-x:auto;
}

.data-table{
    width:100%;
    border-collapse:collapse;
    min-width:850px;
}

.data-table th{
    font-size:8px;
    letter-spacing:.5px;
    color:#899791;
    background:#fafcfa;
    text-align:left;
    padding:12px 14px;
    border-bottom:1px solid #e7eeea;
}

.data-table td{
    font-size:10px;
    padding:14px;
    border-bottom:1px solid #eef3f0;
    color:#435c53;
    vertical-align:middle;
}

.data-table tr:last-child td{
    border-bottom:0;
}

.topic-title{
    font-weight:700;
    color:#29483d;
}

.topic-description{
    font-size:9px;
    color:#8a9892;
    margin-top:4px;
    max-width:330px;
    line-height:1.45;
}

.topic-progress{
    min-width:130px;
}

.topic-progress-bar{
    height:6px;
    background:#edf2ef;
    border-radius:99px;
    overflow:hidden;
}

.topic-progress-bar span{
    display:block;
    height:100%;
    background:#0d704b;
    border-radius:99px;
}

.topic-percent{
    font-size:9px;
    color:#6e8178;
    margin-top:4px;
}

.pill{
    display:inline-block;
    padding:5px 8px;
    border-radius:20px;
    font-size:9px;
    font-weight:700;
}

.good{
    background:#e7f6ec;
    color:#267044;
}

.average{
    background:#fff6df;
    color:#946a18;
}

.low{
    background:#fff0ef;
    color:#a13b31;
}

.empty{
    text-align:center;
    padding:35px!important;
    color:#8b9994;
}


/* ==========================================================
   FOOTER
========================================================== */

.footer{
    padding:16px 30px;
    border-top:1px solid #e4ebe7;
    color:#8a9892;
    font-size:9px;
    display:flex;
    justify-content:space-between;
}

.footer strong{
    color:#5e746a;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media(max-width:1100px){

    .cards{
        grid-template-columns:repeat(2,1fr);
    }

    .filter-grid{
        grid-template-columns:1fr 1fr;
    }

    .course-stats{
        grid-template-columns:repeat(2,1fr);
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
        padding:18px 15px;
    }

    .profile-text{
        display:none;
    }

    .filter-grid{
        grid-template-columns:1fr;
    }

    .cards{
        grid-template-columns:1fr 1fr;
    }

    .course-top,
    .selected-header{
        flex-direction:column;
    }

    .selected-coverage{
        text-align:left;
    }

}

@media(max-width:500px){

    .cards{
        grid-template-columns:1fr;
    }

    .course-stats,
    .status-summary{
        grid-template-columns:1fr;
    }

    .course-actions{
        justify-content:stretch;
        flex-direction:column;
    }

    .course-actions .btn{
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

<aside class="sidebar" id="sidebar">

    <div class="brand">

        <img
            src="../assets/images/ub-logo.png"
            alt="University Logo"
        >

        <div>

            <strong>
                UNIVERSITY OF BUEA
            </strong>

            <span>
                HTTTC KUMBA
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
        <span class="icon">⌂</span>
        Dashboard
    </a>


    <a
        class="side-link"
        href="my_courses.php"
    >
        <span class="icon">▤</span>
        My Courses
    </a>


    <a
        class="side-link active"
        href="coverage.php"
    >
        <span class="icon">◫</span>
        Course Progress
    </a>


    <a
        class="side-link"
        href="coverage.php"
    >
        <span class="icon">＋</span>
        Record Coverage
    </a>


    <a
        class="side-link"
        href="coverage_history.php"
    >
        <span class="icon">◷</span>
        Coverage History
    </a>


    <a
        class="side-link"
        href="profile.php"
    >
        <span class="icon">◉</span>
        Profile
    </a>


    <a
        class="side-link"
        href="change_password.php"
    >
        <span class="icon">▣</span>
        Change Password
    </a>


    <a
        class="side-link"
        href="../auth/logout.php"
    >
        <span class="icon">↪</span>
        Logout
    </a>


    <div class="side-bottom">

        <div
            style="
                font-size:20px;
                margin-bottom:6px;
            "
        >
            ⌂
        </div>

        HTTTC KUMBA

    </div>

</aside>



<!-- ======================================================
     MAIN
======================================================= -->

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
                Course Progress
            </h1>

            <p>
                Monitor your teaching progress across assigned courses
            </p>

        </div>

    </div>


    <div class="profile">

        <div class="bell">
            ♧
        </div>


        <img
            class="avatar"
            src="../assets/images/ub-logo.png"
            alt="Lecturer"
        >


        <div class="profile-text">

            <strong>
                <?= e($lecturerName) ?>
            </strong>

            <span>
                Lecturer
                <?= $staffNo
                    ? ' · ' . e($staffNo)
                    : ''
                ?>
            </span>

        </div>

    </div>

</header>



<section class="content">


<?php if ($error): ?>

    <div
        style="
            background:#fff0ef;
            border:1px solid #f0d1ce;
            color:#9c3b32;
            padding:13px 15px;
            border-radius:7px;
            margin-bottom:18px;
            font-size:11px;
        "
    >

        <?= e($error) ?>

    </div>

<?php endif; ?>


<?php if (!$lecturerId): ?>

    <div
        style="
            background:#fff4e5;
            border:1px solid #f0dfbd;
            color:#856326;
            padding:14px;
            border-radius:8px;
            margin-bottom:18px;
            font-size:11px;
        "
    >

        <strong>
            Lecturer profile not found.
        </strong>

        <br>

        Your login account could not be matched with a lecturer
        profile.

    </div>

<?php endif; ?>



<!-- ======================================================
     FILTERS
======================================================= -->

<form
    method="get"
    class="filter-panel"
>

    <div class="filter-grid">


        <div class="filter-field">

            <label>
                SEARCH COURSE
            </label>

            <input
                type="text"
                name="search"
                value="<?= e($search) ?>"
                placeholder="Course code, name or program..."
            >

        </div>


        <div class="filter-field">

            <label>
                ACADEMIC SESSION
            </label>

            <select name="session_id">

                <option value="">
                    All Sessions
                </option>

                <?php foreach ($sessions as $session): ?>

                    <option
                        value="<?= (int)$session['session_id'] ?>"
                        <?= $sessionFilter ===
                            (int)$session['session_id']
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($session['session_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-field">

            <label>
                SEMESTER
            </label>

            <select name="semester">

                <option value="">
                    All Semesters
                </option>

                <?php foreach ($semesters as $semester): ?>

                    <option
                        value="<?= e($semester) ?>"
                        <?= $semesterFilter === $semester
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($semester) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="filter-field">

            <label>
                LEVEL
            </label>

            <select name="level">

                <option value="">
                    All Levels
                </option>

                <?php foreach ($levels as $level): ?>

                    <option
                        value="<?= e($level) ?>"
                        <?= $levelFilter === $level
                            ? 'selected'
                            : ''
                        ?>
                    >
                        <?= e($level) ?> Level
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div>

            <button
                class="filter-button"
                type="submit"
            >
                Filter
            </button>

        </div>

    </div>


    <?php if (
        $sessionFilter ||
        $semesterFilter ||
        $levelFilter ||
        $search
    ): ?>

        <a
            class="clear-button"
            href="coverage.php"
        >
            Clear all filters
        </a>

    <?php endif; ?>

</form>



<!-- ======================================================
     SUMMARY
======================================================= -->

<div class="cards">


    <div class="card">

        <div class="card-top">

            <div class="card-icon">
                ▤
            </div>

            <div>

                <div class="card-label">
                    COURSES
                </div>

                <div class="card-number">
                    <?= number_format($stats['courses']) ?>
                </div>

            </div>

        </div>

    </div>



    <div class="card">

        <div class="card-top">

            <div class="card-icon">
                ◫
            </div>

            <div>

                <div class="card-label">
                    TOTAL TOPICS
                </div>

                <div class="card-number">
                    <?= number_format($stats['topics']) ?>
                </div>

            </div>

        </div>

    </div>



    <div class="card">

        <div class="card-top">

            <div class="card-icon">
                ✓
            </div>

            <div>

                <div class="card-label">
                    COMPLETED TOPICS
                </div>

                <div class="card-number">
                    <?= number_format(
                        $stats['completed_topics']
                    ) ?>
                </div>

            </div>

        </div>

    </div>



    <div class="card">

        <div class="card-top">

            <div class="card-icon">
                ◔
            </div>

            <div>

                <div class="card-label">
                    OVERALL COVERAGE
                </div>

                <div class="card-number">
                    <?= number_format(
                        $stats['coverage'],
                        1
                    ) ?>%
                </div>

            </div>

        </div>

    </div>

</div>



<!-- ======================================================
     COURSE PROGRESS
======================================================= -->

<div class="panel">


    <div class="panel-head">

        <div>

            <h2>
                COURSE PROGRESS
            </h2>

            <p>
                Overall teaching progress for your assigned courses
            </p>

        </div>

    </div>


    <div class="course-list">


    <?php if ($assignments): ?>


        <?php foreach ($assignments as $course): ?>

            <?php
                $coverage =
                    (float)$course['coverage'];

                $coverageClass =
                    $coverage >= 75
                        ? 'good'
                        : (
                            $coverage >= 50
                                ? 'average'
                                : 'low'
                        );
            ?>


            <div class="course-progress">


                <div class="course-top">


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


                        <div class="course-sub">

                            <?= e(
                                $course['program_name']
                                ?: 'Program not specified'
                            ) ?>

                            ·

                            <?= e(
                                $course['level']
                            ) ?>

                            Level

                            ·

                            <?= e(
                                $course['semester']
                            ) ?>

                            ·

                            <?= e(
                                $course['session_name']
                                ?: 'Session not specified'
                            ) ?>

                        </div>

                    </div>


                    <div>

                        <div class="coverage-number">

                            <?= number_format(
                                $coverage,
                                1
                            ) ?>%

                        </div>

                        <div class="coverage-label">
                            COURSE COVERAGE
                        </div>

                    </div>


                </div>



                <div class="big-progress">

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



                <div class="course-stats">


                    <div class="course-stat">

                        <div class="course-stat-label">
                            EXPECTED HOURS
                        </div>

                        <div class="course-stat-value">

                            <?= number_format(
                                (float)$course['expected_hours'],
                                1
                            ) ?>
                            hrs

                        </div>

                    </div>


                    <div class="course-stat">

                        <div class="course-stat-label">
                            TAUGHT HOURS
                        </div>

                        <div class="course-stat-value">

                            <?= number_format(
                                (float)$course['taught_hours'],
                                1
                            ) ?>
                            hrs

                        </div>

                    </div>


                    <div class="course-stat">

                        <div class="course-stat-label">
                            TOPICS
                        </div>

                        <div class="course-stat-value">

                            <?= (int)$course['total_topics'] ?>

                        </div>

                    </div>


                    <div class="course-stat">

                        <div class="course-stat-label">
                            COMPLETED
                        </div>

                        <div class="course-stat-value">

                            <?= (int)$course['completed_topics'] ?>

                            /

                            <?= (int)$course['total_topics'] ?>

                        </div>

                    </div>


                </div>



                <div class="course-actions">

                    <a
                        class="btn btn-secondary"
                        href="coverage.php?<?= e(
                            buildQuery([
                                'assignment_id' =>
                                    $course['assignment_id']
                            ])
                        ) ?>"
                    >
                        View Topic Breakdown
                    </a>


                    <a
                        class="btn btn-primary"
                        href="coverage.php?assignment_id=<?= (int)$course['assignment_id'] ?>#selected-course"
                    >
                        + Record Coverage
                    </a>

                </div>


            </div>


        <?php endforeach; ?>


    <?php else: ?>


        <div class="empty">

            No assigned courses were found
            for the selected filters.

        </div>


    <?php endif; ?>


    </div>

</div>



<!-- ======================================================
     SELECTED COURSE TOPIC BREAKDOWN
======================================================= -->

<?php if ($selectedCourse): ?>


<div
    class="panel"
    id="selected-course"
>


    <div class="panel-head">

        <div>

            <h2>
                TOPIC BREAKDOWN
            </h2>

            <p>
                Detailed progress for
                <?= e(
                    $selectedCourse['course_code']
                ) ?>
            </p>

        </div>


        <a
            class="btn btn-primary"
            href="coverage.php?assignment_id=<?= (int)$selectedCourse['assignment_id'] ?>#record"
        >
            + Record Coverage
        </a>

    </div>


    <div class="selected">


        <!-- Course Header -->

        <div class="selected-header">


            <div>

                <div class="selected-code">

                    <?= e(
                        $selectedCourse['course_code']
                    ) ?>

                </div>


                <div class="selected-name">

                    <?= e(
                        $selectedCourse['course_name']
                    ) ?>

                </div>


                <div class="selected-meta">

                    <span class="meta-pill">
                        <?= e(
                            $selectedCourse['program_name']
                            ?: 'Program'
                        ) ?>
                    </span>

                    <span class="meta-pill">
                        <?= e(
                            $selectedCourse['level']
                        ) ?> Level
                    </span>

                    <span class="meta-pill">
                        <?= e(
                            $selectedCourse['semester']
                        ) ?>
                    </span>

                    <span class="meta-pill">
                        <?= e(
                            $selectedCourse['session_name']
                            ?: 'Academic Session'
                        ) ?>
                    </span>

                    <span class="meta-pill">
                        <?= e(
                            $selectedCourse['credit_value']
                        ) ?> Credit(s)
                    </span>

                </div>

            </div>


            <div class="selected-coverage">

                <strong>

                    <?= number_format(
                        (float)$selectedCourse['coverage'],
                        1
                    ) ?>%

                </strong>

                <span>
                    Overall course coverage
                </span>

            </div>

        </div>



        <!-- Status Summary -->

        <div class="status-summary">


            <div class="status-box completed">

                <strong>
                    <?= $selectedCounts['completed'] ?>
                </strong>

                <span>
                    Topics Completed
                </span>

            </div>


            <div class="status-box progressing">

                <strong>
                    <?= $selectedCounts['progress'] ?>
                </strong>

                <span>
                    Topics In Progress
                </span>

            </div>


            <div class="status-box pending">

                <strong>
                    <?= $selectedCounts['not_started'] ?>
                </strong>

                <span>
                    Topics Not Started
                </span>

            </div>

        </div>



        <!-- Topic Table -->

        <div class="table-wrap">

            <table class="data-table">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            TOPIC
                        </th>

                        <th>
                            EXPECTED
                        </th>

                        <th>
                            TAUGHT
                        </th>

                        <th>
                            PROGRESS
                        </th>

                        <th>
                            ENTRIES
                        </th>

                        <th>
                            STATUS
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if ($selectedTopics): ?>


                    <?php foreach (
                        $selectedTopics
                        as $topic
                    ): ?>


                        <tr>


                            <td>

                                <?= e(
                                    $topic['topic_number']
                                ) ?>

                            </td>


                            <td>

                                <div class="topic-title">

                                    <?= e(
                                        $topic['topic_title']
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $topic['description']
                                    )
                                ): ?>

                                    <div class="topic-description">

                                        <?= e(
                                            $topic['description']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= number_format(
                                    (float)$topic['expected_hours'],
                                    1
                                ) ?>

                                h

                            </td>


                            <td>

                                <?= number_format(
                                    (float)$topic['taught_hours'],
                                    1
                                ) ?>

                                h

                            </td>


                            <td>

                                <div class="topic-progress">

                                    <div
                                        class="topic-progress-bar"
                                    >

                                        <span
                                            style="
                                                width:<?= min(
                                                    100,
                                                    max(
                                                        0,
                                                        (float)$topic['coverage']
                                                    )
                                                ) ?>%;
                                            "
                                        ></span>

                                    </div>


                                    <div class="topic-percent">

                                        <?= number_format(
                                            (float)$topic['coverage'],
                                            0
                                        ) ?>%

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?= (int)$topic['coverage_entries'] ?>

                            </td>


                            <td>

                                <span
                                    class="pill <?= e(
                                        $topic['status_class']
                                    ) ?>"
                                >

                                    <?= e(
                                        $topic['status']
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty"
                        >

                            No topics have been created
                            for this course yet.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


    </div>

</div>

<?php endif; ?>


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