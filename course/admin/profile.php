<?php
session_start();

if (empty($_SESSION['logged_in']) || !in_array(strtolower(trim($_SESSION['role'] ?? '')), ['admin', 'administrator'], true)) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/profile_photos.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (empty($_SESSION['admin_profile_csrf'])) {
    $_SESSION['admin_profile_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['admin_profile_csrf'];
$profile = null;
$error = '';
$success = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid request token. Please refresh the page.');
        }

        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($fullName === '' || mb_strlen($fullName) < 3) {
            throw new RuntimeException('Please enter a valid full name.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        saveProfilePhoto($_FILES['profile_photo'] ?? [], $userId);

        $check = $pdo->prepare('SELECT user_id FROM users WHERE LOWER(email)=LOWER(?) AND user_id<>? LIMIT 1');
        $check->execute([$email, $userId]);
        if ($check->fetchColumn()) {
            throw new RuntimeException('That email address is already in use.');
        }

        $update = $pdo->prepare('UPDATE users SET full_name=?, email=? WHERE user_id=?');
        $update->execute([$fullName, $email, $userId]);
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email'] = $email;
        $success = 'Administrator profile updated successfully.';
    }

    $stmt = $pdo->prepare('SELECT full_name, email, status, created_at FROM users WHERE user_id=? LIMIT 1');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        throw new RuntimeException('Administrator account could not be found.');
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    $error = 'Unable to load or update the administrator profile.';
}

$profile = $profile ?: [
    'full_name' => $_SESSION['full_name'] ?? 'Administrator',
    'email' => $_SESSION['email'] ?? '',
    'status' => 'active',
    'created_at' => ''
];
$initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $profile['full_name']), 0, 2));
$photoUrl = profilePhotoUrl($userId);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administrator Profile | Course Coverage Management System</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f5f8f6;color:#18372b;font-family:Arial,Helvetica,sans-serif}.layout{display:flex;min-height:100vh}.sidebar{width:250px;background:linear-gradient(180deg,#003e2b,#005037);color:#fff;position:fixed;inset:0 auto 0 0}.brand{height:122px;background:#fff;color:#004d36;display:flex;align-items:center;padding:13px 18px;gap:12px}.brand img{width:74px;height:74px;object-fit:contain}.brand-title{font-weight:800;font-size:15px}.brand-title span{display:block;font-size:11px;margin-top:7px}.menu-title{font-size:11px;color:#a9c6b8;letter-spacing:.7px;margin:24px 20px 10px;font-weight:700}.side-link{display:flex;align-items:center;gap:13px;margin:4px 9px;padding:12px 13px;border-radius:8px;font-size:13px;font-weight:600;color:#eaf4ef;text-decoration:none}.side-link:hover,.side-link.active{background:#51a927}.icon{width:20px;text-align:center}.main{margin-left:250px;width:calc(100% - 250px);min-height:100vh;position:relative}.main:after{content:"";position:fixed;right:6%;bottom:2%;width:min(460px,40vw);height:460px;background:url('../assets/images/ub-logo.png') center/contain no-repeat;opacity:.05;pointer-events:none}.topbar{height:123px;background:#fff;border-bottom:1px solid #dfe7e2;display:flex;align-items:center;justify-content:space-between;padding:0 28px;position:relative;z-index:1}.top-left{display:flex;align-items:center;gap:16px}.mobile-menu{display:none;border:0;background:none;font-size:23px}.heading h1{margin:0;color:#083f2b;font-size:24px}.heading p{margin:6px 0 0;color:#65756e;font-size:12px}.user{display:flex;align-items:center;gap:10px}.avatar,.large-avatar{display:grid;place-items:center;border-radius:50%;font-weight:800}.avatar{width:48px;height:48px;background:#e7f4d9;color:#087341}.user-text strong{display:block;font-size:13px}.user-text span{font-size:11px;color:#65756e}.content{padding:28px;position:relative;z-index:1}.panel{max-width:850px;background:#fff;border:1px solid #dfe7e2;border-radius:12px;box-shadow:0 8px 25px #0037260f;overflow:hidden}.profile-head{display:flex;align-items:center;gap:18px;padding:25px;background:linear-gradient(120deg,#f0f8ee,#fff)}.large-avatar{width:80px;height:80px;background:#087341;color:#fff;font-size:25px}.profile-head strong{font-size:21px;color:#083f2b}.profile-head span{display:block;color:#65756e;font-size:12px;margin-top:6px}.panel-head{padding:20px 24px;border-bottom:1px solid #edf2ee}.panel-head h2{margin:0;color:#083f2b;font-size:15px}.panel-head p{margin:6px 0 0;color:#65756e;font-size:11px}.form{padding:24px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field label{display:block;margin-bottom:7px;color:#597068;font-size:11px;font-weight:800}.field input{width:100%;height:42px;border:1px solid #d8e3dc;border-radius:7px;padding:0 12px;font:inherit}.field input:focus{outline:0;border-color:#0b5d3b;box-shadow:0 0 0 3px #0b5d3b1a}.meta{margin-top:18px;padding:14px;background:#f8fbf9;border:1px solid #edf2ee;border-radius:8px;color:#65756e;font-size:11px}.actions{display:flex;gap:10px;margin-top:20px}.btn{border:0;border-radius:7px;padding:11px 16px;font-weight:700;font-size:12px;text-decoration:none;cursor:pointer}.primary{background:#0b5d3b;color:#fff}.secondary{background:#e8f5ed;color:#0b5d3b}.alert{padding:12px 14px;border-radius:8px;margin-bottom:18px;font-size:12px}.success{background:#eaf7ed;color:#26713b;border:1px solid #ccebd4}.error{background:#fff0ee;color:#a12b22;border:1px solid #f2cbc6}.footer{padding:18px 28px;color:#87958f;font-size:10px;display:flex;justify-content:space-between}@media(max-width:760px){.sidebar{transform:translateX(-100%);transition:.2s;z-index:5}.sidebar.open{transform:translateX(0)}.main{margin-left:0;width:100%}.mobile-menu{display:block}.topbar{height:auto;min-height:78px;padding:13px 15px}.user-text{display:none}.content{padding:18px 15px}.grid{grid-template-columns:1fr}.footer{padding:15px;display:block}.footer strong{display:block;margin-top:5px}}
</style>
</head>
<body>
<div class="layout"><aside class="sidebar" id="sidebar"><div class="brand"><img src="../assets/images/ub-logo.png" alt="University of Buea"><div class="brand-title">COURSE COVERAGE<span>MANAGEMENT SYSTEM<br>HTTTC KUMBA</span></div></div><div class="menu-title">USER ROLE</div><div style="padding:0 19px 11px;font-size:13px;font-weight:700">Administrator</div><div class="menu-title">MAIN MENU</div><a class="side-link" href="dashboard.php"><span class="icon">⌂</span>Dashboard</a><a class="side-link" href="users.php"><span class="icon">♙</span>Users</a><a class="side-link" href="departments.php"><span class="icon">▣</span>Departments</a><a class="side-link" href="programs.php"><span class="icon">◇</span>Programs</a><a class="side-link" href="courses.php"><span class="icon">▤</span>Courses</a><a class="side-link" href="academic_years.php"><span class="icon">▣</span>Academic Years</a><a class="side-link" href="semesters.php"><span class="icon">▦</span>Semesters</a><div class="menu-title">ACCOUNT</div><a class="side-link active" href="profile.php"><span class="icon">◉</span>Profile</a><a class="side-link" href="../change_password.php"><span class="icon">▣</span>Change Password</a><a class="side-link" href="../auth/logout.php"><span class="icon">↪</span>Logout</a></aside><main class="main"><header class="topbar"><div class="top-left"><button class="mobile-menu" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div class="heading"><h1>Administrator Profile</h1><p>Manage your administrator account information</p></div></div><div class="user"><div class="avatar"><?=e($initials ?: 'AD')?></div><div class="user-text"><strong><?=e($profile['full_name'])?></strong><span>Administrator</span></div></div></header><section class="content"><?php if($success): ?><div class="alert success"><?=e($success)?></div><?php endif; ?><?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?><div class="panel"><div class="profile-head"><div class="large-avatar"><?=e($initials ?: 'AD')?></div><div><strong><?=e($profile['full_name'])?></strong><span>Administrator · Account status: <?=e(ucfirst((string)$profile['status']))?></span></div></div><div class="panel-head"><h2>ACCOUNT INFORMATION</h2><p>Update the name and email used by your administrator account.</p></div><form class="form" method="post"><input type="hidden" name="csrf_token" value="<?=e($csrf)?>"><div class="grid"><div class="field"><label for="full_name">FULL NAME</label><input id="full_name" name="full_name" value="<?=e($profile['full_name'])?>" required></div><div class="field"><label for="email">EMAIL ADDRESS</label><input id="email" type="email" name="email" value="<?=e($profile['email'])?>" required></div></div><div class="meta">Your Administrator role and account status are controlled by the system. Use Change Password to update your password securely.</div><div class="actions"><button class="btn primary" type="submit">Save Profile</button><a class="btn secondary" href="../change_password.php">Change Password</a></div></form></div></section><footer class="footer"><span>&copy; <?=date('Y')?> Course Coverage Management System. All Rights Reserved.</span><strong>HTTTC KUMBA - Excellence in Professional Training</strong></footer></main></div>
<script>
(() => {
  const form = document.querySelector('form.form');
  if (!form) return;
  form.enctype = 'multipart/form-data';
  const field = document.createElement('div');
  field.className = 'field';
  field.innerHTML = '<label for="profile_photo">PROFILE PHOTO</label><input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp"><div style="margin-top:6px;font-size:11px;color:#65756e">JPEG, PNG, or WebP; maximum 5 MB.</div>';
  form.querySelector('.grid').appendChild(field);
  const photo = <?=json_encode($photoUrl)?>;
  if (photo) document.querySelectorAll('.avatar,.large-avatar').forEach(el => { el.textContent=''; el.style.backgroundImage=`url("${photo}")`; el.style.backgroundSize='cover'; el.style.backgroundPosition='center'; });
})();
</script>
</body></html>
