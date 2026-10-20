<?php
// faculty/course_create.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];
$facultyDeptId = $faculty['department_id'];

// Get faculty department info
$deptStmt = $pdo->prepare("SELECT id, name, code FROM departments WHERE id = ?");
$deptStmt->execute([$facultyDeptId ?: 0]);
$facultyDept = $deptStmt->fetch();
$deptName = $facultyDept['name'] ?? ($faculty['department_name'] ?? 'Faculty Department');
$facFullName = 'Prof. ' . $faculty['first_name'] . ' ' . $faculty['last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $credits = (int)($_POST['credits'] ?? 3);
    $semester = (int)($_POST['semester'] ?? 1);
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name) || $credits <= 0 || $semester <= 0) {
        setToast('error', 'Please fill in all required course fields.');
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert course bound to faculty's department_id
            $insCourse = $pdo->prepare("
                INSERT INTO courses (code, name, department_id, credits, semester, description)
                VALUES (?, ?, ?, ?, ?, ?)
                RETURNING id
            ");
            $insCourse->execute([$code, $name, $facultyDeptId, $credits, $semester, $description]);
            $newCourseId = $insCourse->fetchColumn();

            // 2. Allocate created course to faculty in faculty_courses
            $insFacCourse = $pdo->prepare("
                INSERT INTO faculty_courses (faculty_id, course_id, semester, academic_year)
                VALUES (?, ?, ?, '2025-2026')
                ON CONFLICT DO NOTHING
            ");
            $insFacCourse->execute([$facultyId, $newCourseId, $semester]);

            // 3. Notify all students belonging to the same department
            if ($facultyDeptId > 0) {
                $studentsStmt = $pdo->prepare("
                    SELECT user_id 
                    FROM student_profiles 
                    WHERE department_id = ?
                ");
                $studentsStmt->execute([$facultyDeptId]);
                $deptStudents = $studentsStmt->fetchAll();

                $notifTitle = "New Department Course Created: $code";
                $notifMsg = "New course '$name' ($code) has been created by $facFullName in your department ($deptName). You can now enroll in this course!";
                $notifLink = "student/courses.php";

                $insNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, link, is_read)
                    VALUES (?, ?, ?, ?, FALSE)
                ");

                foreach ($deptStudents as $stu) {
                    $insNotif->execute([$stu['user_id'], $notifTitle, $notifMsg, $notifLink]);
                }
            }

            $pdo->commit();
            setToast('success', "Course '$name' created successfully! Department students have been notified.");
            logActivity($userId, "Created department course: $code - $name");
            header("Location: courses.php");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            setToast('error', 'Failed to create course: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'Create Department Course';
$pageHeading = 'Create New Course Offering';
$activeNav = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fcc-card {
    max-width: 820px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    padding: 36px 40px;
}
.fcc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 20px;
    margin-bottom: 28px;
    border-bottom: 1px solid #f1f5f9;
}
.fcc-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.fcc-form-group {
    margin-bottom: 22px;
}
.fcc-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 8px;
}
.fcc-input, .fcc-select, .fcc-textarea {
    width: 100%;
    padding: 13px 18px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
    color: #0f172a;
}
.fcc-input:focus, .fcc-select:focus, .fcc-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.fcc-dept-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 12px;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 800;
    font-size: 13.5px;
    margin-bottom: 24px;
}
.fcc-submit-btn {
    width: 100%;
    padding: 15px;
    border-radius: 14px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 15px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    transition: all 0.2s ease;
}
.fcc-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 8px 22px rgba(79, 70, 229, 0.45);
    transform: translateY(-2px);
}
</style>

<div class="fcc-card">
    <div class="fcc-header">
        <h3 class="fcc-title">✨ Create New Department Course</h3>
        <a href="courses.php" style="color: #64748b; font-size: 13px; font-weight: 700; text-decoration: none;">&larr; Back to Courses</a>
    </div>

    <div class="fcc-dept-badge">
        <span>🏢 Department Scope:</span>
        <strong><?php echo htmlspecialchars($deptName); ?></strong>
    </div>

    <form action="course_create.php" method="POST">
        <?php echo csrfField(); ?>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;" class="fcc-form-group">
            <div>
                <label class="fcc-label">Course Code *</label>
                <input type="text" name="code" class="fcc-input" required placeholder="e.g. CS501 / IT302">
            </div>
            <div>
                <label class="fcc-label">Course Full Title *</label>
                <input type="text" name="name" class="fcc-input" required placeholder="e.g. Advanced Cloud Computing & Security">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="fcc-form-group">
            <div>
                <label class="fcc-label">Academic Credits *</label>
                <input type="number" min="1" max="10" name="credits" value="4" class="fcc-input" required>
            </div>
            <div>
                <label class="fcc-label">Academic Semester *</label>
                <input type="number" min="1" max="10" name="semester" value="1" class="fcc-input" required>
            </div>
        </div>

        <div class="fcc-form-group">
            <label class="fcc-label">Course Overview & Syllabus Summary</label>
            <textarea name="description" rows="5" class="fcc-textarea" placeholder="Detail course objectives, topics covered, and prerequisite knowledge..."></textarea>
        </div>

        <button type="submit" class="fcc-submit-btn">📢 Create Course & Notify Department Students</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
