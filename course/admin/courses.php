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

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['course_csrf'])) {
    $_SESSION['course_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['course_csrf'];

$error = '';
$success = '';

/*
 * Courses page is connected to the existing courses table:
 * course_id, course_code, course_name, credit_value,
 * department_id, semester, level, status.
 */

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token. Please refresh the page.');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'save_course') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $courseCode = trim((string)($_POST['course_code'] ?? ''));
            $courseName = trim((string)($_POST['course_name'] ?? ''));
            $creditValue = (int)($_POST['credit_value'] ?? 0);
            $departmentId = (int)($_POST['department_id'] ?? 0);
            $semester = trim((string)($_POST['semester'] ?? ''));
            $level = trim((string)($_POST['level'] ?? ''));
            $status = trim((string)($_POST['status'] ?? 'Active'));

            if ($courseCode === '' || $courseName === '') {
                throw new RuntimeException('Course code and course name are required.');
            }

            if ($departmentId <= 0) {
                throw new RuntimeException('Please select a department.');
            }

            if ($creditValue <= 0) {
                throw new RuntimeException('Credit value must be greater than zero.');
            }

            if ($semester === '') {
                throw new RuntimeException('Please select a semester.');
            }

            if ($level === '') {
                throw new RuntimeException('Please select a level.');
            }

            if (!in_array($status, ['Active', 'Inactive'], true)) {
                $status = 'Active';
            }

            // Verify department exists.
            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_id = ?");
            $stmt->execute([$departmentId]);
            if (!$stmt->fetchColumn()) {
                throw new RuntimeException('Selected department does not exist.');
            }

            if ($courseId > 0) {
                $stmt = $pdo->prepare(
                    "UPDATE courses
                     SET course_code = ?, course_name = ?, credit_value = ?,
                         department_id = ?, semester = ?, level = ?, status = ?
                     WHERE course_id = ?"
                );
                $stmt->execute([
                    $courseCode, $courseName, $creditValue,
                    $departmentId, $semester, $level, $status, $courseId
                ]);
                $success = 'Course updated successfully.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO courses
                    (course_code, course_name, credit_value, department_id, semester, level, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $courseCode, $courseName, $creditValue,
                    $departmentId, $semester, $level, $status
                ]);
                $success = 'Course created successfully and saved to the database.';
            }
        }

        if ($action === 'delete_course') {
            $courseId = (int)($_POST['course_id'] ?? 0);

            if ($courseId <= 0) {
                throw new RuntimeException('Invalid course selected.');
            }

            /*
             * Protect related records. A course should not be deleted if it
             * already has topics or assignments.
             */
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cousre_topics WHERE course_id = ?");
            $stmt->execute([$courseId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('This course cannot be deleted because it already has course topics.');
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_assgnment WHERE course_id = ?");
            $stmt->execute([$courseId]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new RuntimeException('This course cannot be deleted because it has lecturer assignments.');
            }

            $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->execute([$courseId]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Course was not found.');
            }

            $success = 'Course deleted successfully.';
        }
    }

    // Departments for the course form.
    $departmentsStmt = $pdo->query(
        "SELECT department_id, department_name
         FROM departments
         ORDER BY department_name ASC"
    );
    $departments = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Courses + department + useful counts.
    $coursesStmt = $pdo->query(
        "SELECT
            c.course_id,
            c.course_code,
            c.course_name,
            c.credit_value,
            c.department_id,
            c.semester,
            c.level,
            c.status,
            d.department_name,
            (SELECT COUNT(*) FROM cousre_topics t WHERE t.course_id = c.course_id) AS topic_count,
            (SELECT COUNT(*) FROM course_assgnment a WHERE a.course_id = c.course_id) AS assignment_count
         FROM courses c
         LEFT JOIN departments d ON d.department_id = c.department_id
         ORDER BY c.course_name ASC"
    );
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $ex) {
    $error = $ex->getMessage();
    $departments = $departments ?? [];
    $courses = $courses ?? [];
} catch (PDOException $ex) {
    if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
        $error = 'A course with that course code already exists. Please use a unique course code.';
    } else {
        $error = 'A database error occurred. Please check your database structure and connection.';
    }
    $departments = $departments ?? [];
    $courses = $courses ?? [];
}

