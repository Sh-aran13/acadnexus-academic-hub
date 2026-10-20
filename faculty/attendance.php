<?php
// faculty/attendance.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Get faculty allocated courses
$cStmt = $pdo->prepare("
    SELECT c.id, c.code, c.name, d.code as dept_code
    FROM faculty_courses fc 
    JOIN courses c ON fc.course_id = c.id 
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE fc.faculty_id = ?
    ORDER BY c.code ASC
");
$cStmt->execute([$facultyId]);
$courses = $cStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Find active selected course details
$selectedCourse = null;
foreach ($courses as $c) {
    if ((int)$c['id'] === $selectedCourseId) {
        $selectedCourse = $c;
        break;
    }
}

// Handle Save Attendance Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $attDate = $_POST['date'] ?? date('Y-m-d');
    $attendanceData = $_POST['attendance'] ?? []; // student_id => 'Present' | 'Absent'

    // Server-side authorization check: faculty must be assigned to the course
    if (!isFacultyAssignedToCourse($facultyId, $courseId)) {
        setToast('error', 'Access denied: You are not authorized to mark attendance for this course.');
        header("Location: attendance.php");
        exit();
    }

    // Validate date format
    $d = DateTime::createFromFormat('Y-m-d', $attDate);
    if (!$d || $d->format('Y-m-d') !== $attDate) {
        setToast('error', 'Invalid attendance date specified.');
        header("Location: attendance.php?course_id=$courseId");
        exit();
    }

    if ($courseId > 0 && !empty($attendanceData)) {
        try {
            $pdo->beginTransaction();

            // Fetch enrolled student IDs for this course to ensure students are actually enrolled
            $enrolledStmt = $pdo->prepare("SELECT student_id FROM course_enrollments WHERE course_id = ?");
            $enrolledStmt->execute([$courseId]);
            $enrolledStudentIds = $enrolledStmt->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare("
                INSERT INTO attendance (course_id, student_id, date, status, marked_by)
                VALUES (?, ?, ?, ?, ?)
                ON CONFLICT (course_id, student_id, date)
                DO UPDATE SET status = EXCLUDED.status, marked_by = EXCLUDED.marked_by
            ");

            $savedCount = 0;
            foreach ($attendanceData as $sId => $status) {
                $studentIdInt = (int)$sId;
                if (!in_array($studentIdInt, $enrolledStudentIds)) {
                    continue; // Skip any non-enrolled student IDs
                }
                $statusVal = ($status === 'Absent') ? 'Absent' : 'Present';
                $stmt->execute([$courseId, $studentIdInt, $attDate, $statusVal, $facultyId]);
                $savedCount++;
            }

            $pdo->commit();
            setToast('success', "Attendance records successfully saved for {$savedCount} students!");
            logActivity($userId, "Marked attendance for course ID $courseId on $attDate");
            header("Location: attendance.php?course_id=$courseId&date=$attDate");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("Attendance save error: " . $e->getMessage());
            setToast('error', 'Failed to save attendance. Please try again.');
        }
    }
}

// Fetch enrolled students for selected course with their status for this date
$students = [];
$presentCount = 0;
$absentCount = 0;
$isAlreadyMarked = false;

if ($selectedCourseId > 0) {
    $sStmt = $pdo->prepare("
        SELECT sp.id as student_id, sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number, sp.avatar,
               u.email,
               a.status as recorded_status,
               COALESCE(a.status, 'Present') as current_status
        FROM course_enrollments ce
        JOIN student_profiles sp ON ce.student_id = sp.id
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN attendance a ON a.course_id = ce.course_id AND a.student_id = sp.id AND a.date = ?
        WHERE ce.course_id = ?
        ORDER BY sp.first_name ASC, sp.last_name ASC
    ");
    $sStmt->execute([$selectedDate, $selectedCourseId]);
    $students = $sStmt->fetchAll();

    foreach ($students as $s) {
        if (!empty($s['recorded_status'])) {
            $isAlreadyMarked = true;
        }
        if ($s['current_status'] === 'Present') {
            $presentCount++;
        } else {
            $absentCount++;
        }
    }
}

$totalStudents = count($students);
$presentRate = ($totalStudents > 0) ? round(($presentCount / $totalStudents) * 100, 1) : 0;

$pageTitle = 'Classroom Attendance Registry';
$pageHeading = 'Class Attendance Registry';
$activeNav = 'attendance';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Elegant Faculty Attendance Studio Theme */
.fatt-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding-bottom: 40px;
}

/* Hero Banner */
.fatt-hero {
    background: linear-gradient(135deg, #090d16 0%, #1e1b4b 45%, #10b981 100%);
    border-radius: 24px;
    padding: 34px 40px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.4);
    position: relative;
    overflow: hidden;
}
.fatt-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(16, 185, 129, 0.25) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.fatt-hero-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    position: relative;
    z-index: 1;
}
.fatt-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.fatt-hero-sub {
    font-size: 14px;
    color: #c7d2fe;
    margin: 0;
    max-width: 600px;
    line-height: 1.5;
}

