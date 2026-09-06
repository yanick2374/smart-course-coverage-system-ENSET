<?php
session_start();

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin/dashboard.php');
            exit;
        case 'hod':
        case 'head of department':
            header('Location: hod/dashboard.php');
            exit;
        case 'lecturer':
            header('Location: lecturer/dashboard.php');
            exit;
    }
}

$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Course Coverage Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-brand-panel">
            <div class="brand-overlay"></div>
            <div class="brand-content">
                <img src="assets/images/ub-logo.png" alt="University of Buea Logo" class="university-logo">
                <p class="institution">UNIVERSITY OF BUEA</p>
                <h1>HTTTC KUMBA</h1>
                <div class="brand-divider"></div>
                <h2>COURSE COVERAGE<br>MANAGEMENT SYSTEM</h2>
                <p class="brand-description">
                    Monitor course delivery, lecturer assignments and academic coverage
                    from one centralized platform.
                </p>
                <p class="motto">Excellence in Professional Training</p>
            </div>
        </section>

        <section class="login-form-panel">
            <div class="mobile-logo-wrap">
                <img src="assets/images/ub-logo.png" alt="University of Buea Logo">
            </div>

            <div class="login-card">
                <div class="welcome-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20 21a8 8 0 0 0-16 0"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>

                <p class="eyebrow">SECURE ACCESS</p>
                <h2>Welcome Back</h2>
                <p class="login-subtitle">Sign in to access the Course Coverage Management System</p>

                <?php if ($error): ?>
                    <div class="alert alert-error" role="alert">
                        <span class="alert-icon">!</span>
                        <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <form action="auth/authenticate.php" method="POST" autocomplete="on" novalidate>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="5" width="18" height="14" rx="2"/>
                                    <path d="m3 7 9 6 9-6"/>
                                </svg>
                            </span>
                            <input type="email" id="email" name="email" placeholder="Enter your email address" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="label-row">
                            <label for="password">Password</label>
                            <a href="#" class="forgot-link" onclick="return false;">Forgot password?</a>
                        </div>
                        <div class="input-wrap">
                            <span class="input-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="5" y="10" width="14" height="10" rx="2"/>
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                </svg>
                            </span>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                                <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="2.5"/>
                                </svg>
                                <svg id="eyeClosed" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="m3 3 18 18"/>
                                    <path d="M10.6 6.2A10.6 10.6 0 0 1 12 6c6.5 0 10 6 10 6a17.4 17.4 0 0 1-3.2 3.8M6.1 6.1C3.4 8 2 12 2 12s3.5 6 10 6c1.6 0 3-.3 4.2-.8"/>
                                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="remember-row">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>

                    <button type="submit" class="login-button">
                        <span>Sign In</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14"/>
                            <path d="m13 6 6 6-6 6"/>
                        </svg>
                    </button>
                </form>

                <div class="login-info">
                    <span class="status-dot"></span>
                    <span>Authorized users only</span>
                </div>

                <p class="login-footer">
                    For account assistance, contact your system administrator.
                </p>
            </div>
        </section>
    </main>

    <script>
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        const eyeOpen = document.getElementById('eyeOpen');
        const eyeClosed = document.getElementById('eyeClosed');

        toggle.addEventListener('click', () => {
            const isPassword = password.type === 'password';
            password.type = isPassword ? 'text' : 'password';
            eyeOpen.classList.toggle('hidden', isPassword);
            eyeClosed.classList.toggle('hidden', !isPassword);
            toggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    </script>
</body>
</html>
