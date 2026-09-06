<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter your email address and password.';
    header('Location: ../index.php');
    exit;
}

try {
    // The same login works for Admin, HOD and Lecturer.
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.full_name, u.email, u.password, u.role_id, u.status,
                r.role_name
         FROM users u
         INNER JOIN roles r ON r.role_id = u.role_id
         WHERE u.email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        header('Location: ../index.php');
        exit;
    }

    if (strtolower($user['status']) !== 'active') {
        $_SESSION['login_error'] = 'Your account is not active. Please contact the administrator.';
        header('Location: ../index.php');
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        header('Location: ../index.php');
        exit;
    }

    // Prevent session fixation after successful authentication.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = (int) $user['role_id'];
    $_SESSION['role'] = strtolower(trim($user['role_name']));
    $_SESSION['logged_in'] = true;

    // Different dashboards from one login page.
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;

        case 'hod':
        case 'head of department':
            header('Location: ../hod/dashboard.php');
            break;

        case 'lecturer':
            header('Location: ../lecturer/dashboard.php');
            break;

        default:
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['login_error'] = 'Your account has an unsupported role. Please contact the administrator.';
            header('Location: ../index.php');
            break;
    }
    exit;

} catch (PDOException $e) {
    $_SESSION['login_error'] = 'Unable to process your login right now. Please try again.';
    header('Location: ../index.php');
    exit;
}