/* Quick Metrics Row in Hero */
.fatt-metrics {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.fatt-metric-chip {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fatt-metric-chip strong {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.fatt-metric-chip span {
    font-size: 12.5px;
    color: #cbd5e1;
    font-weight: 600;
}

/* Filter Card */
.fatt-filter-box {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.035);
    padding: 24px 28px;
    margin-bottom: 26px;
}
.fatt-filter-grid {
    display: grid;
    grid-template-columns: 2fr 1.2fr auto;
    gap: 20px;
    align-items: flex-end;
}
@media (max-width: 860px) {
    .fatt-filter-grid { grid-template-columns: 1fr; }
}

.fatt-field-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 7px;
}
.fatt-select, .fatt-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    background: #ffffff;
    color: #0f172a;
    transition: all 0.2s ease;
    box-sizing: border-box;
    font-family: inherit;
}
.fatt-select:focus, .fatt-input:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
}

.fatt-refresh-btn {
    padding: 12px 20px;
    border-radius: 12px;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-weight: 700;
    font-size: 13.5px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 44px;
    box-sizing: border-box;
}
.fatt-refresh-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Roster Main Card */
.fatt-roster-box {
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);
    overflow: hidden;
}

.fatt-roster-header {
    padding: 22px 30px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-bottom: 1px solid #edf2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.fatt-roster-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.fatt-roster-title-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    border: 1px solid #a7f3d0;
}
.fatt-roster-title {
    font-size: 19px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    letter-spacing: -0.3px;
}
.fatt-roster-sub {
    font-size: 12.5px;
    color: #64748b;
    margin-top: 2px;
}

/* Bulk Selection Controls */
.fatt-bulk-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.fatt-bulk-btn {
    padding: 7px 14px;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.fatt-bulk-present {
    background: #ecfdf5;
    color: #059669;
    border-color: #a7f3d0;
}
.fatt-bulk-present:hover {
    background: #d1fae5;
}
.fatt-bulk-absent {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}
.fatt-bulk-absent:hover {
    background: #fee2e2;
}

/* Search Box */
.fatt-search-box {
    position: relative;
    min-width: 200px;
}
.fatt-search-box input {
    width: 100%;
    padding: 7px 12px 7px 32px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 12.5px;
    outline: none;
    box-sizing: border-box;
    transition: all 0.2s ease;
}
.fatt-search-box input:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}
.fatt-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #94a3b8;
}

/* Table Design */
.fatt-table-container {
    width: 100%;
    overflow-x: auto;
}
.fatt-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 650px;
}
.fatt-table thead th {
    background: #f8fafc;
    padding: 14px 24px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #e2e8f0;
}
.fatt-table tbody td {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
    transition: background 0.15s ease;
}
.fatt-table tbody tr:hover td {
    background: #fbfcfe;
}
.fatt-table tbody tr:last-child td {
    border-bottom: none;
}

/* Student Profile Cell */
.fatt-student-cell {
    display: flex;
    align-items: center;
    gap: 14px;
}
.fatt-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    object-fit: cover;
    background: #e2e8f0;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    flex-shrink: 0;
}
.fatt-avatar-placeholder {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 15px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(79, 70, 229, 0.2);
}

.fatt-student-info strong {
    font-size: 14.5px;
    color: #0f172a;
    display: block;
    line-height: 1.3;
}
.fatt-student-info span {
    font-size: 12px;
    color: #64748b;
}

/* ID Chip */
.fatt-id-chip {
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    font-size: 12px;
    display: inline-block;
    border: 1px solid #c7d2fe;
}

/* Segmented Toggle Control */
.fatt-toggle-group {
    display: inline-flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
    gap: 4px;
}
.fatt-toggle-item {
    cursor: pointer;
    position: relative;
    margin: 0;
}
.fatt-toggle-item input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.fatt-toggle-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 9px;
    font-size: 12.5px;
    font-weight: 800;
    color: #64748b;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    user-select: none;
}
.fatt-toggle-item input[value="Present"]:checked + .fatt-toggle-pill {
    background: #10b981;
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.35);
}
.fatt-toggle-item input[value="Absent"]:checked + .fatt-toggle-pill {
    background: #ef4444;
    color: #ffffff;
    box-shadow: 0 3px 10px rgba(239, 68, 68, 0.35);
}

