<?php
// faculty/assignment_submissions.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

$assignmentId = (int)($_GET['assignment_id'] ?? 0);

// Fetch assignment info and verify faculty ownership immediately
$aStmt = $pdo->prepare("
    SELECT a.*, c.name as course_name, c.code as course_code, d.name as department_name
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE a.id = ? AND a.faculty_id = ?
");
$aStmt->execute([$assignmentId, $facultyId]);
$assignment = $aStmt->fetch();

if (!$assignment) {
    setToast('error', 'Assignment not found or unauthorized access.');
    header('Location: assignments.php');
    exit();
}

// Handle Grading Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'])) {
    requireCSRF();
    $subId = (int)$_POST['submission_id'];
    $marks = (float)($_POST['marks_obtained'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    // Validate marks bounds
    if ($marks < 0 || $marks > (float)$assignment['max_marks']) {
        setToast('error', "Marks obtained must be between 0 and {$assignment['max_marks']}.");
        header("Location: assignment_submissions.php?assignment_id=" . $assignmentId);
        exit();
    }

    try {
        // Verify this submission actually belongs to this assignment
        $verifySub = $pdo->prepare("SELECT id FROM assignment_submissions WHERE id = ? AND assignment_id = ?");
        $verifySub->execute([$subId, $assignmentId]);
        if (!$verifySub->fetch()) {
            setToast('error', 'Unauthorized submission grading attempt.');
            header("Location: assignment_submissions.php?assignment_id=" . $assignmentId);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE assignment_submissions 
            SET marks_obtained = ?, feedback = ?, graded_at = NOW(), status = 'Graded'
            WHERE id = ? AND assignment_id = ?
        ");
        $stmt->execute([$marks, $feedback, $subId, $assignmentId]);

        // Trigger Notification to Student using parameterized query
        $subStmt = $pdo->prepare("
            SELECT s.student_id, sp.user_id, a.title 
            FROM assignment_submissions s
            JOIN student_profiles sp ON s.student_id = sp.id
            JOIN assignments a ON s.assignment_id = a.id
            WHERE s.id = ?
        ");
        $subStmt->execute([$subId]);
        $subInfo = $subStmt->fetch();

        if ($subInfo) {
            $nStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, link)
                VALUES (?, 'Assignment Graded', ?, ?)
            ");
            $nStmt->execute([
                $subInfo['user_id'],
                "Your submission for '{$subInfo['title']}' has been graded ($marks marks).",
                "student/assignment_view.php?id=" . $assignmentId
            ]);
        }

        setToast('success', 'Student submission evaluated and graded successfully!');
        logActivity($userId, "Graded submission ID $subId ($marks marks)");
        header("Location: assignment_submissions.php?assignment_id=" . $assignmentId);
        exit();
    } catch (Exception $e) {
        error_log("Grading error: " . $e->getMessage());
        setToast('error', 'Failed to save grade. Please try again.');
    }
}

// Fetch submissions with student details and enrollment info
$subStmt = $pdo->prepare("
    SELECT s.*, sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number, sp.avatar, sp.program, u.email
    FROM assignment_submissions s
    JOIN student_profiles sp ON s.student_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
");
$subStmt->execute([$assignmentId]);
$submissions = $subStmt->fetchAll();

// Statistics calculation
$totalCount = count($submissions);
$gradedCount = 0;
$pendingCount = 0;
$totalMarksSum = 0;

foreach ($submissions as $sub) {
    if ($sub['status'] === 'Graded') {
        $gradedCount++;
        $totalMarksSum += (float)$sub['marks_obtained'];
    } else {
        $pendingCount++;
    }
}
$avgScore = $gradedCount > 0 ? round($totalMarksSum / $gradedCount, 1) : 0;
$pctGraded = $totalCount > 0 ? round(($gradedCount / $totalCount) * 100) : 0;

$pageTitle = 'Evaluate Submissions - ' . htmlspecialchars($assignment['title']);
$pageHeading = 'Assignment Evaluation Hub';
$activeNav = 'assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Faculty Grading Console Styling */
.grading-container {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin-bottom: 50px;
}

/* Breadcrumb & Navigation */
.grading-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #475569;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 700;
    padding: 8px 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.back-link:hover {
    color: #4f46e5;
    border-color: #c7d2fe;
    background: #f5f3ff;
    transform: translateX(-2px);
}

/* Hero Assignment Card */
.assignment-hero-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #312e81 100%);
    border-radius: 24px;
    padding: 32px 36px;
    color: #ffffff;
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.45);
    border: 1px solid rgba(255, 255, 255, 0.08);
}
.assignment-hero-card::after {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.28) 0%, rgba(99, 102, 241, 0) 70%);
    pointer-events: none;
    border-radius: 50%;
}
.hero-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 20px;
    position: relative;
    z-index: 2;
}
.hero-tags {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.hero-pill {
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    padding: 5px 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #e0e7ff;
}
.hero-pill.course-pill {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: #ffffff;
    border: none;
}
.hero-title {
    font-size: 26px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 10px 0;
    line-height: 1.25;
    letter-spacing: -0.5px;
}
.hero-desc {
    color: #cbd5e1;
    font-size: 14px;
    line-height: 1.6;
    max-width: 820px;
    margin: 0 0 18px 0;
}
.hero-meta-grid {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #94a3b8;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 16px;
    margin-top: 8px;
}
.hero-meta-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.hero-meta-item strong {
    color: #f8fafc;
}

/* Brief Attachment Pill */
.brief-dl-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
    text-decoration: none;
    font-size: 12.5px;
    font-weight: 700;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.2s;
}
.brief-dl-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-1px);
}

