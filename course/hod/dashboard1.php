<?php
session_start();

if (empty($_SESSION['logged_in']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['hod', 'head of department'], true)) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirectWith(string $type, string $message): void {
    $_SESSION[$type] = $message;
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
$hodName = $_SESSION['full_name'] ?? 'Head of Department';
$userId = (int)($_SESSION['user_id'] ?? 0);
$departmentId = 0;
$departmentName = 'Department not assigned';

try {
    // HOD department must be stored on users.department_id.
    $stmt = $pdo->prepare("SELECT u.department_id, d.department_name FROM users u LEFT JOIN departments d ON d.department_id = u.department_id WHERE u.user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $hod = $stmt->fetch();
    if ($hod) {
        $departmentId = (int)($hod['department_id'] ?? 0);
        $departmentName = $hod['department_name'] ?: 'Department not assigned';
    }
} catch (PDOException $e) {
    $departmentId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_course') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) redirectWith('error', 'Invalid security token. Please try again.');
    if (!$departmentId) redirectWith('error', 'This HOD account has no department assigned. Ask the Administrator to assign a department first.');

    $courseId = (int)($_POST['course_id'] ?? 0);
    $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
    $programId = (int)($_POST['program_id'] ?? 0);
    $sessionId = (int)($_POST['session_id'] ?? 0);
    $semester = trim($_POST['semester'] ?? '');
    $level = trim($_POST['level'] ?? '');

    if (!$courseId || !$lecturerId || !$programId || !$sessionId || $semester === '' || $level === '') {
        redirectWith('error', 'Please complete all course assignment fields.');
    }

    try {
        // Security rule: course and lecturer must belong to this HOD's department.
        $check = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND department_id = ?");
        $check->execute([$courseId, $departmentId]);
        if ((int)$check->fetchColumn() !== 1) redirectWith('error', 'The selected course does not belong to your department.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE lecturer_id = ? AND department_id = ? AND LOWER(status) = 'active'");
        $check->execute([$lecturerId, $departmentId]);
        if ((int)$check->fetchColumn() !== 1) redirectWith('error', 'The selected lecturer does not belong to your department or is inactive.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE program_id = ? AND department_id = ?");
        $check->execute([$programId, $departmentId]);
        if ((int)$check->fetchColumn() !== 1) redirectWith('error', 'The selected program does not belong to your department.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM academic_session WHERE session_id = ?");
        $check->execute([$sessionId]);
        if ((int)$check->fetchColumn() !== 1) redirectWith('error', 'The selected academic session does not exist.');

        $check = $pdo->prepare("SELECT COUNT(*) FROM course_assgnment WHERE course_id = ? AND lecturer_id = ? AND program_id = ? AND session_id = ? AND semester = ? AND level = ?");
        $check->execute([$courseId, $lecturerId, $programId, $sessionId, $semester, $level]);
        if ((int)$check->fetchColumn() > 0) redirectWith('error', 'This course is already assigned to this lecturer for the selected session, semester and level.');

        $insert = $pdo->prepare("INSERT INTO course_assgnment (course_id, lecturer_id, program_id, session_id, semester, level) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$courseId, $lecturerId, $programId, $sessionId, $semester, $level]);
        redirectWith('success', 'Course assigned successfully.');
    } catch (PDOException $e) {
        redirectWith('error', 'The course could not be assigned. Database error: ' . $e->getMessage());
    }
}

$stats = ['lecturers'=>0,'courses'=>0,'assignments'=>0,'avg_coverage'=>0];
$assignments = [];
$recentAssignments = [];
$courses = $lecturers = $programs = $sessions = [];

try {
    if ($departmentId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE department_id = ? AND LOWER(status) = 'active'");
        $stmt->execute([$departmentId]); $stats['lecturers'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
        $stmt->execute([$departmentId]); $stats['courses'] = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_assgnment ca INNER JOIN courses c ON c.course_id = ca.course_id WHERE c.department_id = ?");
        $stmt->execute([$departmentId]); $stats['assignments'] = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT course_id, course_code, course_name, credit_value FROM courses WHERE department_id = ? ORDER BY course_code");
        $stmt->execute([$departmentId]); $courses = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT lecturer_id, staff_no, full_name FROM lecturers WHERE department_id = ? AND LOWER(status) = 'active' ORDER BY full_name");
        $stmt->execute([$departmentId]); $lecturers = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT program_id, program_name FROM programs WHERE department_id = ? ORDER BY program_name");
        $stmt->execute([$departmentId]); $programs = $stmt->fetchAll();
    }

    $sessions = $pdo->query("SELECT session_id, session_name, status FROM academic_session ORDER BY start_date DESC")->fetchAll();

    if ($departmentId) {
        $sql = "SELECT ca.assignment_id, c.course_code, c.course_name, l.full_name lecturer_name, l.staff_no, p.program_name, s.session_name, ca.semester, ca.level,
                       COALESCE((SELECT SUM(cc.hours_taught) FROM course_coverage cc WHERE cc.assignment_id = ca.assignment_id),0) taught_hours,
                       COALESCE((SELECT SUM(ct.expected_hours) FROM cousre_topics ct WHERE ct.course_id = c.course_id),0) expected_hours
                FROM course_assgnment ca
                INNER JOIN courses c ON c.course_id = ca.course_id
                INNER JOIN lecturers l ON l.lecturer_id = ca.lecturer_id
                INNER JOIN programs p ON p.program_id = ca.program_id
                INNER JOIN academic_session s ON s.session_id = ca.session_id
                WHERE c.department_id = ?
                ORDER BY ca.assignment_id DESC LIMIT 8";
        $stmt = $pdo->prepare($sql); $stmt->execute([$departmentId]); $recentAssignments = $stmt->fetchAll();
        $coverageValues=[];
        foreach($recentAssignments as &$r){ $r['coverage']=(float)$r['expected_hours']>0?min(100,((float)$r['taught_hours']/(float)$r['expected_hours'])*100):0; $coverageValues[]=$r['coverage']; }
        unset($r);
        $stats['avg_coverage']=count($coverageValues)?array_sum($coverageValues)/count($coverageValues):0;
    }
} catch (PDOException $e) {}

$success = $_SESSION['success'] ?? ''; unset($_SESSION['success']);
$error = $_SESSION['error'] ?? ''; unset($_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HOD Dashboard | Course Coverage Management System</title>
<style>
:root{--g950:#003d2a;--g900:#004d36;--g700:#087341;--g:#269d43;--light:#e7f4d9;--gold:#f4b51f;--text:#10251c;--muted:#65756e;--border:#dfe7e2;--bg:#f7f9f8;--white:#fff;--shadow:0 8px 25px rgba(0,55,38,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--text);background:var(--bg)}a{text-decoration:none;color:inherit}button,input,select{font:inherit}.layout{display:flex;min-height:100vh}.sidebar{width:250px;position:fixed;inset:0 auto 0 0;background:linear-gradient(180deg,#003e2b,#005037);color:#fff;z-index:20}.brand{height:122px;background:#fff;color:var(--g900);display:flex;align-items:center;padding:13px 18px;gap:12px;border-bottom:1px solid #dce5df}.brand img{width:74px;height:74px;object-fit:contain}.brand-title{font-weight:800;font-size:16px;line-height:1.08}.brand-title span{display:block;font-size:12px;font-weight:600;margin-top:7px}.menu-title{font-size:12px;color:#a9c6b8;letter-spacing:.6px;margin:24px 20px 10px;font-weight:700}.role{padding:0 19px 10px;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px}.role-badge{width:34px;height:34px;border:2px solid rgba(255,255,255,.7);border-radius:50%;display:grid;place-items:center}.side-link{display:flex;align-items:center;gap:13px;margin:4px 9px;padding:12px 13px;border-radius:8px;font-size:14px;font-weight:600;color:#eaf4ef}.side-link:hover,.side-link.active{background:#51a927;color:#fff}.icon{width:20px;text-align:center;font-size:18px}.side-bottom{position:absolute;bottom:22px;left:0;right:0;text-align:center;color:#9bbbad;font-size:11px}.building{font-size:54px;line-height:1;opacity:.22}.main{margin-left:250px;width:calc(100% - 250px)}.topbar{height:123px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 25px 0 27px}.top-left{display:flex;align-items:center;gap:18px}.mobile-menu{display:none;background:none;border:0;font-size:24px}.heading h1{font-size:26px;margin:0 0 3px;color:#083f2b}.heading p{margin:0;color:var(--muted);font-size:14px}.profile{display:flex;align-items:center;gap:13px}.bell{font-size:22px;color:#063f2c}.avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #ddd}.profile-text strong{display:block;font-size:14px}.profile-text span{font-size:12px;color:var(--muted)}.content{padding:23px 22px 18px}.filter-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.department{font-size:13px;color:#426057}.department strong{color:#07502f}.select{border:1px solid #ccd8d2;border-radius:6px;background:#fff;padding:10px 13px;min-width:230px;color:#17362a}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:17px;margin-bottom:20px}.card{background:#fff;border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);padding:20px;min-height:140px}.card-top{display:flex;align-items:center;gap:16px}.card-icon{width:57px;height:57px;border-radius:50%;display:grid;place-items:center;background:#e3f1d2;color:#19783b;font-size:25px}.card:nth-child(3) .card-icon{background:#fff0c9;color:#ee9d00}.card-label{font-size:12px;font-weight:700}.card-number{font-size:28px;font-weight:800;margin-top:5px}.card-link{display:flex;justify-content:flex-end;align-items:center;margin-top:15px;color:#07502f;font-size:12px;font-weight:600;gap:9px}.card-link span{font-size:20px}.grid{display:grid;grid-template-columns:1.25fr .95fr;gap:17px;margin-bottom:17px}.panel{background:#fff;border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);overflow:hidden}.panel-head{padding:16px 20px 12px;display:flex;justify-content:space-between;align-items:center}.panel-head h2{font-size:15px;margin:0;color:#07442c}.panel-head p{margin:4px 0 0;color:var(--muted);font-size:11px}.assign-form{padding:0 20px 20px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:13px}.field label{display:block;font-size:11px;font-weight:700;margin-bottom:6px;color:#29433a}.field input,.field select{width:100%;padding:10px;border:1px solid #cfdad4;border-radius:6px;background:#fff;outline:none}.field input:focus,.field select:focus{border-color:#27984a;box-shadow:0 0 0 3px rgba(39,152,74,.1)}.full{grid-column:1/-1}.btn{margin-top:14px;background:#278d3e;color:#fff;border:0;border-radius:7px;padding:11px 18px;font-weight:700;cursor:pointer}.btn:hover{background:#197a31}.notice{margin-bottom:16px;padding:11px 14px;border-radius:7px;font-size:13px}.success{background:#e5f4df;color:#276c29;border:1px solid #c7e6c0}.error{background:#ffe5e1;color:#a92e25;border:1px solid #f2c1bb}.empty{padding:25px;text-align:center;color:var(--muted);font-size:12px}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse;min-width:720px}.data-table th,.data-table td{text-align:left;padding:11px 10px;border-top:1px solid #edf1ef}.data-table th{font-size:10px;background:#fcfdfc}.data-table td{font-size:11px}.data-table td:first-child,.data-table th:first-child{padding-left:20px}.pill{display:inline-block;padding:5px 9px;border-radius:8px;font-size:10px;font-weight:700}.pill.good{background:#e5f3d9;color:#2b7629}.pill.average{background:#fff0c9;color:#bd7900}.pill.low{background:#ffe3df;color:#d4362d}.footer{height:50px;background:#fff;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 22px;font-size:11px;color:#40534b}.footer strong{color:#17672e;font-style:italic}
@media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:760px){.sidebar{transform:translateX(-100%);transition:.25s}.sidebar.open{transform:translateX(0)}.main{margin-left:0;width:100%}.mobile-menu{display:block}.topbar{height:auto;min-height:78px;padding:12px 15px}.profile-text{display:none}.content{padding:15px 12px}.cards{grid-template-columns:1fr}.filter-row{display:block}.department{margin-bottom:10px}.select{width:100%}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.footer{height:auto;flex-direction:column;gap:7px;padding:10px}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar" id="sidebar">
<div class="brand"><img src="../assets/images/ub-logo.png" alt="University of Buea"><div class="brand-title">COURSE COVERAGE<span>MANAGEMENT SYSTEM<br>HTTTC KUMBA</span></div></div>
<div class="menu-title">USER ROLE</div><div class="role"><span class="role-badge">♙</span> Head of Department</div>
<div class="menu-title">MAIN MENU</div>
<a class="side-link active" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a>
<a class="side-link" href="dashboard.php#assign-course"><span class="icon">＋</span>Assign Course</a>
<a class="side-link" href="assignments.php"><span class="icon">▤</span>Assigned Courses</a>
<a class="side-link" href="coverage.php"><span class="icon">◫</span>Coverage Review</a>
<a class="side-link" href="reports.php"><span class="icon">▥</span>Reports</a>
<a class="side-link" href="lecturers.php"><span class="icon">♟</span>Lecturers</a>
<div class="menu-title">ACCOUNT</div><a class="side-link" href="profile.php"><span class="icon">◉</span>Profile</a><a class="side-link" href="change_password.php"><span class="icon">▣</span>Change Password</a><a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a>
<div class="side-bottom"><div class="building">⌂</div><div>HTTTC KUMBA</div></div>
</aside>
<main class="main">
<header class="topbar"><div class="top-left"><button class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div class="heading"><h1>HOD Dashboard</h1><p>Department academic management and course coverage</p></div></div><div class="profile"><div class="bell">♧</div><img class="avatar" src="../assets/images/ub-logo.png" alt="HOD"><div class="profile-text"><strong><?=e($hodName)?></strong><span>Head of Department</span></div></div></header>
<section class="content">
<div class="filter-row"><div class="department">Department: <strong><?=e($departmentName)?></strong></div><select class="select"><option>Current Academic Session</option><?php foreach($sessions as $s): ?><option><?=e($s['session_name'])?></option><?php endforeach; ?></select></div>
<?php if($success): ?><div class="notice success"><?=e($success)?></div><?php endif; ?><?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?>
<div class="cards">
<div class="card"><div class="card-top"><div class="card-icon">♟</div><div><div class="card-label">DEPARTMENT LECTURERS</div><div class="card-number"><?=number_format($stats['lecturers'])?></div></div></div><a class="card-link" href="lecturers.php">View lecturers <span>›</span></a></div>
<div class="card"><div class="card-top"><div class="card-icon">▤</div><div><div class="card-label">DEPARTMENT COURSES</div><div class="card-number"><?=number_format($stats['courses'])?></div></div></div><a class="card-link" href="#assign-course">Assign course <span>›</span></a></div>
<div class="card"><div class="card-top"><div class="card-icon">⌘</div><div><div class="card-label">COURSE ASSIGNMENTS</div><div class="card-number"><?=number_format($stats['assignments'])?></div></div></div><a class="card-link" href="assignments.php">View assignments <span>›</span></a></div>
<div class="card"><div class="card-top"><div class="card-icon">◔</div><div><div class="card-label">AVERAGE COVERAGE</div><div class="card-number"><?=number_format($stats['avg_coverage'],1)?>%</div></div></div><a class="card-link" href="coverage.php">Review coverage <span>›</span></a></div>
</div>
<div class="grid">
<div class="panel" id="assign-course"><div class="panel-head"><div><h2>ASSIGN COURSE TO LECTURER</h2><p>Only lecturers, courses and programs belonging to your department are available.</p></div></div>
<?php if(!$departmentId): ?><div class="assign-form"><div class="notice error">Your HOD account has no department assigned. The Administrator must assign a department to this HOD account before course assignment can be used.</div></div><?php else: ?>
<form class="assign-form" method="post"><input type="hidden" name="action" value="assign_course"><input type="hidden" name="csrf_token" value="<?=e($csrf)?>"><div class="form-grid">
<div class="field"><label>COURSE</label><select name="course_id" required><option value="">Select course</option><?php foreach($courses as $c): ?><option value="<?=$c['course_id']?>"><?=e($c['course_code'].' - '.$c['course_name'])?></option><?php endforeach; ?></select></div>
<div class="field"><label>LECTURER</label><select name="lecturer_id" required><option value="">Select lecturer</option><?php foreach($lecturers as $l): ?><option value="<?=$l['lecturer_id']?>"><?=e($l['full_name'].' ('.$l['staff_no'].')')?></option><?php endforeach; ?></select></div>
<div class="field"><label>PROGRAM</label><select name="program_id" required><option value="">Select program</option><?php foreach($programs as $p): ?><option value="<?=$p['program_id']?>"><?=e($p['program_name'])?></option><?php endforeach; ?></select></div>
<div class="field"><label>ACADEMIC SESSION</label><select name="session_id" required><option value="">Select session</option><?php foreach($sessions as $s): ?><option value="<?=$s['session_id']?>"><?=e($s['session_name'])?></option><?php endforeach; ?></select></div>
<div class="field"><label>SEMESTER</label><select name="semester" required><option value="">Select semester</option><option>First Semester</option><option>Second Semester</option></select></div>
<div class="field"><label>LEVEL</label><select name="level" required><option value="">Select level</option><option>100</option><option>200</option><option>300</option><option>400</option><option>500</option><option>600</option></select></div>
</div><button class="btn" type="submit">＋ Assign Course</button></form><?php endif; ?></div>
<div class="panel"><div class="panel-head"><div><h2>DEPARTMENT OVERVIEW</h2><p><?=e($departmentName)?></p></div></div><div style="padding:10px 20px 22px"><div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #edf1ef;font-size:12px"><span>Active lecturers</span><strong><?=$stats['lecturers']?></strong></div><div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #edf1ef;font-size:12px"><span>Courses</span><strong><?=$stats['courses']?></strong></div><div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #edf1ef;font-size:12px"><span>Assignments</span><strong><?=$stats['assignments']?></strong></div><div style="display:flex;justify-content:space-between;padding:12px 0;font-size:12px"><span>Average coverage</span><strong><?=number_format($stats['avg_coverage'],1)?>%</strong></div></div></div>
</div>
<div class="panel"><div class="panel-head"><h2>RECENT COURSE ASSIGNMENTS</h2><a class="card-link" style="margin:0" href="assignments.php">View all ›</a></div><div class="table-wrap"><table class="data-table"><thead><tr><th>COURSE</th><th>LECTURER</th><th>PROGRAM</th><th>SESSION</th><th>SEMESTER</th><th>LEVEL</th><th>COVERAGE</th></tr></thead><tbody>
<?php if($recentAssignments): foreach($recentAssignments as $r): $cv=(float)$r['coverage']; $cls=$cv>=75?'good':($cv>=50?'average':'low'); ?><tr><td><strong><?=e($r['course_code'])?></strong><br><?=e($r['course_name'])?></td><td><?=e($r['lecturer_name'])?><br><span style="color:#788780"><?=e($r['staff_no'])?></span></td><td><?=e($r['program_name'])?></td><td><?=e($r['session_name'])?></td><td><?=e($r['semester'])?></td><td><?=e($r['level'])?></td><td><span class="pill <?=$cls?>"><?=number_format($cv,0)?>%</span></td></tr><?php endforeach; else: ?><tr><td colspan="7" class="empty">No course assignments found for your department.</td></tr><?php endif; ?></tbody></table></div></div>
</section><footer class="footer"><span>© <?=date('Y')?> Course Coverage Management System. All Rights Reserved.</span><strong>HTTTC KUMBA - Excellence in Professional Training</strong></footer>
</main></div>
</body></html>