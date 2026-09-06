<?php
require_once __DIR__ . '/config/database.php';

$email = 'admin@ubuea.cm';
$password = 'Admin@12345';
$fullName = 'System Administrator';

$roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) = 'admin' LIMIT 1");
$roleStmt->execute();
$role = $roleStmt->fetch();

if (!$role) {
    exit('Admin role not found. Import setup_roles.sql first.');
}

$check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);

if ($check->fetch()) {
    exit('Admin account already exists for ' . htmlspecialchars($email));
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, email, password, role_id, status, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([$fullName, $email, $hash, $role['role_id'], 'Active']);

echo '<h2>Admin account created successfully.</h2>';
echo '<p>Email: ' . htmlspecialchars($email) . '</p>';
echo '<p>Password: ' . htmlspecialchars($password) . '</p>';
echo '<p><strong>Delete create_admin.php immediately after using it.</strong></p>';
