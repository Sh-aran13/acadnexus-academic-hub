<?php
// config/functions.php
date_default_timezone_set('Asia/Kolkata');
@ini_set('upload_max_filesize', '25M');
@ini_set('post_max_size', '30M');

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/file_service.php';

/**
 * HTML escaping helper for safe output encoding.
 */
function e($text) {
    return htmlspecialchars((string)($text ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitizes input data recursively.
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)($data ?? '')), ENT_QUOTES, 'UTF-8');
}

/**
 * Sets a flash toast notification into session.
 */
function setToast($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['toast'] = [
        'type'    => in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
        'message' => $message
    ];
}

/**
 * Logs security and operational actions into activity_logs with actor context.
 */
function logActivity($userId, $action) {
    try {
        $pdo = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([(int)$userId, $action, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Retrieves user profile information joined with department data.
 */
function getUserProfile($userId, $role) {
    $pdo = getDBConnection();
    if ($role === 'student') {
        $stmt = $pdo->prepare("
            SELECT u.email, u.username, u.role, u.status, s.*, d.name as department_name, d.code as department_code 
            FROM users u
            JOIN student_profiles s ON u.id = s.user_id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE u.id = ?
        ");
    } else if ($role === 'faculty') {
        $stmt = $pdo->prepare("
            SELECT u.email, u.username, u.role, u.status, f.*, d.name as department_name, d.code as department_code 
            FROM users u
            JOIN faculty_profiles f ON u.id = f.user_id
            LEFT JOIN departments d ON f.department_id = d.id
            WHERE u.id = ?
        ");
    } else {
        $stmt = $pdo->prepare("SELECT id, email, username, role, status FROM users WHERE id = ?");
    }
    $stmt->execute([(int)$userId]);
    return $stmt->fetch();
}

/**
 * Gets count of unread notifications for a user.
 */
function getUnreadNotificationCount($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([(int)$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Upload helper delegating to FileService for hardened validation.
 */
function handleFileUpload($file, $targetSubDir, $allowedExtensions = null) {
    // Determine category based on targetSubDir
    $category = 'material';
    if (str_contains($targetSubDir, 'profile')) {
        $category = 'avatar';
    } elseif (str_contains($targetSubDir, 'assignments')) {
        $category = 'assignment_attachment';
    } elseif (str_contains($targetSubDir, 'submissions')) {
        $category = 'submission';
    } elseif (str_contains($targetSubDir, 'circular') || str_contains($targetSubDir, 'announcements')) {
        $category = 'announcement';
    } elseif (str_contains($targetSubDir, 'projects')) {
        $category = 'project';
    }

    $options = [];
    if (!empty($allowedExtensions)) {
        $options['extensions'] = $allowedExtensions;
    }

    return FileService::upload($file, $category, $targetSubDir, $options);
}

/* ==========================================================================
   REUSABLE SERVER-SIDE VALIDATION HELPERS
   ========================================================================== */

class Validator {
    private $errors = [];
    private $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, ?string $label = null): self {
        $val = $this->data[$field] ?? null;
        if ($val === null || (is_string($val) && trim($val) === '') || (is_array($val) && empty($val))) {
            $this->addError($field, ($label ?? ucfirst($field)) . ' is required.');
        }
        return $this;
    }

    public function email(string $field, ?string $label = null): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, ($label ?? ucfirst($field)) . ' must be a valid email address.');
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $label = null): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->addError($field, ($label ?? ucfirst($field)) . " must be at least {$min} characters.");
        }
        return $this;
    }

    public function maxLength(string $field, int $max, ?string $label = null): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && mb_strlen($val) > $max) {
            $this->addError($field, ($label ?? ucfirst($field)) . " cannot exceed {$max} characters.");
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $label = null): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '' && !in_array($val, $allowed, true)) {
            $this->addError($field, ($label ?? ucfirst($field)) . ' contains an invalid selection.');
        }
        return $this;
    }

    public function numericRange(string $field, float $min, float $max, ?string $label = null): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '') {
            $num = (float)$val;
            if ($num < $min || $num > $max) {
                $this->addError($field, ($label ?? ucfirst($field)) . " must be between {$min} and {$max}.");
            }
        }
        return $this;
    }

    public function integerRange(string $field, int $min, int $max, ?string $label = null): self {
        $val = $this->data[$field] ?? null;
        if ($val !== null && $val !== '') {
            $intVal = filter_var($val, FILTER_VALIDATE_INT);
            if ($intVal === false || $intVal < $min || $intVal > $max) {
                $this->addError($field, ($label ?? ucfirst($field)) . " must be an integer between {$min} and {$max}.");
            }
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d', ?string $label = null): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $d = DateTime::createFromFormat($format, $val);
            if (!$d || $d->format($format) !== $val) {
                $this->addError($field, ($label ?? ucfirst($field)) . " must be a valid date ({$format}).");
            }
        }
        return $this;
    }

    public function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): ?string {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
