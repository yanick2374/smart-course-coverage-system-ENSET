<?php

session_start();

if (
    empty($_SESSION['logged_in']) ||
    !in_array(
        strtolower($_SESSION['role'] ?? ''),
        ['hod', 'head of department'],
        true
    )
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
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrf =
    $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| HOD INFORMATION
|--------------------------------------------------------------------------
*/

$hodName =
    $_SESSION['full_name']
    ?? 'Head of Department';

$userId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| ASSIGNMENT ID
|--------------------------------------------------------------------------
*/

$assignmentId =
    (int)(
        $_GET['assignment_id']
        ?? $_POST['assignment_id']
        ?? 0
    );


if (!$assignmentId) {

    $_SESSION['error'] =
        'No course assignment was selected.';

    header(
        'Location: dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET HOD DEPARTMENT
|--------------------------------------------------------------------------
*/

$departmentId = 0;

$departmentName =
    'Department not assigned';

try {

    $stmt = $pdo->prepare("
        SELECT
            u.department_id,
            d.department_name

        FROM users u

        LEFT JOIN departments d
            ON d.department_id =
               u.department_id

        WHERE u.user_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $userId
    ]);

    $hod =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($hod) {

        $departmentId =
            (int)(
                $hod['department_id']
                ?? 0
            );

        $departmentName =
            $hod['department_name']
            ?: 'Department not assigned';
    }

} catch (PDOException $e) {

    $_SESSION['error'] =
        'Unable to determine your department.';

    header(
        'Location: dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD COURSE ASSIGNMENT
|--------------------------------------------------------------------------
|
| We verify that this assignment belongs to
| the HOD's department.
|
*/

$assignment = null;

try {

    $stmt = $pdo->prepare("
        SELECT

            ca.assignment_id,

            ca.course_id,
            ca.lecturer_id,
            ca.program_id,
            ca.session_id,

            ca.semester,
            ca.level,

            c.course_code,
            c.course_name,

            l.full_name AS lecturer_name,
            l.staff_no,

            p.program_name,

            s.session_name

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

        INNER JOIN academic_session s
            ON s.session_id =
               ca.session_id

        WHERE ca.assignment_id = ?

        AND c.department_id = ?

        AND l.department_id = ?

        AND p.department_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $assignmentId,
        $departmentId,
        $departmentId,
        $departmentId
    ]);

    $assignment =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $_SESSION['error'] =
        'Unable to load the course assignment.';

    header(
        'Location: dashboard.php'
    );

    exit;
}


if (!$assignment) {

    $_SESSION['error'] =
        'The selected course assignment does not belong to your department.';

    header(
        'Location: dashboard.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ADD TOPIC
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['action'] ?? '') === 'add_topic'
) {


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (
        !hash_equals(
            $csrf,
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $_SESSION['error'] =
            'Invalid security token.';

        header(
            'Location: manage_scheme.php?assignment_id='
            . $assignmentId
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | TOPIC DATA
    |--------------------------------------------------------------------------
    */

    $topicNumber =
        (int)(
            $_POST['topic_number']
            ?? 0
        );

    $topicTitle =
        trim(
            $_POST['topic_title']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $topicNumber <= 0
        ||
        $topicTitle === ''
    ) {

        $_SESSION['error'] =
            'Please provide a valid topic number and topic title.';

        header(
            'Location: manage_scheme.php?assignment_id='
            . $assignmentId
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE TOPIC NUMBER
        |--------------------------------------------------------------------------
        */

        $check =
            $pdo->prepare("
                SELECT scheme_topic_id

                FROM course_scheme_topics

                WHERE assignment_id = ?
                AND topic_number = ?

                LIMIT 1
            ");

        $check->execute([
            $assignmentId,
            $topicNumber
        ]);


        if ($check->fetch()) {

            $_SESSION['error'] =
                'Topic number '
                . $topicNumber
                . ' already exists for this course.';

            header(
                'Location: manage_scheme.php?assignment_id='
                . $assignmentId
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT TOPIC
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare("
                INSERT INTO course_scheme_topics
                (
                    assignment_id,
                    topic_number,
                    topic_title,
                    created_at
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");

        $stmt->execute([
            $assignmentId,
            $topicNumber,
            $topicTitle
        ]);


        $_SESSION['success'] =
            'Topic added successfully.';


    } catch (PDOException $e) {

        $_SESSION['error'] =
            'Unable to add the topic.';
    }


    header(
        'Location: manage_scheme.php?assignment_id='
        . $assignmentId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DELETE TOPIC
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['action'] ?? '') === 'delete_topic'
) {


    if (
        !hash_equals(
            $csrf,
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $_SESSION['error'] =
            'Invalid security token.';

        header(
            'Location: manage_scheme.php?assignment_id='
            . $assignmentId
        );

        exit;
    }


    $schemeTopicId =
        (int)(
            $_POST['scheme_topic_id']
            ?? 0
        );


    if ($schemeTopicId > 0) {

        try {

            /*
            |--------------------------------------------------------------------------
            | DELETE ONLY IF TOPIC BELONGS TO THIS ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("
                    DELETE FROM course_scheme_topics

                    WHERE scheme_topic_id = ?

                    AND assignment_id = ?
                ");

            $stmt->execute([
                $schemeTopicId,
                $assignmentId
            ]);


            if (
                $stmt->rowCount() > 0
            ) {

                $_SESSION['success'] =
                    'Topic deleted successfully.';

            } else {

                $_SESSION['error'] =
                    'Topic could not be found.';
            }


        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | THIS CAN HAPPEN IF COURSE_COVERAGE REFERENCES THE TOPIC
            |--------------------------------------------------------------------------
            */

            $_SESSION['error'] =
                'This topic cannot be deleted because it is already being used in course coverage.';
        }
    }


    header(
        'Location: manage_scheme.php?assignment_id='
        . $assignmentId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| EDIT TOPIC
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    ($_POST['action'] ?? '') === 'edit_topic'
) {


    if (
        !hash_equals(
            $csrf,
            $_POST['csrf_token'] ?? ''
        )
    ) {

        $_SESSION['error'] =
            'Invalid security token.';

        header(
            'Location: manage_scheme.php?assignment_id='
            . $assignmentId
        );

        exit;
    }


    $schemeTopicId =
        (int)(
            $_POST['scheme_topic_id']
            ?? 0
        );

    $topicNumber =
        (int)(
            $_POST['topic_number']
            ?? 0
        );

    $topicTitle =
        trim(
            $_POST['topic_title']
            ?? ''
        );


    if (
        $schemeTopicId <= 0
        ||
        $topicNumber <= 0
        ||
        $topicTitle === ''
    ) {

        $_SESSION['error'] =
            'Invalid topic information.';

        header(
            'Location: manage_scheme.php?assignment_id='
            . $assignmentId
        );

        exit;
    }


    try {

        /*
        |--------------------------------------------------------------------------
        | CHECK DUPLICATE NUMBER
        |--------------------------------------------------------------------------
        */

        $check =
            $pdo->prepare("
                SELECT scheme_topic_id

                FROM course_scheme_topics

                WHERE assignment_id = ?

                AND topic_number = ?

                AND scheme_topic_id != ?

                LIMIT 1
            ");

        $check->execute([
            $assignmentId,
            $topicNumber,
            $schemeTopicId
        ]);


        if ($check->fetch()) {

            $_SESSION['error'] =
                'Another topic already uses that topic number.';

            header(
                'Location: manage_scheme.php?assignment_id='
                . $assignmentId
            );

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare("
                UPDATE course_scheme_topics

                SET
                    topic_number = ?,
                    topic_title = ?

                WHERE scheme_topic_id = ?

                AND assignment_id = ?
            ");

        $stmt->execute([
            $topicNumber,
            $topicTitle,
            $schemeTopicId,
            $assignmentId
        ]);


        $_SESSION['success'] =
            'Topic updated successfully.';


    } catch (PDOException $e) {

        $_SESSION['error'] =
            'Unable to update the topic.';
    }


    header(
        'Location: manage_scheme.php?assignment_id='
        . $assignmentId
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD SCHEME TOPICS
|--------------------------------------------------------------------------
*/

$topics = [];

try {

    $stmt =
        $pdo->prepare("
            SELECT

                scheme_topic_id,
                assignment_id,
                topic_number,
                topic_title,
                created_at

            FROM course_scheme_topics

            WHERE assignment_id = ?

            ORDER BY topic_number ASC
        ");

    $stmt->execute([
        $assignmentId
    ]);

    $topics =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $_SESSION['error'] =
        'Unable to load scheme topics.';
}


/*
|--------------------------------------------------------------------------
| FLASH MESSAGES
|--------------------------------------------------------------------------
*/

$success =
    $_SESSION['success']
    ?? '';

$error =
    $_SESSION['error']
    ?? '';

unset(
    $_SESSION['success'],
    $_SESSION['error']
);


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalTopics =
    count($topics);

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
    Manage Scheme of Work
</title>


<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:#f5f8f6;

    color:#18382d;

    font-size:13px;
}

a{
    text-decoration:none;
}


/* ======================================================
   LAYOUT
====================================================== */

.app{
    min-height:100vh;

    display:flex;
}


/* ======================================================
   SIDEBAR
====================================================== */

.sidebar{
    width:245px;

    position:fixed;

    left:0;
    top:0;
    bottom:0;

    background:#0d5b3f;

    color:white;

    padding:22px 15px;

    z-index:50;
}

.brand{
    display:flex;

    align-items:center;

    gap:11px;

    padding:
        4px
        8px
        24px;

    border-bottom:
        1px solid
        rgba(255,255,255,.15);
}

.brand-logo{
    width:45px;
    height:45px;

    border-radius:50%;

    background:#fff;

    color:#0d5b3f;

    display:grid;

    place-items:center;

    font-weight:800;
}

.brand strong{
    display:block;

    font-size:12px;
}

.brand span{
    display:block;

    font-size:9px;

    opacity:.7;

    margin-top:4px;
}

.menu-title{
    font-size:9px;

    letter-spacing:1px;

    opacity:.55;

    margin:
        25px
        10px
        9px;
}

.side-link{
    display:flex;

    align-items:center;

    gap:11px;

    color:#eaf7f1;

    padding:
        11px
        12px;

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


/* ======================================================
   MAIN
====================================================== */

.main{
    margin-left:245px;

    flex:1;

    min-width:0;
}


/* ======================================================
   TOPBAR
====================================================== */

.topbar{
    height:82px;

    background:white;

    border-bottom:
        1px solid
        #e4ebe7;

    display:flex;

    justify-content:space-between;

    align-items:center;

    padding:
        0
        30px;
}

.heading h1{
    margin:0;

    font-size:21px;
}

.heading p{
    margin:
        5px
        0
        0;

    color:#7e8e87;

    font-size:11px;
}

.user{
    font-size:11px;

    font-weight:700;
}


/* ======================================================
   CONTENT
====================================================== */

.content{
    padding:
        25px
        30px
        40px;
}


/* ======================================================
   ALERT
====================================================== */

.alert{
    padding:
        12px
        15px;

    border-radius:7px;

    margin-bottom:18px;

    font-size:10px;
}

.alert.success{
    background:#eaf7ef;

    color:#287448;

    border:
        1px solid
        #cce6d7;
}

.alert.error{
    background:#fff0ef;

    color:#a13b32;

    border:
        1px solid
        #f0d3cf;
}


/* ======================================================
   COURSE HEADER
====================================================== */

.course-card{
    background:white;

    border:
        1px solid
        #e3ebe6;

    border-radius:9px;

    padding:20px;

    margin-bottom:18px;

    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.course-top{
    display:flex;

    justify-content:space-between;

    gap:20px;
}

.course-code{
    font-size:10px;

    color:#0d6848;

    font-weight:800;

    letter-spacing:.7px;
}

.course-name{
    margin:
        5px
        0
        0;

    font-size:18px;

    color:#204238;
}

.course-info{
    margin-top:12px;

    display:flex;

    flex-wrap:wrap;

    gap:20px;
}

.info-item{
    font-size:10px;

    color:#84928c;
}

.info-item strong{
    color:#3c594e;

    margin-left:4px;
}


/* ======================================================
   STATS
====================================================== */

.stat{
    min-width:100px;

    text-align:center;

    background:#f5f9f7;

    border:
        1px solid
        #e3ebe6;

    border-radius:7px;

    padding:
        12px
        18px;
}

.stat strong{
    display:block;

    font-size:20px;

    color:#0d6848;
}

.stat span{
    display:block;

    margin-top:4px;

    color:#82918b;

    font-size:8px;

    text-transform:uppercase;
}


/* ======================================================
   GRID
====================================================== */

.grid{
    display:grid;

    grid-template-columns:
        1fr
        330px;

    gap:18px;

    align-items:start;
}


/* ======================================================
   PANEL
====================================================== */

.panel{
    background:white;

    border:
        1px solid
        #e3ebe6;

    border-radius:9px;

    overflow:hidden;

    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.panel-header{
    padding:
        17px
        20px;

    border-bottom:
        1px solid
        #edf2ef;
}

.panel-header h2{
    margin:0;

    font-size:12px;

    color:#23453a;
}

.panel-header p{
    margin:
        5px
        0
        0;

    color:#84928c;

    font-size:9px;
}


/* ======================================================
   TABLE
====================================================== */

.table-wrap{
    overflow-x:auto;
}

table{
    width:100%;

    border-collapse:collapse;
}

th{
    text-align:left;

    padding:
        11px
        16px;

    background:#f7faf8;

    color:#7d8c86;

    font-size:8px;

    letter-spacing:.5px;

    text-transform:uppercase;
}

td{
    padding:
        13px
        16px;

    border-top:
        1px solid
        #edf2ef;

    font-size:10px;

    color:#3d594e;
}

.number{
    width:70px;

    font-weight:800;

    color:#0d6848;
}

.topic{
    font-weight:600;

    color:#304d42;
}

.date{
    color:#8b9892;

    font-size:9px;
}


/* ======================================================
   BUTTONS
====================================================== */

.btn{
    border:0;

    background:#0d6848;

    color:white;

    padding:
        10px
        15px;

    border-radius:7px;

    cursor:pointer;

    font-size:9px;

    font-weight:700;
}

.btn:hover{
    opacity:.9;
}

.btn-delete{
    background:#fff0ef;

    color:#a13b32;

    border:
        1px solid
        #f0d3cf;
}

.btn-edit{
    background:#edf6f1;

    color:#0d6848;

    border:
        1px solid
        #d7e9df;
}


/* ======================================================
   FORM
====================================================== */

.form-body{
    padding:20px;
}

.field{
    margin-bottom:16px;
}

.field label{
    display:block;

    margin-bottom:6px;

    color:#788982;

    font-size:8px;

    font-weight:700;

    letter-spacing:.5px;
}

.field input{
    width:100%;

    padding:
        10px
        11px;

    border:
        1px solid
        #dce6e1;

    border-radius:7px;

    outline:none;

    font-size:10px;

    color:#304e43;
}

.field input:focus{
    border-color:#0d6848;
}

.btn-full{
    width:100%;
}


/* ======================================================
   EMPTY
====================================================== */

.empty{
    padding:45px 20px;

    text-align:center;

    color:#899791;

    font-size:10px;
}

.empty strong{
    display:block;

    color:#60776d;

    margin-bottom:5px;

    font-size:12px;
}


/* ======================================================
   RESPONSIVE
====================================================== */

@media(max-width:900px){

    .grid{
        grid-template-columns:1fr;
    }

}

@media(max-width:800px){

    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
    }

    .topbar{
        padding:
            0
            15px;
    }

    .content{
        padding:
            18px
            15px
            30px;
    }

    .course-top{
        flex-direction:column;
    }

    .course-info{
        flex-direction:column;

        gap:8px;
    }

}

</style>

</head>


<body>


<div class="app">


<!-- ======================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-logo">
            UB
        </div>

        <div>

            <strong>
                COURSE COVERAGE
            </strong>

            <span>
                HOD Portal
            </span>

        </div>

    </div>


    <div class="menu-title">
        HOD MENU
    </div>


    <a
        href="dashboard.php"
        class="side-link"
    >
        <span class="side-icon">⌂</span>
        Dashboard
    </a>


    <a
        href="dashboard.php#assign-course"
        class="side-link"
    >
        <span class="side-icon">＋</span>
        Assign Courses
    </a>


    <a
        href="assigned_courses.php"
        class="side-link active"
    >
        <span class="side-icon">▤</span>
        Assigned Courses
    </a>


    <a
        href="../auth/logout.php"
        class="side-link"
    >
        <span class="side-icon">↪</span>
        Logout
    </a>

</aside>


<!-- ======================================================
     MAIN
====================================================== -->

<main class="main">


<header class="topbar">

    <div class="heading">

        <h1>
            Scheme of Work
        </h1>

        <p>
            Manage topics for an assigned course
        </p>

    </div>


    <div class="user">

        <?= e($hodName) ?>

    </div>

</header>


<section class="content">


<?php if ($success): ?>

    <div class="alert success">

        <?= e($success) ?>

    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="alert error">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<!-- ======================================================
     COURSE INFORMATION
====================================================== -->

<div class="course-card">

    <div class="course-top">

        <div>

            <div class="course-code">

                <?= e(
                    $assignment['course_code']
                ) ?>

            </div>


            <div class="course-name">

                <?= e(
                    $assignment['course_name']
                ) ?>

            </div>


            <div class="course-info">

                <div class="info-item">

                    Lecturer:

                    <strong>
                        <?= e(
                            $assignment['lecturer_name']
                        ) ?>
                    </strong>

                </div>


                <div class="info-item">

                    Program:

                    <strong>
                        <?= e(
                            $assignment['program_name']
                        ) ?>
                    </strong>

                </div>


                <div class="info-item">

                    Session:

                    <strong>
                        <?= e(
                            $assignment['session_name']
                        ) ?>
                    </strong>

                </div>


                <div class="info-item">

                    Semester:

                    <strong>
                        <?= e(
                            $assignment['semester']
                        ) ?>
                    </strong>

                </div>


                <div class="info-item">

                    Level:

                    <strong>
                        <?= e(
                            $assignment['level']
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>


        <div class="stat">

            <strong>
                <?= $totalTopics ?>
            </strong>

            <span>
                Topics
            </span>

        </div>

    </div>

</div>



<!-- ======================================================
     MAIN GRID
====================================================== -->

<div class="grid">


<!-- ======================================================
     TOPICS
====================================================== -->

<div class="panel">

    <div class="panel-header">

        <h2>
            SCHEME OF WORK
        </h2>

        <p>
            Topics that the lecturer is expected to teach.
        </p>

    </div>


    <?php if (!$topics): ?>

        <div class="empty">

            <strong>
                No topics added yet
            </strong>

            Start by adding the first topic
            using the form.

        </div>

    <?php else: ?>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            Topic
                        </th>

                        <th>
                            Added
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $topics
                    as $topic
                ): ?>

                    <tr>


                        <td class="number">

                            <?= (int)
                                $topic['topic_number']
                            ?>

                        </td>


                        <td class="topic">

                            <?= e(
                                $topic['topic_title']
                            ) ?>

                        </td>


                        <td class="date">

                            <?= e(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $topic['created_at']
                                    )
                                )
                            ) ?>

                        </td>


                        <td>

                            <form
                                method="post"
                                style="display:inline"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($csrf) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_topic"
                                >

                                <input
                                    type="hidden"
                                    name="assignment_id"
                                    value="<?= $assignmentId ?>"
                                >

                                <input
                                    type="hidden"
                                    name="scheme_topic_id"
                                    value="<?= (int)$topic['scheme_topic_id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-delete"
                                    onclick="
                                        return confirm(
                                            'Delete this topic?'
                                        );
                                    "
                                >
                                    Delete
                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>


                </tbody>

            </table>

        </div>


    <?php endif; ?>

</div>



<!-- ======================================================
     ADD TOPIC
====================================================== -->

<div class="panel">

    <div class="panel-header">

        <h2>
            ADD TOPIC
        </h2>

        <p>
            Add a topic to this course scheme.
        </p>

    </div>


    <div class="form-body">


        <form
            method="post"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrf) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="add_topic"
            >

            <input
                type="hidden"
                name="assignment_id"
                value="<?= $assignmentId ?>"
            >


            <div class="field">

                <label>
                    TOPIC NUMBER
                </label>

                <input
                    type="number"
                    name="topic_number"
                    min="1"
                    value="<?= $totalTopics + 1 ?>"
                    required
                >

            </div>


            <div class="field">

                <label>
                    TOPIC TITLE
                </label>

                <input
                    type="text"
                    name="topic_title"
                    placeholder="Enter topic title"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn btn-full"
            >

                + Add Topic

            </button>


        </form>

    </div>

</div>


</div>


</section>


</main>


</div>


</body>

</html>