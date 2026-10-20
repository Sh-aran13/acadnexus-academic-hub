<?php
// config/auth.php

// Initialize session with hardened security cookies
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
              (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    
    // PHP session cookie parameters
    session_set_cookie_params([
        'lifetime' => 7200, // 2 hours
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

// Session timeout validation (2 hours of inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
    session_unset();
    session_destroy();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['toast'] = [
        'type' => 'warning',
        'message' => 'Your session has expired due to inactivity. Please log in again.'
    ];
}
$_SESSION['last_activity'] = time();

/**
 * Checks whether current request has an active authenticated session
 * and validates user status against the live database.
 */
function isAuthenticated() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    return validateSessionUser();
}

/**
 * Validates that the logged-in user exists, is active, and session role matches DB.
 */
function validateSessionUser() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, email, role, status FROM users WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'active') {
            // Terminate invalid or inactive session immediately
            logoutUser();
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['toast'] = [
                'type' => 'error',
                'message' => 'Your account is inactive or has been suspended. Please contact an administrator.'
            ];
            return false;
        }

        // Keep role synchronized if changed by admin
        if ($user['role'] !== ($_SESSION['role'] ?? '')) {
            $_SESSION['role'] = $user['role'];
        }

        return true;
    } catch (Exception $e) {
        error_log("User session verification error: " . $e->getMessage());
        return true; // Fallback to session state on transient DB blip
    }
}

/**
 * Ensures user is authenticated. If not, redirects to login.php.
 */
function requireLogin() {
    if (!isAuthenticated()) {
        $_SESSION['toast'] = [
            'type' => 'warning',
            'message' => 'Please log in to access this page.'
        ];
        $loginUrl = getRelativePathTo('login.php');
        if (!headers_sent()) {
            header("Location: $loginUrl");
            exit();
        }
        throw new RuntimeException("Unauthenticated access - redirect required to $loginUrl");
    }
}

/**
 * Ensures user has one of the authorized roles ('student', 'faculty', 'admin', or array of roles).
 *
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles) {
    requireLogin();

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $currentRole = $_SESSION['role'] ?? '';

    if (!in_array($currentRole, $roles, true)) {
        $_SESSION['toast'] = [
            'type' => 'error',
            'message' => 'Access denied: You do not have permission to view this resource.'
        ];
        $target = getDashboardUrl($currentRole);
        if (!headers_sent()) {
            header("Location: $target");
            exit();
        }
        throw new RuntimeException("Unauthorized role access - redirect required to $target");
    }
}

/**
 * Redirects user to their role dashboard if already logged in.
 */
function redirectIfAuthenticated() {
    if (isset($_SESSION['user_id']) && validateSessionUser()) {
        $target = getDashboardUrl($_SESSION['role']);
        header("Location: $target");
        exit();
    }
}

/**
 * Helper to determine dashboard path based on role and current script depth.
 */
function getDashboardUrl($role) {
    $prefix = getRelativePathTo('');
    switch ($role) {
        case 'student':
            return $prefix . 'student/dashboard.php';
        case 'faculty':
            return $prefix . 'faculty/dashboard.php';
        case 'admin':
            return $prefix . 'admin/dashboard.php';
        default:
            return $prefix . 'login.php';
    }
}

/**
 * Helper to calculate relative path to a root-level file based on script execution depth.
 */
function getRelativePathTo($file) {
    $script = $_SERVER['PHP_SELF'] ?? '';
    $inSubFolder = (strpos($script, '/student/') !== false || 
                    strpos($script, '/faculty/') !== false || 
                    strpos($script, '/admin/') !== false);
    return ($inSubFolder ? '../' : './') . ltrim($file, '/');
}

/**
 * Safely logs out user, invalidating session and removing session cookie.
 */
