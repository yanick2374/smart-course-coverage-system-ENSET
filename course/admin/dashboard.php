<?php
session_start();

if (empty($_SESSION['logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$adminName = $_SESSION['full_name'] ?? 'Administrator';
$firstName = trim(explode(' ', trim($adminName))[0] ?? 'Administrator');

$stats = [
    'users' => 0,
    'lecturers' => 0,
    'courses' => 0,
    'departments' => 0,
    'assignments' => 0,
    'avg_coverage' => 0,
];
$coverageRows = [];
$statusCounts = ['Excellent' => 0, 'Good' => 0, 'Average' => 0, 'Poor' => 0];
$recentUsers = [];
$alerts = [];
$activeSession = 'All Academic Sessions';

try {
    $stats['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['lecturers'] = (int)$pdo->query("SELECT COUNT(*) FROM lecturers WHERE LOWER(status) = 'active' OR status = ''")->fetchColumn();
    $stats['courses'] = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $stats['departments'] = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $stats['assignments'] = (int)$pdo->query("SELECT COUNT(*) FROM course_assgnment")->fetchColumn();

    // Coverage is calculated from hours taught against the expected hours of each course's topics.
    $coverageSql = "
        SELECT
            ca.assignment_id,
            c.course_code,
            c.course_name,
            l.full_name AS lecturer_name,
            COALESCE(SUM(DISTINCT ct.expected_hours), 0) AS expected_hours,
            COALESCE((SELECT SUM(cc2.hours_taught)
                      FROM course_coverage cc2
                      WHERE cc2.assignment_id = ca.assignment_id), 0) AS taught_hours
        FROM course_assgnment ca
        INNER JOIN courses c ON c.course_id = ca.course_id
        LEFT JOIN lecturers l ON l.lecturer_id = ca.lecturer_id
        LEFT JOIN cousre_topics ct ON ct.course_id = c.course_id
        GROUP BY ca.assignment_id, c.course_code, c.course_name, l.full_name
        ORDER BY c.course_code ASC
    ";
    $coverageRows = $pdo->query($coverageSql)->fetchAll();

    $coverageValues = [];
    foreach ($coverageRows as &$row) {
        $expected = (float)$row['expected_hours'];
        $taught = (float)$row['taught_hours'];
        $row['coverage'] = $expected > 0 ? min(100, max(0, ($taught / $expected) * 100)) : 0;
        $coverageValues[] = $row['coverage'];
    }
    unset($row);

    $stats['avg_coverage'] = count($coverageValues) ? array_sum($coverageValues) / count($coverageValues) : 0;

    // Highest five course assignments by coverage for the overview chart.
    usort($coverageRows, function ($a, $b) {
        return $b['coverage'] <=> $a['coverage'];
    });
    $coverageChart = array_slice($coverageRows, 0, 5);

    foreach ($coverageRows as $row) {
        if ($row['coverage'] >= 75) {
            $statusCounts['Excellent']++;
        } elseif ($row['coverage'] >= 50) {
            $statusCounts['Good']++;
        } elseif ($row['coverage'] >= 25) {
            $statusCounts['Average']++;
        } else {
            $statusCounts['Poor']++;
        }
    }

    // Most recently created user accounts.
    $recentUsers = $pdo->query("SELECT full_name, email, status, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Low coverage alerts.
    $alerts = array_values(array_filter($coverageRows, fn($r) => $r['coverage'] < 50));
    usort($alerts, fn($a, $b) => $a['coverage'] <=> $b['coverage']);
    $alerts = array_slice($alerts, 0, 5);

    $sessionStmt = $pdo->query("SELECT session_name FROM academic_session ORDER BY start_date DESC LIMIT 1");
    $latest = $sessionStmt->fetchColumn();
    if ($latest) $activeSession = $latest;
} catch (PDOException $ex) {
    // Keep the interface available even if an optional dashboard query fails.
}

$chartMax = max(100, ...array_map(fn($r) => (float)$r['coverage'], $coverageChart ?: [['coverage' => 0]]));
$totalStatus = max(1, array_sum($statusCounts));
$excellentPct = round(($statusCounts['Excellent'] / $totalStatus) * 100, 1);
$goodPct = round(($statusCounts['Good'] / $totalStatus) * 100, 1);
$averagePct = round(($statusCounts['Average'] / $totalStatus) * 100, 1);
$poorPct = round(($statusCounts['Poor'] / $totalStatus) * 100, 1);

function coverageClass(float $value): string {
    if ($value >= 75) return 'excellent';
    if ($value >= 50) return 'good';
    if ($value >= 25) return 'average';
    return 'poor';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard | Course Coverage Management System</title>
<style>
:root{
    --green-950:#003d2a;--green-900:#004d36;--green-800:#00613f;--green-700:#087341;
    --green:#269d43;--green-light:#e7f4d9;--gold:#f4b51f;--text:#10251c;--muted:#65756e;
    --border:#dfe7e2;--bg:#f7f9f8;--white:#fff;--shadow:0 8px 25px rgba(0,55,38,.08)
}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,"Segoe UI",Arial,sans-serif;color:var(--text);background:var(--bg)}
a{text-decoration:none;color:inherit}button{font:inherit;border:0;background:none;cursor:pointer}
.layout{display:flex;min-height:100vh}.sidebar{width:250px;background:linear-gradient(180deg,#003e2b,#005037);color:#fff;position:fixed;left:0;top:0;bottom:0;z-index:20;overflow-y:auto}.brand{height:122px;background:#fff;color:var(--green-900);display:flex;align-items:center;padding:13px 18px;gap:12px;border-bottom:1px solid #dce5df}.brand img{width:74px;height:74px;object-fit:contain}.brand-title{font-weight:800;font-size:16px;line-height:1.08}.brand-title span{display:block;font-size:12px;font-weight:600;margin-top:7px}.menu-title{font-size:12px;color:#a9c6b8;letter-spacing:.6px;margin:25px 20px 10px;font-weight:700}.side-link{display:flex;align-items:center;gap:13px;margin:4px 9px;padding:12px 13px;border-radius:8px;font-size:14px;font-weight:600;color:#eaf4ef}.side-link:hover,.side-link.active{background:#51a927;color:#fff}.icon{width:20px;text-align:center;font-size:18px;flex:none}.side-bottom{position:absolute;bottom:22px;left:0;right:0;text-align:center;color:#9bbbad;font-size:11px;padding:0 20px}.side-bottom .building{font-size:54px;line-height:1;opacity:.22;margin-bottom:5px}
.main{margin-left:250px;width:calc(100% - 250px);min-width:0}.topbar{height:123px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 25px 0 27px}.top-left{display:flex;align-items:center;gap:18px}.mobile-menu{display:none;font-size:24px}.heading h1{font-size:26px;margin:0 0 3px;color:#083f2b}.heading p{margin:0;color:var(--muted);font-size:14px}.profile{display:flex;align-items:center;gap:13px}.bell{font-size:23px;position:relative;margin-right:6px;color:#063f2c}.bell::after{content:'3';position:absolute;top:-7px;right:-8px;background:#1c8036;color:#fff;width:17px;height:17px;border-radius:50%;font-size:10px;display:grid;place-items:center;font-weight:700}.avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:1px solid #ddd;background:#eaf0ec}.profile-text{line-height:1.2}.profile-text strong{display:block;font-size:14px}.profile-text span{font-size:12px;color:var(--muted)}.profile-arrow{font-size:18px;margin-left:10px}
.content{padding:23px 22px 18px;position:relative}.watermark{position:absolute;right:28%;top:40px;width:510px;height:510px;opacity:.045;pointer-events:none}.filter-row{display:flex;justify-content:flex-end;margin-bottom:22px}.select{border:1px solid #ccd8d2;border-radius:6px;background:#fff;padding:10px 13px;min-width:245px;color:#17362a;font-size:13px;outline:none}
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:17px;margin-bottom:20px}.card{background:#fff;border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);padding:22px 20px 17px;min-height:145px}.card-top{display:flex;align-items:center;gap:16px}.card-icon{width:57px;height:57px;border-radius:50%;display:grid;place-items:center;background:#e3f1d2;color:#19783b;font-size:25px}.card:nth-child(3) .card-icon{background:#fff0c9;color:#ee9d00}.card-label{font-size:13px;color:#202a26;font-weight:600}.card-number{font-size:28px;font-weight:800;margin-top:5px;letter-spacing:.2px}.card-link{display:flex;justify-content:flex-end;align-items:center;margin-top:16px;color:#07502f;font-size:13px;font-weight:600;gap:12px}.card-link span{font-size:22px}
.grid-main{display:grid;grid-template-columns:1.43fr .88fr;gap:17px;margin-bottom:14px}.panel{background:#fff;border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);overflow:hidden}.panel-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px}.panel-head h2{font-size:15px;margin:0;color:#07442c}.panel-head .small-select{border:1px solid #d5ded9;padding:8px 10px;border-radius:6px;font-size:12px;background:#fff}.chart-wrap{height:278px;padding:8px 20px 17px 27px;display:flex;align-items:stretch;gap:14px}.y-axis{width:26px;display:flex;flex-direction:column;justify-content:space-between;padding:7px 0 29px;font-size:11px;color:#3d4e47;text-align:right}.chart-area{flex:1;position:relative;padding:5px 5px 32px;border-bottom:1px solid #cfd8d3}.gridline{position:absolute;left:0;right:0;border-top:1px solid #e6ece8}.g100{top:5px}.g80{top:25%}.g60{top:45%}.g40{top:65%}.g20{top:85%}.bars{height:100%;display:flex;align-items:flex-end;justify-content:space-around;position:relative;z-index:2}.bar-group{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;min-width:70px}.bar-value{font-size:12px;font-weight:600;margin-bottom:7px}.bar{width:42px;background:#197d2c;border-radius:3px 3px 0 0;min-height:3px}.bar-label{font-size:12px;margin-top:8px;white-space:nowrap;color:#263d34}.x-title{position:absolute;bottom:-30px;left:0;right:0;text-align:center;font-size:12px;color:#27443a}
.status-body{height:278px;display:flex;align-items:center;justify-content:center;gap:22px;padding:5px 20px 17px}.donut{width:190px;height:190px;border-radius:50%;background:conic-gradient(#2f8d28 0 <?=$excellentPct?>%,#2f6db0 <?=$excellentPct?>% <?=$excellentPct + $goodPct?>%,#f7ad12 <?=$excellentPct + $goodPct?>% <?=$excellentPct + $goodPct + $averagePct?>%,#e4372d <?=$excellentPct + $goodPct + $averagePct?>% 100%);position:relative;flex:none}.donut::after{content:'';position:absolute;inset:42px;background:#fff;border-radius:50%}.donut-center{position:absolute;z-index:2;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}.donut-center strong{font-size:25px}.donut-center span{font-size:12px}.legend{font-size:12px;line-height:1.7}.legend-row{display:flex;align-items:center;gap:10px;margin:8px 0;white-space:nowrap}.swatch{width:13px;height:13px;border-radius:2px}.legend .value{margin-left:auto;padding-left:12px}.swatch.excellent{background:#2f8d28}.swatch.good{background:#2f6db0}.swatch.average{background:#f7ad12}.swatch.poor{background:#e4372d}
.bottom-grid{display:grid;grid-template-columns:1.43fr .88fr;gap:17px}.table-wrap{overflow:auto}.data-table{width:100%;border-collapse:collapse;font-size:12px}.data-table th,.data-table td{padding:9px 10px;border-top:1px solid #edf1ef;text-align:left;white-space:nowrap}.data-table th{font-size:11px;color:#172a22;background:#fcfdfc}.data-table td:first-child,.data-table th:first-child{padding-left:20px}.status-pill{display:inline-block;padding:5px 10px;border-radius:8px;font-size:11px;font-weight:600}.status-pill.active,.status-pill.approved{background:#e6f3d8;color:#287626}.status-pill.pending{background:#fff0c9;color:#ef9700}.status-pill.rejected{background:#ffe3df;color:#e13b2f}.coverage-low{color:#e3342a;font-weight:700}.view-all{font-size:12px;color:#07542f}.alert-list{padding:0 20px 10px}.alert-item{display:grid;grid-template-columns:1fr auto;gap:15px;padding:10px 0;border-top:1px solid #edf1ef}.alert-item:first-child{border-top:0}.alert-name{font-size:12px;font-weight:600}.alert-course{font-size:11px;color:#65756e;margin-top:4px}.alert-pct{font-size:12px;color:#e2362b;font-weight:700;align-self:center}
.footer{height:50px;background:#fff;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 22px;font-size:11px;color:#40534b}.footer strong{color:#17672e;font-style:italic}
@media(max-width:1100px){.cards{grid-template-columns:repeat(2,1fr)}.grid-main,.bottom-grid{grid-template-columns:1fr}.status-body{justify-content:flex-start}.sidebar{width:225px}.main{margin-left:225px;width:calc(100% - 225px)}}
@media(max-width:760px){.sidebar{transform:translateX(-100%);transition:.25s;width:250px}.sidebar.open{transform:translateX(0)}.main{margin-left:0;width:100%}.mobile-menu{display:block}.topbar{height:auto;min-height:78px;padding:12px 15px}.heading h1{font-size:20px}.profile-text,.profile-arrow{display:none}.cards{grid-template-columns:1fr}.content{padding:15px 12px}.filter-row{justify-content:stretch}.select{width:100%}.status-body{flex-direction:column;height:auto;padding:10px 20px 24px}.donut{width:170px;height:170px}.footer{height:auto;gap:8px;flex-direction:column;padding:10px}.table-wrap{font-size:11px}}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <img src="../assets/images/ub-logo.png" alt="University of Buea">
        <div class="brand-title">COURSE COVERAGE<span>MANAGEMENT SYSTEM<br>HTTTC KUMBA</span></div>
    </div>
    <div class="menu-title">USER ROLE</div>
    <div style="padding:0 19px 11px;font-size:14px;font-weight:700;display:flex;align-items:center;gap:10px"><span style="width:34px;height:34px;border:2px solid rgba(255,255,255,.7);border-radius:50%;display:grid;place-items:center">♙</span> Administrator</div>
    <div class="menu-title">MAIN MENU</div>
    <a class="side-link active" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a>
    <a class="side-link" href="users.php"><span class="icon">♙</span>Users</a>
    <a class="side-link" href="departments.php"><span class="icon">▣</span>Departments</a>
    <a class="side-link" href="programs.php"><span class="icon">◇</span>Programs</a>
    <a class="side-link" href="courses.php"><span class="icon">▤</span>Courses</a>
    <a class="side-link" href="academic_years.php"><span class="icon">▣</span>Academic Years</a>
    <a class="side-link" href="semesters.php"><span class="icon">▦</span>Semesters</a>
    <div class="menu-title">ACCOUNT</div>
    <a class="side-link" href="profile.php"><span class="icon">◉</span>Profile</a>
    <a class="side-link" href="../change_password.php"><span class="icon">▣</span>Change Password</a>
    <a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a>
    <div class="side-bottom"><div class="building">⌂</div><div>HTTTC KUMBA</div></div>
</aside>

<main class="main">
<header class="topbar">
    <div class="top-left">
        <button class="mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
        <div class="heading"><h1>Admin Dashboard</h1><p>System administration and academic overview</p></div>
    </div>
    <div class="profile">
        <div class="bell">♧</div>
        <img class="avatar" src="../assets/images/ub-logo.png" alt="Administrator">
        <div class="profile-text"><strong><?=e($adminName)?></strong><span>Administrator</span></div>
        <div class="profile-arrow">⌄</div>
    </div>
</header>

<section class="content">
    <img class="watermark" src="../assets/images/ub-logo.png" alt="">
    <div class="filter-row">
        <select class="select" onchange="window.location.href='dashboard.php?session='+encodeURIComponent(this.value)">
            <option><?=e($activeSession)?></option>
        </select>
    </div>

    <div class="cards">
        <div class="card"><div class="card-top"><div class="card-icon">♙</div><div><div class="card-label">TOTAL USERS</div><div class="card-number"><?=number_format($stats['users'])?></div></div></div><a class="card-link" href="users.php">Manage users <span>›</span></a></div>
        <div class="card"><div class="card-top"><div class="card-icon">♟</div><div><div class="card-label">TOTAL LECTURERS</div><div class="card-number"><?=number_format($stats['lecturers'])?></div></div></div><a class="card-link" href="users.php">View lecturers <span>›</span></a></div>
        <div class="card"><div class="card-top"><div class="card-icon">▤</div><div><div class="card-label">TOTAL COURSES</div><div class="card-number"><?=number_format($stats['courses'])?></div></div></div><a class="card-link" href="courses.php">View courses <span>›</span></a></div>
        <div class="card"><div class="card-top"><div class="card-icon">⌘</div><div><div class="card-label">DEPARTMENTS</div><div class="card-number"><?=number_format($stats['departments'])?></div></div></div><a class="card-link" href="departments.php">View departments <span>›</span></a></div>
    </div>

    <div class="grid-main">
        <div class="panel">
            <div class="panel-head"><h2>COURSE COVERAGE OVERVIEW</h2><select class="small-select"><option>Top 5 Courses</option></select></div>
            <div class="chart-wrap">
                <div class="y-axis"><span>100</span><span>80</span><span>60</span><span>40</span><span>20</span><span>0</span></div>
                <div class="chart-area">
                    <div class="gridline g100"></div><div class="gridline g80"></div><div class="gridline g60"></div><div class="gridline g40"></div><div class="gridline g20"></div>
                    <div class="bars">
                        <?php foreach($coverageChart as $r): $pct=(float)$r['coverage']; ?>
                        <div class="bar-group"><div class="bar-value"><?=round($pct)?>%</div><div class="bar" style="height:<?=max(2, min(100,$pct))?>%"></div><div class="bar-label"><?=e($r['course_code'])?></div></div>
                        <?php endforeach; if (!$coverageChart): ?><div style="color:#788780;font-size:13px;align-self:center">No course coverage data available yet.</div><?php endif; ?>
                    </div>
                    <div class="x-title">Courses</div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>COVERAGE STATUS</h2></div>
            <div class="status-body">
                <div class="donut"><div class="donut-center"><strong><?=number_format($stats['assignments'])?></strong><span>Assignments</span></div></div>
                <div class="legend">
                    <div class="legend-row"><span class="swatch excellent"></span><span>Excellent (≥75%)</span><span class="value"><?=$statusCounts['Excellent']?> (<?=$excellentPct?>%)</span></div>
                    <div class="legend-row"><span class="swatch good"></span><span>Good (50% - 74%)</span><span class="value"><?=$statusCounts['Good']?> (<?=$goodPct?>%)</span></div>
                    <div class="legend-row"><span class="swatch average"></span><span>Average (25% - 49%)</span><span class="value"><?=$statusCounts['Average']?> (<?=$averagePct?>%)</span></div>
                    <div class="legend-row"><span class="swatch poor"></span><span>Poor (&lt;25%)</span><span class="value"><?=$statusCounts['Poor']?> (<?=$poorPct?>%)</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-grid">
        <div class="panel">
            <div class="panel-head"><h2>RECENT USER ACCOUNTS</h2><a class="view-all" href="users.php">View all</a></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>#</th><th>USER</th><th>EMAIL</th><th>DATE</th><th>STATUS</th></tr></thead><tbody>
            <?php if($recentUsers): foreach($recentUsers as $i=>$u): ?>
            <tr><td><?=$i+1?></td><td><?=e($u['full_name'])?></td><td><?=e($u['email'])?></td><td><?=e(date('d M Y', strtotime($u['created_at'])))?></td><td><span class="status-pill <?=strtolower($u['status'])==='active'?'active':'rejected'?>"><?=e(ucfirst($u['status']))?></span></td></tr>
            <?php endforeach; else: ?><tr><td colspan="5" style="text-align:center;color:#788780;padding:28px">No user accounts found.</td></tr><?php endif; ?></tbody></table></div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2>LOW COVERAGE ALERTS</h2><a class="view-all" href="../hod/coverage.php">View all</a></div>
            <div class="alert-list">
            <?php if($alerts): foreach($alerts as $a): ?>
                <div class="alert-item"><div><div class="alert-name"><?=e($a['lecturer_name'] ?: 'Unassigned Lecturer')?></div><div class="alert-course"><?=e($a['course_code'].' - '.$a['course_name'])?></div></div><div class="alert-pct"><?=round($a['coverage'])?>%</div></div>
            <?php endforeach; else: ?><div style="padding:25px 0;text-align:center;color:#788780;font-size:12px">No low coverage alerts.</div><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<footer class="footer"><span>© <?=date('Y')?> Course Coverage Management System. All Rights Reserved.</span><strong>HTTTC KUMBA - Excellence in Professional Training</strong></footer>
</main></div>
</body></html>