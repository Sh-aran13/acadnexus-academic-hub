<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// System Counts
$studentsCount = (int)$pdo->query("SELECT COUNT(*) FROM student_profiles")->fetchColumn();
$facultyCount = (int)$pdo->query("SELECT COUNT(*) FROM faculty_profiles")->fetchColumn();
$coursesCount = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$deptCount = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$projectsCount = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// Department Distribution Chart Data
$dStmt = $pdo->query("
    SELECT d.code, d.name, COUNT(s.id) as student_count
    FROM departments d
    LEFT JOIN student_profiles s ON d.id = s.department_id
    GROUP BY d.id, d.code, d.name
    ORDER BY student_count DESC
");
$deptDistData = $dStmt->fetchAll();

$deptLabels = [];
$deptCounts = [];
foreach ($deptDistData as $dd) {
    $deptLabels[] = $dd['code'] ?: 'General';
    $deptCounts[] = (int)$dd['student_count'];
}

// Activity Logs
$logStmt = $pdo->query("
    SELECT l.*, u.username, u.role
    FROM activity_logs l
    JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC LIMIT 8
");
$logs = $logStmt->fetchAll();

$pageTitle = 'Admin Executive Dashboard';
$pageHeading = 'Administrator Command Center';
$activeNav = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.adm-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    border-radius: 24px;
    padding: 36px 44px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.adm-hero::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -15%;
    width: 360px;
    height: 360px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 50%;
    pointer-events: none;
}
.adm-hero-badge {
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
.adm-hero h1 {
    font-size: 30px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.adm-hero p {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.85);
    margin: 0;
}

/* 5 Top Dynamic Stat Cards Grid */
.adm-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 20px;
    margin-bottom: 36px;
}
.adm-stat-card {
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
.adm-stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
}
.adm-stat-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-size: 22px;
    flex-shrink: 0;
}
.adm-stat-val {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.1;
}
.adm-stat-lbl {
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    margin-top: 2px;
}

/* Quick System Control Bar */
.adm-ops-bar {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 22px 28px;
    margin-bottom: 36px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.02);
}
.adm-ops-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.adm-ops-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
}
.adm-op-btn {
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
.adm-op-btn:hover {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    border-color: #4f46e5;
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.25);
    transform: translateY(-2px);
}

.adm-dash-grid {
    display: grid;
    grid-template-columns: 1.7fr 1fr;
    gap: 24px;
}
@media (max-width: 1024px) {
    .adm-dash-grid { grid-template-columns: 1fr; }
}

.adm-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 26px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}
.adm-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #f1f5f9;
}
.adm-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.adm-table {
    width: 100%;
    border-collapse: collapse;
}
.adm-table th {
    padding: 12px 14px;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f8fafc;
}
.adm-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.adm-table tr:last-child td {
    border-bottom: none;
}
</style>

<!-- Admin Hero Banner -->
<div class="adm-hero">
    <div>
        <div class="adm-hero-badge">🛠️ Administrator Portal • System Infrastructure</div>
        <h1>System Command Center 🛠️</h1>
        <p>Real-time campus user database management, course allocations, audit logs, and infrastructure analytics.</p>
    </div>
</div>

<!-- 5 Top Stat Cards -->
<div class="adm-stats-grid">
    <div class="adm-stat-card">
        <div class="adm-stat-icon" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
            👨‍🎓
        </div>
        <div>
            <div class="adm-stat-val"><?php echo $studentsCount; ?></div>
            <div class="adm-stat-lbl">Enrolled Students</div>
        </div>
    </div>

    <div class="adm-stat-card">
        <div class="adm-stat-icon" style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
            👨‍🏫
        </div>
        <div>
            <div class="adm-stat-val"><?php echo $facultyCount; ?></div>
            <div class="adm-stat-lbl">Faculty Staff</div>
        </div>
    </div>

    <div class="adm-stat-card">
        <div class="adm-stat-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
            📖
        </div>
        <div>
            <div class="adm-stat-val"><?php echo $coursesCount; ?></div>
            <div class="adm-stat-lbl">Active Courses</div>
        </div>
    </div>

    <div class="adm-stat-card">
        <div class="adm-stat-icon" style="background: linear-gradient(135deg, #06b6d4, #0e7490);">
            🏛️
        </div>
        <div>
            <div class="adm-stat-val"><?php echo $deptCount; ?></div>
            <div class="adm-stat-lbl">Departments</div>
        </div>
    </div>

    <div class="adm-stat-card">
        <div class="adm-stat-icon" style="background: linear-gradient(135deg, #f59e0b, #b45309);">
            💡
        </div>
        <div>
            <div class="adm-stat-val"><?php echo $projectsCount; ?></div>
            <div class="adm-stat-lbl">Allocated Projects</div>
        </div>
    </div>
</div>

<!-- Quick Administration Controls -->
<div class="adm-ops-bar">
    <div class="adm-ops-title">⚡ Quick Infrastructure Actions</div>
    <div class="adm-ops-grid">
        <a href="users.php" class="adm-op-btn">👥 User Accounts</a>
        <a href="students.php" class="adm-op-btn">👨‍🎓 Student Directory</a>
        <a href="faculty.php" class="adm-op-btn">👨‍🏫 Faculty Staff</a>
        <a href="departments.php" class="adm-op-btn">🏛️ Departments</a>
        <a href="projects.php" class="adm-op-btn">💡 Project Allocations</a>
        <a href="reports.php" class="adm-op-btn">📊 System Analytics</a>
    </div>
</div>

<!-- Main Dual Column Layout -->
<div class="adm-dash-grid">
    <!-- Left Column: Recent System Audit Logs -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h3 class="adm-card-title">Recent System Audit Logs</h3>
            <span style="font-size: 12px; font-weight: 700; color: #64748b;">Live Security Feed</span>
        </div>
        <table class="adm-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr>
                        <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($l['username']); ?></strong></td>
                        <td>
                            <span style="padding: 3px 8px; border-radius: 6px; background: #eef2ff; color: #4f46e5; font-size: 11px; font-weight: 800; text-transform: uppercase;">
                                <?php echo htmlspecialchars($l['role']); ?>
                            </span>
                        </td>
                        <td style="font-size: 13px; color: #334155;"><?php echo htmlspecialchars($l['action']); ?></td>
                        <td><code style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 11px;"><?php echo htmlspecialchars($l['ip_address']); ?></code></td>
                        <td style="font-size: 12px; color: #64748b;"><?php echo date('M d, H:i', strtotime($l['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Right Column: Students by Department Chart -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h3 class="adm-card-title">Student Enrollment Distribution</h3>
        </div>
        <div style="height: 260px; position: relative;">
            <canvas id="deptDistChart"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dCtx = document.getElementById('deptDistChart').getContext('2d');
    new Chart(dCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($deptLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($deptCounts); ?>,
                backgroundColor: ['#4f46e5', '#7c3aed', '#10b981', '#f59e0b', '#06b6d4', '#ec4899'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