/* Metric KPI Ribbon */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
}
.kpi-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
    border-color: #cbd5e1;
}
.kpi-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.kpi-icon-indigo { background: #e0e7ff; color: #4338ca; }
.kpi-icon-emerald { background: #dcfce7; color: #047857; }
.kpi-icon-amber { background: #fef3c7; color: #b45309; }
.kpi-icon-violet { background: #ede9fe; color: #6d28d9; }
.kpi-value {
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
    letter-spacing: -0.5px;
}
.kpi-label {
    font-size: 12.5px;
    color: #64748b;
    font-weight: 600;
    margin-top: 2px;
}

/* Submissions Section Header */
.section-headline-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 8px;
}
.section-headline-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
}
.submission-count-badge {
    background: #eef2ff;
    color: #4f46e5;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
}

/* Submission Card */
.sub-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 26px 28px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}
.sub-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.06);
}
.sub-card.graded-border {
    border-left: 5px solid #10b981;
}
.sub-card.pending-border {
    border-left: 5px solid #f59e0b;
}

/* Card Header: Student Avatar & Meta */
.sub-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 20px;
}
.student-profile-block {
    display: flex;
    align-items: center;
    gap: 16px;
}
.student-avatar {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    font-weight: 800;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    flex-shrink: 0;
    overflow: hidden;
}
.student-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.student-name {
    font-size: 16.5px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 2px 0;
    letter-spacing: -0.2px;
}
.student-info-sub {
    font-size: 12.5px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.student-id-tag {
    font-weight: 700;
    color: #334155;
    background: #f1f5f9;
    padding: 2px 7px;
    border-radius: 6px;
}

/* Status Pill */
.sub-status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.2px;
}
.status-pill-graded {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}
.status-pill-pending {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}
.status-pill-late {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Submission Text Content Box */
.sub-text-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 20px;
    font-size: 13.5px;
    color: #334155;
    line-height: 1.6;
}
.sub-text-title {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    color: #64748b;
    letter-spacing: 0.6px;
    margin-bottom: 6px;
}

