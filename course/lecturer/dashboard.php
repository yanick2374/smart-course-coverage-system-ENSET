<?php
session_start();

if (empty($_SESSION['logged_in']) || strtolower(trim($_SESSION['role'] ?? '')) !== 'lecturer') {
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$userId = (int)($_SESSION['user_id'] ?? 0);
$lecturerName = $_SESSION['full_name'] ?? 'Lecturer';
$lecturerEmail = $_SESSION['email'] ?? '';

$lecturer = null;
$departmentId = 0;
$departmentName = 'Department not assigned';
$lecturerId = 0;
$staffNo = '';

try {
    // The lecturer account is linked to the lecturers table through email in the supplied schema.
    $stmt = $pdo->prepare("SELECT l.lecturer_id, l.staff_no, l.full_name, l.email, l.department_id, l.phone,
                                  d.department_name
                           FROM lecturers l
                           LEFT JOIN departments d ON d.department_id = l.department_id
                           WHERE LOWER(l.email) = LOWER(?)
                           LIMIT 1");
    $stmt->execute([$lecturerEmail]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback if the login session contains the user id and the users table has department_id.
    if (!$lecturer && $userId) {
        $stmt = $pdo->prepare("SELECT l.lecturer_id, l.staff_no, l.full_name, l.email, l.department_id,
                                      d.department_name
                               FROM users u
                               INNER JOIN lecturers l ON LOWER(l.email) = LOWER(u.email)
                               LEFT JOIN departments d ON d.department_id = l.department_id
                               WHERE u.user_id = ?
                               LIMIT 1");
        $stmt->execute([$userId]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($lecturer) {
        $lecturerId = (int)$lecturer['lecturer_id'];
        $staffNo = $lecturer['staff_no'] ?? '';
        $lecturerName = $lecturer['full_name'] ?: $lecturerName;
        $lecturerEmail = $lecturer['email'] ?: $lecturerEmail;
        $departmentId = (int)($lecturer['department_id'] ?? 0);
        $departmentName = $lecturer['department_name'] ?: 'Department not assigned';
    }
} catch (PDOException $e) {
    // The page remains usable enough to show an error message below.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_progress') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        redirectWith('error', 'Invalid security token. Please try again.');
    }
    if (!$lecturerId) {
        redirectWith('error', 'Your lecturer profile could not be found. Make sure your login email matches your lecturer record.');
    }

    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    $topicId = (int)($_POST['topic_id'] ?? 0);
    $dateTaught = trim($_POST['date_taught'] ?? '');
    $hoursTaught = (float)($_POST['hours_taught'] ?? 0);
    $coverageStatus = trim($_POST['coverage_status'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$assignmentId || !$topicId || $dateTaught === '' || $hoursTaught <= 0 || $coverageStatus === '') {
        redirectWith('error', 'Please complete all progress fields. Hours taught must be greater than zero.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTaught)) {
        redirectWith('error', 'Please provide a valid teaching date.');
    }
    if ($hoursTaught > 24) {
        redirectWith('error', 'Hours taught cannot be greater than 24 hours for one progress entry.');
    }

    try {
        // Make sure this assignment belongs to the logged-in lecturer.
        $check = $pdo->prepare("SELECT ca.assignment_id, ca.course_id, c.course_code, c.course_name,
                                       ct.topic_id, ct.topic_title, ct.expected_hours
                                FROM course_assgnment ca
                                INNER JOIN courses c ON c.course_id = ca.course_id
                                INNER JOIN cousre_topics ct ON ct.course_id = ca.course_id
                                WHERE ca.assignment_id = ?
                                  AND ca.lecturer_id = ?
                                  AND ct.topic_id = ?
                                LIMIT 1");
        $check->execute([$assignmentId, $lecturerId, $topicId]);
        $valid = $check->fetch(PDO::FETCH_ASSOC);

        if (!$valid) {
            redirectWith('error', 'You can only submit progress for courses and topics assigned to you.');
        }

        // Prevent a single topic from being pushed beyond its expected hours.
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(hours_taught),0)
                                  FROM course_coverage
                                  WHERE assignment_id = ? AND topic_id = ?");
        $sumStmt->execute([$assignmentId, $topicId]);
        $alreadyTaught = (float)$sumStmt->fetchColumn();
        $expectedHours = (float)$valid['expected_hours'];

        if ($expectedHours > 0 && ($alreadyTaught + $hoursTaught) > $expectedHours) {
            $remaining = max(0, $expectedHours - $alreadyTaught);
            redirectWith('error', 'This topic has ' . number_format($remaining, 2) . ' hour(s) remaining. Reduce the hours for this entry.');
        }

        $pdo->beginTransaction();

        // The supplied SQL makes coverage_id AUTO_INCREMENT. If the local database was created
        // without AUTO_INCREMENT, repair it before inserting rather than manually generating IDs.
        try {
            $pdo->exec("ALTER TABLE course_coverage MODIFY coverage_id INT(11) NOT NULL AUTO_INCREMENT");
        } catch (PDOException $ignore) {
            // Existing installations with sufficient privileges continue normally.
        }

        $insert = $pdo->prepare("INSERT INTO course_coverage
            (assignment_id, topic_id, date_taught, hours_taught, coverage_status, remarks, updated_by, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $insert->execute([
            $assignmentId,
            $topicId,
            $dateTaught,
            number_format($hoursTaught, 2, '.', ''),
            $coverageStatus,
            $remarks,
            $userId
        ]);

        // Notify the HOD(s) for the lecturer's department when possible.
        try {
            $hodRole = $pdo->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) IN ('hod','head of department') LIMIT 1");
            $hodRole->execute();
            $hodRoleId = (int)$hodRole->fetchColumn();
            if ($hodRoleId && $departmentId) {
                $hods = $pdo->prepare("SELECT user_id FROM users WHERE role_id = ? AND status = 'active' AND department_id = ?");
                $hods->execute([$hodRoleId, $departmentId]);
                $notificationInsert = $pdo->prepare("INSERT INTO notifications
                    (user_id, title, message, notification_type, is_read, created_at)
                    VALUES (?, ?, ?, ?, 0, NOW())");
                while ($hod = $hods->fetch(PDO::FETCH_ASSOC)) {
                    $notificationInsert->execute([
                        (int)$hod['user_id'],
                        0,
                        $lecturerName . ' submitted progress for ' . $valid['course_code'] . ' - ' . $valid['topic_title'],
                        'coverage'
                    ]);
                }
            }
        } catch (PDOException $ignore) {
            // Notifications are supplementary and should not stop a successful coverage submission.
        }

        $pdo->commit();
        redirectWith('success', 'Course progress submitted successfully. The updated coverage will appear on the HOD dashboard.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        redirectWith('error', 'Unable to save the progress entry: ' . $e->getMessage());
    }
}

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$stats = [
    'assigned_courses' => 0,
    'topics' => 0,
    'completed_topics' => 0,
    'coverage' => 0,
];
$assignments = [];
$topicsByCourse = [];
$departments = [];
$recentProgress = [];
$courseProgress = [];
$sessions = [];

try {
    $departments = $pdo->query("SELECT department_id, department_name, description FROM departments ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);

    $sessions = $pdo->query("SELECT session_id, session_name, status FROM academic_session ORDER BY start_date DESC, session_id DESC")->fetchAll(PDO::FETCH_ASSOC);

    if ($lecturerId) {
        $stmt = $pdo->prepare("SELECT ca.assignment_id, ca.course_id, ca.program_id, ca.session_id, ca.semester, ca.level,
                                      c.course_code, c.course_name, c.credit_value, c.description,
                                      p.program_name,
                                      s.session_name,
                                      d.department_name,
                                      COALESCE((SELECT SUM(cc.hours_taught) FROM course_coverage cc WHERE cc.assignment_id = ca.assignment_id),0) AS taught_hours,
                                      COALESCE((SELECT SUM(ct.expected_hours) FROM cousre_topics ct WHERE ct.course_id = ca.course_id),0) AS expected_hours
                               FROM course_assgnment ca
                               INNER JOIN courses c ON c.course_id = ca.course_id
                               LEFT JOIN programs p ON p.program_id = ca.program_id
                               LEFT JOIN academic_session s ON s.session_id = ca.session_id
                               LEFT JOIN departments d ON d.department_id = c.department_id
                               WHERE ca.lecturer_id = ?
                               ORDER BY ca.assignment_id DESC");
        $stmt->execute([$lecturerId]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $topicStmt = $pdo->prepare("SELECT topic_id, course_id, topic_number, topic_title, description, expected_hours
                                    FROM cousre_topics
                                    WHERE course_id = ?
                                    ORDER BY topic_number, topic_id");
        $topicIds = [];
        foreach ($assignments as &$a) {
            $expected = (float)$a['expected_hours'];
            $taught = (float)$a['taught_hours'];
            $a['coverage'] = $expected > 0 ? min(100, ($taught / $expected) * 100) : 0;
            $stats['assigned_courses']++;
            $stats['topics'] += 0;
            $topicStmt->execute([(int)$a['course_id']]);
            $courseTopics = $topicStmt->fetchAll(PDO::FETCH_ASSOC);
            $topicsByCourse[(int)$a['course_id']] = $courseTopics;
            foreach ($courseTopics as $topic) {
                $topicIds[(int)$topic['topic_id']] = true;
                $stats['topics']++;
            }
        }
        unset($a);

        $topicProgress = $pdo->prepare("SELECT topic_id, COALESCE(SUM(hours_taught),0) taught_hours
                                        FROM course_coverage
                                        WHERE assignment_id = ?
                                        GROUP BY topic_id");
        foreach ($assignments as &$a) {
            $topicProgress->execute([(int)$a['assignment_id']]);
            $progressRows = $topicProgress->fetchAll(PDO::FETCH_ASSOC);
            $done = 0;
            foreach ($progressRows as $pr) {
                $topicId = (int)$pr['topic_id'];
                $expected = 0;
                foreach ($topicsByCourse[(int)$a['course_id']] ?? [] as $topic) {
                    if ((int)$topic['topic_id'] === $topicId) {
                        $expected = (float)$topic['expected_hours'];
                        break;
                    }
                }
                if ($expected > 0 && (float)$pr['taught_hours'] >= $expected) $done++;
            }
            $a['completed_topics'] = $done;
            $a['total_topics'] = count($topicsByCourse[(int)$a['course_id']] ?? []);
            if ($a['total_topics'] > 0) $stats['completed_topics'] += $done;
        }
        unset($a);

        $coverageValues = array_column($assignments, 'coverage');
        $stats['coverage'] = count($coverageValues) ? array_sum($coverageValues) / count($coverageValues) : 0;

        $recentStmt = $pdo->prepare("SELECT cc.coverage_id, cc.date_taught, cc.hours_taught, cc.coverage_status, cc.remarks,
                                            c.course_code, c.course_name, ct.topic_number, ct.topic_title,
                                            ca.assignment_id
                                     FROM course_coverage cc
                                     INNER JOIN course_assgnment ca ON ca.assignment_id = cc.assignment_id
                                     INNER JOIN courses c ON c.course_id = ca.course_id
                                     INNER JOIN cousre_topics ct ON ct.topic_id = cc.topic_id
                                     WHERE ca.lecturer_id = ?
                                     ORDER BY cc.updated_at DESC, cc.coverage_id DESC
                                     LIMIT 8");
        $recentStmt->execute([$lecturerId]);
        $recentProgress = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = $error ?: 'Some dashboard information could not be loaded. Check your database connection and table structure.';
}

// Prepare a compact JSON object for the topic selector.
$topicJson = [];
foreach ($assignments as $a) {
    $aid = (int)$a['assignment_id'];
    $topicJson[$aid] = [];
    foreach ($topicsByCourse[(int)$a['course_id']] ?? [] as $topic) {
        $topicJson[$aid][] = [
            'topic_id' => (int)$topic['topic_id'],
            'topic_number' => (int)$topic['topic_number'],
            'topic_title' => $topic['topic_title'],
            'expected_hours' => (float)$topic['expected_hours'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lecturer Dashboard | Course Coverage Management System</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f5f8f6;color:#173128;font-size:13px}.app{display:flex;min-height:100vh}.sidebar{width:245px;background:#0d5b3f;color:#fff;position:fixed;left:0;top:0;bottom:0;padding:22px 15px;display:flex;flex-direction:column;z-index:20}.brand{display:flex;align-items:center;gap:11px;padding:4px 8px 24px;border-bottom:1px solid rgba(255,255,255,.16)}.brand img{width:48px;height:48px;object-fit:contain;background:#fff;border-radius:50%;padding:4px}.brand strong{font-size:12px;line-height:1.35}.brand span{display:block;font-size:10px;opacity:.75;margin-top:2px}.menu-title{font-size:9px;letter-spacing:1.2px;opacity:.55;margin:25px 10px 9px}.side-link{display:flex;align-items:center;gap:11px;text-decoration:none;color:#eaf7f1;padding:11px 12px;border-radius:7px;margin:3px 0;font-size:12px}.side-link:hover,.side-link.active{background:rgba(255,255,255,.13)}.icon{width:18px;text-align:center;opacity:.9}.side-bottom{margin-top:auto;border-top:1px solid rgba(255,255,255,.14);padding:18px 8px 4px;font-size:9px;opacity:.65;text-align:center}.main{margin-left:245px;flex:1;min-width:0}.topbar{height:82px;background:#fff;border-bottom:1px solid #e4ebe7;display:flex;justify-content:space-between;align-items:center;padding:0 30px;position:sticky;top:0;z-index:10}.top-left{display:flex;align-items:center;gap:14px}.mobile-menu{display:none;border:0;background:none;font-size:22px}.heading h1{font-size:21px;margin:0;color:#18382d}.heading p{margin:5px 0 0;color:#7c8d86;font-size:11px}.profile{display:flex;align-items:center;gap:10px}.bell{font-size:18px;color:#547168;margin-right:7px}.avatar{width:38px;height:38px;border-radius:50%;object-fit:contain;background:#f1f5f2;padding:4px}.profile-text strong{display:block;font-size:11px}.profile-text span{display:block;font-size:10px;color:#8a9893;margin-top:3px}.content{padding:25px 30px 35px}.filter-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.department{background:#e9f4ee;border:1px solid #d5e8dd;border-radius:8px;padding:10px 13px;color:#40665a;font-size:11px}.select,.field select,.field input,.field textarea{width:100%;border:1px solid #dce6e1;border-radius:7px;background:#fff;padding:11px 12px;font-size:12px;color:#29483d;outline:none}.filter-row .select{width:230px}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}.card{background:#fff;border:1px solid #e4ebe7;border-radius:9px;padding:16px;box-shadow:0 2px 8px rgba(25,70,53,.03)}.card-top{display:flex;gap:12px;align-items:center}.card-icon{width:42px;height:42px;border-radius:8px;background:#e8f4ee;color:#0b704a;display:grid;place-items:center;font-size:19px}.card-label{font-size:9px;color:#87968f;letter-spacing:.5px}.card-number{font-size:23px;font-weight:700;color:#183b2f;margin-top:5px}.card-link{display:flex;justify-content:space-between;margin-top:13px;padding-top:11px;border-top:1px solid #edf2ef;color:#0b704a;text-decoration:none;font-size:10px;font-weight:600}.grid{display:grid;grid-template-columns:1.65fr 1fr;gap:18px;margin-bottom:18px}.panel{background:#fff;border:1px solid #e4ebe7;border-radius:9px;box-shadow:0 2px 8px rgba(25,70,53,.03);overflow:hidden}.panel-head{padding:17px 20px;border-bottom:1px solid #edf2ef;display:flex;justify-content:space-between;align-items:center}.panel-head h2{margin:0;font-size:11px;letter-spacing:.45px;color:#23453a}.panel-head p{margin:5px 0 0;font-size:10px;color:#84928c}.assign-form{padding:20px}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.field label{display:block;font-size:9px;font-weight:700;letter-spacing:.4px;color:#75877f;margin-bottom:6px}.field textarea{min-height:75px;resize:vertical}.field.full{grid-column:1/-1}.btn{border:0;background:#0d6848;color:#fff;border-radius:7px;padding:11px 17px;font-size:11px;font-weight:700;cursor:pointer;margin-top:15px}.btn:hover{background:#09583d}.btn.secondary{background:#edf5f1;color:#0d6848}.notice{padding:12px 14px;border-radius:7px;margin-bottom:17px;font-size:11px}.success{background:#e7f6ec;border:1px solid #cbe9d5;color:#24683c}.error{background:#fff0ef;border:1px solid #f0d1ce;color:#9c3b32}.overview{padding:12px 20px 20px}.overview-row{display:flex;justify-content:space-between;align-items:center;padding:13px 0;border-bottom:1px solid #edf2ef;font-size:11px}.overview-row:last-child{border-bottom:0}.overview-row strong{color:#183d31}.progress{height:7px;background:#edf2ef;border-radius:99px;overflow:hidden;min-width:90px}.progress span{display:block;height:100%;background:#0d704b;border-radius:99px}.mini-progress{display:flex;align-items:center;gap:9px}.pill{display:inline-block;padding:4px 8px;border-radius:20px;font-size:9px;font-weight:700}.good{background:#e7f6ec;color:#267044}.average{background:#fff6df;color:#946a18}.low{background:#fff0ef;color:#a13b31}.table-wrap{overflow-x:auto}.data-table{width:100%;border-collapse:collapse;min-width:850px}.data-table th{font-size:8px;letter-spacing:.5px;color:#899791;background:#fafcfa;text-align:left;padding:11px 14px;border-bottom:1px solid #e7eeea}.data-table td{font-size:10px;padding:13px 14px;border-bottom:1px solid #eef3f0;color:#435c53;vertical-align:middle}.data-table tr:last-child td{border-bottom:0}.empty{text-align:center;padding:25px!important;color:#8b9994}.footer{padding:16px 30px;border-top:1px solid #e4ebe7;color:#8a9892;font-size:9px;display:flex;justify-content:space-between}.footer strong{color:#5e746a}.department-list{padding:10px 20px 18px;max-height:250px;overflow:auto}.dept{padding:11px 0;border-bottom:1px solid #edf2ef}.dept:last-child{border-bottom:0}.dept strong{display:block;font-size:11px}.dept span{display:block;font-size:9px;color:#87958f;margin-top:3px}.course-name{font-weight:700;color:#29483d}.course-sub{font-size:9px;color:#88968f;margin-top:3px}.no-profile{padding:20px;background:#fff4e5;border:1px solid #f0dfbd;border-radius:8px;color:#856326;margin-bottom:18px}.help{font-size:9px;color:#87958f;margin-top:5px;line-height:1.5}
@media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:800px){.sidebar{transform:translateX(-100%);transition:.2s}.sidebar.open{transform:translateX(0)}.main{margin-left:0}.mobile-menu{display:block}.topbar{padding:0 16px}.profile-text{display:none}.content{padding:18px 15px}.filter-row{gap:10px;align-items:stretch;flex-direction:column}.filter-row .select{width:100%}.cards{grid-template-columns:1fr 1fr}.footer{padding:14px 15px;gap:10px;flex-direction:column}.form-grid{grid-template-columns:1fr}}@media(max-width:500px){.cards{grid-template-columns:1fr}.topbar{height:72px}.heading h1{font-size:17px}.bell{display:none}}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><img src="../assets/images/ub-logo.png" alt="University Logo"><div><strong>UNIVERSITY OF BUEA</strong><span>HTTTC KUMBA</span></div></div>
  <div class="menu-title">LECTURER MENU</div>
  <a class="side-link active" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a>
  <a class="side-link" href="my_courses.php"><span class="icon">▤</span>My Courses</a>
  <a class="side-link" href="coverage.php"><span class="icon">◫</span>Course Progress</a>
  <a class="side-link" href="#submit-progress"><span class="icon">＋</span>Record Coverage</a>
  <a class="side-link" href="#history"><span class="icon">◷</span>Coverage History</a>
  <a class="side-link" href="profile.php"><span class="icon">◉</span>Profile</a>
  <a class="side-link" href="change_password.php"><span class="icon">▣</span>Change Password</a>
  <a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a>
  <div class="side-bottom"><div style="font-size:20px;margin-bottom:6px">⌂</div>HTTTC KUMBA</div>
</aside>

<main class="main">
<header class="topbar">
  <div class="top-left"><button class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div class="heading"><h1>Lecturer Dashboard</h1><p>Manage assigned courses and submit course coverage progress</p></div></div>
  <div class="profile"><div class="bell">♧</div><img class="avatar" src="../assets/images/ub-logo.png" alt="Lecturer"><div class="profile-text"><strong><?=e($lecturerName)?></strong><span>Lecturer<?= $staffNo ? ' · '.e($staffNo) : '' ?></span></div></div>
</header>

<section class="content">
<div class="filter-row"><div class="department">My Department: <strong><?=e($departmentName)?></strong></div><select class="select" id="sessionFilter"><option value="">All Academic Sessions</option><?php foreach($sessions as $s): ?><option value="<?=e($s['session_id'])?>"><?=e($s['session_name'])?></option><?php endforeach; ?></select></div>

<?php if($success): ?><div class="notice success"><?=e($success)?></div><?php endif; ?>
<?php if($error): ?><div class="notice error"><?=e($error)?></div><?php endif; ?>
<?php if(!$lecturerId): ?><div class="no-profile"><strong>Lecturer profile not found.</strong><div class="help">Your login account was found, but no matching record exists in the lecturers table. The Administrator should make sure the lecturer's login email matches the lecturer profile email.</div></div><?php endif; ?>

<div class="cards">
  <div class="card"><div class="card-top"><div class="card-icon">▤</div><div><div class="card-label">ASSIGNED COURSES</div><div class="card-number"><?=number_format($stats['assigned_courses'])?></div></div></div><a class="card-link" href="#my-courses">View my courses <span>›</span></a></div>
  <div class="card"><div class="card-top"><div class="card-icon">◫</div><div><div class="card-label">COURSE TOPICS</div><div class="card-number"><?=number_format($stats['topics'])?></div></div></div><a class="card-link" href="#submit-progress">Record progress <span>›</span></a></div>
  <div class="card"><div class="card-top"><div class="card-icon">✓</div><div><div class="card-label">COMPLETED TOPICS</div><div class="card-number"><?=number_format($stats['completed_topics'])?></div></div></div><a class="card-link" href="#history">View history <span>›</span></a></div>
  <div class="card"><div class="card-top"><div class="card-icon">◔</div><div><div class="card-label">MY AVERAGE COVERAGE</div><div class="card-number"><?=number_format($stats['coverage'],1)?>%</div></div></div><a class="card-link" href="coverage.php">View progress <span>›</span></a></div>
</div>

<div class="grid">
<div class="panel" id="submit-progress">
  <div class="panel-head"><div><h2>RECORD COURSE PROGRESS</h2><p>Submit the topic you taught and the hours completed. The HOD dashboard will update automatically.</p></div></div>
  <form class="assign-form" method="post">
    <input type="hidden" name="action" value="submit_progress"><input type="hidden" name="csrf_token" value="<?=e($csrf)?>">
    <div class="form-grid">
      <div class="field full"><label>ASSIGNED COURSE</label><select name="assignment_id" id="assignmentSelect" required><option value="">Select one of your assigned courses</option><?php foreach($assignments as $a): ?><option value="<?=$a['assignment_id']?>" data-session="<?=e($a['session_id'])?>"><?=e($a['course_code'].' - '.$a['course_name'].' | '.$a['program_name'].' | '.$a['level'].' | '.$a['semester'])?></option><?php endforeach; ?></select></div>
      <div class="field full"><label>TOPIC TAUGHT</label><select name="topic_id" id="topicSelect" required disabled><option value="">Select a course first</option></select><div class="help">Only topics belonging to the selected assigned course are shown.</div></div>
      <div class="field"><label>DATE TAUGHT</label><input type="date" name="date_taught" value="<?=date('Y-m-d')?>" required></div>
      <div class="field"><label>HOURS TAUGHT</label><input type="number" name="hours_taught" min="0.25" max="24" step="0.25" placeholder="e.g. 2.00" required></div>
      <div class="field"><label>COVERAGE STATUS</label><select name="coverage_status" required><option value="">Select status</option><option value="Completed">Completed</option><option value="In Progress">In Progress</option><option value="Partially Covered">Partially Covered</option></select></div>
      <div class="field"><label>REMARKS</label><textarea name="remarks" placeholder="Optional note about the lesson, challenges or follow-up..."></textarea></div>
    </div>
    <button class="btn" type="submit">＋ Submit Progress</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>MY DEPARTMENT</h2><p>Department information available to you</p></div></div>
  <div class="overview">
    <div class="overview-row"><span>Department</span><strong><?=e($departmentName)?></strong></div>
    <div class="overview-row"><span>Lecturer</span><strong><?=e($lecturerName)?></strong></div>
    <div class="overview-row"><span>Staff Number</span><strong><?=e($staffNo ?: '—')?></strong></div>
    <div class="overview-row"><span>Email</span><strong><?=e($lecturerEmail)?></strong></div>
    <div class="overview-row"><span>Assigned courses</span><strong><?=number_format($stats['assigned_courses'])?></strong></div>
  </div>
  <div class="department-list">
    <?php if($departments): foreach($departments as $dept): ?>
      <div class="dept"><strong><?=e($dept['department_name'])?></strong><span><?=e($dept['description'] ?: 'University department')?></span></div>
    <?php endforeach; else: ?><div class="empty">No departments found.</div><?php endif; ?>
  </div>
</div>
</div>

<div class="panel" id="my-courses" style="margin-bottom:18px">
  <div class="panel-head"><div><h2>MY ASSIGNED COURSES</h2><p>Courses assigned to you by the Head of Department</p></div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>COURSE</th><th>PROGRAM</th><th>SESSION</th><th>SEMESTER</th><th>LEVEL</th><th>TOPICS</th><th>PROGRESS</th><th>STATUS</th></tr></thead><tbody>
  <?php if($assignments): foreach($assignments as $a): $cv=(float)$a['coverage']; $cls=$cv>=75?'good':($cv>=50?'average':'low'); ?>
  <tr class="assignment-row" data-session="<?=e($a['session_id'])?>"><td><span class="course-name"><?=e($a['course_code'])?></span><div class="course-sub"><?=e($a['course_name'])?></div></td><td><?=e($a['program_name'])?></td><td><?=e($a['session_name'])?></td><td><?=e($a['semester'])?></td><td><?=e($a['level'])?></td><td><?=e($a['completed_topics'])?> / <?=e($a['total_topics'])?></td><td><div class="mini-progress"><div class="progress"><span style="width:<?=min(100,max(0,$cv))?>%"></span></div><strong><?=number_format($cv,0)?>%</strong></div></td><td><span class="pill <?=$cls?>"><?= $cv >= 75 ? 'Good' : ($cv >= 50 ? 'In Progress' : 'Low') ?></span></td></tr>
  <?php endforeach; else: ?><tr><td colspan="8" class="empty">No courses have been assigned to you yet. Your HOD must assign a course first.</td></tr><?php endif; ?></tbody></table></div>
</div>

<div class="panel" id="history">
  <div class="panel-head"><div><h2>RECENT COVERAGE HISTORY</h2><p>Progress entries submitted by you</p></div></div>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>DATE</th><th>COURSE</th><th>TOPIC</th><th>HOURS</th><th>STATUS</th><th>REMARKS</th></tr></thead><tbody>
  <?php if($recentProgress): foreach($recentProgress as $r): $status=strtolower($r['coverage_status']); $cls=strpos($status,'complete')!==false?'good':(strpos($status,'progress')!==false?'average':'low'); ?><tr><td><?=e($r['date_taught'])?></td><td><strong><?=e($r['course_code'])?></strong><br><span style="color:#87958f"><?=e($r['course_name'])?></span></td><td><?=e('Topic '.$r['topic_number'].': '.$r['topic_title'])?></td><td><?=number_format((float)$r['hours_taught'],2)?> h</td><td><span class="pill <?=$cls?>"><?=e($r['coverage_status'])?></span></td><td><?=e($r['remarks'] ?: '—')?></td></tr><?php endforeach; else: ?><tr><td colspan="6" class="empty">No coverage submissions yet.</td></tr><?php endif; ?></tbody></table></div>
</div>

</section>
<footer class="footer"><span>© <?=date('Y')?> Course Coverage Management System. All Rights Reserved.</span><strong>HTTTC KUMBA - Excellence in Professional Training</strong></footer>
</main></div>
<script>
const topicsByAssignment = <?=json_encode($topicJson, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const assignmentSelect = document.getElementById('assignmentSelect');
const topicSelect = document.getElementById('topicSelect');
const sessionFilter = document.getElementById('sessionFilter');
function populateTopics(){
  const aid = assignmentSelect ? assignmentSelect.value : '';
  topicSelect.innerHTML = '';
  if(!aid){ topicSelect.disabled=true; topicSelect.add(new Option('Select a course first','')); return; }
  topicSelect.disabled=false;
  topicSelect.add(new Option('Select topic taught',''));
  (topicsByAssignment[aid] || []).forEach(t => {
    topicSelect.add(new Option('Topic '+t.topic_number+' - '+t.topic_title+' (Expected '+t.expected_hours+'h)', t.topic_id));
  });
}
if(assignmentSelect){ assignmentSelect.addEventListener('change', populateTopics); }
if(sessionFilter){
  sessionFilter.addEventListener('change', function(){
    const value=this.value;
    document.querySelectorAll('.assignment-row').forEach(row=>{ row.style.display = (!value || row.dataset.session===value) ? '' : 'none'; });
    if(assignmentSelect){
      [...assignmentSelect.options].forEach((opt,i)=>{ if(i===0) return; opt.hidden = !!value && opt.dataset.session!==value; });
      if(assignmentSelect.value && assignmentSelect.selectedOptions[0]?.hidden){ assignmentSelect.value=''; populateTopics(); }
    }
  });
}
</script>
</body>
</html>