$editId = (int)($_GET['edit'] ?? 0);
$editCourse = null;

foreach ($courses as $course) {
    if ((int)$course['course_id'] === $editId) {
        $editCourse = $course;
        break;
    }
}

$adminName = $_SESSION['full_name'] ?? 'Administrator';
$totalCourses = count($courses);
$activeCourses = 0;
$totalTopics = 0;
$totalAssignments = 0;

foreach ($courses as $course) {
    if (strcasecmp((string)$course['status'], 'Active') === 0) {
        $activeCourses++;
    }
    $totalTopics += (int)$course['topic_count'];
    $totalAssignments += (int)$course['assignment_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses | Administrator</title>
<style>
:root{
 --green:#0b5d3b;--green-dark:#073f2a;--green-light:#e8f5ed;
 --text:#18372b;--muted:#708078;--border:#e7ece9;--bg:#f5f8f6;
 --white:#fff;--danger:#b42318;--danger-bg:#fff0ee;
 --shadow:0 4px 16px rgba(17,52,38,.06)
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
.top-left{display:flex;align-items:center}
.profile{display:flex;align-items:center;gap:10px}.avatar{width:38px;height:38px;object-fit:contain;border-radius:50%;border:1px solid var(--border)}
.profile-text{display:flex;flex-direction:column;gap:3px}.profile-text strong{font-size:11px}.profile-text span{font-size:9px;color:var(--muted)}
.content{padding:25px 30px 30px}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:18px}
.page-title h2{margin:0;font-size:19px;color:var(--green-dark)}.page-title p{margin:5px 0 0;font-size:11px;color:var(--muted)}
.btn{border:0;border-radius:8px;padding:10px 14px;font-size:11px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn-primary{background:var(--green);color:#fff}.btn-primary:hover{background:var(--green-dark)}
.btn-secondary{background:#eef3f0;color:var(--green)}.btn-danger{background:var(--danger-bg);color:var(--danger)}
.alert{padding:12px 15px;border-radius:8px;font-size:11px;margin-bottom:18px}
.alert.success{background:#eaf7ed;color:#26713b;border:1px solid #ccebd4}.alert.error{background:#fff0ee;color:#a12b22;border:1px solid #f2cbc6}
.grid{display:grid;grid-template-columns:360px 1fr;gap:18px;align-items:start}
.panel{background:#fff;border:1px solid var(--border);border-radius:10px;box-shadow:var(--shadow);overflow:hidden}
.panel-head{padding:17px 20px;border-bottom:1px solid var(--border)}
.panel-head h3{margin:0;font-size:12px;color:var(--green-dark)}.panel-head p{margin:4px 0 0;font-size:10px;color:var(--muted)}
.form{padding:20px}.field{margin-bottom:14px}.field label{display:block;font-size:10px;font-weight:800;color:#5f7068;margin-bottom:6px}
.field input,.field select{width:100%;border:1px solid #dce5e0;border-radius:7px;padding:10px;font-size:11px;outline:none;background:#fff}
.field input:focus,.field select:focus{border-color:var(--green)}
.form-actions{display:flex;gap:8px;margin-top:6px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;padding:15px 20px;border-bottom:1px solid var(--border)}
.stat{background:#f7faf8;border-radius:8px;padding:12px}.stat span{display:block;color:var(--muted);font-size:9px;font-weight:700}.stat strong{display:block;color:var(--green-dark);font-size:20px;margin-top:4px}
.filters{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap}
.search,.filter{border:1px solid #dce5e0;border-radius:7px;padding:9px 10px;font-size:11px;background:#fff}
.search{flex:1;min-width:180px}.filter{min-width:150px}
.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1000px}
th,td{padding:12px 10px;border-bottom:1px solid #edf1ef;text-align:left}th{background:#fbfcfb;color:#6c7b75;font-size:9px;letter-spacing:.4px}
td{font-size:11px}td:first-child,th:first-child{padding-left:20px}
.code{font-weight:800;color:var(--green)}.badge{display:inline-block;border-radius:20px;padding:5px 8px;font-size:9px;font-weight:700;background:#eaf6ed;color:#27713b}
.badge.inactive{background:#f1f3f2;color:#66736d}.actions{display:flex;gap:6px;align-items:center}.empty{text-align:center;padding:35px;color:var(--muted);font-size:11px}
.mobile-menu{display:none}
@media(max-width:1150px){.grid{grid-template-columns:1fr}.sidebar{width:220px}.main{margin-left:220px;width:calc(100% - 220px)}}
@media(max-width:760px){
 .sidebar{transform:translateX(-100%);transition:.2s;width:260px}.sidebar.open{transform:translateX(0)}
 .main{margin-left:0;width:100%}.topbar{padding:0 15px}.content{padding:18px 15px}
 .mobile-menu{display:block;border:0;background:transparent;font-size:22px;color:var(--green);margin-right:10px}
 .profile-text{display:none}.stats{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>
<div class="layout">

<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img src="../assets/images/ub-logo.png" alt="University of Buea">
        <div class="brand-title">COURSE COVERAGE
            <span>MANAGEMENT SYSTEM<br>HTTTC KUMBA</span>
        </div>
    </div>

    <div class="menu-title">USER ROLE</div>
    <div class="role"><span class="role-badge">♙</span> Administrator</div>

    <div class="menu-title">MAIN MENU</div>
    <a class="side-link" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a>
    <a class="side-link" href="user.php"><span class="icon">♟</span>Users</a>
    <a class="side-link" href="department.php"><span class="icon">▣</span>Departments</a>
    <a class="side-link" href="programs.php"><span class="icon">▤</span>Programs</a>
    <a class="side-link active" href="courses.php"><span class="icon">▦</span>Courses</a>
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
            <h1>Courses</h1>
            <p>Manage courses offered by HTTTC Kumba</p>
        </div>
    </div>
    <div class="profile">
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
            <h2>Course Management</h2>
            <p>Create and manage courses directly from the database.</p>
        </div>
        <a href="#course-form" class="btn btn-primary">＋ <?= $editCourse ? 'Edit Course' : 'Add New Course' ?></a>
    </div>

    <?php if ($success): ?><div class="alert success"><?=e($success)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>

    <div class="grid">
        <section class="panel" id="course-form">
            <div class="panel-head">
                <h3><?= $editCourse ? 'EDIT COURSE' : 'CREATE NEW COURSE' ?></h3>
                <p><?= $editCourse ? 'Update the selected course.' : 'The information entered here is saved directly into the courses table.' ?></p>
            </div>

            <form class="form" method="post">
                <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
                <input type="hidden" name="action" value="save_course">
                <input type="hidden" name="course_id" value="<?=e($editCourse['course_id'] ?? 0)?>">

                <div class="field">
                    <label>COURSE CODE</label>
                    <input type="text" name="course_code" maxlength="50" placeholder="e.g. CEN201"
                           value="<?=e($editCourse['course_code'] ?? '')?>" required>
                </div>

                <div class="field">
                    <label>COURSE NAME</label>
                    <input type="text" name="course_name" maxlength="150" placeholder="e.g. Data Structures"
                           value="<?=e($editCourse['course_name'] ?? '')?>" required>
                </div>

                <div class="field">
                    <label>DEPARTMENT</label>
                    <select name="department_id" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?=e($department['department_id'])?>"
                                <?= ((int)($editCourse['department_id'] ?? 0) === (int)$department['department_id']) ? 'selected' : '' ?>>
                                <?=e($department['department_name'])?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>CREDIT VALUE</label>
                    <input type="number" name="credit_value" min="1" max="20"
                           value="<?=e($editCourse['credit_value'] ?? 3)?>" required>
                </div>

                <div class="field">
                    <label>SEMESTER</label>
                    <select name="semester" required>
                        <?php
                        $semesters = ['First','Second'];
                        foreach ($semesters as $s):
                        ?>
                            <option value="<?=e($s)?>" <?= (($editCourse['semester'] ?? '') === $s) ? 'selected' : '' ?>>
                                <?=e($s)?> Semester
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>LEVEL</label>
                    <select name="level" required>
                        <?php foreach (['100','200','300','400','500','601','602'] as $level): ?>
                            <option value="<?=e($level)?>" <?= (($editCourse['level'] ?? '') === $level) ? 'selected' : '' ?>>
                                <?=e($level)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>STATUS</label>
                    <select name="status">
                        <option value="Active" <?= (($editCourse['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
                        <option value="Inactive" <?= (($editCourse['status'] ?? '') === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <?= $editCourse ? 'Save Changes' : 'Create Course' ?>
                    </button>
                    <?php if ($editCourse): ?>
                        <a class="btn btn-secondary" href="courses.php">Cancel</a>
                    <?php else: ?>
                        <button class="btn btn-secondary" type="reset">Clear</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h3>ALL COURSES</h3>
                <p>Courses retrieved from the database.</p>
            </div>

            <div class="stats">
                <div class="stat"><span>TOTAL COURSES</span><strong><?=e($totalCourses)?></strong></div>
                <div class="stat"><span>ACTIVE COURSES</span><strong><?=e($activeCourses)?></strong></div>
                <div class="stat"><span>COURSE TOPICS</span><strong><?=e($totalTopics)?></strong></div>
                <div class="stat"><span>ASSIGNMENTS</span><strong><?=e($totalAssignments)?></strong></div>
            </div>

            <div class="filters">
                <input class="search" id="courseSearch" type="search" placeholder="Search course code or name...">
                <select class="filter" id="departmentFilter">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?=e(strtolower($department['department_name']))?>"><?=e($department['department_name'])?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="table-wrap">
                <table id="coursesTable">
                    <thead>
                        <tr>
                            <th>COURSE</th>
                            <th>DEPARTMENT</th>
                            <th>CREDIT</th>
                            <th>SEMESTER</th>
                            <th>LEVEL</th>
                            <th>TOPICS</th>
                            <th>ASSIGNMENTS</th>
                            <th>STATUS</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($courses): ?>
                        <?php foreach ($courses as $course): ?>
                            <tr data-department="<?=e(strtolower($course['department_name'] ?? ''))?>"
                                data-status="<?=e(strtolower($course['status']))?>">
                                <td>
                                    <div class="code"><?=e($course['course_code'])?></div>
                                    <div style="font-size:10px;margin-top:3px"><?=e($course['course_name'])?></div>
                                </td>
                                <td><?=e($course['department_name'] ?? '—')?></td>
                                <td><?=e($course['credit_value'])?></td>
                                <td><?=e($course['semester'])?></td>
                                <td><?=e($course['level'])?></td>
                                <td><span class="badge"><?=e($course['topic_count'])?></span></td>
                                <td><span class="badge"><?=e($course['assignment_count'])?></span></td>
                                <td>
                                    <span class="badge <?=strtolower($course['status']) === 'inactive' ? 'inactive' : ''?>">
                                        <?=e($course['status'])?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="btn btn-secondary" href="courses.php?edit=<?=e($course['course_id'])?>#course-form">Edit</a>
                                        <form method="post" onsubmit="return confirm('Delete this course? Courses with topics or lecturer assignments cannot be deleted.');">
                                            <input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?=e($course['course_id'])?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="empty">No courses found. Create your first course using the form.</td></tr>
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
const search = document.getElementById('courseSearch');
const dept = document.getElementById('departmentFilter');
const status = document.getElementById('statusFilter');
const rows = document.querySelectorAll('#coursesTable tbody tr');

function filterCourses() {
    const q = (search.value || '').toLowerCase().trim();
    const d = (dept.value || '').toLowerCase();
    const s = (status.value || '').toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const rowDept = row.dataset.department || '';
        const rowStatus = row.dataset.status || '';

        const matchSearch = !q || text.includes(q);
        const matchDept = !d || rowDept === d;
        const matchStatus = !s || rowStatus === s;

        row.style.display = matchSearch && matchDept && matchStatus ? '' : 'none';
    });
}

search?.addEventListener('input', filterCourses);
dept?.addEventListener('change', filterCourses);
status?.addEventListener('change', filterCourses);
</script>
</body>
</html>