function logoutUser() {
    $_SESSION = [];
    if (ini_get("session.use_cookies") && !headers_sent()) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Safe internal redirect validator. Prevents open redirect vulnerabilities.
 */
function safeRedirect($target, $fallback = 'login.php') {
    // Only allow relative internal paths
    if (preg_match('#^(https?:)?//#i', $target) || str_contains($target, "\r") || str_contains($target, "\n")) {
        $target = $fallback;
    }
    header("Location: " . $target);
    exit();
}

/* ==========================================================================
   CSRF PROTECTION UTILITIES
   ========================================================================== */

/**
 * Generates or retrieves existing CSRF token.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns HTML hidden input field containing CSRF token.
 */
function csrfField() {
    $token = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifies CSRF token from POST body or request headers.
 *
 * @param string|null $token Provided token or null to auto-extract from request
 * @return bool
 */
function verifyCSRFToken($token = null) {
    if ($token === null) {
        if (!empty($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_XSRF_TOKEN'];
        } else {
            // Check raw JSON payload if application/json
            $input = json_decode(file_get_contents('php://input'), true);
            if (is_array($input) && !empty($input['csrf_token'])) {
                $token = $input['csrf_token'];
            }
        }
    }

    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Enforces CSRF token check for state-changing requests. Halts or redirects if invalid.
 */
function requireCSRF() {
    if (!verifyCSRFToken()) {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                  || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
        
        if ($isAjax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'error'   => 'Invalid or expired CSRF security token. Please refresh the page and try again.'
            ]);
            exit();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['toast'] = [
                'type'    => 'error',
                'message' => 'Action failed: Security validation token expired or invalid. Please try again.'
            ];
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer) && str_contains($referer, $_SERVER['HTTP_HOST'] ?? '')) {
            header("Location: $referer");
            exit();
        }

        http_response_code(403);
        die("403 Forbidden: Invalid CSRF security token.");
    }
}

/* ==========================================================================
   LOGIN RATE LIMITING & BRUTE FORCE PROTECTION
   ========================================================================== */

/**
 * Checks whether an IP or account identifier is currently throttled due to excessive failed attempts.
 *
 * @param string $identifier Username or Email
 * @param string $ip
 * @return array ['throttled' => bool, 'wait_seconds' => int, 'message' => string]
 */
function checkLoginThrottling($identifier, $ip) {
    try {
        $pdo = getDBConnection();
        
        // Ensure login_attempts table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id SERIAL PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                identifier VARCHAR(150) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_success BOOLEAN DEFAULT FALSE
            )
        ");

        $windowMinutes = 15;
        $maxAttempts = 5;

        // Count failed attempts from this IP or identifier in window
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE (ip_address = ? OR identifier = ?) 
              AND is_success = FALSE 
              AND attempted_at >= (NOW() - INTERVAL '15 minutes')
        ");
        $stmt->execute([$ip, strtolower(trim($identifier))]);
        $failures = (int)$stmt->fetchColumn();

        if ($failures >= $maxAttempts) {
            return [
                'throttled'    => true,
                'failures'     => $failures,
                'wait_seconds' => 900,
                'message'      => "Too many failed login attempts. Please wait 15 minutes before trying again."
            ];
        }

        return ['throttled' => false, 'failures' => $failures];
    } catch (Exception $e) {
        error_log("Login throttling check error: " . $e->getMessage());
        return ['throttled' => false, 'failures' => 0];
    }
}

/**
 * Records a login attempt (successful or failed).
 */
