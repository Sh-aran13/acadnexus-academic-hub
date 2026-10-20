<?php
// faculty/dashboard.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// 1. Dynamic PostgreSQL Statistics
// Assigned Courses
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_courses WHERE faculty_id = ?");
$cStmt->execute([$facultyId]);
$coursesCount = (int)$cStmt->fetchColumn();

// Total Students Enrolled under faculty's courses
$sStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ce.student_id)
    FROM course_enrollments ce
    JOIN faculty_courses fc ON ce.course_id = fc.course_id
    WHERE fc.faculty_id = ?
");
$sStmt->execute([$facultyId]);
$totalStudents = (int)$sStmt->fetchColumn();

// Created Assignments
$aStmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE faculty_id = ?");
$aStmt->execute([$facultyId]);
$assignmentsCount = (int)$aStmt->fetchColumn();

// Pending Reviews
$pStmt = $pdo->prepare("
    SELECT COUNT(s.id)
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE a.faculty_id = ? AND s.status = 'Submitted'
");
$pStmt->execute([$facultyId]);
$pendingReviews = (int)$pStmt->fetchColumn();

// 2. Recent Submissions needing grading
$recStmt = $pdo->prepare("
    SELECT s.id as submission_id, s.submitted_at, s.status,
           sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number, sp.avatar,
           a.title as assignment_title, c.code as course_code
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN student_profiles sp ON s.student_id = sp.id
    JOIN courses c ON a.course_id = c.id
    WHERE a.faculty_id = ?
    ORDER BY s.submitted_at DESC LIMIT 5
");
$recStmt->execute([$facultyId]);
$recentSubmissions = $recStmt->fetchAll();

// 3. Class Performance Chart Data
$chartStmt = $pdo->prepare("
    SELECT c.code, AVG(COALESCE(m.total_marks, 0)) as avg_score
    FROM faculty_courses fc
    JOIN courses c ON fc.course_id = c.id
    LEFT JOIN marks m ON c.id = m.course_id
    WHERE fc.faculty_id = ?
    GROUP BY c.code, c.name
");
$chartStmt->execute([$facultyId]);
$classPerfData = $chartStmt->fetchAll();

$chartLabels = [];
$chartScores = [];
foreach ($classPerfData as $cpd) {
    $chartLabels[] = $cpd['code'];
    $chartScores[] = round((float)$cpd['avg_score'], 1);
}

$pageTitle = 'Faculty Command Center';
$pageHeading = 'Faculty Dashboard & Insights';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fac-hero {
    background: linear-gradient(135deg, #0f172a 0%, #311b92 50%, #6366f1 100%);
    border-radius: 24px;
    padding: 36px 44px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(49, 27, 146, 0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.fac-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 50%;
    pointer-events: none;
}
.fac-hero-content h1 {
    font-size: 30px;
    font-weight: 800;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}
.fac-hero-content p {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.85);
    margin: 0;
}
.fac-hero-badge {
    display: inline-block;
    padding: 6px 14px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.25);
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 12px;
}

/* Glass & Modern Cards */
.fac-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 36px;
}
.fac-stat-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
}
.fac-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}
.fac-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 24px;
    flex-shrink: 0;
}
.fac-stat-val {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.fac-stat-lbl {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin-top: 2px;
}

/* Operations Bar */
.fac-ops-bar {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 22px 28px;
    margin-bottom: 36px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.02);
}
.fac-ops-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.fac-ops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
}
.fac-op-btn {
    padding: 12px 16px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 13.5px;
    font-weight: 700;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-align: center;
}
.fac-op-btn:hover {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.25);
    transform: translateY(-2px);
}

.fac-dash-grid {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 24px;
}
@media (max-width: 1024px) {
    .fac-dash-grid { grid-template-columns: 1fr; }
}

.fac-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}
.fac-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
}
.fac-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.fac-table {
    width: 100%;
    border-collapse: collapse;
}
.fac-table th {
    padding: 12px 14px;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
    border-radius: 8px;
}
.fac-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.fac-table tr:last-child td {
    border-bottom: none;
}
</style>

