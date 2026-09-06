<?php
session_start();

if (empty($_SESSION['logged_in'])) {
    header('Location: ../index.php');
    exit;
}

/*
 * This page is for the ADMINISTRATOR only.
 * It does NOT use the HOD department restriction.
 */
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['admin', 'administrator'], true)) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['department_csrf'])) {
    $_SESSION['department_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['department_csrf'];
$error = '';
$success = '';

try {
    /* CREATE / UPDATE */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $name = trim((string)($_POST['department_name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($name === '') {
                throw new RuntimeException('Department name is required.');
            }

            if (mb_strlen($name) > 200) {
                throw new RuntimeException('Department name cannot exceed 200 characters.');
            }

            if ($departmentId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE departments
                     SET department_name = ?, description = ?
                     WHERE department_id = ?"
                );
                $stmt->execute([$name, $description, $departmentId]);
                $success = 'Department updated successfully.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO departments (department_name, description)
                     VALUES (?, ?)"
                );
                $stmt->execute([$name, $description]);
                $success = 'Department created successfully.';
            }
        }

        if ($action === 'delete') {
            $departmentId = (int)($_POST['department_id'] ?? 0);

            if ($departmentId <= 0) {
                throw new RuntimeException('Invalid department selected.');
            }

            /*
             * Prevent accidental deletion where the department is already
             * referenced by lecturers, courses or programmes.
             */
            $checks = [
                'lecturers' => "SELECT COUNT(*) FROM lecturers WHERE department_id = ?",
                'courses'   => "SELECT COUNT(*) FROM courses WHERE department_id = ?",
                'programs'  => "SELECT COUNT(*) FROM programs WHERE department_id = ?"
            ];

            foreach ($checks as $table => $sql) {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$departmentId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        "This department cannot be deleted because it is already being used by {$table}."
                    );
                }
            }

            $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
            $stmt->execute([$departmentId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Department was not found.');
            }

            $success = 'Department deleted successfully.';
        }
    }

    /* LOAD ALL DEPARTMENTS WITH COUNTS */
    $stmt = $pdo->query(
        "SELECT
            d.department_id,
            d.department_name,
            d.description,
            (SELECT COUNT(*) FROM lecturers l WHERE l.department_id = d.department_id) AS lecturer_count,
            (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.department_id) AS course_count,
            (SELECT COUNT(*) FROM programs p WHERE p.department_id = d.department_id) AS program_count
         FROM departments d
         ORDER BY d.department_name ASC"
    );
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
    $departments = $departments ?? [];
} catch (PDOException $ex) {
    if ((int)$ex->errorInfo[1] === 1062) {
        $error = 'That department name already exists. Please use a different name.';
    } else {
        $error = 'A database error occurred while processing the department.';
    }
    $departments = $departments ?? [];
}

$adminName = $_SESSION['full_name'] ?? 'Administrator';
$editId = (int)($_GET['edit'] ?? 0);
$editDepartment = null;

