<?php
// student/attendance.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

// Course-wise attendance summary
$summaryStmt = $pdo->prepare("
    SELECT c.id as course_id, c.code, c.name,
           COUNT(a.id) as total_classes,
           SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
           SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    LEFT JOIN attendance a ON c.id = a.course_id AND a.student_id = ?
    WHERE ce.student_id = ?
    GROUP BY c.id, c.code, c.name
");
$summaryStmt->execute([$studentId, $studentId]);
$attendanceSummary = $summaryStmt->fetchAll();

// Overall statistics
$overallClasses = 0;
$overallPresent = 0;
foreach ($attendanceSummary as $s) {
    $overallClasses += (int)$s['total_classes'];
    $overallPresent += (int)$s['present_count'];
}
$overallPct = ($overallClasses > 0) ? round(($overallPresent / $overallClasses) * 100, 1) : 100.0;
$threshold = 75.0; // Institutional threshold

// Detailed daily log with Course and Faculty Instructor
$logStmt = $pdo->prepare("
    SELECT a.*, c.code as course_code, c.name as course_name,
           fp.first_name as fac_fn, fp.last_name as fac_ln
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN faculty_profiles fp ON a.marked_by = fp.id
    WHERE a.student_id = ?
    ORDER BY a.date DESC, a.created_at DESC
");
$logStmt->execute([$studentId]);
$logs = $logStmt->fetchAll();

$pageTitle = 'Attendance Tracker';
$pageHeading = 'Attendance Analytics & Log';
$activeNav = 'attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .att-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #10b981 100%);
        border-radius: 24px;
        padding: 32px 40px;
        color: #ffffff;
        margin-bottom: 32px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .att-hero h2 {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .att-hero p {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.85);
    }

    .att-score-badge {
        background: rgba(255, 255, 255, 0.12);
        backdrop-filter: blur(12px);
        padding: 14px 24px;
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        text-align: center;
    }

    .att-score-num {
        font-size: 32px;
        font-weight: 800;
        color: #ffffff;
        line-height: 1;
    }

    .att-score-lbl {
        font-size: 11px;
        font-weight: 700;
        color: #6ee7b7;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 4px;
    }

    /* Warning Banner */
    .att-alert-banner {
        background: linear-gradient(135deg, #fee2e2 0%, #fef2f2 100%);
        border: 1px solid #fca5a5;
        color: #991b1b;
        padding: 20px 24px;
        border-radius: 20px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        gap: 18px;
        box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.15);
    }

    .alert-icon-wrap {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: rgba(239, 68, 68, 0.15);
        color: #dc2626;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Stat Cards Grid */
    .att-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 22px;
        margin-bottom: 32px;
    }

    .att-stat-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 22px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
        display: flex;
        align-items: center;
        gap: 18px;
        transition: transform 0.25s;
    }

    .att-stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(15, 23, 42, 0.08);
    }

    .att-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        flex-shrink: 0;
    }

    .att-stat-val {
        font-size: 26px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.1;
    }

    .att-stat-lbl {
        font-size: 13px;
        font-weight: 700;
        color: var(--text-muted);
    }

    /* Course Breakdown Cards */
    .course-breakdown-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-bottom: 32px;
    }

    .course-att-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 24px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
    }

    .catt-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .catt-code {
        font-size: 13px;
        font-weight: 800;
        color: var(--accent);
        background: rgba(37, 99, 235, 0.08);
        padding: 4px 12px;
        border-radius: 8px;
    }

    .catt-pct {
        font-size: 18px;
        font-weight: 800;
    }

    .catt-title {
        font-size: 16px;
        font-weight: 800;
        color: var(--text-main);
        margin-bottom: 14px;
    }

    .progress-bar-track {
        width: 100%;
        height: 10px;
        background: #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 14px;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .catt-footer {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
        color: #64748b;
    }

    /* Elegant Daily Attendance Activity Logs Card & Table */
    .logs-card {
        background: #ffffff;
        border-radius: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        overflow: hidden;
        margin-top: 10px;
    }

    .logs-header {
        padding: 24px 32px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border-bottom: 1px solid #edf2f7;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .logs-title-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logs-title-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        border: 1px solid #c7d2fe;
    }

    .logs-title {
        font-size: 19px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.3px;
    }

    .logs-subtitle {
        font-size: 12.5px;
        color: #64748b;
        margin-top: 2px;
    }

    .logs-controls {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .logs-search {
        position: relative;
        min-width: 240px;
    }

    .logs-search input {
        width: 100%;
        padding: 9px 14px 9px 36px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        font-size: 13px;
        outline: none;
        background: #ffffff;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .logs-search input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }

    .logs-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 13px;
        color: #94a3b8;
    }

    .logs-filter-group {
        display: flex;
        background: #f1f5f9;
        padding: 3px;
        border-radius: 10px;
        gap: 4px;
    }

    .logs-filter-btn {
        border: none;
        background: transparent;
        padding: 6px 14px;
        border-radius: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .logs-filter-btn.active {
        background: #ffffff;
        color: #4f46e5;
        box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    }

    /* Table Design */
    .logs-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .att-log-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 700px;
    }

    .att-log-table thead th {
        background: #f8fafc;
        padding: 14px 28px;
        text-align: left;
        font-size: 11.5px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        border-bottom: 1px solid #e2e8f0;
    }

    .att-log-table tbody td {
        padding: 16px 28px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 13.5px;
        color: #334155;
        vertical-align: middle;
        transition: background 0.15s ease;
    }

    .att-log-table tbody tr:hover td {
        background: #fbfcfe;
    }

    .att-log-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Date Capsule */
    .att-date-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        padding: 7px 14px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        color: #1e293b;
    }

    /* Course Tag & Instructor info */
    .att-course-cell {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .att-course-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
        font-weight: 800;
        color: #0f172a;
    }

    .att-code-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 800;
        background: rgba(79, 70, 229, 0.08);
        color: #4f46e5;
        border: 1px solid rgba(79, 70, 229, 0.15);
    }

    .att-instructor-sub {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Status Badges */
    .att-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0.3px;
    }

    .att-status-present {
        background: #ecfdf5;
        color: #059669;
        border: 1px solid #a7f3d0;
        box-shadow: 0 2px 6px rgba(16, 185, 129, 0.1);
    }

    .att-status-absent {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.1);
    }

    .att-pulse-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
    }
    .att-status-present .att-pulse-dot { background: #10b981; }
    .att-status-absent .att-pulse-dot { background: #ef4444; }

    /* Elegant Pagination Controls */
    .logs-pagination-footer {
        padding: 18px 32px;
        background: #ffffff;
        border-top: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .logs-page-info {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }
    .logs-page-info strong {
        color: #0f172a;
    }

    .logs-page-btns {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .logs-nav-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 7px 14px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        color: #334155;
        font-size: 12.5px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .logs-nav-btn:hover:not(:disabled) {
        background: #4f46e5;
        color: #ffffff;
        border-color: #4f46e5;
        box-shadow: 0 3px 10px rgba(79, 70, 229, 0.25);
        transform: translateY(-1px);
    }
    .logs-nav-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .logs-page-numbers {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .logs-num-btn {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .logs-num-btn:hover {
        border-color: #6366f1;
        color: #4f46e5;
    }
    .logs-num-btn.active {
        background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
        color: #ffffff;
        border-color: #4338ca;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
    }

    @media (max-width: 1024px) {
        .att-stats-grid { grid-template-columns: repeat(2, 1fr); }
        .course-breakdown-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .att-hero {
            padding: 24px 20px;
            border-radius: 20px;
            margin-bottom: 24px;
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
        }
        .att-hero h2 {
            font-size: 22px;
        }
        .att-score-badge {
            width: 100%;
            padding: 12px 18px;
        }
        .att-stats-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .logs-header {
            padding: 18px 20px;
            flex-direction: column;
            align-items: stretch;
        }
        .logs-controls {
            width: 100%;
        }
        .logs-search {
            width: 100%;
            min-width: 100%;
        }
        .logs-pagination-footer {
            padding: 16px 20px;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .course-att-card {
            padding: 18px 16px;
            border-radius: 18px;
        }
    }
</style>

<!-- Hero Section -->
<div class="att-hero">
    <div>
        <h2>Attendance Analytics 🎯</h2>
        <p>Monitor class attendance performance, track threshold limits, and review your daily attendance logs.</p>
    </div>

    <div class="att-score-badge">
        <div class="att-score-num"><?php echo $overallPct; ?>%</div>
        <div class="att-score-lbl">Overall Attendance Score</div>
    </div>
</div>

<!-- Warning Alert if Below 75% -->
<?php if ($overallPct < $threshold): ?>
    <div class="att-alert-banner">
        <div class="alert-icon-wrap">
            <svg width="26" height="26" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <strong style="font-size: 16px; display: block; margin-bottom: 2px;">Low Attendance Warning Alert!</strong>
            <div style="font-size: 14px;">Your current overall attendance (<?php echo $overallPct; ?>%) is below the mandatory institutional requirement of <?php echo $threshold; ?>%. Please ensure consistent attendance in remaining lectures.</div>
        </div>
    </div>
<?php endif; ?>

<!-- 4 Stat Metric Cards -->
<div class="att-stats-grid">
    <div class="att-stat-card">
        <div class="att-stat-icon" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div>
            <div class="att-stat-val"><?php echo $overallClasses; ?></div>
            <div class="att-stat-lbl">Total Classes</div>
        </div>
    </div>

    <div class="att-stat-card">
        <div class="att-stat-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <div>
            <div class="att-stat-val"><?php echo $overallPresent; ?></div>
            <div class="att-stat-lbl">Present Attended</div>
        </div>
    </div>

    <div class="att-stat-card">
        <div class="att-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </div>
        <div>
            <div class="att-stat-val"><?php echo $overallClasses - $overallPresent; ?></div>
            <div class="att-stat-lbl">Absences</div>
        </div>
    </div>

    <div class="att-stat-card">
        <div class="att-stat-icon" style="background: linear-gradient(135deg, #7c3aed, #6d28d9);">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        </div>
        <div>
            <div class="att-stat-val"><?php echo $overallPct; ?>%</div>
            <div class="att-stat-lbl">Overall Ratio</div>
        </div>
    </div>
</div>

<!-- Course Breakdown Grid -->
<h3 style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 20px;">Course-wise Attendance Breakdown</h3>
<div class="course-breakdown-grid">
    <?php foreach ($attendanceSummary as $c): ?>
        <?php 
            $cTotal = (int)$c['total_classes'];
            $cPresent = (int)$c['present_count'];
            $cPct = ($cTotal > 0) ? round(($cPresent / $cTotal) * 100, 1) : 100.0;
            $fillColor = ($cPct >= $threshold) ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #ef4444, #dc2626)';
            $pctColor = ($cPct >= $threshold) ? '#10b981' : '#ef4444';
        ?>
        <div class="course-att-card">
            <div class="catt-header">
                <span class="catt-code"><?php echo htmlspecialchars($c['code']); ?></span>
                <span class="catt-pct" style="color: <?php echo $pctColor; ?>;"><?php echo $cPct; ?>%</span>
            </div>

            <div class="catt-title"><?php echo htmlspecialchars($c['name']); ?></div>

            <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width: <?php echo $cPct; ?>%; background: <?php echo $fillColor; ?>;"></div>
            </div>

            <div class="catt-footer">
                <span>Present: <strong><?php echo $cPresent; ?></strong> classes</span>
                <span>Absent: <strong><?php echo $cTotal - $cPresent; ?></strong> classes</span>
                <span>Total: <strong><?php echo $cTotal; ?></strong> held</span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Daily Attendance Activity Logs Card -->
<div class="logs-card">
    <div class="logs-header">
        <div class="logs-title-wrap">
            <div class="logs-title-icon">📊</div>
            <div>
                <h3 class="logs-title">Daily Attendance Activity Logs</h3>
                <div class="logs-subtitle">Complete chronological record of verified classroom attendance sessions</div>
            </div>
        </div>

        <div class="logs-controls">
            <!-- Search Filter -->
            <div class="logs-search">
                <span class="logs-search-icon">🔍</span>
                <input type="text" id="attLogSearch" placeholder="Filter by course, code, or date..." onkeyup="filterAttLogs()">
            </div>

            <!-- Status Tab Pills -->
            <div class="logs-filter-group">
                <button type="button" class="logs-filter-btn active" onclick="filterAttStatus('all', this)">All (<?php echo count($logs); ?>)</button>
                <button type="button" class="logs-filter-btn" onclick="filterAttStatus('present', this)">Present</button>
                <button type="button" class="logs-filter-btn" onclick="filterAttStatus('absent', this)">Absent</button>
            </div>
        </div>
    </div>

    <div class="logs-table-wrap">
        <table class="att-log-table" id="attLogsTable">
            <thead>
                <tr>
                    <th style="width: 25%;">Session Date</th>
                    <th style="width: 45%;">Course & Allocation</th>
                    <th style="width: 15%;">Verified By</th>
                    <th style="width: 15%; text-align: right; padding-right: 32px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #94a3b8; padding: 48px;">
                            <div style="font-size: 38px; margin-bottom: 8px;">📭</div>
                            <div style="font-weight: 700; color: #475569; font-size: 15px;">No Attendance Logs Found</div>
                            <div style="font-size: 13px; margin-top: 4px;">There are no classroom attendance sessions logged for your account yet.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): 
                        $statusClass = ($l['status'] === 'Present') ? 'present' : 'absent';
                        $dateFormatted = date('M d, Y', strtotime($l['date']));
                        $dayOfWeek = date('l', strtotime($l['date']));
                        $instructor = (!empty($l['fac_fn']) || !empty($l['fac_ln'])) 
                            ? ('Prof. ' . trim(($l['fac_fn'] ?? '') . ' ' . ($l['fac_ln'] ?? ''))) 
                            : 'Course Faculty';
                        $searchMetadata = strtolower($dateFormatted . ' ' . $dayOfWeek . ' ' . $l['course_code'] . ' ' . $l['course_name'] . ' ' . $l['status'] . ' ' . $instructor);
                    ?>
                        <tr class="att-row" data-status="<?php echo $statusClass; ?>" data-search="<?php echo htmlspecialchars($searchMetadata); ?>">
                            <td>
                                <div class="att-date-badge">
                                    <span>📅</span>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;"><?php echo $dateFormatted; ?></div>
                                        <div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo $dayOfWeek; ?></div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="att-course-cell">
                                    <div class="att-course-tag">
                                        <span class="att-code-pill"><?php echo htmlspecialchars($l['course_code']); ?></span>
                                        <span><?php echo htmlspecialchars($l['course_name']); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div class="att-instructor-sub">
                                    <span>👨‍🏫</span>
                                    <span style="font-weight: 600; color: #475569; font-size: 12.5px;"><?php echo htmlspecialchars($instructor); ?></span>
                                </div>
                            </td>

                            <td style="text-align: right; padding-right: 32px;">
                                <?php if ($l['status'] === 'Present'): ?>
                                    <span class="att-status-pill att-status-present">
                                        <span class="att-pulse-dot"></span> Present
                                    </span>
                                <?php else: ?>
                                    <span class="att-status-pill att-status-absent">
                                        <span class="att-pulse-dot"></span> Absent
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer Bar (5 records per page) -->
    <div class="logs-pagination-footer" id="logsPaginationBar">
        <div class="logs-page-info">
            Showing <strong id="showingStart">1</strong> to <strong id="showingEnd">5</strong> of <strong id="showingTotal"><?php echo count($logs); ?></strong> logs
        </div>

        <div class="logs-page-btns">
            <button type="button" class="logs-nav-btn" id="prevPageBtn" onclick="changeAttPage(-1)">
                &larr; Previous
            </button>
            <div class="logs-page-numbers" id="logsPageNumbers">
                <!-- Page numbers rendered dynamically -->
            </div>
            <button type="button" class="logs-nav-btn" id="nextPageBtn" onclick="changeAttPage(1)">
                Next &rarr;
            </button>
        </div>
    </div>
</div>

<script>
let currentAttStatus = 'all';
let currentAttPage = 1;
const recordsPerPage = 5;

function filterAttStatus(status, btn) {
    currentAttStatus = status;
    document.querySelectorAll('.logs-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentAttPage = 1;
    applyAttPagination();
}

function filterAttLogs() {
    currentAttPage = 1;
    applyAttPagination();
}

function getFilteredRows() {
    const term = (document.getElementById('attLogSearch')?.value || '').toLowerCase().trim();
    const rows = Array.from(document.querySelectorAll('.att-row'));

    return rows.filter(row => {
        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search');

        const matchesStatus = (currentAttStatus === 'all' || rowStatus === currentAttStatus);
        const matchesTerm = (term === '' || rowSearch.includes(term));
        return matchesStatus && matchesTerm;
    });
}

function applyAttPagination() {
    const allRows = Array.from(document.querySelectorAll('.att-row'));
    const matchedRows = getFilteredRows();
    const totalMatched = matchedRows.length;
    const totalPages = Math.ceil(totalMatched / recordsPerPage) || 1;

    if (currentAttPage > totalPages) currentAttPage = totalPages;
    if (currentAttPage < 1) currentAttPage = 1;

    const startIndex = (currentAttPage - 1) * recordsPerPage;
    const endIndex = Math.min(startIndex + recordsPerPage, totalMatched);

    // Hide all rows first
    allRows.forEach(r => r.style.display = 'none');

    // Show only active page's matched rows
    matchedRows.slice(startIndex, endIndex).forEach(r => {
        r.style.display = '';
    });

    // Update Showing count text
    const showingStart = document.getElementById('showingStart');
    const showingEnd = document.getElementById('showingEnd');
    const showingTotal = document.getElementById('showingTotal');
    if (showingStart && showingEnd && showingTotal) {
        showingStart.textContent = totalMatched === 0 ? 0 : (startIndex + 1);
        showingEnd.textContent = endIndex;
        showingTotal.textContent = totalMatched;
    }

    // Update Nav Buttons State
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    if (prevBtn) prevBtn.disabled = (currentAttPage <= 1);
    if (nextBtn) nextBtn.disabled = (currentAttPage >= totalPages || totalMatched === 0);

    // Render Page Number Buttons
    renderPageNumberButtons(totalPages);
}

function renderPageNumberButtons(totalPages) {
    const container = document.getElementById('logsPageNumbers');
    if (!container) return;
    container.innerHTML = '';

    if (totalPages <= 1) return;

    // Show up to 5 numbered buttons with sliding window
    let startPage = Math.max(1, currentAttPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'logs-num-btn' + (i === currentAttPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => {
            currentAttPage = i;
            applyAttPagination();
        };
        container.appendChild(btn);
    }
}

function changeAttPage(delta) {
    currentAttPage += delta;
    applyAttPagination();
}

// Initialise pagination on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    applyAttPagination();
});
// Also run immediately if script evaluates after DOMContentLoaded
applyAttPagination();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
