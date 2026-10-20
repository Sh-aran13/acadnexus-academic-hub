<?php
// login.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/auth.php';

// Redirect if already logged in
redirectIfAuthenticated();

$errorMsg = '';
$successMsg = '';
$redirectUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $loginInput = trim($_POST['username_email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Verify CSRF Token
    if (!verifyCSRFToken()) {
        $errorMsg = 'Security validation failed: invalid CSRF token. Please refresh and try again.';
        setToast('error', $errorMsg);
    } elseif (empty($loginInput) || empty($password)) {
        $errorMsg = 'Please enter both your email/username and password.';
        setToast('error', $errorMsg);
    } else {
        // Rate limiting check
        $throttle = checkLoginThrottling($loginInput, $clientIp);
        if ($throttle['throttled']) {
            $errorMsg = $throttle['message'];
            setToast('error', $errorMsg);
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)");
                $stmt->execute([$loginInput, $loginInput]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] !== 'active') {
                        recordLoginAttempt($loginInput, $clientIp, false);
                        // Generic error to prevent enumeration of account status
                        $errorMsg = 'Invalid email/username or password.';
                        setToast('error', $errorMsg);
                    } else {
                        // Successful login
                        recordLoginAttempt($loginInput, $clientIp, true);

                        // Secure session setup
                        session_regenerate_id(true);
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        // Get profile display name
                        $name = $user['username'];
                        if ($user['role'] === 'student') {
                            $pStmt = $pdo->prepare("SELECT first_name, last_name FROM student_profiles WHERE user_id = ?");
                            $pStmt->execute([$user['id']]);
                            $profile = $pStmt->fetch();
                            if ($profile) $name = $profile['first_name'] . ' ' . $profile['last_name'];
                        } elseif ($user['role'] === 'faculty') {
                            $pStmt = $pdo->prepare("SELECT first_name, last_name FROM faculty_profiles WHERE user_id = ?");
                            $pStmt->execute([$user['id']]);
                            $profile = $pStmt->fetch();
                            if ($profile) $name = 'Prof. ' . $profile['first_name'] . ' ' . $profile['last_name'];
                        } elseif ($user['role'] === 'admin') {
                            $name = 'Administrator';
                        }

                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $name;
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['last_activity'] = time();

                        logActivity($user['id'], 'User login successful');

                        // Safe redirect restricted to internal role dashboard
                        $redirectUrl = getDashboardUrl($user['role']);
                        $successMsg = "Welcome back, " . htmlspecialchars($name) . "! Redirecting to your dashboard...";
                        setToast('success', $successMsg);
                    }
                } else {
                    recordLoginAttempt($loginInput, $clientIp, false);
                    $errorMsg = 'Invalid email/username or password.';
                    setToast('error', $errorMsg);
                }
            } catch (Exception $e) {
                error_log("Login error: " . $e->getMessage());
                $errorMsg = 'An unexpected authentication error occurred. Please try again.';
                setToast('error', $errorMsg);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | AcadNexus – Unified Academic Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #0f172a;
            --accent-blue: #2563eb;
            --accent-purple: #7c3aed;
            --accent-gradient: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        html {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        body {
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            display: flex;
            background-color: var(--bg-light);
            overflow-x: hidden;
            position: relative;
        }

        .login-wrapper {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            width: 100%;
            max-width: 100%;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* LEFT PANEL: Dark Academic Visual */
        .login-left {
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            color: #ffffff;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -20%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.25) 0%, transparent 70%);
            border-radius: 50%;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #ffffff;
            z-index: 2;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: var(--accent-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.4);
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
        }

        .left-hero-text {
            z-index: 2;
            max-width: 540px;
            margin: auto 0;
        }

        .left-hero-text h1 {
            font-size: 46px;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .left-hero-text h1 span {
            background: linear-gradient(135deg, #60a5fa, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .left-hero-text p {
            font-size: 16px;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        /* 4 Glass-Style Feature Cards */
        .glass-features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .gf-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.3s;
        }

        .gf-card:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.1);
        }

        .gf-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .gf-tag {
            font-size: 10px;
            font-weight: 800;
            color: #a78bfa;
            letter-spacing: 1px;
        }

        .gf-title {
            font-size: 13px;
            font-weight: 700;
            color: #ffffff;
        }

        .left-footer {
            z-index: 2;
            font-size: 13px;
            color: #64748b;
        }

        /* RIGHT PANEL: White Login Form */
        .login-right {
            background: #ffffff;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }

        .login-box {
            width: 100%;
            max-width: 440px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .grad-cap-icon {
            width: 56px;
            height: 56px;
            background: rgba(37, 99, 235, 0.1);
            color: var(--accent-blue);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px auto;
        }

        .login-header h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .login-header p {
            font-size: 14px;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            padding-right: 44px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            background: #f8fafc;
            font-size: 14px;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--accent-blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            font-size: 13px;
        }

        .forgot-link {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
        }
        .forgot-link:hover { text-decoration: underline; }

        .btn-submit {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: var(--accent-gradient);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.35);
        }

        .register-links {
            margin-top: 24px;
            text-align: center;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .register-links p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .register-buttons {
            display: flex;
            gap: 12px;
        }

        .btn-reg {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-dark);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }

        .btn-reg:hover {
            border-color: var(--accent-blue);
            color: var(--accent-blue);
            background: rgba(37, 99, 235, 0.04);
        }

        /* NEW ULTRA-MODERN DEMO CREDENTIALS CARD */
        .demo-credentials-card {
            margin-top: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 18px;
            padding: 18px;
            box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.08);
            position: relative;
        }

        .demo-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .demo-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-sparkle {
            font-size: 18px;
        }

        .demo-card-title {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.2px;
        }

        .demo-card-sub {
            font-size: 11px;
            color: #64748b;
        }

        .demo-pass-badge {
            font-size: 11px;
            font-weight: 700;
            background: #dbeafe;
            color: #1e40af;
            padding: 3px 9px;
            border-radius: 20px;
            border: 1px solid #bfdbfe;
        }

        .demo-role-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .demo-role-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }

        .demo-role-pill:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.12);
        }

        .role-pill-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-right: 10px;
            flex-shrink: 0;
        }

        .student-icon { background: #dbeafe; }
        .faculty-icon { background: #f3e8ff; }
        .admin-icon { background: #dcfce7; }

        .role-pill-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .role-pill-name {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
        }

        .role-pill-email {
            font-size: 11px;
            color: #64748b;
        }

        .role-fill-btn {
            font-size: 11px;
            font-weight: 700;
            color: #2563eb;
            background: rgba(37, 99, 235, 0.08);
            padding: 4px 9px;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .demo-role-pill:hover .role-fill-btn {
            background: #2563eb;
            color: #ffffff;
        }

        @media (max-width: 992px) {
            .login-wrapper {
                grid-template-columns: 1fr;
                min-height: 100vh;
                width: 100%;
            }
            .login-left { display: none; }
            .login-right {
                padding: 32px 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: var(--bg-light);
            }
            .login-box {
                max-width: 440px;
                width: 100%;
                margin: 0 auto;
            }
        }

        @media (max-width: 480px) {
            .login-right {
                padding: 24px 14px;
            }
            .login-header h2 {
                font-size: 24px;
            }
            .grad-cap-icon {
                width: 52px;
                height: 52px;
                border-radius: 14px;
                margin-bottom: 14px;
            }
            .form-input {
                font-size: 15px;
                padding: 13px 14px;
            }
            .btn-submit {
                padding: 14px;
                font-size: 15px;
            }
            .register-buttons {
                flex-direction: column;
                gap: 8px;
            }
            .demo-credentials-card {
                padding: 14px;
                border-radius: 16px;
            }
            .demo-role-pill {
                padding: 10px 10px;
            }
            .role-pill-icon {
                width: 30px;
                height: 30px;
                font-size: 14px;
                margin-right: 8px;
            }
            .role-pill-name {
                font-size: 12px;
            }
            .role-pill-email {
                font-size: 10.5px;
            }
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/includes/toast.php'; ?>

    <div class="login-wrapper">
        <!-- LEFT PANEL: Dark Academic Campus Visual -->
        <div class="login-left">
            <a href="index.php" class="brand-link">
                <div class="brand-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                </div>
                <div class="brand-title">AcadNexus</div>
            </a>

            <div class="left-hero-text">
                <h1>One Platform. <br><span>A Smarter Campus.</span></h1>
                <p>Simplifying learning, teaching and academic activities for a more connected and productive campus experience.</p>

                <div class="glass-features-grid">
                    <div class="gf-card">
                        <div class="gf-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <div>
                            <div class="gf-tag">ACCESS</div>
                            <div class="gf-title">Learning Resources</div>
                        </div>
                    </div>

                    <div class="gf-card">
                        <div class="gf-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div>
                            <div class="gf-tag">CONNECT</div>
                            <div class="gf-title">Faculty & Peers</div>
                        </div>
                    </div>

                    <div class="gf-card">
                        <div class="gf-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <div>
                            <div class="gf-tag">TRACK</div>
                            <div class="gf-title">Academic Progress</div>
                        </div>
                    </div>

                    <div class="gf-card">
                        <div class="gf-icon">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <div class="gf-tag">STAY</div>
                            <div class="gf-title">Organized & Updated</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="left-footer">
                &copy; 2026 AcadNexus. Unified Student–Faculty Platform.
            </div>
        </div>

        <!-- RIGHT PANEL: White Login Form -->
        <div class="login-right">
            <div class="login-box">
                <div class="login-header">
                    <div class="grad-cap-icon">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                    </div>
                    <h2>Welcome Back</h2>
                    <p>Sign in to your academic hub</p>
                </div>

                <form action="login.php" method="POST" id="loginForm">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label class="form-label" for="username_email">Email / Username</label>
                        <div class="input-wrapper">
                            <input type="text" id="username_email" name="username_email" class="form-input" placeholder="student@acadnexus.test or username" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                                <svg id="eyeIcon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label style="display: flex; align-items: center; gap: 8px; color: var(--text-muted); cursor: pointer;">
                            <input type="checkbox" style="accent-color: var(--accent-blue);"> Remember me
                        </label>
                        <a href="#" onclick="alert('Demo reset instructions: All seed accounts use password: Password123'); return false;" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span>&rarr; Login</span>
                    </button>
                </form>

                <div class="register-links">
                    <p>Don't have an account?</p>
                    <div class="register-buttons">
                        <a href="student/register.php" class="btn-reg">Register as Student</a>
                        <a href="faculty/register.php" class="btn-reg">Register as Faculty</a>
                    </div>
                </div>

                <!-- NEW INTERACTIVE ULTRA-MODERN DEMO ACCOUNTS CARD -->
                <div class="demo-credentials-card">
                    <div class="demo-card-header">
                        <div class="demo-header-left">
                            <span class="demo-sparkle">⚡</span>
                            <div>
                                <div class="demo-card-title">One-Click Demo Logins</div>
                                <div class="demo-card-sub">Click a role to auto-fill credentials</div>
                            </div>
                        </div>
                        <span class="demo-pass-badge">Pass: Password123</span>
                    </div>

                    <div class="demo-role-grid">
                        <button type="button" class="demo-role-pill" onclick="autoFillDemo('student@acadnexus.test', 'Password123', 'Student')">
                            <div class="role-pill-icon student-icon">🎓</div>
                            <div class="role-pill-info">
                                <span class="role-pill-name">Student Account</span>
                                <span class="role-pill-email">student@acadnexus.test</span>
                            </div>
                            <span class="role-fill-btn">Fill &rarr;</span>
                        </button>

                        <button type="button" class="demo-role-pill" onclick="autoFillDemo('faculty@acadnexus.test', 'Password123', 'Faculty')">
                            <div class="role-pill-icon faculty-icon">👨‍🏫</div>
                            <div class="role-pill-info">
                                <span class="role-pill-name">Faculty Account</span>
                                <span class="role-pill-email">faculty@acadnexus.test</span>
                            </div>
                            <span class="role-fill-btn">Fill &rarr;</span>
                        </button>

                        <button type="button" class="demo-role-pill" onclick="autoFillDemo('admin@acadnexus.test', 'Password123', 'Admin')">
                            <div class="role-pill-icon admin-icon">🛡️</div>
                            <div class="role-pill-info">
                                <span class="role-pill-name">Admin Account</span>
                                <span class="role-pill-email">admin@acadnexus.test</span>
                            </div>
                            <span class="role-fill-btn">Fill &rarr;</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const pwd = document.getElementById('password');
            if (pwd.type === 'password') {
                pwd.type = 'text';
            } else {
                pwd.type = 'password';
            }
        }

        function autoFillDemo(email, password, roleName) {
            const inputEmail = document.getElementById('username_email');
            const inputPwd = document.getElementById('password');
            if (inputEmail && inputPwd) {
                inputEmail.value = email;
                inputPwd.value = password;
                
                inputEmail.style.borderColor = '#2563eb';
                inputPwd.style.borderColor = '#2563eb';
                inputEmail.style.boxShadow = '0 0 0 4px rgba(37, 99, 235, 0.15)';
                inputPwd.style.boxShadow = '0 0 0 4px rgba(37, 99, 235, 0.15)';
                
                setTimeout(() => {
                    inputEmail.style.boxShadow = '';
                    inputPwd.style.boxShadow = '';
                }, 800);

                if (typeof showJsToast === 'function') {
                    showJsToast('info', 'Autofilled ' + roleName + ' demo credentials!');
                }
            }
        }

        <?php if (!empty($redirectUrl)): ?>
        setTimeout(function() {
            window.location.href = "<?php echo $redirectUrl; ?>";
        }, 1200);
        <?php endif; ?>
    </script>
</body>
</html>
