<?php

session_start();

/*
|--------------------------------------------------------------------------
| LECTURER PROFILE
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| This page DOES NOT use lecturers.user_id.
|
| Lecturer identification:
|
| $_SESSION['email']
|        ↓
| lecturers.email
|        ↓
| lecturers.lecturer_id
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| ACCESS CONTROL
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
require_once __DIR__ . '/../config/profile_photos.php';


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
| SESSION INFORMATION
|--------------------------------------------------------------------------
*/

$loginEmail = trim(
    $_SESSION['email'] ?? ''
);

$sessionName =
    $_SESSION['full_name'] ?? 'Lecturer';

$userId = (int)($_SESSION['user_id'] ?? 0);


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$lecturerId = 0;

$lecturer = null;

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| FORM STATE
|--------------------------------------------------------------------------
*/

$editMode =
    isset($_GET['edit'])
    &&
    $_GET['edit'] === '1';


/*
|--------------------------------------------------------------------------
| LOAD LECTURER
|--------------------------------------------------------------------------
|
| We deliberately match using EMAIL.
|
| NO:
|
|     WHERE l.user_id = ?
|
|--------------------------------------------------------------------------
*/

if ($loginEmail === '') {

    $error =
        'Unable to load lecturer information because your login account does not contain an email address.';

} else {

    try {

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


        if ($lecturer) {

            $lecturerId =
                (int)$lecturer['lecturer_id'];

        } else {

            $error =
                'Your login account could not be matched to a lecturer profile using your email address.';

        }

    } catch (PDOException $e) {

        $error =
            'Unable to load lecturer information because of a database error.';

    }
}


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
|
| We allow the lecturer to update:
|
| - Full name
| - Phone
|
| We DO NOT allow:
|
| - lecturer_id
| - staff_no
| - department_id
| - email
| - status
|
| These are controlled by the institution.
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $lecturerId > 0
) {

    $action =
        $_POST['action'] ?? '';


    if (
        $action === 'update_profile'
    ) {

        $fullName =
            trim(
                $_POST['full_name'] ?? ''
            );

        $phone =
            trim(
                $_POST['phone'] ?? ''
            );


        /*
        --------------------------------------------------------------
        VALIDATION
        --------------------------------------------------------------
        */

        if ($fullName === '') {

            $error =
                'Full name cannot be empty.';

        } else {

            try {

                if ($userId <= 0) {
                    throw new RuntimeException('Unable to identify your user account for the photo upload.');
                }
                saveProfilePhoto($_FILES['profile_photo'] ?? [], $userId);

                $stmt = $pdo->prepare("
                    UPDATE lecturers

                    SET
                        full_name = ?,
                        phone = ?

                    WHERE lecturer_id = ?

                    LIMIT 1
                ");

                $stmt->execute([
                    $fullName,
                    $phone !== ''
                        ? $phone
                        : null,
                    $lecturerId
                ]);


                /*
                ----------------------------------------------------------
                UPDATE SESSION NAME
                ----------------------------------------------------------
                */

                $_SESSION['full_name'] =
                    $fullName;


                /*
                ----------------------------------------------------------
                RELOAD LECTURER
                ----------------------------------------------------------
                */

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
                        ON d.department_id =
                           l.department_id

                    WHERE LOWER(TRIM(l.email))
                          =
                          LOWER(TRIM(?))

                    LIMIT 1
                ");

                $stmt->execute([
                    $loginEmail
                ]);

                $lecturer =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                $success =
                    'Your profile has been updated successfully.';


                $editMode = false;

            } catch (RuntimeException | PDOException $e) {

                $error =
                    $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update your profile. Please try again.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| DERIVED VALUES
|--------------------------------------------------------------------------
*/

if ($lecturer) {

    $lecturerName =
        $lecturer['full_name']
        ?: $sessionName;

    $lecturerEmail =
        $lecturer['email']
        ?: $loginEmail;

    $staffNo =
        $lecturer['staff_no']
        ?: 'Not assigned';

    $phone =
        $lecturer['phone']
        ?: 'Not provided';

    $departmentName =
        $lecturer['department_name']
        ?: 'Department not assigned';

    $status =
        $lecturer['status']
        ?: 'Active';

} else {

    $lecturerName =
        $sessionName;

    $lecturerEmail =
        $loginEmail;

    $staffNo =
        'Not available';

    $phone =
        'Not available';

    $departmentName =
        'Not available';

    $status =
        'Unknown';
}


/*
|--------------------------------------------------------------------------
| INITIALS
|--------------------------------------------------------------------------
*/

$nameParts =
    preg_split(
        '/\s+/',
        trim($lecturerName)
    );

$initials = '';

foreach (
    array_slice(
        $nameParts,
        0,
        2
    )
    as $part
) {

    if ($part !== '') {

        $initials .=
            strtoupper(
                substr(
                    $part,
                    0,
                    1
                )
            );
    }
}

$photoUrl = $userId > 0 ? profilePhotoUrl($userId) : null;

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
    My Profile | Course Coverage Management System
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

button,
input{
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

    margin:
        25px
        10px
        9px;
}

.side-link{
    color:#eaf7f1;

    display:flex;

    align-items:center;

    gap:11px;

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

.side-bottom{
    margin-top:auto;

    border-top:
        1px solid
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


/* ==========================================================
   TOPBAR
========================================================== */

.topbar{
    height:82px;

    background:#fff;

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
    margin:
        5px
        0
        0;

    font-size:11px;

    color:#7e8e87;
}

.profile{
    display:flex;

    align-items:center;

    gap:10px;
}

.avatar-small{
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


/* ==========================================================
   CONTENT
========================================================== */

.content{
    padding:
        25px
        30px
        35px;
}


/* ==========================================================
   ALERTS
========================================================== */

.alert{
    border-radius:8px;

    padding:
        13px
        15px;

    margin-bottom:18px;

    font-size:11px;
}

.alert.error{
    background:#fff0ef;

    color:#a13b32;

    border:
        1px solid
        #f0d3cf;
}

.alert.success{
    background:#eaf7ef;

    color:#287448;

    border:
        1px solid
        #cce6d7;
}


/* ==========================================================
   PAGE HEADER
========================================================== */

.page-header{
    display:flex;

    justify-content:space-between;

    align-items:flex-end;

    gap:20px;

    margin-bottom:20px;
}

.page-header h2{
    margin:0;

    font-size:17px;

    color:#204238;
}

.page-header p{
    margin:
        6px
        0
        0;

    color:#82918b;

    font-size:10px;
}

.edit-button{
    display:inline-block;

    background:#0d6848;

    color:#fff;

    padding:
        10px
        15px;

    border-radius:7px;

    font-size:10px;

    font-weight:700;
}


/* ==========================================================
   PROFILE GRID
========================================================== */

.profile-grid{
    display:grid;

    grid-template-columns:
        280px
        1fr;

    gap:18px;

    align-items:start;
}


/* ==========================================================
   PROFILE CARD
========================================================== */

.profile-card{
    background:#fff;

    border:
        1px solid
        #e3ebe6;

    border-radius:9px;

    padding:25px 20px;

    text-align:center;

    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.avatar-large{
    width:90px;
    height:90px;

    border-radius:50%;

    margin:
        0
        auto
        15px;

    background:#e8f4ee;

    color:#0d6848;

    display:grid;

    place-items:center;

    font-size:28px;

    font-weight:700;
}

.profile-card h2{
    margin:0;

    font-size:16px;

    color:#204238;
}

.profile-card .role{
    margin-top:6px;

    color:#7d8c86;

    font-size:10px;
}

.status{
    display:inline-block;

    margin-top:13px;

    padding:
        5px
        10px;

    border-radius:20px;

    background:#e8f6ee;

    color:#287448;

    font-size:8px;

    font-weight:700;

    text-transform:uppercase;
}

.profile-card .staff{
    margin-top:18px;

    padding-top:15px;

    border-top:
        1px solid
        #edf2ef;

    color:#7e8d87;

    font-size:9px;
}

.profile-card .staff strong{
    display:block;

    margin-top:5px;

    color:#31564a;

    font-size:11px;
}


/* ==========================================================
   DETAILS
========================================================== */

.details-card{
    background:#fff;

    border:
        1px solid
        #e3ebe6;

    border-radius:9px;

    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);

    overflow:hidden;
}

.card-header{
    padding:
        17px
        20px;

    border-bottom:
        1px solid
        #edf2ef;
}

.card-header h3{
    margin:0;

    font-size:11px;

    letter-spacing:.5px;

    color:#23453a;
}

.card-header p{
    margin:
        5px
        0
        0;

    color:#84928c;

    font-size:10px;
}

.details-body{
    padding:20px;
}

.details-grid{
    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:18px 25px;
}

.detail label{
    display:block;

    color:#8a9892;

    font-size:8px;

    letter-spacing:.5px;

    font-weight:700;

    margin-bottom:6px;
}

.detail-value{
    color:#304e43;

    font-size:11px;

    font-weight:600;

    min-height:20px;
}

.detail-value.muted{
    color:#8a9892;

    font-weight:400;
}


/* ==========================================================
   EDIT FORM
========================================================== */

.form-grid{
    display:grid;

    grid-template-columns:
        repeat(2,1fr);

    gap:18px 25px;
}

.form-field label{
    display:block;

    color:#778980;

    font-size:8px;

    font-weight:700;

    letter-spacing:.5px;

    margin-bottom:6px;
}

.form-field input{
    width:100%;

    padding:
        10px
        11px;

    border:
        1px solid
        #dce6e1;

    border-radius:7px;

    outline:none;

    color:#304c42;

    font-size:10px;

    background:#fff;
}

.form-field input:focus{
    border-color:#0d6848;
}

.form-field input:disabled{
    background:#f4f7f5;

    color:#899791;

    cursor:not-allowed;
}

.form-help{
    margin-top:5px;

    color:#9aa6a1;

    font-size:8px;
}

.form-actions{
    margin-top:22px;

    padding-top:17px;

    border-top:
        1px solid
        #edf2ef;

    display:flex;

    justify-content:flex-end;

    gap:8px;
}

.cancel-button{
    display:inline-block;

    background:#edf3f0;

    color:#4e675d;

    border:0;

    padding:
        10px
        15px;

    border-radius:7px;

    font-size:10px;

    font-weight:700;
}

.save-button{
    border:0;

    background:#0d6848;

    color:#fff;

    padding:
        10px
        18px;

    border-radius:7px;

    font-size:10px;

    font-weight:700;

    cursor:pointer;
}


/* ==========================================================
   ACCOUNT INFORMATION
========================================================== */

.account-card{
    margin-top:18px;

    background:#fff;

    border:
        1px solid
        #e3ebe6;

    border-radius:9px;

    overflow:hidden;

    box-shadow:
        0 2px 8px
        rgba(25,70,53,.03);
}

.account-grid{
    padding:20px;

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:15px;
}

.account-item{
    background:#f8faf9;

    border:
        1px solid
        #e7eeea;

    border-radius:7px;

    padding:13px;
}

.account-item span{
    display:block;

    color:#8b9993;

    font-size:8px;
}

.account-item strong{
    display:block;

    margin-top:5px;

    color:#304e43;

    font-size:10px;
}


/* ==========================================================
   FOOTER
========================================================== */

.footer{
    padding:
        17px
        30px;

    border-top:
        1px solid
        #e3ebe6;

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

@media(max-width:900px){

    .profile-grid{
        grid-template-columns:1fr;
    }

    .profile-card{
        text-align:left;

        display:flex;

        align-items:center;

        gap:18px;
    }

    .avatar-large{
        margin:0;

        flex-shrink:0;
    }

    .stats{
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:800px){

    .sidebar{
        transform:
            translateX(-100%);

        transition:.2s;
    }

    .sidebar.open{
        transform:
            translateX(0);
    }

    .main{
        margin-left:0;
    }

    .mobile-menu{
        display:block;
    }

    .topbar{
        padding:
            0
            16px;
    }

    .content{
        padding:
            18px
            15px
            25px;
    }

    .profile-text{
        display:none;
    }

    .details-grid,
    .form-grid{
        grid-template-columns:1fr;
    }

    .account-grid{
        grid-template-columns:1fr;
    }

    .footer{
        flex-direction:column;

        padding:
            16px
            15px;
    }

}


@media(max-width:500px){

    .page-header{
        align-items:flex-start;

        flex-direction:column;
    }

    .profile-card{
        display:block;

        text-align:center;
    }

    .avatar-large{
        margin:
            0
            auto
            15px;
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
        class="side-link"
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
        href="coverage_history.php"
    >

        <span class="side-icon">
            ◷
        </span>

        Coverage History

    </a>


    <a
        class="side-link active"
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
                My Profile
            </h1>

            <p>
                View and manage your lecturer profile
            </p>

        </div>

    </div>


    <div class="profile">

        <div class="avatar-small">

            <?= e(
                $initials ?: 'L'
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
            </span>

        </div>

    </div>

</header>



<!-- ======================================================
     CONTENT
======================================================= -->

<section class="content">


<?php if ($error): ?>

    <div class="alert error">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<?php if ($success): ?>

    <div class="alert success">

        <?= e($success) ?>

    </div>

<?php endif; ?>



<!-- ======================================================
     PAGE HEADER
======================================================= -->

<div class="page-header">


    <div>

        <h2>
            Lecturer Profile
        </h2>

        <p>
            Your professional and account information.
        </p>

    </div>


    <?php if (
        $lecturerId > 0
        &&
        !$editMode
    ): ?>

        <a
            href="profile.php?edit=1"
            class="edit-button"
        >
            Edit Profile
        </a>

    <?php endif; ?>


</div>



<!-- ======================================================
     PROFILE GRID
======================================================= -->

<div class="profile-grid">


<!-- ======================================================
     LEFT PROFILE CARD
======================================================= -->

<div class="profile-card">


    <div class="avatar-large">

        <?= e(
            $initials ?: 'L'
        ) ?>

    </div>


    <div>

        <h2>

            <?= e(
                $lecturerName
            ) ?>

        </h2>


        <div class="role">

            Lecturer

        </div>


        <span class="status">

            <?= e(
                $status
            ) ?>

        </span>


        <div class="staff">

            Staff Number

            <strong>

                <?= e(
                    $staffNo
                ) ?>

            </strong>

        </div>

    </div>


</div>



<!-- ======================================================
     RIGHT DETAILS
======================================================= -->

<div class="details-card">


    <div class="card-header">

        <h3>
            PROFESSIONAL INFORMATION
        </h3>

        <p>
            Information associated with your lecturer profile.
        </p>

    </div>


    <?php if (
        $editMode
        &&
        $lecturerId > 0
    ): ?>


        <!-- ==================================================
             EDIT FORM
        =================================================== -->

        <div class="details-body">


            <form
                method="POST"
                action="profile.php"
                enctype="multipart/form-data"
            >


                <input
                    type="hidden"
                    name="action"
                    value="update_profile"
                >


                <div class="form-grid">


                    <!-- FULL NAME -->

                    <div class="form-field">

                        <label>
                            FULL NAME
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            value="<?= e(
                                $lecturer['full_name']
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- EMAIL -->

                    <div class="form-field">

                        <label>
                            EMAIL ADDRESS
                        </label>

                        <input
                            type="email"
                            value="<?= e(
                                $lecturerEmail
                            ) ?>"
                            disabled
                        >

                        <div class="form-help">
                            Your login email is managed by the system.
                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="form-field">

                        <label>
                            PHONE NUMBER
                        </label>

                        <input
                            type="text"
                            name="phone"
                            value="<?= e(
                                $lecturer['phone'] ?? ''
                            ) ?>"
                            placeholder="Enter phone number"
                        >

                    </div>


                    <!-- STAFF NUMBER -->

                    <div class="form-field">

                        <label>
                            STAFF NUMBER
                        </label>

                        <input
                            type="text"
                            value="<?= e(
                                $staffNo
                            ) ?>"
                            disabled
                        >

                        <div class="form-help">
                            Staff number cannot be changed here.
                        </div>

                    </div>


                    <!-- DEPARTMENT -->

                    <div class="form-field">

                        <label>
                            DEPARTMENT
                        </label>

                        <input
                            type="text"
                            value="<?= e(
                                $departmentName
                            ) ?>"
                            disabled
                        >

                        <div class="form-help">
                            Department is managed by the institution.
                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="form-field">

                        <label>
                            ACCOUNT STATUS
                        </label>

                        <input
                            type="text"
                            value="<?= e(
                                $status
                            ) ?>"
                            disabled
                        >

                    </div>


                </div>



                <div class="form-field">

                    <label for="profile_photo">PROFILE PHOTO</label>

                    <input id="profile_photo" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">

                    <div class="form-help">JPEG, PNG, or WebP image; maximum 5 MB.</div>

                </div>

                <!-- FORM ACTIONS -->

                <div class="form-actions">


                    <a
                        href="profile.php"
                        class="cancel-button"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        class="save-button"
                    >
                        Save Changes
                    </button>


                </div>


            </form>

        </div>


    <?php else: ?>


        <!-- ==================================================
             VIEW MODE
        =================================================== -->

        <div class="details-body">


            <div class="details-grid">


                <div class="detail">

                    <label>
                        FULL NAME
                    </label>

                    <div class="detail-value">

                        <?= e(
                            $lecturerName
                        ) ?>

                    </div>

                </div>


                <div class="detail">

                    <label>
                        EMAIL ADDRESS
                    </label>

                    <div class="detail-value">

                        <?= e(
                            $lecturerEmail
                        ) ?>

                    </div>

                </div>


                <div class="detail">

                    <label>
                        PHONE NUMBER
                    </label>

                    <div
                        class="detail-value
                        <?= $phone === 'Not provided'
                            ? 'muted'
                            : ''
                        ?>"
                    >

                        <?= e(
                            $phone
                        ) ?>

                    </div>

                </div>


                <div class="detail">

                    <label>
                        STAFF NUMBER
                    </label>

                    <div class="detail-value">

                        <?= e(
                            $staffNo
                        ) ?>

                    </div>

                </div>


                <div class="detail">

                    <label>
                        DEPARTMENT
                    </label>

                    <div class="detail-value">

                        <?= e(
                            $departmentName
                        ) ?>

                    </div>

                </div>


                <div class="detail">

                    <label>
                        ACCOUNT STATUS
                    </label>

                    <div class="detail-value">

                        <?= e(
                            $status
                        ) ?>

                    </div>

                </div>


            </div>


        </div>


    <?php endif; ?>


</div>


</div>



<!-- ======================================================
     ACCOUNT INFORMATION
======================================================= -->

<div class="account-card">


    <div class="card-header">

        <h3>
            ACCOUNT INFORMATION
        </h3>

        <p>
            Your system account and profile relationship.
        </p>

    </div>


    <div class="account-grid">


        <div class="account-item">

            <span>
                LOGIN EMAIL
            </span>

            <strong>
                <?= e(
                    $loginEmail
                ) ?>
            </strong>

        </div>


        <div class="account-item">

            <span>
                ACCOUNT ROLE
            </span>

            <strong>
                Lecturer
            </strong>

        </div>


        <div class="account-item">

            <span>
                PROFILE ID
            </span>

            <strong>

                <?php if (
                    $lecturerId > 0
                ): ?>

                    #<?= $lecturerId ?>

                <?php else: ?>

                    Not found

                <?php endif; ?>

            </strong>

        </div>


    </div>


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


<script>
const profilePhoto = <?=json_encode($photoUrl)?>;
if (profilePhoto) document.querySelectorAll('.avatar-large,.avatar-small').forEach((avatar) => {
    avatar.textContent = '';
    avatar.style.backgroundImage = `url("${profilePhoto}")`;
    avatar.style.backgroundSize = 'cover';
    avatar.style.backgroundPosition = 'center';
});
</script>
</body>

</html>