function recordLoginAttempt($identifier, $ip, $isSuccess) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, identifier, is_success) 
            VALUES (?, ?, ?)
        ");
        $stmt->bindValue(1, $ip, PDO::PARAM_STR);
        $stmt->bindValue(2, strtolower(trim($identifier)), PDO::PARAM_STR);
        $stmt->bindValue(3, (bool)$isSuccess, PDO::PARAM_BOOL);
        $stmt->execute();

        // If login succeeded, clear recent failed attempts for this identifier & IP
        if ($isSuccess) {
            $clr = $pdo->prepare("DELETE FROM login_attempts WHERE (identifier = ? OR ip_address = ?) AND is_success = FALSE");
            $clr->execute([strtolower(trim($identifier)), $ip]);
        }

        // Clean up attempts older than 2 days
        if (rand(1, 20) === 1) {
            $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL '2 days')");
        }
    } catch (Exception $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/* ==========================================================================
   RESOURCE-LEVEL AUTHORIZATION HELPERS
   ========================================================================== */

/**
 * Verifies if a faculty member is assigned to a specific course.
 */
function isFacultyAssignedToCourse($facultyId, $courseId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM faculty_courses WHERE faculty_id = ? AND course_id = ?");
        $stmt->execute([(int)$facultyId, (int)$courseId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verifies if a student is enrolled in a specific course.
 */
function isStudentEnrolledInCourse($studentId, $courseId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM course_enrollments WHERE student_id = ? AND course_id = ?");
        $stmt->execute([(int)$studentId, (int)$courseId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verifies if a faculty member is authorized to manage a specific assignment.
 */
function canFacultyManageAssignment($facultyId, $assignmentId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT 1 FROM assignments WHERE id = ? AND faculty_id = ?");
        $stmt->execute([(int)$assignmentId, (int)$facultyId]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks authorization to view or download an assignment submission.
 */
function canAccessSubmission($userId, $role, $submissionId) {
    try {
        $pdo = getDBConnection();
        if ($role === 'admin') return true;

        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM assignment_submissions sub
                JOIN student_profiles sp ON sub.student_id = sp.id
                WHERE sub.id = ? AND sp.user_id = ?
            ");
            $stmt->execute([(int)$submissionId, (int)$userId]);
            return (bool)$stmt->fetchColumn();
        }

        if ($role === 'faculty') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM assignment_submissions sub
                JOIN assignments a ON sub.assignment_id = a.id
                JOIN faculty_profiles fp ON fp.user_id = ?
                JOIN faculty_courses fc ON fc.faculty_id = fp.id AND fc.course_id = a.course_id
                WHERE sub.id = ?
            ");
            $stmt->execute([(int)$userId, (int)$submissionId]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks authorization to view or download a course material.
 */
function canAccessMaterial($userId, $role, $materialId) {
    try {
        $pdo = getDBConnection();
        if ($role === 'admin') return true;

        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM materials m
                JOIN student_profiles sp ON sp.user_id = ?
                JOIN course_enrollments ce ON ce.student_id = sp.id AND ce.course_id = m.course_id
                WHERE m.id = ?
            ");
            $stmt->execute([(int)$userId, (int)$materialId]);
            return (bool)$stmt->fetchColumn();
        }

        if ($role === 'faculty') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM materials m
                JOIN faculty_profiles fp ON fp.user_id = ?
                JOIN faculty_courses fc ON fc.faculty_id = fp.id AND fc.course_id = m.course_id
                WHERE m.id = ?
            ");
            $stmt->execute([(int)$userId, (int)$materialId]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks authorization for project records.
 */
function canAccessProject($userId, $role, $projectId) {
    try {
        $pdo = getDBConnection();
        if ($role === 'admin') return true;

        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM projects p
                JOIN student_profiles sp ON p.student_id = sp.id
                WHERE p.id = ? AND sp.user_id = ?
            ");
            $stmt->execute([(int)$projectId, (int)$userId]);
            return (bool)$stmt->fetchColumn();
        }

        if ($role === 'faculty') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM projects p
                JOIN faculty_profiles fp ON p.faculty_advisor_id = fp.id
                WHERE p.id = ? AND fp.user_id = ?
            ");
            $stmt->execute([(int)$projectId, (int)$userId]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Checks authorization for appointment records.
 */
function canAccessAppointment($userId, $role, $appointmentId) {
    try {
        $pdo = getDBConnection();
        if ($role === 'admin') return true;

        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM appointments a
                JOIN student_profiles sp ON a.student_id = sp.id
                WHERE a.id = ? AND sp.user_id = ?
            ");
            $stmt->execute([(int)$appointmentId, (int)$userId]);
            return (bool)$stmt->fetchColumn();
        }

        if ($role === 'faculty') {
            $stmt = $pdo->prepare("
                SELECT 1 
                FROM appointments a
                JOIN faculty_profiles fp ON a.faculty_id = fp.id
                WHERE a.id = ? AND fp.user_id = ?
            ");
            $stmt->execute([(int)$appointmentId, (int)$userId]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    } catch (Exception $e) {
        return false;
    }
}