<!-- Faculty Welcome Hero Banner -->
<div class="fac-hero">
    <div class="fac-hero-content">
        <div class="fac-hero-badge">🎓 Faculty Portal • <?php echo htmlspecialchars($faculty['department_name'] ?? 'Academic Department'); ?></div>
        <h1>Welcome back, Professor <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?> 👋</h1>
        <p><?php echo htmlspecialchars($faculty['designation']); ?> • Office: <?php echo htmlspecialchars($faculty['office_room'] ?? 'Main Faculty Block'); ?></p>
    </div>
</div>

<!-- 4 Key Performance Metrics -->
<div class="fac-stats-grid">
    <div class="fac-stat-card">
        <div class="fac-stat-icon" style="background: linear-gradient(135deg, #6366f1, #4338ca);">
            📖
        </div>
        <div>
            <div class="fac-stat-val"><?php echo $coursesCount; ?></div>
            <div class="fac-stat-lbl">Active Courses</div>
        </div>
    </div>

    <div class="fac-stat-card">
        <div class="fac-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            👨‍🎓
        </div>
        <div>
            <div class="fac-stat-val"><?php echo $totalStudents; ?></div>
            <div class="fac-stat-lbl">Enrolled Students</div>
        </div>
    </div>

    <div class="fac-stat-card">
        <div class="fac-stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
            📝
        </div>
        <div>
            <div class="fac-stat-val"><?php echo $assignmentsCount; ?></div>
            <div class="fac-stat-lbl">Published Assignments</div>
        </div>
    </div>

    <div class="fac-stat-card">
        <div class="fac-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #b45309);">
            ⏳
        </div>
        <div>
            <div class="fac-stat-val"><?php echo $pendingReviews; ?></div>
            <div class="fac-stat-lbl">Pending Submissions</div>
        </div>
    </div>
</div>

<!-- Quick Faculty Operations -->
<div class="fac-ops-bar">
    <div class="fac-ops-title">⚡ Quick Academic Actions</div>
    <div class="fac-ops-grid">
        <a href="assignment_create.php" class="fac-op-btn">➕ Create Assignment</a>
        <a href="materials.php" class="fac-op-btn">📂 Upload Material</a>
        <a href="attendance.php" class="fac-op-btn">📋 Mark Attendance</a>
        <a href="marks.php" class="fac-op-btn">💯 Enter Marks</a>
        <a href="announcements.php" class="fac-op-btn">📢 Post Notice</a>
        <a href="appointments.php" class="fac-op-btn">📅 Appointments</a>
    </div>
</div>

<!-- Dashboard Dual Column Layout -->
<div class="fac-dash-grid">
    <!-- Left Column: Recent Submissions -->
    <div class="fac-card">
        <div class="fac-card-header">
            <h3 class="fac-card-title">Recent Submissions Needing Review</h3>
            <a href="assignments.php" style="color: #4f46e5; font-size: 13px; font-weight: 700; text-decoration: none;">View All &rarr;</a>
        </div>

        <?php if (empty($recentSubmissions)): ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                <div style="font-size: 36px; margin-bottom: 8px;">🎉</div>
                <p style="margin: 0; font-size: 14px;">All caught up! No pending student submissions to review.</p>
            </div>
        <?php else: ?>
            <table class="fac-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Assignment</th>
                        <th>Submitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSubmissions as $sub): 
                        $roll = !empty($sub['reg_number']) ? $sub['reg_number'] : $sub['student_id_number'];
                    ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?></div>
                                <div style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($roll); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 13px; color: #1e293b;"><?php echo htmlspecialchars($sub['assignment_title']); ?></div>
                                <div style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($sub['course_code']); ?></div>
                            </td>
                            <td style="font-size: 12px; color: #64748b;"><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></td>
                            <td>
                                <a href="assignment_submissions.php?assignment_id=<?php echo $sub['submission_id']; ?>" style="padding: 6px 12px; border-radius: 8px; background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: 12px; text-decoration: none; display: inline-block;">Grade</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Right Column: Class Performance Chart -->
    <div class="fac-card">
        <div class="fac-card-header">
            <h3 class="fac-card-title">Course Average Marks (%)</h3>
        </div>
        <div style="height: 260px; position: relative;">
            <canvas id="facultyPerfChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fCtx = document.getElementById('facultyPerfChart').getContext('2d');
    new Chart(fCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Course Class Average (%)',
                data: <?php echo json_encode($chartScores); ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.85)',
                borderColor: '#4f46e5',
                borderWidth: 1,
                borderRadius: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