/* Bottom Floating/Fixed Bar */
.fatt-bottom-bar {
    padding: 22px 30px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.fatt-bottom-info {
    font-size: 13.5px;
    color: #475569;
}
.fatt-save-btn {
    padding: 13px 34px;
    border-radius: 12px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    font-weight: 800;
    font-size: 14.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.fatt-save-btn:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
    transform: translateY(-1px);
}
</style>

<div class="fatt-wrapper">
    <!-- Hero Banner with Live Registry Overview -->
    <div class="fatt-hero">
        <div class="fatt-hero-top">
            <div>
                <h1 class="fatt-hero-title">Classroom Attendance Registry ✍️</h1>
                <p class="fatt-hero-sub">
                    Manage real-time classroom attendance for <?php echo !empty($selectedCourse) ? htmlspecialchars($selectedCourse['code'] . ' (' . $selectedCourse['name'] . ')') : 'allocated courses'; ?>. Toggle attendance status individually or in bulk.
                </p>
            </div>
        </div>

        <div class="fatt-metrics">
            <div class="fatt-metric-chip">
                <strong><?php echo $totalStudents; ?></strong>
                <span>Enrolled Students</span>
            </div>
            <div class="fatt-metric-chip">
                <strong style="color: #34d399;" id="heroPresentCount"><?php echo $presentCount; ?></strong>
                <span>Present</span>
            </div>
            <div class="fatt-metric-chip">
                <strong style="color: #f87171;" id="heroAbsentCount"><?php echo $absentCount; ?></strong>
                <span>Absent</span>
            </div>
            <div class="fatt-metric-chip">
                <strong style="color: #60a5fa;" id="heroRateCount"><?php echo $presentRate; ?>%</strong>
                <span>Attendance Turnout</span>
            </div>
            <div class="fatt-metric-chip">
                <span style="font-weight: 800; color: <?php echo $isAlreadyMarked ? '#34d399' : '#fbbf24'; ?>;">
                    <?php echo $isAlreadyMarked ? '✅ Record Verified' : '⏳ Ready to Submit'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div class="fatt-filter-box">
        <form action="attendance.php" method="GET" class="fatt-filter-grid">
            <div>
                <label class="fatt-field-label">Select Allocated Course *</label>
                <select name="course_id" class="fatt-select" onchange="this.form.submit()">
                    <?php if (empty($courses)): ?>
                        <option value="">No allocated courses found</option>
                    <?php else: ?>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($selectedCourseId === (int)$c['id']) ? 'selected' : ''; ?>>
                                [<?php echo htmlspecialchars($c['code']); ?>] <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label class="fatt-field-label">Attendance Registry Date *</label>
                <input type="date" name="date" class="fatt-input" value="<?php echo htmlspecialchars($selectedDate); ?>" onchange="this.form.submit()">
            </div>

            <div>
                <button type="submit" class="fatt-refresh-btn">
                    <span>🔄 Refresh Roster</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Student Attendance Roster Box -->
    <div class="fatt-roster-box">
        <div class="fatt-roster-header">
            <div class="fatt-roster-title-wrap">
                <div class="fatt-roster-title-icon">📋</div>
                <div>
                    <h2 class="fatt-roster-title">
                        Class Roster (<?php echo date('l, M d, Y', strtotime($selectedDate)); ?>)
                    </h2>
                    <div class="fatt-roster-sub">
                        <?php echo $totalStudents; ?> students enrolled in <?php echo htmlspecialchars($selectedCourse['code'] ?? 'Course'); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($students)): ?>
                <div class="fatt-bulk-actions">
                    <!-- Quick Live Search -->
                    <div class="fatt-search-box">
                        <span class="fatt-search-icon">🔍</span>
                        <input type="text" id="rosterSearchInput" placeholder="Filter by name / ID..." onkeyup="filterRosterStudents()">
                    </div>

                    <!-- Bulk Switchers -->
                    <button type="button" class="fatt-bulk-btn fatt-bulk-present" onclick="setAllAttendance('Present')">
                        ✅ Mark All Present
                    </button>
                    <button type="button" class="fatt-bulk-btn fatt-bulk-absent" onclick="setAllAttendance('Absent')">
                        ❌ Mark All Absent
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($students)): ?>
            <div style="padding: 60px 24px; text-align: center; color: #94a3b8;">
                <div style="font-size: 46px; margin-bottom: 12px;">👨‍🎓</div>
                <h3 style="font-size: 17px; font-weight: 800; color: #475569; margin: 0 0 6px 0;">No Students Enrolled</h3>
                <p style="margin: 0; font-size: 14px;">There are no student enrollments registered for this course section.</p>
            </div>
        <?php else: ?>
            <form action="attendance.php" method="POST" id="attendanceSaveForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">

                <div class="fatt-table-container">
                    <table class="fatt-table" id="rosterTable">
                        <thead>
                            <tr>
                                <th style="width: 60px; text-align: center;">#</th>
                                <th style="width: 42%;">Student Information</th>
                                <th style="width: 25%;">ID & Roll Code</th>
                                <th style="width: 28%; text-align: right; padding-right: 32px;">Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $idx => $s): 
                                $studentName = trim($s['first_name'] . ' ' . $s['last_name']);
                                $initials = strtoupper(substr($s['first_name'] ?? 'S', 0, 1) . substr($s['last_name'] ?? '', 0, 1));
                                $rollCode = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                                $searchData = strtolower($studentName . ' ' . $rollCode . ' ' . ($s['student_id_number'] ?? ''));
                            ?>
                                <tr class="roster-row" data-search="<?php echo htmlspecialchars($searchData); ?>">
                                    <td style="font-weight: 700; color: #64748b; text-align: center;">
                                        <?php echo $idx + 1; ?>
                                    </td>

                                    <td>
                                        <div class="fatt-student-cell">
                                            <?php if (!empty($s['avatar'])): ?>
                                                <img src="<?php echo '../' . htmlspecialchars($s['avatar']); ?>" alt="Student Photo" class="fatt-avatar">
                                            <?php else: ?>
                                                <div class="fatt-avatar-placeholder"><?php echo $initials; ?></div>
                                            <?php endif; ?>
                                            <div class="fatt-student-info">
                                                <strong><?php echo htmlspecialchars($studentName); ?></strong>
                                                <span><?php echo htmlspecialchars($s['email'] ?? ''); ?></span>
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="fatt-id-chip">
                                            <?php echo htmlspecialchars($rollCode); ?>
                                        </span>
                                    </td>

                                    <td style="text-align: right; padding-right: 32px;">
                                        <div class="fatt-toggle-group">
                                            <label class="fatt-toggle-item">
                                                <input type="radio" 
                                                       name="attendance[<?php echo $s['student_id']; ?>]" 
                                                       value="Present" 
                                                       class="att-radio-input"
                                                       <?php echo ($s['current_status'] === 'Present') ? 'checked' : ''; ?>
                                                       onchange="recalculateMetrics()">
                                                <span class="fatt-toggle-pill">
                                                    ✓ Present
                                                </span>
                                            </label>

                                            <label class="fatt-toggle-item">
                                                <input type="radio" 
                                                       name="attendance[<?php echo $s['student_id']; ?>]" 
                                                       value="Absent" 
                                                       class="att-radio-input"
                                                       <?php echo ($s['current_status'] === 'Absent') ? 'checked' : ''; ?>
                                                       onchange="recalculateMetrics()">
                                                <span class="fatt-toggle-pill">
                                                    ✗ Absent
                                                </span>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="fatt-bottom-bar">
                    <div class="fatt-bottom-info">
                        Ready to finalize attendance for <strong><?php echo count($students); ?></strong> students on <strong><?php echo date('M d, Y', strtotime($selectedDate)); ?></strong>.
                    </div>

                    <button type="submit" class="fatt-save-btn">
                        <span>💾 Save Attendance Records</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// Filter Roster by Search Term
