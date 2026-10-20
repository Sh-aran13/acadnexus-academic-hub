<?php
// admin/reports.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// Fetch attendance shortage students (< 75%)
$shortageStmt = $pdo->query("
    SELECT sp.student_id_number, sp.first_name, sp.last_name, sp.program,
           COUNT(a.id) as total_classes,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
    FROM student_profiles sp
    JOIN attendance a ON sp.id = a.student_id
    GROUP BY sp.id, sp.student_id_number, sp.first_name, sp.last_name, sp.program
    HAVING (SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)::float / COUNT(a.id)::float) < 0.75
");
$shortageList = $shortageStmt->fetchAll();

// Top Performing Students
$topStmt = $pdo->query("
    SELECT sp.student_id_number, sp.first_name, sp.last_name, sp.program,
           AVG(m.total_marks) as avg_score
    FROM student_profiles sp
    JOIN marks m ON sp.id = m.student_id
    GROUP BY sp.id, sp.student_id_number, sp.first_name, sp.last_name, sp.program
    ORDER BY avg_score DESC LIMIT 10
");
$topStudents = $topStmt->fetchAll();

// Department student breakdown for chart analytics
$deptBreakdown = $pdo->query("
    SELECT d.name as dept_name, d.code as dept_code, COUNT(sp.id) as student_count
    FROM departments d
    LEFT JOIN student_profiles sp ON d.id = sp.department_id
    GROUP BY d.id, d.name, d.code
    ORDER BY student_count DESC
")->fetchAll();

// Overall Stats Summary
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM student_profiles")->fetchColumn();
$totalFaculty  = (int)$pdo->query("SELECT COUNT(*) FROM faculty_profiles")->fetchColumn();
$shortageCount = count($shortageList);
$topCount      = count($topStudents);

$pageTitle = 'Institutional Reports & Analytics';
$pageHeading = 'Academic Reports & Analytics';
$activeNav = 'reports';
require_once __DIR__ . '/../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.rpt-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4338ca 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 14px 35px rgba(15, 23, 42, 0.15);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}
.rpt-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.rpt-hero-sub {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}
.rpt-stats-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.rpt-stat-chip {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 10px 18px;
    border-radius: 14px;
    text-align: center;
    min-width: 110px;
}
.rpt-stat-val {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.rpt-stat-lbl {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.75);
    text-transform: uppercase;
    font-weight: 700;
    margin-top: 2px;
}

.rpt-metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}
@media (max-width: 1024px) {
    .rpt-metrics-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .rpt-metrics-grid { grid-template-columns: 1fr; }
}

.rpt-metric-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.02);
    display: flex;
    align-items: center;
    gap: 16px;
}
.rpt-metric-ico {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.rpt-metric-title {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    margin: 0 0 2px 0;
    text-transform: uppercase;
}
.rpt-metric-num {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.rpt-charts-row {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 24px;
    margin-bottom: 28px;
}
@media (max-width: 1024px) {
    .rpt-charts-row { grid-template-columns: 1fr; }
}

.rpt-chart-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    padding: 24px;
}
.rpt-chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.rpt-chart-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.rpt-tables-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}
@media (max-width: 1024px) {
    .rpt-tables-grid { grid-template-columns: 1fr; }
}