if ($editId > 0) {
    foreach ($departments as $department) {
        if ((int)$department['department_id'] === $editId) {
            $editDepartment = $department;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Departments | Administrator</title>
<style>
:root{
    --green:#0b5d3b;
    --green-dark:#073f2a;
    --green-light:#e8f5ed;
    --accent:#8abf45;
    --text:#18372b;
    --muted:#708078;
    --border:#e7ece9;
    --bg:#f5f8f6;
    --white:#fff;
    --danger:#b42318;
    --danger-bg:#fff0ee;
    --shadow:0 4px 16px rgba(17,52,38,.06);
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--text)}
.layout{display:flex;min-height:100vh}
.sidebar{width:260px;background:#fff;border-right:1px solid var(--border);padding:20px 14px;position:fixed;top:0;bottom:0;left:0;z-index:20}
.brand{display:flex;gap:11px;align-items:center;padding:4px 8px 22px}
.brand img{width:45px;height:45px;object-fit:contain}
.brand-title{font-size:12px;font-weight:800;color:var(--green-dark);line-height:1.35}
.brand-title span{font-size:9px;color:#718078;letter-spacing:.4px}
.menu-title{font-size:9px;font-weight:800;color:#98a39e;letter-spacing:1px;margin:18px 9px 8px}
.role{background:var(--green-light);color:var(--green);border-radius:9px;padding:10px;font-size:11px;font-weight:700}
.role-badge{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:7px;background:#fff;margin-right:7px}
.side-link{display:flex;align-items:center;gap:10px;text-decoration:none;color:#56675f;font-size:12px;font-weight:600;padding:11px 10px;border-radius:8px;margin:2px 0}
.side-link:hover,.side-link.active{background:var(--green-light);color:var(--green)}
.icon{width:20px;text-align:center;font-size:14px}
.side-bottom{position:absolute;bottom:18px;left:20px;right:20px;color:#89958f;font-size:9px;text-align:center}
.building{font-size:24px;color:var(--green);margin-bottom:4px}
.main{margin-left:260px;width:calc(100% - 260px);min-height:100vh}
.topbar{height:78px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 30px}
.heading h1{margin:0;font-size:22px;color:var(--green-dark)}
.heading p{margin:5px 0 0;color:var(--muted);font-size:11px}
.profile{display:flex;align-items:center;gap:10px}
.avatar{width:38px;height:38px;object-fit:contain;border-radius:50%;border:1px solid var(--border)}
.profile-text{display:flex;flex-direction:column;gap:3px}
.profile-text strong{font-size:11px}.profile-text span{font-size:9px;color:var(--muted)}
.bell{font-size:18px;color:var(--green);margin-right:5px}
.content{padding:25px 30px 30px}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}
.page-title h2{margin:0;font-size:19px;color:var(--green-dark)}
.page-title p{margin:5px 0 0;font-size:11px;color:var(--muted)}
.btn{border:0;border-radius:8px;padding:10px 14px;font-size:11px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:var(--green-dark)}
.btn-secondary{background:#eef3f0;color:var(--green)}
.btn-danger{background:var(--danger-bg);color:var(--danger)}
.alert{padding:12px 15px;border-radius:8px;font-size:11px;margin-bottom:18px}
.alert.success{background:#eaf7ed;color:#26713b;border:1px solid #ccebd4}
.alert.error{background:#fff0ee;color:#a12b22;border:1px solid #f2cbc6}
.grid{display:grid;grid-template-columns:1fr 2fr;gap:18px;align-items:start}
.panel{background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow);overflow:hidden}
.panel-head{padding:17px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center}
.panel-head h3{margin:0;font-size:12px;color:var(--green-dark)}
.panel-head p{margin:4px 0 0;font-size:10px;color:var(--muted)}
.form{padding:20px}
.field{margin-bottom:14px}
.field label{display:block;font-size:10px;font-weight:800;color:#5f7068;margin-bottom:6px}
.field input,.field textarea{width:100%;border:1px solid #dce5e0;border-radius:7px;padding:10px;font-size:11px;outline:none;font-family:inherit}
.field textarea{min-height:100px;resize:vertical}
.field input:focus,.field textarea:focus{border-color:var(--green)}
.form-actions{display:flex;gap:8px;margin-top:6px}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:15px 20px;border-bottom:1px solid var(--border)}
.stat{background:#f7faf8;border-radius:8px;padding:12px}
.stat span{display:block;color:var(--muted);font-size:9px;font-weight:700}.stat strong{display:block;color:var(--green-dark);font-size:20px;margin-top:4px}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse;min-width:760px}
th,td{padding:12px 11px;border-bottom:1px solid #edf1ef;text-align:left}
th{background:#fbfcfb;color:#6c7b75;font-size:9px;letter-spacing:.4px}
td{font-size:11px}
td:first-child,th:first-child{padding-left:20px}
.badge{display:inline-block;border-radius:20px;padding:5px 8px;font-size:9px;font-weight:700;background:#eaf6ed;color:#27713b}
.actions{display:flex;gap:6px;align-items:center}
.empty{text-align:center;padding:35px;color:var(--muted);font-size:11px}
.search{width:220px;border:1px solid #dce5e0;border-radius:7px;padding:9px 10px;font-size:11px}
.footer{padding:20px 30px;color:#8a9690;font-size:9px;display:flex;justify-content:space-between}
.mobile-menu{display:none}
@media(max-width:1000px){.grid{grid-template-columns:1fr}.sidebar{width:220px}.main{margin-left:220px;width:calc(100% - 220px)}}
@media(max-width:760px){
    .sidebar{transform:translateX(-100%);transition:.2s;width:260px}.sidebar.open{transform:translateX(0)}
    .main{margin-left:0;width:100%}.topbar{padding:0 15px}.content{padding:18px 15px}
    .mobile-menu{display:block;border:0;background:transparent;font-size:22px;color:var(--green);margin-right:10px}
    .top-left{display:flex;align-items:center}.profile-text{display:none}.footer{padding:15px;display:block}
    .footer strong{display:block;margin-top:5px}.search{width:160px}
}
</style>
</head>
<body>
<div class="layout">

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img src="../assets/images/ub-logo.png" alt="University of Buea">
        <div class="brand-title">
            COURSE COVERAGE
            <span>MANAGEMENT SYSTEM<br>HTTTC KUMBA</span>
        </div>
    </div>

    <div class="menu-title">USER ROLE</div>
    <div class="role"><span class="role-badge">♙</span> Administrator</div>

    <div class="menu-title">MAIN MENU</div>
    <a class="side-link" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a>
    <a class="side-link" href="users.php"><span class="icon">♟</span>Users</a>
    <a class="side-link active" href="departments.php"><span class="icon">▣</span>Departments</a>
    <a class="side-link" href="programs.php"><span class="icon">▤</span>Programs</a>
    <a class="side-link" href="courses.php"><span class="icon">▦</span>Courses</a>
    <a class="side-link" href="academic_years.php"><span class="icon">◫</span>Academic Years</a>
    <a class="side-link" href="semesters.php"><span class="icon">◳</span>Semesters</a>

    <div class="menu-title">ACCOUNT</div>
    <a class="side-link" href="profile.php"><span class="icon">◉</span>Profile</a>
    <a class="side-link" href="change_password.php"><span class="icon">▣</span>Change Password</a>
    <a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a>

    <div class="side-bottom"><div class="building">⌂</div><div>HTTTC KUMBA</div></div>
</aside>

<main class="main">
<header class="topbar">
    <div class="top-left">
        <button class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
        <div class="heading">
            <h1>Departments</h1>
            <p>Manage departments, academic resources and department structure</p>
        </div>
    </div>
    <div class="profile">
        <div class="bell">♧</div>
        <img class="avatar" src="../assets/images/ub-logo.png" alt="Administrator">
        <div class="profile-text">
            <strong><?=e($adminName)?></strong>
            <span>Administrator</span>
        </div>
    </div>
</header>

<section class="content">
    <div class="toolbar">
        <div class="page-title">
            <h2>Department Management</h2>
            <p>Create, update and manage all departments in the system.</p>
        </div>
        <a href="#department-form" class="btn btn-primary">＋ <?= $editDepartment ? 'Edit Department' : 'Create Department' ?></a>
    </div>

    <?php if ($success): ?>
        <div class="alert success"><?=e($success)?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error"><?=e($error)?></div>
    <?php endif; ?>

    <div class="grid">
        <section class="panel" id="department-form">
            <div class="panel-head">
                <div>
                    <h3><?= $editDepartment ? 'EDIT DEPARTMENT' : 'CREATE DEPARTMENT' ?></h3>
                    <p><?= $editDepartment ? 'Update the selected department.' : 'Add a new department to the database.' ?></p>
                </div>
            </div>

            <form class="form" method="post">
                <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="department_id" value="<?=e($editDepartment['department_id'] ?? 0)?>">

                <div class="field">
                    <label for="department_name">DEPARTMENT NAME</label>
                    <input id="department_name" name="department_name" type="text" maxlength="200"
                           placeholder="e.g. Computer Engineering"
                           value="<?=e($editDepartment['department_name'] ?? '')?>" required>
                </div>

                <div class="field">
                    <label for="description">DESCRIPTION</label>
                    <textarea id="description" name="description"
                              placeholder="Enter a short description of the department..."><?=e($editDepartment['description'] ?? '')?></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <?= $editDepartment ? 'Save Changes' : 'Create Department' ?>
                    </button>

                    <?php if ($editDepartment): ?>
                        <a class="btn btn-secondary" href="departments.php">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" type="reset">Clear</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h3>ALL DEPARTMENTS</h3>
                    <p>Departments currently stored in the database.</p>
                </div>
                <input class="search" id="departmentSearch" type="search" placeholder="Search departments...">
            </div>

            <div class="stats">
                <div class="stat">
                    <span>TOTAL DEPARTMENTS</span>
                    <strong><?=count($departments)?></strong>
                </div>
                <div class="stat">
                    <span>TOTAL LECTURERS</span>
                    <strong><?php $x=0; foreach($departments as $d){$x+=(int)$d['lecturer_count'];} echo $x; ?></strong>
                </div>
                <div class="stat">
                    <span>TOTAL COURSES</span>
                    <strong><?php $x=0; foreach($departments as $d){$x+=(int)$d['course_count'];} echo $x; ?></strong>
                </div>
            </div>

            <div class="table-wrap">
                <table id="departmentsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>DEPARTMENT</th>
                            <th>LECTURERS</th>
                            <th>COURSES</th>
                            <th>PROGRAMMES</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($departments): ?>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td><?=e($d['department_id'])?></td>
                                <td>
                                    <strong><?=e($d['department_name'])?></strong>
                                    <?php if (trim((string)$d['description']) !== ''): ?>
                                        <div style="font-size:9px;color:#89958f;margin-top:4px">
                                            <?=e(mb_strimwidth($d['description'], 0, 70, '…'))?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge"><?=e($d['lecturer_count'])?></span></td>
                                <td><span class="badge"><?=e($d['course_count'])?></span></td>
                                <td><span class="badge"><?=e($d['program_count'])?></span></td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn-secondary" href="departments.php?edit=<?=e($d['department_id'])?>#department-form">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this department? This is only allowed when it is not being used by lecturers, courses or programmes.');">
                                            <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="department_id" value="<?=e($d['department_id'])?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty">No departments have been created yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>

<footer class="footer">
    <span>© <?=date('Y')?> Course Coverage Management System. All Rights Reserved.</span>
    <strong>HTTTC KUMBA - Excellence in Professional Training</strong>
</footer>
</main>
</div>

<script>
const search = document.getElementById('departmentSearch');
const rows = document.querySelectorAll('#departmentsTable tbody tr');

search?.addEventListener('input', function () {
    const term = this.value.toLowerCase().trim();
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});
</script>
</body>
</html>