function filterRosterStudents() {
    const term = (document.getElementById('rosterSearchInput')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.roster-row');

    rows.forEach(row => {
        const search = row.getAttribute('data-search') || '';
        if (term === '' || search.includes(term)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Mark All Students as Present or Absent
function setAllAttendance(status) {
    const inputs = document.querySelectorAll(`.att-radio-input[value="${status}"]`);
    inputs.forEach(input => {
        input.checked = true;
    });
    recalculateMetrics();
}

// Dynamically update the quick metrics in the hero bar
function recalculateMetrics() {
    const allCheckedPresent = document.querySelectorAll('.att-radio-input[value="Present"]:checked').length;
    const allCheckedAbsent = document.querySelectorAll('.att-radio-input[value="Absent"]:checked').length;
    const total = allCheckedPresent + allCheckedAbsent;

    const presentEl = document.getElementById('heroPresentCount');
    const absentEl = document.getElementById('heroAbsentCount');
    const rateEl = document.getElementById('heroRateCount');

    if (presentEl) presentEl.textContent = allCheckedPresent;
    if (absentEl) absentEl.textContent = allCheckedAbsent;
    if (rateEl && total > 0) {
        const rate = ((allCheckedPresent / total) * 100).toFixed(1);
        rateEl.textContent = rate + '%';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
