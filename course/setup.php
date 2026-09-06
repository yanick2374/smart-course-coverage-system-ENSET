<?php
// One-time setup for the first Admin account.
require_once __DIR__ . '/config/database.php';

$email = 'admin@ubuea.cm';
$password = 'Admin@12345';
$fullName = 'System Administrator';

try {
    // Ensure the three roles exist.
    $roles = ['Admin', 'HOD', 'Lecturer'];
    $roleStmt = $pdo->prepare('INSERT INTO roles (role_name) VALUES (?) ON DUPLICATE KEY UPDATE role_name = VALUES(role_name)');
    foreach ($roles as $role) {
        $roleStmt->execute([$role]);
    }

    $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) = 'admin' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch();
    if (!$role) {
        throw new Exception('The Admin role could not be found.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        // Reset the known test Admin account so the credentials below definitely work.
        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, password = ?, role_id = ?, status = ? WHERE user_id = ?');
        $stmt->execute([$fullName, $hash, $role['role_id'], 'Active', $existing['user_id']]);
        $message = 'Existing Admin account updated successfully.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$fullName, $email, $hash, $role['role_id'], 'Active']);
        $message = 'Admin account created successfully.';
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2>Setup failed</h2><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>System Setup</title>
<style>body{font-family:Arial,sans-serif;background:#f4f7f5;padding:40px}.box{max-width:650px;margin:auto;background:#fff;padding:30px;border-radius:14px;box-shadow:0 5px 25px #0001}code{background:#f1f1f1;padding:3px 6px;border-radius:5px}.ok{color:#176b35}</style>
</head><body><div class="box">
<h2 class="ok">✓ <?= htmlspecialchars($message) ?></h2>
<p>You can now log in using:</p>
<p><strong>Email:</strong> <code><?= htmlspecialchars($email) ?></code></p>
<p><strong>Password:</strong> <code><?= htmlspecialchars($password) ?></code></p>
<p><a href="index.php">Go to Login</a></p>
<p><strong>Security:</strong> Delete <code>setup.php</code> after successful setup.</p>
</div></body></html>
