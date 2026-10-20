<?php
// student/dashboard.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

// 1. Fetch Real PostgreSQL Statistics
// Enrolled Courses
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE student_id = ?");
$cStmt->execute([$studentId]);
$totalCourses = (int)$cStmt->fetchColumn();

// Assignments count & completed
$aStmt = $pdo->prepare("
    SELECT COUNT(a.id) as total_assignments,
           COUNT(s.id) as submitted_count
    FROM assignments a
    JOIN course_enrollments ce ON a.course_id = ce.course_id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE ce.student_id = ? AND a.is_published = TRUE
");
$aStmt->execute([$studentId, $studentId]);
$aStats = $aStmt->fetch();
$totalAssignments = (int)($aStats['total_assignments'] ?? 0);
$submittedAssignments = (int)($aStats['submitted_count'] ?? 0);

// Attendance Percentage
$attStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM attendance
    WHERE student_id = ?
");
$attStmt->execute([$studentId]);
$attData = $attStmt->fetch();
$totalClasses = (int)($attData['total_classes'] ?? 0);
$presentClasses = (int)($attData['present_count'] ?? 0);
$absentClasses = (int)($attData['absent_count'] ?? 0);
$attendancePct = ($totalClasses > 0) ? round(($presentClasses / $totalClasses) * 100, 1) : 100.0;

// Academic Performance Score Avg
$scoreStmt = $pdo->prepare("SELECT AVG(total_marks) FROM marks WHERE student_id = ?");
$scoreStmt->execute([$studentId]);
$avgScore = round((float)($scoreStmt->fetchColumn() ?? 0), 1);

// 2. Fetch Upcoming Assignments
$upStmt = $pdo->prepare("
    SELECT a.id, a.title, a.due_date, a.max_marks, c.name as course_name, c.code as course_code,
           s.status as submission_status, s.marks_obtained
    FROM assignments a
    JOIN course_enrollments ce ON a.course_id = ce.course_id
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE ce.student_id = ? AND a.is_published = TRUE
    ORDER BY a.due_date ASC LIMIT 5
");
$upStmt->execute([$studentId, $studentId]);
$upcomingAssignments = $upStmt->fetchAll();

// 3. Subject-wise performance data for Chart.js
$chartStmt = $pdo->prepare("
    SELECT c.code, c.name, COALESCE(m.total_marks, 0) as score
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN marks m ON c.id = m.course_id AND m.student_id = ?
    WHERE ce.student_id = ?
");
$chartStmt->execute([$studentId, $studentId]);
$performanceData = $chartStmt->fetchAll();

$subjectLabels = [];
$subjectScores = [];
foreach ($performanceData as $pd) {
    $subjectLabels[] = $pd['code'];
    $subjectScores[] = (float)$pd['score'];
}

// 4. Recent Announcements (Campus-wide or Enrolled Course specific)
$annStmt = $pdo->prepare("
    SELECT * FROM announcements 
    WHERE target_course_id IS NULL 
       OR target_course_id IN (SELECT course_id FROM course_enrollments WHERE student_id = ?)
    ORDER BY created_at DESC 
    LIMIT 3
");
$annStmt->execute([$studentId]);
$announcements = $annStmt->fetchAll();

$pageTitle = 'Student Dashboard';
$pageHeading = 'Student Portal';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Redesigned High-End Styling for Student Dashboard -->
<style>
    .dash-hero-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #1e40af 100%);
        border-radius: 24px;
        padding: 36px 40px;
        color: #ffffff;
        margin-bottom: 32px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .dash-hero-card::after {
        content: '';
        position: absolute;
        right: -50px;
        top: -50px;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(124, 58, 237, 0.3) 0%, transparent 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .hero-avatar-area {
        display: flex;
        align-items: center;
        gap: 24px;
        z-index: 2;
    }

    .hero-avatar {
        width: 76px;
        height: 76px;
        border-radius: 22px;
        background: linear-gradient(135deg, #6366f1, #a855f7);
        color: #ffffff;
        font-size: 30px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4), 0 0 0 4px rgba(255, 255, 255, 0.15);
        flex-shrink: 0;
    }

    .hero-title-group h1 {
        font-size: 30px;
        font-weight: 800;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .hero-meta-tags {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .meta-tag {
        font-size: 12px;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(10px);
        color: rgba(255, 255, 255, 0.9);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .meta-tag-highlight {
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        border-color: rgba(110, 231, 183, 0.3);
    }

    .hero-stats-quick {
        z-index: 2;
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .gpa-big-badge {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(12px);
        padding: 12px 20px;
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        text-align: center;
    }

    .gpa-num {
        font-size: 26px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
    }

    .gpa-label {
        font-size: 11px;
        font-weight: 700;
        color: #cbd5e1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    /* Redesigned 4 Stat Cards Grid */
    .dashboard-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 22px;
        margin-bottom: 32px;
    }

    .modern-stat-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 22px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .modern-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(15, 23, 42, 0.08);
        border-color: rgba(37, 99, 235, 0.3);
    }

    .mstat-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .mstat-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    }

    .mstat-val {
        font-size: 30px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1;
        margin-bottom: 4px;
        letter-spacing: -0.5px;
    }

    .mstat-lbl {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-muted);
    }

    .mstat-footer {
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Action Shortcuts Hub */
    .shortcuts-hub {
        background: #ffffff;
        border-radius: 20px;
        padding: 22px 28px;
        border: 1px solid var(--border-color);
        margin-bottom: 32px;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
    }

    .shortcuts-header {
        font-size: 14px;
        font-weight: 800;
        color: var(--text-main);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .shortcuts-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
    }

    .sc-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        text-decoration: none;
        color: var(--text-main);
        font-size: 12px;
        font-weight: 700;
        transition: all 0.25s ease;
        text-align: center;
    }

    .sc-btn:hover {
        background: var(--accent-gradient);
        color: #ffffff;
        border-color: transparent;
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
    }

    .sc-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: #ffffff;
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.2s;
    }

    .sc-btn:hover .sc-icon {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
    }

    /* Grids & Cards */
    .content-layout-grid {
        display: grid;
        grid-template-columns: 1.8fr 1.2fr;
        gap: 32px;
        margin-bottom: 32px;
    }

    .glass-box {
        background: #ffffff;
        border-radius: 22px;
        padding: 28px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 25px rgba(15, 23, 42, 0.03);
    }

    .box-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
    }

    .box-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-main);
        letter-spacing: -0.3px;
    }

    /* Redesigned Table */
    .premium-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .premium-table th {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #94a3b8;
        padding: 0 16px 10px 16px;
        text-align: left;
    }

    .premium-table tr.item-row {
        background: #f8fafc;
        transition: all 0.2s;
    }

    .premium-table tr.item-row:hover {
        background: #f1f5f9;
        transform: scale(1.005);
    }

    .premium-table td {
        padding: 14px 16px;
        font-size: 14px;
        border-top: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }

    .premium-table td:first-child {
        border-left: 1px solid #e2e8f0;
        border-top-left-radius: 14px;
        border-bottom-left-radius: 14px;
    }

    .premium-table td:last-child {
        border-right: 1px solid #e2e8f0;
        border-top-right-radius: 14px;
        border-bottom-right-radius: 14px;
    }

    /* Timeline Widget */
    .timeline-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .timeline-item {
        padding: 16px;
        background: #f8fafc;
        border-radius: 14px;
        border-left: 4px solid var(--accent);
        transition: all 0.2s;
    }

    .timeline-item:hover {
        background: #f1f5f9;
        transform: translateX(4px);
    }

    @media (max-width: 1200px) {
        .dashboard-stats-row { grid-template-columns: repeat(2, 1fr); }
        .shortcuts-grid { grid-template-columns: repeat(3, 1fr); }
        .content-layout-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .dash-hero-card {
            flex-direction: column;
            text-align: center;
            gap: 16px;
            padding: 22px 16px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        .hero-avatar-area {
            flex-direction: column;
            align-items: center;
            gap: 12px;
            width: 100%;
        }
        .hero-avatar {
            width: 60px;
            height: 60px;
            font-size: 24px;
            border-radius: 16px;
        }
        .hero-title-group {
            width: 100%;
        }
        .hero-title-group h1 {
            font-size: 21px;
            letter-spacing: -0.3px;
            line-height: 1.25;
            word-break: break-word;
        }
        .hero-meta-tags {
            justify-content: center;
            gap: 6px;
        }
        .meta-tag {
            font-size: 11px;
            padding: 4px 10px;
        }
        .hero-stats-quick {
            width: 100%;
            align-items: center;
            text-align: center;
        }
        .gpa-big-badge {
            width: 100%;
            padding: 12px 14px;
            border-radius: 14px;
        }
        .gpa-num {
            font-size: 24px;
        }
        .dashboard-stats-row {
            grid-template-columns: 1fr;
            gap: 14px;
            margin-bottom: 22px;
        }
        .modern-stat-card {
            padding: 18px 16px;
            border-radius: 18px;
        }
        .shortcuts-hub {
            padding: 18px 14px;
            border-radius: 18px;
            margin-bottom: 22px;
        }
        .shortcuts-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .sc-btn {
            padding: 12px 6px;
            font-size: 11px;
            gap: 6px;
            border-radius: 12px;
        }
        .sc-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
        }
        .sc-icon svg {
            width: 17px;
            height: 17px;
        }
        .glass-box {
            padding: 18px 14px;
            border-radius: 18px;
        }
        .box-title {
            font-size: 16px;
        }
        .content-layout-grid {
            gap: 20px;
            margin-bottom: 20px;
            width: 100%;
            max-width: 100%;
        }
    }

    /* Enforce strict containment on mobile */
    .table-responsive-wrapper {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 14px;
        margin: 0;
        padding: 0;
    }
    .table-responsive-wrapper::-webkit-scrollbar {
        height: 4px;
    }
    .table-responsive-wrapper::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    /* Mobile Assignment Card Feed */
    .mobile-assignments-list {
        display: none;
        flex-direction: column;
        gap: 12px;
        width: 100%;
    }

    .mob-assign-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: transform 0.2s, background 0.2s;
    }

    .mob-assign-card:active {
        background: #f1f5f9;
        transform: scale(0.99);
    }

    .mob-assign-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .mob-assign-title {
        font-size: 14.5px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.35;
    }

    .mob-assign-course {
        font-size: 12px;
        font-weight: 600;
        color: var(--accent);
        margin-top: 3px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .mob-assign-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 8px;
        border-top: 1px solid #edf2f7;
        font-size: 12px;
    }

    .mob-assign-due {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #475569;
        font-weight: 700;
    }

    .mob-assign-btn {
        padding: 7px 16px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--accent-gradient);
        color: #ffffff;
        box-shadow: 0 3px 10px var(--accent-glow);
    }

    @media (max-width: 768px) {
        .glass-box {
            padding: 16px 14px;
            border-radius: 18px;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
            box-sizing: border-box;
        }
        .box-header {
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .table-responsive-wrapper {
            display: none !important;
        }
        .mobile-assignments-list {
            display: flex !important;
        }
    }
</style>

<!-- Ultra-Modern Hero Banner -->
<div class="dash-hero-card">
    <div class="hero-avatar-area">
        <div class="hero-avatar">
            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
        </div>
        <div class="hero-title-group">
            <h1>Good Morning, <?php echo htmlspecialchars($student['first_name']); ?> 👋</h1>
            <div class="hero-meta-tags">
                <span class="meta-tag"><?php echo htmlspecialchars($student['student_id_number']); ?></span>
                <span class="meta-tag"><?php echo htmlspecialchars($student['program']); ?></span>
                <span class="meta-tag">Semester <?php echo $student['semester']; ?> (Sec <?php echo htmlspecialchars($student['section']); ?>)</span>
                <span class="meta-tag meta-tag-highlight">● Status: Active</span>
            </div>
        </div>
    </div>

    <div class="hero-stats-quick">
        <div class="gpa-big-badge">
            <div class="gpa-num"><?php echo $avgScore; ?>%</div>
            <div class="gpa-label">Overall GPA Score</div>
        </div>
    </div>
</div>

<!-- 4 Modern Stat Cards -->
<div class="dashboard-stats-row">
    <!-- Stat 1: Attendance -->
    <div class="modern-stat-card">
        <div class="mstat-top">
            <div class="mstat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <?php if ($attendancePct >= 75): ?>
                <span class="badge badge-graded">Optimal</span>
            <?php else: ?>
                <span class="badge badge-urgent">Shortage Warning</span>
            <?php endif; ?>
        </div>
        <div class="mstat-val"><?php echo $attendancePct; ?>%</div>
        <div class="mstat-lbl">Attendance Threshold</div>
        <div class="mstat-footer">
            <span>Present: <strong><?php echo $presentClasses; ?></strong> / <?php echo $totalClasses; ?></span>
            <a href="attendance.php" style="color: var(--accent); font-weight:700; text-decoration:none;">Log &rarr;</a>
        </div>
    </div>

    <!-- Stat 2: Assignments -->
    <div class="modern-stat-card">
        <div class="mstat-top">
            <div class="mstat-icon" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <span class="badge badge-submitted"><?php echo $totalAssignments - $submittedAssignments; ?> Pending</span>
        </div>
        <div class="mstat-val"><?php echo $submittedAssignments; ?> / <?php echo $totalAssignments; ?></div>
        <div class="mstat-lbl">Assignments Submitted</div>
        <div class="mstat-footer">
            <span>Completion Rate</span>
            <strong><?php echo ($totalAssignments > 0) ? round(($submittedAssignments / $totalAssignments) * 100) : 100; ?>%</strong>
        </div>
    </div>

    <!-- Stat 3: Enrolled Courses -->
    <div class="modern-stat-card">
        <div class="mstat-top">
            <div class="mstat-icon" style="background: linear-gradient(135deg, #7c3aed, #6d28d9);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            </div>
            <span class="badge badge-submitted">Sem <?php echo $student['semester']; ?></span>
        </div>
        <div class="mstat-val"><?php echo $totalCourses; ?></div>
        <div class="mstat-lbl">Active Enrolled Courses</div>
        <div class="mstat-footer">
            <span>Academic Load</span>
            <a href="courses.php" style="color: var(--accent); font-weight:700; text-decoration:none;">View Syllabus &rarr;</a>
        </div>
    </div>

    <!-- Stat 4: Average Score -->
    <div class="modern-stat-card">
        <div class="mstat-top">
            <div class="mstat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
            </div>
            <span class="badge badge-graded">Top Performer</span>
        </div>
        <div class="mstat-val"><?php echo $avgScore; ?>%</div>
        <div class="mstat-lbl">Average Score</div>
        <div class="mstat-footer">
            <span>Grade Standing</span>
            <a href="results.php" style="color: var(--accent); font-weight:700; text-decoration:none;">Grade Sheet &rarr;</a>
        </div>
    </div>
</div>

<!-- Quick Shortcuts Hub -->
<div class="shortcuts-hub">
    <div class="shortcuts-header">
        <span>⚡</span> Action Shortcuts Hub
    </div>
    <div class="shortcuts-grid">
        <a href="assignments.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <span>Submit Work</span>
        </a>

        <a href="materials.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <span>Study Materials</span>
        </a>

        <a href="attendance.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <span>Attendance</span>
        </a>

        <a href="results.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <span>View Marks</span>
        </a>

        <a href="appointments.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <span>Book Meeting</span>
        </a>

        <a href="projects.php" class="sc-btn">
            <div class="sc-icon">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
            </div>
            <span>My Projects</span>
        </a>
    </div>
</div>

<!-- Main Content Layout Grid -->
<div class="content-layout-grid">
    <!-- Left Column: Upcoming Assignments & Subject Performance Chart -->
    <div style="display: flex; flex-direction: column; gap: 32px;">
        <!-- Upcoming Assignments Widget -->
        <div class="glass-box">
            <div class="box-header">
                <div class="box-title">📌 Upcoming Assignments & Deadlines</div>
                <a href="assignments.php" style="color: var(--accent); font-size: 13px; font-weight: 800; text-decoration: none;">View All &rarr;</a>
            </div>

            <?php if (empty($upcomingAssignments)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <p>🎉 All caught up! No upcoming assignments pending.</p>
                </div>
            <?php else: ?>
                <!-- Desktop / Tablet Table View -->
                <div class="table-responsive-wrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="premium-table" style="min-width: 540px;">
                        <thead>
                            <tr>
                                <th>Assignment & Subject</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcomingAssignments as $ua): ?>
                                <tr class="item-row">
                                    <td>
                                        <strong style="color: var(--text-main); font-size: 14px;"><?php echo htmlspecialchars($ua['title']); ?></strong>
                                        <div style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($ua['course_code'] . ' - ' . $ua['course_name']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; font-size: 13px;"><?php echo date('M d, H:i', strtotime($ua['due_date'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($ua['submission_status'] === 'Graded'): ?>
                                            <span class="badge badge-graded">Graded (<?php echo $ua['marks_obtained']; ?>/<?php echo $ua['max_marks']; ?>)</span>
                                        <?php elseif ($ua['submission_status'] === 'Submitted'): ?>
                                            <span class="badge badge-submitted">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="assignment_view.php?id=<?php echo $ua['id']; ?>" class="btn-qa" style="padding: 6px 12px; font-size: 12px;">Submit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Native Cards View: Fully Visible, No Scrolling Needed -->
                <div class="mobile-assignments-list">
                    <?php foreach ($upcomingAssignments as $ua): ?>
                        <div class="mob-assign-card">
                            <div class="mob-assign-top">
                                <div>
                                    <div class="mob-assign-title"><?php echo htmlspecialchars($ua['title']); ?></div>
                                    <div class="mob-assign-course">
                                        <span>📚</span>
                                        <span><?php echo htmlspecialchars($ua['course_code'] . ' · ' . $ua['course_name']); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($ua['submission_status'] === 'Graded'): ?>
                                        <span class="badge badge-graded" style="font-size: 11px;">Graded <?php echo $ua['marks_obtained']; ?>/<?php echo $ua['max_marks']; ?></span>
                                    <?php elseif ($ua['submission_status'] === 'Submitted'): ?>
                                        <span class="badge badge-submitted" style="font-size: 11px;">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending" style="font-size: 11px;">Pending</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mob-assign-meta">
                                <div class="mob-assign-due">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Due: <strong><?php echo date('M d, h:i A', strtotime($ua['due_date'])); ?></strong></span>
                                </div>
                                <a href="assignment_view.php?id=<?php echo $ua['id']; ?>" class="mob-assign-btn">
                                    <?php echo ($ua['submission_status'] === 'Submitted' || $ua['submission_status'] === 'Graded') ? 'View Submission' : 'Submit Now &rarr;'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Subject Performance Chart -->
        <div class="glass-box">
            <div class="box-header">
                <div class="box-title">📊 Subject Performance Overview (%)</div>
            </div>
            <div style="height: 260px;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Right Column: Attendance Doughnut & Announcements Feed -->
    <div style="display: flex; flex-direction: column; gap: 32px;">
        <!-- Attendance Ratio Chart -->
        <div class="glass-box">
            <div class="box-header">
                <div class="box-title">🎯 Attendance Ratio</div>
            </div>
            <div style="height: 200px; position: relative; margin-bottom: 16px;">
                <canvas id="attendanceChart"></canvas>
            </div>
            <div style="display: flex; justify-content: space-around; padding-top: 12px; border-top: 1px solid #f1f5f9; font-size: 13px;">
                <div><span style="color: #10b981;">●</span> Present: <strong><?php echo $presentClasses; ?></strong></div>
                <div><span style="color: #ef4444;">●</span> Absent: <strong><?php echo $absentClasses; ?></strong></div>
            </div>
        </div>

        <!-- Campus Bulletins -->
        <div class="glass-box">
            <div class="box-header">
                <div class="box-title">📢 Campus Announcements</div>
                <a href="announcements.php" style="color: var(--accent); font-size: 13px; font-weight: 800; text-decoration: none;">View All</a>
            </div>

            <div class="timeline-list">
                <?php foreach ($announcements as $ann): ?>
                    <div class="timeline-item">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <strong style="font-size: 14px; color: var(--text-main);"><?php echo htmlspecialchars($ann['title']); ?></strong>
                            <span style="font-size: 11px; color: var(--text-muted);"><?php echo date('M d', strtotime($ann['publish_date'])); ?></span>
                        </div>
                        <p style="font-size: 13px; color: #64748b; line-height: 1.4;">
                            <?php if (!empty($ann['content'])): ?>
                                <?php echo htmlspecialchars(substr($ann['content'], 0, 100)) . (strlen($ann['content']) > 100 ? '...' : ''); ?>
                            <?php elseif (!empty($ann['circular_image'])): ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px; color: #4f46e5; font-weight: 700;">🖼️ Circular Notice Image Attached</span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Embedded JavaScript for Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Attendance Doughnut Chart
    const attCtx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(attCtx, {
        type: 'doughnut',
        data: {
            labels: ['Present', 'Absent'],
            datasets: [{
                data: [<?php echo $presentClasses; ?>, <?php echo $absentClasses; ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            cutout: '76%'
        }
    });

    // Performance Bar Chart
    const perfCtx = document.getElementById('performanceChart').getContext('2d');
    new Chart(perfCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($subjectLabels); ?>,
            datasets: [{
                label: 'Score (%)',
                data: <?php echo json_encode($subjectScores); ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                borderColor: '#2563eb',
                borderRadius: 10,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100 }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
