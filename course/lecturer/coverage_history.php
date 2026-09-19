<?php
session_start();

if (empty($_SESSION['logged_in']) || strtolower(trim($_SESSION['role'] ?? '')) !== 'lecturer') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$email = trim($_SESSION['email'] ?? '');
$name = $_SESSION['full_name'] ?? 'Lecturer';
$lecturerId = 0;
$records = [];
$error = '';
$courseFilter = trim($_GET['course'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

try {
    $stmt = $pdo->prepare("SELECT l.lecturer_id, l.full_name, l.email
                           FROM lecturers l
                           WHERE LOWER(TRIM(l.email)) = LOWER(TRIM(?))
                           LIMIT 1");
    $stmt->execute([$email]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer && $userId) {
        $stmt = $pdo->prepare("SELECT l.lecturer_id, l.full_name, l.email
                               FROM users u
                               INNER JOIN lecturers l ON LOWER(l.email) = LOWER(u.email)
                               WHERE u.user_id = ?
                               LIMIT 1");
        $stmt->execute([$userId]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($lecturer) {
        $lecturerId = (int)$lecturer['lecturer_id'];
        $name = $lecturer['full_name'] ?: $name;
        $email = $lecturer['email'] ?: $email;
    }

    if ($lecturerId > 0) {
        $sql = "SELECT cc.date_taught, cc.hours_taught, cc.coverage_status, cc.remarks,
                       c.course_code, c.course_name,
                       ct.topic_number, ct.topic_title, ct.expected_hours
                FROM course_coverage cc
                INNER JOIN course_assgnment ca
                    ON ca.assignment_id = cc.assignment_id
                   AND ca.lecturer_id = :lecturer_id
                INNER JOIN courses c ON c.course_id = ca.course_id
                INNER JOIN cousre_topics ct
                    ON ct.topic_id = cc.topic_id
                   AND ct.course_id = ca.course_id
                WHERE 1 = 1";
        $params = [':lecturer_id' => $lecturerId];

        if ($courseFilter !== '') {
            $sql .= " AND (c.course_code LIKE :course_code OR c.course_name LIKE :course_name)";
            $params[':course_code'] = '%' . $courseFilter . '%';
            $params[':course_name'] = '%' . $courseFilter . '%';
        }
        if ($statusFilter !== '') {
            $sql .= " AND cc.coverage_status = :coverage_status";
            $params[':coverage_status'] = $statusFilter;
        }

        $sql .= " ORDER BY cc.date_taught DESC, cc.updated_at DESC, cc.coverage_id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $exception) {
    $error = 'Unable to load your coverage history. Please try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Coverage History | Course Coverage Management System</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f8f6;color:#173128;font:13px Arial,Helvetica,sans-serif}.app{display:flex;min-height:100vh}.sidebar{width:245px;position:fixed;inset:0 auto 0 0;background:#0d5b3f;color:#fff;padding:22px 15px;z-index:5}.brand{display:flex;align-items:center;gap:11px;padding:4px 8px 24px;border-bottom:1px solid #ffffff29}.brand img{width:48px;height:48px;object-fit:contain;background:#fff;border-radius:50%;padding:4px}.brand strong{font-size:12px}.brand span{display:block;font-size:10px;opacity:.75;margin-top:3px}.menu-title{font-size:9px;letter-spacing:1px;opacity:.6;margin:25px 10px 9px}.side-link{display:flex;align-items:center;gap:11px;color:#eaf7f1;text-decoration:none;padding:11px 12px;border-radius:7px;margin:3px 0;font-size:12px}.side-link:hover,.side-link.active{background:#ffffff22}.icon{width:18px;text-align:center}.main{margin-left:245px;flex:1;min-width:0}.topbar{height:82px;background:#fff;border-bottom:1px solid #e4ebe7;display:flex;justify-content:space-between;align-items:center;padding:0 30px}.heading h1{margin:0;color:#18382d;font-size:21px}.heading p{margin:5px 0 0;color:#7c8d86;font-size:11px}.user{display:flex;align-items:center;gap:10px}.avatar{width:38px;height:38px;border-radius:50%;object-fit:contain;background:#f1f5f2;padding:4px}.content{padding:25px 30px 35px}.panel{background:#fff;border:1px solid #e4ebe7;border-radius:10px;overflow:hidden;box-shadow:0 6px 20px #163e2e0a}.panel-head{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:20px 22px;border-bottom:1px solid #edf1ef}.panel-head h2{margin:0;color:#1b4435;font-size:13px}.panel-head p{margin:6px 0 0;color:#84928d;font-size:11px}.filters{display:flex;gap:8px;flex-wrap:wrap}.filters input,.filters select,.filters button{height:34px;border:1px solid #d6e1db;border-radius:6px;padding:0 10px;background:#fff;font:11px Arial;color:#40564d}.filters button{background:#0d5b3f;color:#fff;border:0;font-weight:700;cursor:pointer}.clear{display:flex;align-items:center;color:#547168;text-decoration:none;font-size:11px}.table-wrap{overflow:auto}.table{width:100%;min-width:760px;border-collapse:collapse}.table th,.table td{text-align:left;padding:13px 16px;border-bottom:1px solid #eef2f0}.table th{background:#f8faf9;color:#778780;font-size:10px;letter-spacing:.7px}.table td{color:#40564d}.course{font-weight:700;color:#1c503c}.sub{display:block;color:#87958f;font-size:11px;margin-top:4px}.pill{display:inline-block;padding:5px 9px;border-radius:12px;font-size:10px;font-weight:700;background:#fff5d8;color:#92701a}.empty{text-align:center;color:#84928d;padding:32px!important}.notice{margin-bottom:18px;padding:13px 15px;border-radius:8px;background:#fff0ef;border:1px solid #ffd0cd;color:#9b241e}.footer{padding:15px 30px;color:#87958f;font-size:10px}@media(max-width:800px){.sidebar{transform:translateX(-100%)}.main{margin-left:0}.content{padding:18px 15px}.topbar{padding:0 16px}.user span{display:none}.panel-head{align-items:flex-start;flex-direction:column}.filters{width:100%}.filters input,.filters select,.filters button,.clear{width:100%;height:36px;justify-content:center}}
</style>
</head>
<body>
<div class="app"><aside class="sidebar"><div class="brand"><img src="../assets/images/ub-logo.png" alt="University Logo"><div><strong>UNIVERSITY OF BUEA</strong><span>HTTTC KUMBA</span></div></div><div class="menu-title">LECTURER MENU</div><a class="side-link" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a><a class="side-link" href="my_courses.php"><span class="icon">▤</span>My Courses</a><a class="side-link" href="coverage.php"><span class="icon">◫</span>Course Progress</a><a class="side-link" href="record_coverage.php"><span class="icon">＋</span>Record Coverage</a><a class="side-link active" href="coverage_history.php"><span class="icon">◷</span>Coverage History</a><a class="side-link" href="profile.php"><span class="icon">◉</span>Profile</a><a class="side-link" href="../change_password.php"><span class="icon">▣</span>Change Password</a><a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a></aside><main class="main"><header class="topbar"><div class="heading"><h1>Coverage History</h1><p>Review your submitted course coverage records</p></div><div class="user"><img class="avatar" src="../assets/images/ub-logo.png" alt="University logo"><span><?=e($name)?></span></div></header><section class="content"><?php if($error): ?><div class="notice"><?=e($error)?></div><?php endif; ?><?php if(!$lecturerId && !$error): ?><div class="notice">Your lecturer profile could not be found.</div><?php endif; ?><div class="panel"><div class="panel-head"><div><h2>PREVIOUS COVERAGE RECORDS</h2><p>Records submitted by <?=e($name)?><?= $email ? ' (' . e($email) . ')' : '' ?></p></div><form class="filters" method="get"><input type="search" name="course" value="<?=e($courseFilter)?>" placeholder="Course code or name"><select name="status"><option value="">All statuses</option><?php foreach(['Completed','In Progress','Partially Covered'] as $option): ?><option value="<?=e($option)?>" <?=$statusFilter===$option?'selected':''?>><?=e($option)?></option><?php endforeach; ?></select><button type="submit">Filter</button><?php if($courseFilter||$statusFilter): ?><a class="clear" href="coverage_history.php">Clear</a><?php endif; ?></form></div><div class="table-wrap"><table class="table"><thead><tr><th>DATE</th><th>COURSE</th><th>TOPIC</th><th>HOURS</th><th>STATUS</th><th>REMARKS</th></tr></thead><tbody><?php if($records): foreach($records as $record): ?><tr><td><?=e($record['date_taught'])?></td><td><span class="course"><?=e($record['course_code'])?></span><span class="sub"><?=e($record['course_name'])?></span></td><td>Topic <?=e($record['topic_number'])?>: <?=e($record['topic_title'])?></td><td><?=number_format((float)$record['hours_taught'],2)?> h</td><td><span class="pill"><?=e($record['coverage_status'])?></span></td><td><?=e($record['remarks'] ?: '-')?></td></tr><?php endforeach; else: ?><tr><td colspan="6" class="empty">No coverage records found.</td></tr><?php endif; ?></tbody></table></div></div></section><footer class="footer">&copy; <?=date('Y')?> Course Coverage Management System</footer></main></div>
</body></html>
