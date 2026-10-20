<?php
// logout.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/auth.php';

// Verify CSRF token from POST or query param
$token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;

if (isset($_SESSION['user_id'])) {
    if (!$token || !verifyCSRFToken($token)) {
        $_SESSION['toast'] = [
            'type'    => 'error',
            'message' => 'Logout rejected: Invalid or missing CSRF token.'
        ];
        $target = getDashboardUrl($_SESSION['role'] ?? '');
        header("Location: $target");
        exit();
    }

    logActivity($_SESSION['user_id'], 'User logged out');
}

// Invalidate session cleanly
logoutUser();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['toast'] = [
    'type'    => 'success',
    'message' => 'Logged out successfully.'
];

header('Location: login.php');
exit();
