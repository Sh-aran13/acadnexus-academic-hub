<?php
// download.php
// Centralized Authenticated & Authorized Document Download Gateway

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/file_service.php';

requireLogin();

$pdo = getDBConnection();
$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$inline = isset($_GET['inline']) && $_GET['inline'] === '1';

if ($id <= 0 || empty($type)) {
    http_response_code(400);
    die("Invalid file download request.");
}

switch ($type) {
    case 'material':
        $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        $mat = $stmt->fetch();

        if (!$mat || empty($mat['file_path'])) {
            http_response_code(404);
            die("Course material not found.");
        }

        if (!canAccessMaterial($userId, $role, $id)) {
            http_response_code(403);
            die("Access denied: You are not authorized to access this course material.");
        }

        $dispName = $mat['title'] . '.' . ($mat['file_type'] ?: pathinfo($mat['file_path'], PATHINFO_EXTENSION));
        FileService::serveDownload($mat['file_path'], $dispName, $inline);
        break;

    case 'submission':
        $stmt = $pdo->prepare("SELECT * FROM assignment_submissions WHERE id = ?");
        $stmt->execute([$id]);
        $sub = $stmt->fetch();

        if (!$sub || empty($sub['file_path'])) {
            http_response_code(404);
            die("Submission document not found.");
        }

        if (!canAccessSubmission($userId, $role, $id)) {
            http_response_code(403);
            die("Access denied: You are not authorized to view this assignment submission.");
        }

        $ext = pathinfo($sub['file_path'], PATHINFO_EXTENSION);
        $dispName = "Assignment_Submission_{$id}.{$ext}";
        FileService::serveDownload($sub['file_path'], $dispName, $inline);
        break;

    case 'assignment':
        $stmt = $pdo->prepare("SELECT * FROM assignments WHERE id = ?");
        $stmt->execute([$id]);
        $assign = $stmt->fetch();

        if (!$assign || empty($assign['attachment'])) {
            http_response_code(404);
            die("Assignment attachment not found.");
        }

        // Verify access: Admin, assigned Faculty, or enrolled Student
        $allowed = false;
        if ($role === 'admin') {
            $allowed = true;
        } elseif ($role === 'faculty') {
            $fac = getUserProfile($userId, 'faculty');
            $allowed = isFacultyAssignedToCourse($fac['id'] ?? 0, $assign['course_id']);
        } elseif ($role === 'student') {
            $stu = getUserProfile($userId, 'student');
            $allowed = isStudentEnrolledInCourse($stu['id'] ?? 0, $assign['course_id']);
        }

        if (!$allowed) {
            http_response_code(403);
            die("Access denied: You are not enrolled or allocated to this course.");
        }

        $ext = pathinfo($assign['attachment'], PATHINFO_EXTENSION);
        $dispName = "{$assign['title']}_Brief.{$ext}";
        FileService::serveDownload($assign['attachment'], $dispName, $inline);
        break;

    case 'announcement':
        $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        $ann = $stmt->fetch();

        if (!$ann || empty($ann['circular_image'])) {
            http_response_code(404);
            die("Circular attachment not found.");
        }

        // Verify audience access
        $allowed = false;
        if ($role === 'admin' || empty($ann['target_department_id']) && empty($ann['target_course_id'])) {
            $allowed = true;
        } elseif ($role === 'student') {
            $stu = getUserProfile($userId, 'student');
            $stuId = $stu['id'] ?? 0;
            $deptId = $stu['department_id'] ?? 0;
            $allowed = ($ann['target_department_id'] && (int)$ann['target_department_id'] === (int)$deptId)
                       || ($ann['target_course_id'] && isStudentEnrolledInCourse($stuId, $ann['target_course_id']));
        } elseif ($role === 'faculty') {
            $fac = getUserProfile($userId, 'faculty');
            $facId = $fac['id'] ?? 0;
            $deptId = $fac['department_id'] ?? 0;
            $allowed = ($ann['target_department_id'] && (int)$ann['target_department_id'] === (int)$deptId)
                       || ($ann['target_course_id'] && isFacultyAssignedToCourse($facId, $ann['target_course_id']));
        }

        if (!$allowed) {
            http_response_code(403);
            die("Access denied: Announcement restricted to specific academic group.");
        }

        $ext = pathinfo($ann['circular_image'], PATHINFO_EXTENSION);
        $dispName = "Circular_{$ann['title']}.{$ext}";
        FileService::serveDownload($ann['circular_image'], $dispName, $inline);
        break;

    case 'project':
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $proj = $stmt->fetch();

        if (!$proj || empty($proj['document_path'])) {
            http_response_code(404);
            die("Project document not found.");
        }

        if (!canAccessProject($userId, $role, $id)) {
            http_response_code(403);
            die("Access denied: You are not authorized to access this project document.");
        }

        $ext = pathinfo($proj['document_path'], PATHINFO_EXTENSION);
        $dispName = "Project_{$proj['title']}.{$ext}";
        FileService::serveDownload($proj['document_path'], $dispName, $inline);
        break;

    default:
        http_response_code(400);
        die("Unsupported document category.");
}
