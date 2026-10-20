<?php
// faculty/marks.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Get faculty courses
$cStmt = $pdo->prepare("
    SELECT c.id, c.code, c.name 
    FROM faculty_courses fc 
    JOIN courses c ON fc.course_id = c.id 
    WHERE fc.faculty_id = ?
");
$cStmt->execute([$facultyId]);
$courses = $cStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

// Handle Save Marks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $marksData = $_POST['marks'] ?? [];

    // Authorization check: faculty must be allocated to teach this course
    if (!isFacultyAssignedToCourse($facultyId, $courseId)) {
        setToast('error', 'Access denied: You are not authorized to enter marks for this course.');
        header("Location: marks.php");
        exit();
    }

    if ($courseId > 0 && !empty($marksData)) {
        try {
            $pdo->beginTransaction();

            // Fetch enrolled student IDs
            $enrolledStmt = $pdo->prepare("SELECT student_id FROM course_enrollments WHERE course_id = ?");
            $enrolledStmt->execute([$courseId]);
            $enrolledStudentIds = $enrolledStmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("
                INSERT INTO marks (course_id, student_id, internal_marks, assignment_marks, quiz_marks, mid_exam, final_exam, total_marks, grade)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON CONFLICT (course_id, student_id)
                DO UPDATE SET 
                    internal_marks = EXCLUDED.internal_marks,
                    assignment_marks = EXCLUDED.assignment_marks,
                    quiz_marks = EXCLUDED.quiz_marks,
                    mid_exam = EXCLUDED.mid_exam,
                    final_exam = EXCLUDED.final_exam,
                    total_marks = EXCLUDED.total_marks,
                    grade = EXCLUDED.grade,
                    updated_at = NOW()
            ");

            $updatedCount = 0;
            foreach ($marksData as $sId => $m) {
                $studentIdInt = (int)$sId;
                if (!in_array($studentIdInt, $enrolledStudentIds)) {
                    continue; // Skip any non-enrolled IDs
                }

                $internal = max(0, min(20, (float)($m['internal'] ?? 0)));
                $assignment = max(0, min(25, (float)($m['assignment'] ?? 0)));
                $quiz = max(0, min(10, (float)($m['quiz'] ?? 0)));
                $mid = max(0, min(30, (float)($m['mid'] ?? 0)));
                $final = max(0, min(15, (float)($m['final'] ?? 0)));
                $total = round($internal + $assignment + $quiz + $mid + $final, 2);

                $grade = 'F';
                if ($total >= 90) $grade = 'A+';
                elseif ($total >= 80) $grade = 'A';
                elseif ($total >= 70) $grade = 'B+';
                elseif ($total >= 60) $grade = 'B';
                elseif ($total >= 50) $grade = 'C';

                $stmt->execute([$courseId, $studentIdInt, $internal, $assignment, $quiz, $mid, $final, $total, $grade]);
                $updatedCount++;
            }

            $pdo->commit();
            setToast('success', "Student examination marks updated successfully ({$updatedCount} records)!");
            logActivity($userId, "Updated marks for course ID $courseId");
            header("Location: marks.php?course_id=$courseId");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Marks save error: " . $e->getMessage());
            setToast('error', 'Failed to save marks. Please check values and try again.');
        }
    }
}

// Fetch enrolled students & existing marks
$students = [];
if ($selectedCourseId > 0) {
    $sStmt = $pdo->prepare("
        SELECT sp.id as student_id, sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number,
               COALESCE(m.internal_marks, 0) as internal,
               COALESCE(m.assignment_marks, 0) as assignment,
               COALESCE(m.quiz_marks, 0) as quiz,
               COALESCE(m.mid_exam, 0) as mid,
               COALESCE(m.final_exam, 0) as final,
               COALESCE(m.total_marks, 0) as total,
               COALESCE(m.grade, 'P') as grade
        FROM course_enrollments ce
        JOIN student_profiles sp ON ce.student_id = sp.id
        LEFT JOIN marks m ON m.course_id = ce.course_id AND m.student_id = sp.id
        WHERE ce.course_id = ?
        ORDER BY sp.first_name ASC
    ");
    $sStmt->execute([$selectedCourseId]);
    $students = $sStmt->fetchAll();
}

$pageTitle = 'Enter Marks';
$pageHeading = 'Marks & Grading Ledger';
$activeNav = 'marks';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fmarks-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    padding: 24px;
    margin-bottom: 28px;
}
.fmarks-select {
    width: 100%;
    max-width: 420px;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    background: #ffffff;
}
.fmarks-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.fmarks-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.fmarks-table {
    width: 100%;
    border-collapse: collapse;
}
.fmarks-table th {
    background: #f8fafc;
    padding: 14px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.fmarks-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.fmarks-table tr:last-child td {
    border-bottom: none;
}

.fmarks-num-input {
    width: 72px;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    font-weight: 700;
    text-align: center;
    outline: none;
    transition: all 0.2s ease;
}
.fmarks-num-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.fmarks-grade-badge {
    padding: 4px 10px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #059669;
    font-weight: 800;
    font-size: 12px;
    border: 1px solid #a7f3d0;
}

.fmarks-submit-btn {
    padding: 14px 32px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 14px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    transition: all 0.2s ease;
}
.fmarks-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.45);
}
</style>

<div class="fmarks-card">
    <form action="marks.php" method="GET">
        <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Select Course Allocation *</label>
        <select name="course_id" class="fmarks-select" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($selectedCourseId === $c['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="fmarks-table-card">
    <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9;">
        <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;">💯 Student Examination Marks & Grading Ledger</h3>
    </div>

    <?php if (empty($students)): ?>
        <div style="padding: 45px; text-align: center; color: #94a3b8;">
            <div style="font-size: 38px; margin-bottom: 8px;">💯</div>
            <p style="margin: 0; font-size: 14px;">No students enrolled in this course.</p>
        </div>
    <?php else: ?>
        <form action="marks.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">

            <table class="fmarks-table">
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Internal (20)</th>
                        <th>Assignment (25)</th>
                        <th>Quiz (10)</th>
                        <th>Mid Exam (30)</th>
                        <th>Final Exam (15)</th>
                        <th>Total Score</th>
                        <th>Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): 
                        $roll = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                                <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($roll); ?></div>
                            </td>
                            <td><input type="number" step="0.5" max="20" name="marks[<?php echo $s['student_id']; ?>][internal]" value="<?php echo $s['internal']; ?>" class="fmarks-num-input"></td>
                            <td><input type="number" step="0.5" max="25" name="marks[<?php echo $s['student_id']; ?>][assignment]" value="<?php echo $s['assignment']; ?>" class="fmarks-num-input"></td>
                            <td><input type="number" step="0.5" max="10" name="marks[<?php echo $s['student_id']; ?>][quiz]" value="<?php echo $s['quiz']; ?>" class="fmarks-num-input"></td>
                            <td><input type="number" step="0.5" max="30" name="marks[<?php echo $s['student_id']; ?>][mid]" value="<?php echo $s['mid']; ?>" class="fmarks-num-input"></td>
                            <td><input type="number" step="0.5" max="15" name="marks[<?php echo $s['student_id']; ?>][final]" value="<?php echo $s['final']; ?>" class="fmarks-num-input"></td>
                            <td><strong style="font-size: 16px; color: #4f46e5;"><?php echo $s['total']; ?></strong></td>
                            <td><span class="fmarks-grade-badge"><?php echo htmlspecialchars($s['grade']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="padding: 20px 24px; text-align: right; background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <button type="submit" class="fmarks-submit-btn">💾 Save Marks & Calculate Grades</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