.rpt-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.rpt-table-header {
    padding: 18px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.rpt-table-header.danger {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border-bottom: 1px solid #fecaca;
}
.rpt-table-header.success {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-bottom: 1px solid #bbf7d0;
}

.rpt-table-title {
    font-size: 16px;
    font-weight: 800;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.rpt-table-title.danger { color: #991b1b; }
.rpt-table-title.success { color: #166534; }

.rpt-table-container {
    width: 100%;
    overflow-x: auto;
}
.rpt-data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 480px;
}
.rpt-data-table th {
    background: #f8fafc;
    padding: 12px 20px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.rpt-data-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}
.rpt-data-table tr:last-child td {
    border-bottom: none;
}

.rpt-roll-badge {
    padding: 4px 9px;
    border-radius: 8px;
    background: #fee2e2;
    color: #991b1b;
    font-weight: 800;
    font-size: 11.5px;
}
.rpt-top-badge {
    padding: 4px 9px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #047857;
    font-weight: 800;
    font-size: 11.5px;
    border: 1px solid #a7f3d0;
}
.rpt-rank-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 12px;
    font-weight: 800;
    margin-right: 6px;
}
</style>

<!-- Hero Section -->
<div class="rpt-hero">
    <div>
        <h2 class="rpt-hero-title">Institutional Reports & Analytics</h2>
        <p class="rpt-hero-sub">Comprehensive audit metrics, academic performance standings, and attendance risk alerts.</p>
    </div>
    <div class="rpt-stats-row">
        <div class="rpt-stat-chip">
            <div class="rpt-stat-val"><?php echo $totalStudents; ?></div>
            <div class="rpt-stat-lbl">Enrolled</div>
        </div>
        <div class="rpt-stat-chip">
            <div class="rpt-stat-val"><?php echo $shortageCount; ?></div>
            <div class="rpt-stat-lbl">Risk Flags</div>
        </div>
        <div class="rpt-stat-chip">
            <div class="rpt-stat-val"><?php echo $topCount; ?></div>
            <div class="rpt-stat-lbl">Honors List</div>
        </div>
    </div>
</div>

<!-- Metric Widgets Row -->
<div class="rpt-metrics-grid">
    <div class="rpt-metric-card">
        <div class="rpt-metric-ico" style="background: #eef2ff; color: #4f46e5;">🎓</div>
        <div>
            <div class="rpt-metric-title">Total Enrolled</div>
            <div class="rpt-metric-num"><?php echo $totalStudents; ?> Students</div>
        </div>
    </div>

    <div class="rpt-metric-card">
        <div class="rpt-metric-ico" style="background: #f0fdf4; color: #166534;">👨‍🏫</div>
        <div>
            <div class="rpt-metric-title">Faculty Body</div>
            <div class="rpt-metric-num"><?php echo $totalFaculty; ?> Members</div>
        </div>
    </div>

    <div class="rpt-metric-card">
        <div class="rpt-metric-ico" style="background: #fef2f2; color: #dc2626;">⚠️</div>
        <div>
            <div class="rpt-metric-title">Attendance Risk</div>
            <div class="rpt-metric-num"><?php echo $shortageCount; ?> Flags</div>
        </div>
    </div>

    <div class="rpt-metric-card">
        <div class="rpt-metric-ico" style="background: #fffbeefb; color: #d97706;">🏆</div>
        <div>
            <div class="rpt-metric-title">Top Achievers</div>
            <div class="rpt-metric-num"><?php echo $topCount; ?> Scholars</div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="rpt-charts-row">
    <div class="rpt-chart-card">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title">📊 Top Academic Achievers Performance (% Marks)</h3>
            <span style="font-size: 11.5px; color: #64748b; font-weight: 600;">Ranked by Average Course Marks</span>
        </div>
        <div style="height: 260px; position: relative;">
            <canvas id="achieversChart"></canvas>
        </div>
    </div>

    <div class="rpt-chart-card">
        <div class="rpt-chart-header">
            <h3 class="rpt-chart-title">🏛️ Department Enrolment Breakdown</h3>
            <span style="font-size: 11.5px; color: #64748b; font-weight: 600;">Active Students</span>
        </div>
        <div style="height: 260px; position: relative;">
            <canvas id="deptChart"></canvas>
        </div>
    </div>
</div>

<!-- Data Tables Grid -->
<div class="rpt-tables-grid">
    <!-- Attendance Shortage Alert Table -->
    <div class="rpt-table-card">
        <div class="rpt-table-header danger">
            <h3 class="rpt-table-title danger">
                <span>⚠️</span> Attendance Shortage Alert (&lt; 75%)
            </h3>
            <span style="font-size: 12px; color: #991b1b; font-weight: 700; background: rgba(239,68,68,0.15); padding: 3px 9px; border-radius: 6px;"><?php echo $shortageCount; ?> Flagged</span>
        </div>
        <div class="rpt-table-container">
            <table class="rpt-data-table">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Present Ratio</th>
                        <th>Attendance %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shortageList)): ?>
                        <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 28px;">No attendance shortage flags reported across campus.</td></tr>
                    <?php else: ?>
                        <?php foreach ($shortageList as $sh): 
                            $pct = round(($sh['present_count'] / $sh['total_classes']) * 100, 1);
                        ?>
                            <tr>
                                <td><span class="rpt-roll-badge"><?php echo htmlspecialchars($sh['student_id_number']); ?></span></td>
                                <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($sh['first_name'] . ' ' . $sh['last_name']); ?></strong></td>
                                <td><span style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($sh['program']); ?></span></td>
                                <td><strong><?php echo $sh['present_count']; ?></strong> / <?php echo $sh['total_classes']; ?></td>
                                <td><strong style="color: #dc2626; font-size: 14px;"><?php echo $pct; ?>%</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Top Academic Achievers Table -->
    <div class="rpt-table-card">
        <div class="rpt-table-header success">
            <h3 class="rpt-table-title success">
                <span>🏆</span> Top Academic Achievers
            </h3>
            <span style="font-size: 12px; color: #166534; font-weight: 700; background: rgba(34,197,94,0.15); padding: 3px 9px; border-radius: 6px;">Top 10 Leaders</span>
        </div>
        <div class="rpt-table-container">
            <table class="rpt-data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student Name</th>
                        <th>Program</th>
                        <th>Average Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topStudents)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #94a3b8; padding: 28px;">No published course marks evaluated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topStudents as $index => $top): 
                            $rank = $index + 1;
                            $rankIcon = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : "#$rank"));
                        ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 800; color: #0f172a; font-size: 13px;"><?php echo $rankIcon; ?></span>
                                    <span class="rpt-top-badge" style="margin-left: 6px;"><?php echo htmlspecialchars($top['student_id_number']); ?></span>
                                </td>
                                <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($top['first_name'] . ' ' . $top['last_name']); ?></strong></td>
                                <td><span style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($top['program']); ?></span></td>
                                <td><strong style="color: #059669; font-size: 14.5px;"><?php echo round((float)$top['avg_score'], 1); ?>%</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Top Achievers Chart
    const achieversData = <?php 
        $names = array_map(function($t) { return $t['first_name'] . ' ' . substr($t['last_name'], 0, 1) . '.'; }, $topStudents);
        $scores = array_map(function($t) { return round((float)$t['avg_score'], 1); }, $topStudents);
        echo json_encode(['labels' => $names, 'scores' => $scores]);
    ?>;

    const ctxAchievers = document.getElementById('achieversChart');
    if (ctxAchievers && achieversData.labels.length > 0) {
        new Chart(ctxAchievers, {
            type: 'bar',
            data: {
                labels: achieversData.labels,
                datasets: [{
                    label: 'Avg Score (%)',
                    data: achieversData.scores,
                    backgroundColor: 'rgba(79, 70, 229, 0.85)',
                    borderColor: '#4f46e5',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 100, ticks: { font: { size: 11 } } },
                    x: { ticks: { font: { size: 11 } } }
                }
            }
        });
    }

    // Department Breakdown Chart
    const deptData = <?php 
        $deptNames = array_map(function($d) { return $d['dept_code'] ?: $d['dept_name']; }, $deptBreakdown);
        $deptCounts = array_map(function($d) { return (int)$d['student_count']; }, $deptBreakdown);
        echo json_encode(['labels' => $deptNames, 'counts' => $deptCounts]);
    ?>;

    const ctxDept = document.getElementById('deptChart');
    if (ctxDept && deptData.labels.length > 0) {
        new Chart(ctxDept, {
            type: 'doughnut',
            data: {
                labels: deptData.labels,
                datasets: [{
                    data: deptData.counts,
                    backgroundColor: ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12 } }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
