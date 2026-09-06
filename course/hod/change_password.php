<?php

session_start();


/*
|--------------------------------------------------------------------------
| CHECK LOGIN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {

    header("Location: index.php");

    exit;
}


require_once __DIR__ . '/config/database.php';


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


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$userId =
    (int)$_SESSION['user_id'];

$userRole =
    strtolower(
        trim(
            $_SESSION['role'] ?? ''
        )
    );

$userName =
    $_SESSION['full_name']
    ?? 'User';


/*
|--------------------------------------------------------------------------
| ROLE DISPLAY
|--------------------------------------------------------------------------
*/

if (
    $userRole === 'admin' ||
    $userRole === 'administrator'
) {

    $roleDisplay = 'Administrator';

    $dashboard =
        'admin/dashboard.php';

}
elseif ($userRole === 'hod') {

    $roleDisplay = 'Head of Department';

    $dashboard =
        'hod/dashboard.php';

}
elseif ($userRole === 'lecturer') {

    $roleDisplay = 'Lecturer';

    $dashboard =
        'lecturer/dashboard.php';

}
else {

    $roleDisplay =
        ucfirst($userRole);

    $dashboard =
        'index.php';
}


/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['password_csrf'])) {

    $_SESSION['password_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrf =
    $_SESSION['password_csrf'];


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
        |--------------------------------------------------------------------------
        | VERIFY CSRF
        |--------------------------------------------------------------------------
        */

        if (!hash_equals(
            $csrf,
            $_POST['csrf_token'] ?? ''
        )) {

            throw new RuntimeException(
                'Invalid security token. Please refresh the page.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET FORM VALUES
        |--------------------------------------------------------------------------
        */

        $currentPassword =
            $_POST['current_password']
            ?? '';

        $newPassword =
            $_POST['new_password']
            ?? '';

        $confirmPassword =
            $_POST['confirm_password']
            ?? '';


        /*
        |--------------------------------------------------------------------------
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $currentPassword === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {

            throw new RuntimeException(
                'Please complete all password fields.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD LENGTH
        |--------------------------------------------------------------------------
        */

        if (strlen($newPassword) < 8) {

            throw new RuntimeException(
                'The new password must contain at least 8 characters.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | CONFIRM PASSWORD
        |--------------------------------------------------------------------------
        */

        if ($newPassword !== $confirmPassword) {

            throw new RuntimeException(
                'The new passwords do not match.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "SELECT
                user_id,
                password,
                status

             FROM users

             WHERE user_id = ?

             LIMIT 1"
        );


        $stmt->execute([
            $userId
        ]);


        $user =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | USER NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!$user) {

            throw new RuntimeException(
                'Your user account could not be found.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ACCOUNT STATUS
        |--------------------------------------------------------------------------
        */

        if (
            isset($user['status']) &&
            strtolower($user['status']) !== 'active'
        ) {

            throw new RuntimeException(
                'Your account is not active.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFY CURRENT PASSWORD
        |--------------------------------------------------------------------------
        */

        if (!password_verify(
            $currentPassword,
            $user['password']
        )) {

            throw new RuntimeException(
                'The current password is incorrect.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT SAME PASSWORD
        |--------------------------------------------------------------------------
        */

        if (password_verify(
            $newPassword,
            $user['password']
        )) {

            throw new RuntimeException(
                'Your new password must be different from your current password.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | HASH NEW PASSWORD
        |--------------------------------------------------------------------------
        */

        $hashedPassword =
            password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );


        /*
        |--------------------------------------------------------------------------
        | UPDATE DATABASE
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "UPDATE users

             SET password = ?

             WHERE user_id = ?"
        );


        $stmt->execute([
            $hashedPassword,
            $userId
        ]);


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        $success =
            'Your password has been changed successfully.';


        /*
        |--------------------------------------------------------------------------
        | CLEAR FORM
        |--------------------------------------------------------------------------
        */

        $_POST = [];


    }
    catch (RuntimeException $ex) {

        $error =
            $ex->getMessage();

    }
    catch (PDOException $ex) {

        $error =
            'A database error occurred. Please try again.';
    }
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
Change Password | Course Coverage Management System
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

    --success: #26713b;

    --success-bg: #eaf7ed;

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


/* ==================================================
   CONTENT
================================================== */

.content {

    padding: 30px;
}


/* ==================================================
   PAGE TITLE
================================================== */

.page-title {

    margin-bottom: 20px;
}


.page-title h2 {

    margin: 0;

    font-size: 20px;

    color: var(--green-dark);
}


.page-title p {

    margin: 5px 0 0;

    font-size: 11px;

    color: var(--muted);
}


/* ==================================================
   ALERTS
================================================== */

.alert {

    padding: 13px 15px;

    border-radius: 8px;

    font-size: 11px;

    margin-bottom: 18px;

    max-width: 720px;
}


.alert.success {

    background:
        var(--success-bg);

    color:
        var(--success);

    border:
        1px solid #ccebd4;
}


.alert.error {

    background:
        var(--danger-bg);

    color:
        var(--danger);

    border:
        1px solid #f2cbc6;
}


/* ==================================================
   PASSWORD AREA
================================================== */

.password-layout {

    display: grid;

    grid-template-columns:
        minmax(0, 700px)
        300px;

    gap: 20px;

    align-items: start;
}


.panel {

    background: #fff;

    border:
        1px solid var(--border);

    border-radius: 10px;

    box-shadow: var(--shadow);
}


.panel-header {

    padding: 18px 22px;

    border-bottom:
        1px solid var(--border);
}


.panel-header h3 {

    margin: 0;

    font-size: 13px;

    color: var(--green-dark);
}


.panel-header p {

    margin: 5px 0 0;

    font-size: 10px;

    color: var(--muted);
}


.form {

    padding: 22px;
}


.field {

    margin-bottom: 17px;
}


.field label {

    display: block;

    font-size: 10px;

    font-weight: 800;

    color: #5f7068;

    margin-bottom: 7px;
}


.password-input {

    position: relative;
}


.password-input input {

    width: 100%;

    padding:
        11px 42px 11px 11px;

    border:
        1px solid #dce5e0;

    border-radius: 7px;

    font-size: 11px;

    outline: none;

    background: #fff;
}


.password-input input:focus {

    border-color:
        var(--green);
}


.toggle-password {

    position: absolute;

    right: 10px;

    top: 50%;

    transform:
        translateY(-50%);

    border: none;

    background: transparent;

    cursor: pointer;

    color: #74827c;

    font-size: 11px;
}


.password-hint {

    margin-top: 6px;

    font-size: 9px;

    color: var(--muted);

    line-height: 1.5;
}


.form-actions {

    display: flex;

    gap: 8px;

    margin-top: 5px;
}


.btn {

    border: none;

    border-radius: 8px;

    padding:
        10px 15px;

    font-size: 11px;

    font-weight: 700;

    cursor: pointer;

    text-decoration: none;

    display: inline-block;
}


.btn-primary {

    background:
        var(--green);

    color: #fff;
}


.btn-primary:hover {

    background:
        var(--green-dark);
}


.btn-secondary {

    background:
        #eef3f0;

    color:
        var(--green);
}


/* ==================================================
   INFORMATION CARD
================================================== */

.info {

    padding: 20px;
}


.info h3 {

    margin: 0 0 12px;

    font-size: 12px;

    color: var(--green-dark);
}


.info-item {

    padding: 10px 0;

    border-bottom:
        1px solid #edf1ef;
}


.info-item:last-child {

    border-bottom: none;
}


.info-item span {

    display: block;

    font-size: 9px;

    color: var(--muted);

    margin-bottom: 4px;
}


.info-item strong {

    font-size: 11px;

    color: var(--text);
}


.security-list {

    margin: 16px 0 0;

    padding-left: 16px;
}


.security-list li {

    font-size: 10px;

    color: #65746e;

    margin-bottom: 8px;

    line-height: 1.4;
}


/* ==================================================
   RESPONSIVE
================================================== */

@media(max-width:950px) {

    .password-layout {

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


    .topbar {

        padding: 0 15px;
    }


    .content {

        padding: 20px 15px;
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
src="assets/images/ub-logo.png"
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

<?=e($roleDisplay)?>

</div>


<div class="menu-title">
MAIN MENU
</div>


<?php if (
    $userRole === 'admin' ||
    $userRole === 'administrator'
): ?>


<a
class="side-link"
href="admin/dashboard.php"
>

<span class="icon">
⌂
</span>

Dashboard

</a>


<a
class="side-link"
href="admin/user.php"
>

<span class="icon">
♟
</span>

Users

</a>


<a
class="side-link"
href="admin/department.php"
>

<span class="icon">
▣
</span>

Departments

</a>


<a
class="side-link"
href="admin/programs.php"
>

<span class="icon">
▤
</span>

Programs

</a>


<a
class="side-link"
href="admin/courses.php"
>

<span class="icon">
▦
</span>

Courses

</a>


<a
class="side-link"
href="admin/academic_years.php"
>

<span class="icon">
◫
</span>

Academic Years

</a>


<a
class="side-link"
href="admin/semesters.php"
>

<span class="icon">
◳
</span>

Semesters

</a>


<?php elseif (
    $userRole === 'hod'
): ?>


<a
class="side-link"
href="hod/dashboard.php"
>

<span class="icon">
⌂
</span>

Dashboard

</a>


<a
class="side-link"
href="hod/assign_course.php"
>

<span class="icon">
▦
</span>

Assign Courses

</a>


<a
class="side-link"
href="hod/assignments.php"
>

<span class="icon">
▤
</span>

Assignments

</a>


<a
class="side-link"
href="hod/coverage.php"
>

<span class="icon">
◫
</span>

Coverage

</a>


<a
class="side-link"
href="hod/reports.php"
>

<span class="icon">
▥
</span>

Reports

</a>


<?php elseif (
    $userRole === 'lecturer'
): ?>


<a
class="side-link"
href="lecturer/dashboard.php"
>

<span class="icon">
⌂
</span>

Dashboard

</a>


<a
class="side-link"
href="lecturer/my_courses.php"
>

<span class="icon">
▦
</span>

My Courses

</a>


<a
class="side-link"
href="lecturer/coverage.php"
>

<span class="icon">
◫
</span>

Coverage

</a>


<a
class="side-link"
href="lecturer/history.php"
>

<span class="icon">
▤
</span>

History

</a>


<?php endif; ?>


<div class="menu-title">
ACCOUNT
</div>


<a
class="side-link"
href="<?=e($dashboard)?>"
>

<span class="icon">
⌂
</span>

Dashboard

</a>


<a
class="side-link active"
href="change_password.php"
>

<span class="icon">
▣
</span>

Change Password

</a>


<a
class="side-link"
href="auth/logout.php"
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
Change Password
</h1>

<p>
Secure your account by updating your password.
</p>

</div>


<div class="profile">


<img
class="avatar"
src="assets/images/ub-logo.png"
alt="User"
>


<div class="profile-text">

<strong>
<?=e($userName)?>
</strong>

<span>
<?=e($roleDisplay)?>
</span>

</div>


</div>


</header>



<section class="content">


<div class="page-title">

<h2>
Change Your Password
</h2>

<p>
Update the password used to access your account.
</p>

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



<div class="password-layout">


<!-- ==================================================
     PASSWORD FORM
================================================== -->

<section class="panel">


<div class="panel-header">

<h3>
PASSWORD UPDATE
</h3>

<p>
Enter your current password and choose a new password.
</p>

</div>


<form
method="post"
class="form"
autocomplete="off"
>


<input
type="hidden"
name="csrf_token"
value="<?=e($csrf)?>"
>


<!-- CURRENT PASSWORD -->

<div class="field">

<label>
CURRENT PASSWORD
</label>


<div class="password-input">

<input
type="password"
id="current_password"
name="current_password"
placeholder="Enter your current password"
required
>


<button
type="button"
class="toggle-password"
onclick="togglePassword('current_password', this)"
>

Show

</button>

</div>

</div>



<!-- NEW PASSWORD -->

<div class="field">

<label>
NEW PASSWORD
</label>


<div class="password-input">

<input
type="password"
id="new_password"
name="new_password"
placeholder="Enter your new password"
minlength="8"
required
>


<button
type="button"
class="toggle-password"
onclick="togglePassword('new_password', this)"
>

Show

</button>

</div>


<div class="password-hint">

Use at least 8 characters. A combination of
uppercase letters, lowercase letters, numbers,
and symbols is recommended.

</div>

</div>



<!-- CONFIRM -->

<div class="field">

<label>
CONFIRM NEW PASSWORD
</label>


<div class="password-input">

<input
type="password"
id="confirm_password"
name="confirm_password"
placeholder="Confirm your new password"
minlength="8"
required
>


<button
type="button"
class="toggle-password"
onclick="togglePassword('confirm_password', this)"
>

Show

</button>

</div>

</div>



<div class="form-actions">


<button
type="submit"
class="btn btn-primary"
>

Change Password

</button>


<a
href="<?=e($dashboard)?>"
class="btn btn-secondary"
>

Cancel

</a>


</div>


</form>


</section>



<!-- ==================================================
     ACCOUNT INFORMATION
================================================== -->

<section class="panel">


<div class="info">


<h3>
ACCOUNT INFORMATION
</h3>


<div class="info-item">

<span>
NAME
</span>

<strong>
<?=e($userName)?>
</strong>

</div>


<div class="info-item">

<span>
ROLE
</span>

<strong>
<?=e($roleDisplay)?>
</strong>

</div>


<div class="info-item">

<span>
ACCOUNT
</span>

<strong>
Active
</strong>

</div>


<h3 style="margin-top:20px;">
PASSWORD SECURITY
</h3>


<ul class="security-list">

<li>
Never share your password with another person.
</li>

<li>
Use a password that is difficult to guess.
</li>

<li>
Do not reuse your system password elsewhere.
</li>

<li>
Always log out when using a shared computer.
</li>

</ul>


</div>


</section>


</div>


</section>


</main>



<script>

function togglePassword(
    fieldId,
    button
)
{

    const field =
        document.getElementById(
            fieldId
        );


    if (
        field.type === 'password'
    ) {

        field.type = 'text';

        button.textContent =
            'Hide';

    } else {

        field.type = 'password';

        button.textContent =
            'Show';
    }
}

</script>


</body>

</html>