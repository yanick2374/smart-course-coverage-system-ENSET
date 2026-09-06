<?php
session_start();

if (empty($_SESSION['logged_in']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_users'])) {
    $_SESSION['csrf_users'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_users'];

$success = '';
$error = '';
$editUser = null;

try {
    $roles = $pdo->query("SELECT role_id, role_name FROM roles ORDER BY FIELD(LOWER(role_name),'admin','hod','lecturer'), role_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $roles = [];
    $error = 'Unable to load roles from the database.';
}

$roleMap = [];
foreach ($roles as $role) {
    $roleMap[strtolower(trim($role['role_name']))] = (int)$role['role_id'];
}

// Ensure the three application roles exist.
foreach (['Admin', 'HOD', 'Lecturer'] as $requiredRole) {
    if (!isset($roleMap[strtolower($requiredRole)])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO roles (role_name) VALUES (?)");
            $stmt->execute([$requiredRole]);
            $roleMap[strtolower($requiredRole)] = (int)$pdo->lastInsertId();
        } catch (PDOException $ex) {
            $error = 'The required application roles could not be prepared.';
        }
    }
}

$roleMap = array_map('intval', $roleMap);

// Departments are used when creating a Lecturer profile.
try {
    $departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $departments = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create') {
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';
                $roleName = strtolower(trim($_POST['role_name'] ?? ''));
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                $departmentId = (int)($_POST['department_id'] ?? 0);

                if (!in_array($roleName, ['admin', 'hod', 'lecturer'], true)) {
                    throw new RuntimeException('Please select a valid role.');
                }
                if ($fullName === '' || mb_strlen($fullName) < 3) {
                    throw new RuntimeException('Please enter the user\'s full name.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Please enter a valid email address.');
                }
                if (strlen($password) < 8) {
                    throw new RuntimeException('Password must contain at least 8 characters.');
                }
                if ($password !== $confirm) {
                    throw new RuntimeException('The passwords do not match.');
                }
                if (!isset($roleMap[$roleName])) {
                    throw new RuntimeException('Selected role is not available.');
                }

                $check = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $check->execute([$email]);
                if ($check->fetchColumn()) {
                    throw new RuntimeException('An account with this email already exists.');
                }

                $pdo->beginTransaction();

                $insert = $pdo->prepare("INSERT INTO users (full_name, email, password, role_id, department_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                if ($roleName === 'hod') {
                    if ($departmentId <= 0) throw new RuntimeException('Please select the HOD department.');
                    $deptCheck = $pdo->prepare("SELECT department_id FROM departments WHERE department_id = ? LIMIT 1");
                    $deptCheck->execute([$departmentId]);
                    if (!$deptCheck->fetchColumn()) throw new RuntimeException('Selected HOD department does not exist.');
                } else {
                    $departmentId = null;
                }
                $insert->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $roleMap[$roleName], $departmentId, $status]);
                $userId = (int)$pdo->lastInsertId();

                // A Lecturer login is also represented in the lecturers table.
                if ($roleName === 'lecturer') {
                    $staffNo = trim($_POST['staff_no'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $departmentId = (int)($_POST['department_id'] ?? 0);

                    if ($staffNo === '') {
                        throw new RuntimeException('Staff number is required for a Lecturer.');
                    }
                    if ($departmentId <= 0) {
                        throw new RuntimeException('Please select the Lecturer department.');
                    }

                    $staffCheck = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE staff_no = ? LIMIT 1");
                    $staffCheck->execute([$staffNo]);
                    if ($staffCheck->fetchColumn()) {
                        throw new RuntimeException('That staff number is already registered.');
                    }

                    $lecturerInsert = $pdo->prepare("INSERT INTO lecturers (staff_no, full_name, email, department_id, phone, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $lecturerInsert->execute([$staffNo, $fullName, $email, $departmentId, $phone, $status]);
                }

                $pdo->commit();
                $success = ucfirst($roleName) . ' account created successfully. The user can now log in through the common login page.';
            }

            elseif ($action === 'update') {
                $userId = (int)($_POST['user_id'] ?? 0);
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $roleName = strtolower(trim($_POST['role_name'] ?? ''));
                $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                $newPassword = $_POST['password'] ?? '';
                $departmentId = (int)($_POST['department_id'] ?? 0);

                if ($userId <= 0) throw new RuntimeException('Invalid user selected.');
                if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Please provide a valid name and email.');
                if (!isset($roleMap[$roleName])) throw new RuntimeException('Invalid role selected.');

                $check = $pdo->prepare("SELECT user_id FROM users WHERE LOWER(email)=LOWER(?) AND user_id <> ? LIMIT 1");
                $check->execute([$email, $userId]);
                if ($check->fetchColumn()) throw new RuntimeException('Another account already uses that email.');

                $pdo->beginTransaction();
                if ($newPassword !== '') {
                    if (strlen($newPassword) < 8) throw new RuntimeException('New password must contain at least 8 characters.');
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, password=?, role_id=?, department_id=?, status=? WHERE user_id=?");
                    if ($roleName === 'hod') {
                        if ($departmentId <= 0) throw new RuntimeException('Please select the HOD department.');
                    } else { $departmentId = null; }
                    $stmt->execute([$fullName, $email, password_hash($newPassword, PASSWORD_DEFAULT), $roleMap[$roleName], $departmentId, $status, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role_id=?, department_id=?, status=? WHERE user_id=?");
                    if ($roleName === 'hod') {
                        if ($departmentId <= 0) throw new RuntimeException('Please select the HOD department.');
                    } else { $departmentId = null; }
                    $stmt->execute([$fullName, $email, $roleMap[$roleName], $departmentId, $status, $userId]);
                }

                // Keep an existing Lecturer profile synchronized with account details.
                if ($roleName === 'lecturer') {
                    $staffNo = trim($_POST['staff_no'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $departmentId = (int)($_POST['department_id'] ?? 0);
                    if ($staffNo === '' || $departmentId <= 0) throw new RuntimeException('Staff number and department are required for a Lecturer.');

                    $existingLecturer = $pdo->prepare("SELECT lecturer_id FROM lecturers WHERE LOWER(email)=LOWER(?) OR staff_no=? LIMIT 1");
                    $existingLecturer->execute([$email, $staffNo]);
                    $lecturerId = $existingLecturer->fetchColumn();

                    if ($lecturerId) {
                        $u = $pdo->prepare("UPDATE lecturers SET staff_no=?, full_name=?, email=?, department_id=?, phone=?, status=? WHERE lecturer_id=?");
                        $u->execute([$staffNo, $fullName, $email, $departmentId, $phone, $status, $lecturerId]);
                    } else {
                        $i = $pdo->prepare("INSERT INTO lecturers (staff_no, full_name, email, department_id, phone, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $i->execute([$staffNo, $fullName, $email, $departmentId, $phone, $status]);
                    }
                }

                $pdo->commit();
                $success = 'User account updated successfully.';
            }

            elseif ($action === 'toggle_status') {
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) throw new RuntimeException('Invalid user selected.');
                if ($userId === (int)($_SESSION['user_id'] ?? 0)) throw new RuntimeException('You cannot deactivate your own administrator account.');

                $stmt = $pdo->prepare("UPDATE users SET status = CASE WHEN LOWER(status)='active' THEN 'inactive' ELSE 'active' END WHERE user_id=?");
                $stmt->execute([$userId]);
                $success = 'User account status updated.';
            }

            elseif ($action === 'delete') {
                $userId = (int)($_POST['user_id'] ?? 0);
                if ($userId <= 0) throw new RuntimeException('Invalid user selected.');
                if ($userId === (int)($_SESSION['user_id'] ?? 0)) throw new RuntimeException('You cannot delete your own administrator account.');

                $pdo->beginTransaction();
                $find = $pdo->prepare("SELECT email FROM users WHERE user_id=? LIMIT 1");
                $find->execute([$userId]);
                $email = $find->fetchColumn();
                if (!$email) throw new RuntimeException('User account not found.');

                // Remove lecturer profile only when it belongs to this login email.
                $delLecturer = $pdo->prepare("DELETE FROM lecturers WHERE LOWER(email)=LOWER(?)");
                $delLecturer->execute([$email]);
                $delUser = $pdo->prepare("DELETE FROM users WHERE user_id=?");
                $delUser->execute([$userId]);
                $pdo->commit();
                $success = 'User account deleted successfully.';
            }
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Database operation failed. Please check your database connection and table structure.';
        }
    }
}

// Edit mode.
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, u.email, u.role_id, r.role_name, u.status,
                                      l.staff_no, l.phone, l.department_id
                               FROM users u
                               LEFT JOIN roles r ON r.role_id=u.role_id
                               LEFT JOIN lecturers l ON LOWER(l.email)=LOWER(u.email)
                               WHERE u.user_id=? LIMIT 1");
        $stmt->execute([$editId]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

$search = trim($_GET['search'] ?? '');
$filterRole = strtolower(trim($_GET['role'] ?? ''));
$filterStatus = strtolower(trim($_GET['status'] ?? ''));

$sql = "SELECT u.user_id, u.full_name, u.email, u.status, u.created_at, r.role_name,
               l.staff_no, d.department_name
        FROM users u
        LEFT JOIN roles r ON r.role_id=u.role_id
        LEFT JOIN lecturers l ON LOWER(l.email)=LOWER(u.email)
        LEFT JOIN departments d ON d.department_id=COALESCE(u.department_id, l.department_id)
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR l.staff_no LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if (in_array($filterRole, ['admin','hod','lecturer'], true)) {
    $sql .= " AND LOWER(r.role_name)=?";
    $params[] = $filterRole;
}
if (in_array($filterStatus, ['active','inactive'], true)) {
    $sql .= " AND LOWER(u.status)=?";
    $params[] = $filterStatus;
}
$sql .= " ORDER BY u.user_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE LOWER(r.role_name)='admin'")->fetchColumn();
$totalHods = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE LOWER(r.role_name)='hod'")->fetchColumn();
$totalLecturers = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE LOWER(r.role_name)='lecturer'")->fetchColumn();

$adminName = $_SESSION['full_name'] ?? 'Administrator';
$firstName = trim(explode(' ', trim($adminName))[0] ?? 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management | Course Coverage Management System</title>
<style>
:root{--green:#08783f;--green2:#0a9a52;--dark:#0c2f21;--bg:#f4f7f5;--text:#17231d;--muted:#6d7b74;--line:#e3e9e5;--white:#fff;--danger:#c83232;--warning:#a96a00;--shadow:0 10px 30px rgba(14,45,31,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--text)}
a{text-decoration:none;color:inherit}.layout{display:flex;min-height:100vh}.sidebar{width:255px;background:linear-gradient(180deg,#075e32,#063f26);color:#fff;padding:24px 16px;position:fixed;inset:0 auto 0 0}.brand{display:flex;gap:12px;align-items:center;padding:4px 8px 25px;border-bottom:1px solid rgba(255,255,255,.14)}.brand-mark{width:45px;height:45px;border-radius:50%;background:#fff;display:grid;place-items:center;color:var(--green);font-weight:800}.brand b{display:block;font-size:15px}.brand span{font-size:11px;opacity:.72}.nav-title{font-size:10px;text-transform:uppercase;letter-spacing:1.5px;opacity:.55;margin:25px 10px 9px}.nav a{display:flex;align-items:center;gap:12px;padding:12px 13px;border-radius:10px;color:#dff4e8;font-size:14px;margin:4px 0}.nav a:hover,.nav a.active{background:rgba(255,255,255,.13);color:#fff}.icon{width:20px;text-align:center}.main{margin-left:255px;width:calc(100% - 255px);padding:28px 34px}.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}.crumb{font-size:13px;color:var(--muted)}.title{margin:4px 0 0;font-size:27px}.profile{display:flex;align-items:center;gap:12px}.avatar{width:42px;height:42px;border-radius:50%;background:#dcefe5;color:var(--green);display:grid;place-items:center;font-weight:800}.profile small{display:block;color:var(--muted)}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}.card{background:#fff;border:1px solid var(--line);border-radius:15px;padding:20px;box-shadow:var(--shadow)}.metric{font-size:29px;font-weight:800;margin:8px 0 2px}.label{font-size:13px;color:var(--muted)}.section{background:#fff;border:1px solid var(--line);border-radius:15px;box-shadow:var(--shadow);padding:22px;margin-bottom:22px}.section-head{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}.section h2{font-size:18px;margin:0}.btn{border:0;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer;font-size:13px}.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:#066632}.btn-light{background:#eef5f1;color:#155f3a}.btn-danger{background:#fff0f0;color:var(--danger)}.btn-warning{background:#fff6e6;color:var(--warning)}.filters{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px}.input,.select{width:100%;border:1px solid #d8e1dc;border-radius:9px;padding:11px 12px;font-size:14px;outline:none;background:#fff}.input:focus,.select:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(8,120,63,.08)}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:900px}.table th{background:#f7faf8;color:#66756d;font-size:11px;text-transform:uppercase;letter-spacing:.6px;text-align:left;padding:13px;border-bottom:1px solid var(--line)}.table td{padding:14px 13px;border-bottom:1px solid #edf1ee;font-size:13px;vertical-align:middle}.table tr:hover td{background:#fbfdfc}.badge{display:inline-flex;padding:5px 9px;border-radius:20px;font-size:11px;font-weight:800}.role-admin{background:#e9f1ff;color:#2d5aa3}.role-hod{background:#fff1dc;color:#9a5d00}.role-lecturer{background:#e7f7ee;color:#08783f}.status-active{background:#e7f7ee;color:#08783f}.status-inactive{background:#ffeaea;color:#b52b2b}.actions{display:flex;gap:6px;flex-wrap:wrap}.notice{padding:13px 15px;border-radius:10px;margin-bottom:18px;font-size:13px}.success{background:#e8f7ee;color:#096b39;border:1px solid #c8ead7}.error{background:#fff0f0;color:#ae2525;border:1px solid #f0cccc}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}.field label{display:block;font-size:12px;font-weight:700;margin-bottom:7px;color:#53635b}.full{grid-column:1/-1}.help{font-size:11px;color:var(--muted);margin-top:6px}.edit-banner{background:#eef8f2;border:1px solid #cfe9d9;padding:13px 15px;border-radius:10px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center}.hidden{display:none}.modal-bg{position:fixed;inset:0;background:rgba(3,22,14,.55);display:none;align-items:center;justify-content:center;padding:20px;z-index:50}.modal-bg.show{display:flex}.modal{background:#fff;width:min(760px,100%);max-height:90vh;overflow:auto;border-radius:17px;padding:24px;box-shadow:0 25px 70px rgba(0,0,0,.2)}.modal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.modal-head h2{margin:0}.close{border:0;background:#f0f3f1;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:18px}.form-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:20px}.role-extra{display:none}.role-extra.show{display:block}.empty{text-align:center;padding:45px;color:var(--muted)}
@media(max-width:1000px){.cards{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr 1fr}.filter-button{grid-column:1/-1}.sidebar{width:220px}.main{margin-left:220px;width:calc(100% - 220px)}}
@media(max-width:700px){.sidebar{position:static;width:100%;height:auto}.layout{display:block}.main{margin:0;width:100%;padding:18px}.cards{grid-template-columns:1fr}.form-grid,.filters{grid-template-columns:1fr}.full{grid-column:auto}.topbar{align-items:flex-start;gap:15px}.profile{display:none}.section-head{align-items:flex-start;flex-direction:column}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
    <div class="brand"><div class="brand-mark">UB</div><div><b>COURSE COVERAGE</b><span>University Administration</span></div></div>
    <div class="nav-title">Main Menu</div>
    <nav class="nav">
        <a href="dashboard.php"><span class="icon">⌂</span> Dashboard</a>
        <a href="user.php" class="active"><span class="icon">♙</span> Users</a>
        <a href="departments.php"><span class="icon">▦</span> Departments</a>
        <a href="programs.php"><span class="icon">▤</span> Programs</a>
        <a href="courses.php"><span class="icon">▣</span> Courses</a>
        <a href="academic_years.php"><span class="icon">◫</span> Academic Years</a>
        <a href="semesters.php"><span class="icon">◧</span> Semesters</a>
    </nav>
    <div class="nav-title">Account</div>
    <nav class="nav">
        <a href="profile.php"><span class="icon">◉</span> Profile</a>
        <a href="../auth/logout.php"><span class="icon">↪</span> Logout</a>
    </nav>
</aside>

<main class="main">
    <div class="topbar">
        <div><div class="crumb">Administrator / Users</div><h1 class="title">User Management</h1></div>
        <div class="profile"><div><b><?php echo e($adminName); ?></b><small>Administrator</small></div><div class="avatar"><?php echo e(strtoupper(substr($firstName,0,1))); ?></div></div>
    </div>

    <?php if ($success): ?><div class="notice success"><?php echo e($success); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?php echo e($error); ?></div><?php endif; ?>

    <div class="cards">
        <div class="card"><div class="label">All Users</div><div class="metric"><?php echo $totalUsers; ?></div></div>
        <div class="card"><div class="label">Administrators</div><div class="metric"><?php echo $totalAdmins; ?></div></div>
        <div class="card"><div class="label">HODs</div><div class="metric"><?php echo $totalHods; ?></div></div>
        <div class="card"><div class="label">Lecturers</div><div class="metric"><?php echo $totalLecturers; ?></div></div>
    </div>

    <section class="section">
        <div class="section-head">
            <div><h2><?php echo $editUser ? 'Edit User Account' : 'Create User Account'; ?></h2><div class="help">Create accounts for Admin, HOD and Lecturer roles. Lecturer accounts also create/update their lecturer profile.</div></div>
            <?php if (!$editUser): ?><button class="btn btn-primary" type="button" onclick="openCreate()">+ Create New User</button><?php endif; ?>
        </div>

        <?php if ($editUser): ?>
        <div class="edit-banner"><span>Editing <b><?php echo e($editUser['full_name']); ?></b></span><a class="btn btn-light" href="user.php">Cancel Edit</a></div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?php echo (int)$editUser['user_id']; ?>">
            <div class="form-grid">
                <div class="field"><label>Full Name</label><input class="input" name="full_name" value="<?php echo e($editUser['full_name']); ?>" required></div>
                <div class="field"><label>Email Address</label><input class="input" type="email" name="email" value="<?php echo e($editUser['email']); ?>" required></div>
                <div class="field"><label>Role</label><select class="select" name="role_name" id="editRole" onchange="toggleExtra(this.value,'editExtra')" required><?php foreach(['admin'=>'Admin','hod'=>'HOD','lecturer'=>'Lecturer'] as $key=>$label): ?><option value="<?php echo $key; ?>" <?php echo strtolower($editUser['role_name']??'')===$key?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
                <div class="field"><label>Status</label><select class="select" name="status"><option value="active" <?php echo strtolower($editUser['status'])==='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo strtolower($editUser['status'])==='inactive'?'selected':''; ?>>Inactive</option></select></div>
                <div class="field full"><label>New Password <span style="font-weight:400;color:#8a958f">(leave blank to keep current password)</span></label><input class="input" type="password" name="password" minlength="8"></div>
                <div id="editExtra" class="full role-extra <?php echo in_array(strtolower($editUser['role_name']??''),['hod','lecturer'],true)?'show':''; ?>">
                    <div class="form-grid">
                        <div class="field"><label>Staff Number</label><input class="input" name="staff_no" value="<?php echo e($editUser['staff_no'] ?? ''); ?>"></div>
                        <div class="field"><label>Phone</label><input class="input" name="phone" value="<?php echo e($editUser['phone'] ?? ''); ?>"></div>
                        <div class="field full"><label>Department</label><select class="select" name="department_id"><option value="">Select department</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d['department_id']; ?>" <?php echo (int)($editUser['department_id']??0)===(int)$d['department_id']?'selected':''; ?>><?php echo e($d['department_name']); ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
            </div>
            <div class="form-actions"><a class="btn btn-light" href="user.php">Cancel</a><button class="btn btn-primary">Save Changes</button></div>
        </form>
        <?php else: ?>
        <div class="empty">Use <b>+ Create New User</b> to open the account creation form.</div>
        <?php endif; ?>
    </section>

    <section class="section">
        <div class="section-head"><div><h2>All Users</h2><div class="help">Search, filter, edit, activate/deactivate or delete user accounts.</div></div></div>
        <form class="filters" method="get">
            <input class="input" name="search" value="<?php echo e($search); ?>" placeholder="Search name, email or staff number...">
            <select class="select" name="role"><option value="">All Roles</option><option value="admin" <?php echo $filterRole==='admin'?'selected':''; ?>>Admin</option><option value="hod" <?php echo $filterRole==='hod'?'selected':''; ?>>HOD</option><option value="lecturer" <?php echo $filterRole==='lecturer'?'selected':''; ?>>Lecturer</option></select>
            <select class="select" name="status"><option value="">All Statuses</option><option value="active" <?php echo $filterStatus==='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $filterStatus==='inactive'?'selected':''; ?>>Inactive</option></select>
            <button class="btn btn-light filter-button">Filter</button>
        </form>
        <div style="height:18px"></div>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Department / Staff No.</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (!$users): ?><tr><td colspan="7" class="empty">No users found.</td></tr><?php endif; ?>
            <?php foreach($users as $u): $role=strtolower($u['role_name']??''); ?>
                <tr>
                    <td><b><?php echo e($u['full_name']); ?></b><br><small style="color:#7a8781">ID #<?php echo (int)$u['user_id']; ?></small></td>
                    <td><?php echo e($u['email']); ?></td>
                    <td><span class="badge role-<?php echo in_array($role,['admin','hod','lecturer'],true)?$role:'lecturer'; ?>"><?php echo e($u['role_name'] ?: 'Unknown'); ?></span></td>
                    <td><?php echo e($u['department_name'] ?: '—'); ?><?php if ($u['staff_no']): ?><br><small style="color:#7a8781"><?php echo e($u['staff_no']); ?></small><?php endif; ?></td>
                    <td><span class="badge status-<?php echo strtolower($u['status'])==='active'?'active':'inactive'; ?>"><?php echo e(ucfirst($u['status'])); ?></span></td>
                    <td><?php echo e(date('d M Y', strtotime($u['created_at']))); ?></td>
                    <td><div class="actions">
                        <a class="btn btn-light" href="user.php?edit=<?php echo (int)$u['user_id']; ?>">Edit</a>
                        <?php if ((int)$u['user_id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Change this account status?');"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>"><button class="btn btn-warning" type="submit"><?php echo strtolower($u['status'])==='active'?'Deactivate':'Activate'; ?></button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this user account? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?php echo (int)$u['user_id']; ?>"><button class="btn btn-danger" type="submit">Delete</button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
</main>
</div>

<div class="modal-bg" id="createModal">
<div class="modal">
    <div class="modal-head"><div><h2>Create New User</h2><div class="help">The account will be stored in the <b>users</b> table.</div></div><button class="close" type="button" onclick="closeCreate()">×</button></div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo e($csrf); ?>"><input type="hidden" name="action" value="create">
        <div class="form-grid">
            <div class="field"><label>Full Name</label><input class="input" name="full_name" placeholder="Enter full name" required></div>
            <div class="field"><label>Email Address</label><input class="input" type="email" name="email" placeholder="user@example.com" required></div>
            <div class="field"><label>Role</label><select class="select" name="role_name" id="createRole" onchange="toggleExtra(this.value,'createExtra')" required><option value="">Select role</option><option value="admin">Admin</option><option value="hod">HOD</option><option value="lecturer">Lecturer</option></select></div>
            <div class="field"><label>Status</label><select class="select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
            <div class="field"><label>Password</label><input class="input" type="password" name="password" minlength="8" required></div>
            <div class="field"><label>Confirm Password</label><input class="input" type="password" name="confirm_password" minlength="8" required></div>
            <div id="createExtra" class="full role-extra">
                <div class="form-grid">
                    <div id="createStaffField" class="field"><label>Staff Number <span style="font-weight:400;color:#8a958f">(Lecturer only)</span></label><input class="input" name="staff_no" placeholder="e.g. UB/LECT/001"></div>
                    <div id="createPhoneField" class="field"><label>Phone <span style="font-weight:400;color:#8a958f">(Lecturer only)</span></label><input class="input" name="phone" placeholder="Phone number"></div>
                    <div class="field full"><label>Department <span style="font-weight:400;color:#8a958f">(HOD / Lecturer)</span></label><select class="select" name="department_id"><option value="">Select department</option><?php foreach($departments as $d): ?><option value="<?php echo (int)$d['department_id']; ?>"><?php echo e($d['department_name']); ?></option><?php endforeach; ?></select></div>
                </div>
                <?php if (!$departments): ?><div class="help" style="color:#b52b2b">No departments are currently stored. Create a department first before creating a HOD or Lecturer.</div><?php endif; ?>
            </div>
        </div>
        <div class="form-actions"><button type="button" class="btn btn-light" onclick="closeCreate()">Cancel</button><button class="btn btn-primary">Create User</button></div>
    </form>
</div>
</div>
<script>
function openCreate(){document.getElementById('createModal').classList.add('show');}
function closeCreate(){document.getElementById('createModal').classList.remove('show');}
function toggleExtra(role,id){const el=document.getElementById(id); if(el) el.classList.toggle('show',role==='hod'||role==='lecturer'); const sf=document.getElementById('createStaffField'); const pf=document.getElementById('createPhoneField'); if(sf) sf.style.display=role==='lecturer'?'block':'none'; if(pf) pf.style.display=role==='lecturer'?'block':'none';}
document.getElementById('createModal').addEventListener('click',function(e){if(e.target===this)closeCreate();});
</script>
</body>
</html>