/* Action Strip for Attached Student Files */
.file-action-strip {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}
.file-identity {
    display: flex;
    align-items: center;
    gap: 12px;
}
.file-type-icon {
    width: 42px;
    height: 42px;
    background: #ffffff;
    border-radius: 10px;
    border: 1px solid #86efac;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.file-name-meta {
    display: flex;
    flex-direction: column;
}
.file-disp-title {
    font-size: 13.5px;
    font-weight: 700;
    color: #166534;
}
.file-disp-sub {
    font-size: 12px;
    color: #15803d;
}

/* Dual Document Action Buttons */
.file-btn-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.doc-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    cursor: pointer;
}
.btn-view-tab {
    background: #ffffff;
    color: #15803d;
    border: 1px solid #86efac;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.btn-view-tab:hover {
    background: #dcfce7;
    color: #14532d;
    border-color: #4ade80;
    transform: translateY(-1px);
}
.btn-download-file {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.btn-download-file:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

/* Evaluator Grading Console Form */
.grading-form-container {
    background: #faf5ff;
    border: 1px solid #e9d5ff;
    border-radius: 16px;
    padding: 20px 22px;
    position: relative;
}
.grading-form-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    color: #7e22ce;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.grading-grid {
    display: grid;
    grid-template-columns: 180px 1fr 160px;
    gap: 16px;
    align-items: end;
}
@media (max-width: 860px) {
    .grading-grid {
        grid-template-columns: 1fr;
    }
}
.grading-input-wrap {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.grading-input-wrap label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
}
.marks-field-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.grade-input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    background: #ffffff;
    outline: none;
    transition: all 0.2s;
}
.grade-input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}
.feedback-input {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    color: #0f172a;
    background: #ffffff;
    outline: none;
    transition: all 0.2s;
}
.feedback-input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
}
.save-grade-btn {
    width: 100%;
    padding: 11px 18px;
    border-radius: 10px;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: #ffffff;
    font-weight: 800;
    font-size: 13.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.save-grade-btn:hover {
    background: linear-gradient(135deg, #6d28d9, #5b21b6);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(124, 58, 237, 0.4);
}

/* Empty State */
.empty-submissions-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px dashed #cbd5e1;
    padding: 60px 24px;
    text-align: center;
    color: #64748b;
}
.empty-submissions-icon {
    font-size: 48px;
    margin-bottom: 14px;
}
.empty-submissions-card h3 {
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 6px;
}
.empty-submissions-card p {
    font-size: 14px;
    max-width: 440px;
    margin: 0 auto;
}
</style>

<div class="grading-container">

    <!-- Top Navigation Breadcrumb -->
    <div class="grading-nav">
        <a href="assignments.php" class="back-link">
            <span>&larr;</span> Back to Assignments
        </a>
        <div style="font-size: 13px; color: #64748b; font-weight: 600;">
            Evaluator Mode &bull; Course: <strong style="color: #0f172a;"><?php echo htmlspecialchars($assignment['course_code']); ?></strong>
        </div>
    </div>

    <!-- Assignment Hero Header Card -->
    <div class="assignment-hero-card">
        <div class="hero-header-row">
            <div>
                <div class="hero-tags">
                    <span class="hero-pill course-pill"><?php echo htmlspecialchars($assignment['course_code']); ?></span>
                    <span class="hero-pill"><?php echo htmlspecialchars($assignment['course_name']); ?></span>
                    <?php if (!empty($assignment['department_name'])): ?>
                        <span class="hero-pill"><?php echo htmlspecialchars($assignment['department_name']); ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                <?php if (!empty($assignment['description'])): ?>
                    <p class="hero-desc"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($assignment['attachment'])): ?>
                <div>
                    <a href="../download.php?type=assignment&id=<?php echo $assignment['id']; ?>" class="brief-dl-btn">
                        <span>📎</span> Download Assignment Brief
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="hero-meta-grid">
            <div class="hero-meta-item">
                <span>🎯</span> Max Marks: <strong><?php echo $assignment['max_marks']; ?> pts</strong>
            </div>
            <div class="hero-meta-item">
                <span>📅</span> Due Date: <strong><?php echo date('d/m/Y , h:i:s A', strtotime($assignment['due_date'])); ?></strong>
            </div>
            <div class="hero-meta-item">
                <span>📝</span> Created: <strong><?php echo date('d/m/Y , h:i:s A', strtotime($assignment['created_at'])); ?></strong>
            </div>
        </div>
    </div>

    <!-- Submissions Statistics Ribbon -->
    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-icon-wrap kpi-icon-indigo">📥</div>
            <div>
                <div class="kpi-value"><?php echo $totalCount; ?></div>
                <div class="kpi-label">Total Submissions</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-wrap kpi-icon-emerald">✅</div>
            <div>
                <div class="kpi-value"><?php echo $gradedCount; ?> <span style="font-size: 14px; font-weight: 600; color: #10b981;">(<?php echo $pctGraded; ?>%)</span></div>
                <div class="kpi-label">Graded & Evaluated</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-wrap kpi-icon-amber">⏳</div>
            <div>
                <div class="kpi-value"><?php echo $pendingCount; ?></div>
                <div class="kpi-label">Pending Evaluation</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon-wrap kpi-icon-violet">📊</div>
            <div>
                <div class="kpi-value"><?php echo $avgScore; ?> <span style="font-size: 13px; font-weight: 600; color: #64748b;">/ <?php echo $assignment['max_marks']; ?></span></div>
                <div class="kpi-label">Average Score</div>
            </div>
        </div>
    </div>

    <!-- Submissions Section List -->
    <div class="section-headline-bar">
        <div class="section-headline-title">
            Student Submissions
            <span class="submission-count-badge"><?php echo $totalCount; ?> Submissions</span>
        </div>
    </div>

    <?php if (empty($submissions)): ?>
        <div class="empty-submissions-card">
            <div class="empty-submissions-icon">📭</div>
            <h3>No Submissions Received Yet</h3>
            <p>Enrolled students have not turned in any coursework submissions for this assignment yet. Submissions will appear here once submitted.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <?php foreach ($submissions as $sub): 
                $isGraded = ($sub['status'] === 'Graded');
                $isLate = ($sub['status'] === 'Late');
                $studentName = $sub['first_name'] . ' ' . $sub['last_name'];
                $initials = strtoupper(substr($sub['first_name'], 0, 1) . substr($sub['last_name'], 0, 1));
                $studentIdStr = !empty($sub['reg_number']) ? $sub['reg_number'] : ($sub['student_id_number'] ?? 'Student');
                $borderClass = $isGraded ? 'graded-border' : ($isLate ? 'pending-border' : 'pending-border');
                
                $hasFile = !empty($sub['file_path']);
                $fileExt = $hasFile ? strtolower(pathinfo($sub['file_path'], PATHINFO_EXTENSION)) : '';
            ?>
                <div class="sub-card <?php echo $borderClass; ?>">
                    
                    <!-- Sub Header (Student Info & Status) -->
                    <div class="sub-header">
                        <div class="student-profile-block">
                            <div class="student-avatar">
                                <?php if (!empty($sub['avatar']) && file_exists(__DIR__ . '/../' . ltrim($sub['avatar'], '/'))): ?>
                                    <img src="../<?php echo htmlspecialchars(ltrim($sub['avatar'], '/')); ?>" alt="<?php echo htmlspecialchars($studentName); ?>">
                                <?php else: ?>
                                    <span><?php echo $initials; ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="student-name"><?php echo htmlspecialchars($studentName); ?></h3>
                                <div class="student-info-sub">
                                    <span class="student-id-tag"><?php echo htmlspecialchars($studentIdStr); ?></span>
                                    <span>&bull;</span>
                                    <span><?php echo htmlspecialchars($sub['email']); ?></span>
                                    <?php if (!empty($sub['program'])): ?>
                                        <span>&bull;</span>
                                        <span><?php echo htmlspecialchars($sub['program']); ?></span>
                                    <?php endif; ?>
                                    <span>&bull;</span>
                                    <span>Submitted: <strong><?php echo date('d/m/Y , h:i:s A', strtotime($sub['submitted_at'])); ?></strong></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <?php if ($isGraded): ?>
                                <span class="sub-status-pill status-pill-graded">
                                    <span>✓</span> Graded: <?php echo $sub['marks_obtained']; ?> / <?php echo $assignment['max_marks']; ?> pts
                                </span>
                            <?php elseif ($isLate): ?>
                                <span class="sub-status-pill status-pill-late">
                                    <span>⚠️</span> Late Submission
                                </span>
                            <?php else: ?>
                                <span class="sub-status-pill status-pill-pending">
                                    <span>⏳</span> Awaiting Evaluation
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submission Notes/Content -->
                    <?php if (!empty($sub['submission_text'])): ?>
                        <div class="sub-text-box">
                            <div class="sub-text-title">Student Submission Notes</div>
                            <?php echo nl2br(htmlspecialchars($sub['submission_text'])); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Attached Student File: Download & View in New Tab -->
                    <?php if ($hasFile): ?>
                        <div class="file-action-strip">
                            <div class="file-identity">
                                <div class="file-type-icon">
                                    <?php 
                                    if ($fileExt === 'pdf') {
                                        echo '📄';
                                    } elseif (in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                        echo '🖼️';
                                    } elseif (in_array($fileExt, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                                        echo '📦';
                                    } elseif (in_array($fileExt, ['doc', 'docx'])) {
                                        echo '📝';
                                    } else {
                                        echo '📁';
                                    }
                                    ?>
                                </div>
                                <div class="file-name-meta">
                                    <span class="file-disp-title">Attached Work: Submission Document (<?php echo strtoupper($fileExt); ?>)</span>
                                    <span class="file-disp-sub">Securely verified and ready for review</span>
                                </div>
                            </div>

                            <div class="file-btn-group">
                                <!-- View in New Tab (Inline) -->
                                <a href="../download.php?type=submission&id=<?php echo $sub['id']; ?>&inline=1" target="_blank" rel="noopener noreferrer" class="doc-btn btn-view-tab" title="Open file in a new browser tab for preview">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                    View
                                </a>

                                <!-- Force Download File -->
                                <a href="../download.php?type=submission&id=<?php echo $sub['id']; ?>" class="doc-btn btn-download-file" title="Download file to local device">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                    </svg>
                                    Download File
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Evaluation & Feedback Grading Console -->
                    <div class="grading-form-container">
                        <div class="grading-form-title">
                            <span>✍️</span> Grading & Assessment Panel
                        </div>
                        <form action="assignment_submissions.php?assignment_id=<?php echo $assignmentId; ?>" method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                            
                            <div class="grading-grid">
                                <div class="grading-input-wrap">
                                    <label>Score (Out of <?php echo $assignment['max_marks']; ?>)</label>
                                    <div class="marks-field-wrap">
                                        <input type="number" 
                                               step="0.5" 
                                               min="0" 
                                               max="<?php echo $assignment['max_marks']; ?>" 
                                               name="marks_obtained" 
                                               value="<?php echo $sub['marks_obtained'] !== null ? htmlspecialchars((string)$sub['marks_obtained']) : ''; ?>" 
                                               required 
                                               placeholder="0 - <?php echo $assignment['max_marks']; ?>"
                                               class="grade-input">
                                    </div>
                                </div>

                                <div class="grading-input-wrap">
                                    <label>Faculty Feedback & Commentary</label>
                                    <input type="text" 
                                           name="feedback" 
                                           value="<?php echo htmlspecialchars($sub['feedback'] ?? ''); ?>" 
                                           placeholder="Provide constructive feedback, notes on methodology, or remarks..." 
                                           class="feedback-input">
                                </div>

                                <div>
                                    <button type="submit" class="save-grade-btn">
                                        <span>💾</span> <?php echo $isGraded ? 'Update Grade' : 'Submit Grade'; ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
