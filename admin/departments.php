<?php
// admin/departments.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// Add or Delete Department Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $delDeptId = (int)($_POST['dept_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$delDeptId]);
            setToast('success', 'Department deleted successfully.');
            logActivity($_SESSION['user_id'], "Admin deleted department ID: $delDeptId");
        } catch (Exception $e) {
            setToast('error', 'Cannot delete department with active faculty, students, or courses: ' . $e->getMessage());
        }
        header('Location: departments.php');
        exit();
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name)) {
        setToast('error', 'Department code and name are required.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO departments (code, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $description]);
            setToast('success', "Department '$name' ($code) created successfully!");
            logActivity($_SESSION['user_id'], "Admin created department: $code - $name");
            header('Location: departments.php');
            exit();
        } catch (Exception $e) {
            setToast('error', 'Failed to create department: ' . $e->getMessage());
        }
    }
}

// Fetch all departments with aggregated counts
$deptStmt = $pdo->query("
    SELECT d.*, 
           COUNT(DISTINCT fp.id) as faculty_count, 
           COUNT(DISTINCT sp.id) as student_count, 
           COUNT(DISTINCT c.id) as course_count
    FROM departments d
    LEFT JOIN faculty_profiles fp ON d.id = fp.department_id
    LEFT JOIN student_profiles sp ON d.id = sp.department_id
    LEFT JOIN courses c ON d.id = c.department_id
    GROUP BY d.id, d.code, d.name, d.description
    ORDER BY d.code ASC
");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// Determine active selected department ID
$selectedDeptId = (int)($_GET['dept_id'] ?? 0);
if ($selectedDeptId <= 0 && !empty($departments)) {
    $selectedDeptId = (int)$departments[0]['id'];
}

// Fetch selected department object
$selectedDept = null;
foreach ($departments as $d) {
    if ((int)$d['id'] === $selectedDeptId) {
        $selectedDept = $d;
        break;
    }
}

// Fetch Faculty members of selected department
$deptFaculty = [];
if ($selectedDeptId > 0) {
    $fStmt = $pdo->prepare("
        SELECT fp.*, u.email, u.status 
        FROM faculty_profiles fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.department_id = ?
        ORDER BY fp.first_name ASC
    ");
    $fStmt->execute([$selectedDeptId]);
    $deptFaculty = $fStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Student profiles of selected department
$deptStudents = [];
if ($selectedDeptId > 0) {
    $sStmt = $pdo->prepare("
        SELECT sp.*, u.email, u.status 
        FROM student_profiles sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.department_id = ?
        ORDER BY sp.first_name ASC
    ");
    $sStmt->execute([$selectedDeptId]);
    $deptStudents = $sStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Courses offered by selected department
$deptCourses = [];
if ($selectedDeptId > 0) {
    $cStmt = $pdo->prepare("
        SELECT c.* 
        FROM courses c 
        WHERE c.department_id = ?
        ORDER BY c.code ASC
    ");
    $cStmt->execute([$selectedDeptId]);
    $deptCourses = $cStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aggregated campus stats
$totalDepts    = count($departments);
$totalFaculty  = array_sum(array_column($departments, 'faculty_count'));
$totalStudents = array_sum(array_column($departments, 'student_count'));

$activeTab = $_GET['tab'] ?? 'faculty';

$pageTitle = 'Department Directory Management';
$pageHeading = 'Academic Departments';
$activeNav = 'departments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.dpt-hero {
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
.dpt-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.dpt-hero-sub {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}
.dpt-stats-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.dpt-stat-chip {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 10px 18px;
    border-radius: 14px;
    text-align: center;
    min-width: 110px;
}
.dpt-stat-val {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.dpt-stat-lbl {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.75);
    text-transform: uppercase;
    font-weight: 700;
    margin-top: 2px;
}

.dpt-top-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 28px;
    margin-bottom: 32px;
    align-items: start;
}
@media (max-width: 1024px) {
    .dpt-top-grid { grid-template-columns: 1fr; }
}

.dpt-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    padding: 24px;
}
.dpt-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 18px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}

.dpt-form-group {
    margin-bottom: 16px;
}
.dpt-label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.dpt-input, .dpt-textarea {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
    font-family: inherit;
}
.dpt-input:focus, .dpt-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}

.dpt-submit-btn {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    font-weight: 800;
    font-size: 14px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transition: all 0.2s ease;
}
.dpt-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
}

/* Department Grid Cards */
.dpt-grid-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
}

.dpt-dept-card {
    background: #ffffff;
    border-radius: 18px;
    border: 2px solid #e2e8f0;
    padding: 20px;
    transition: all 0.25s ease;
    position: relative;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.dpt-dept-card:hover {
    border-color: #6366f1;
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.12);
}
.dpt-dept-card.active {
    border-color: #4f46e5;
    background: linear-gradient(135deg, #ffffff 0%, #eef2ff 100%);
    box-shadow: 0 8px 24px rgba(79, 70, 229, 0.15);
}

.dpt-badge-code {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.dpt-card-header-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.dpt-card-dept-name {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 6px 0 4px 0;
}
.dpt-card-desc {
    font-size: 12px;
    color: #64748b;
    line-height: 1.4;
    margin-bottom: 16px;
}

.dpt-card-stats {
    display: flex;
    gap: 12px;
    border-top: 1px solid #f1f5f9;
    padding-top: 12px;
    font-size: 12px;
    color: #475569;
    font-weight: 700;
}
.dpt-card-stat-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Directory Section */
.dpt-directory-card {
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-top: 10px;
}
.dpt-dir-header {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    padding: 24px 32px;
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.dpt-dir-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px 0;
}
.dpt-dir-sub {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}

.dpt-dir-tabs {
    display: flex;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 0 24px;
    gap: 10px;
}
.dpt-tab-btn {
    padding: 14px 20px;
    font-size: 13.5px;
    font-weight: 800;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dpt-tab-btn:hover {
    color: #4f46e5;
}
.dpt-tab-btn.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
    background: #ffffff;
}

.dpt-tab-count {
    padding: 2px 8px;
    border-radius: 99px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 11px;
    font-weight: 800;
}

/* Directory Tables */
.dpt-table-wrap {
    width: 100%;
    overflow-x: auto;
}
.dpt-dir-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}
.dpt-dir-table th {
    background: #f8fafc;
    padding: 14px 24px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.dpt-dir-table td {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.dpt-dir-table tr:last-child td {
    border-bottom: none;
}

.dpt-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #4f46e5;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 15px;
    object-fit: cover;
}
.dpt-id-tag {
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    font-size: 12px;
}
.dpt-status-active {
    padding: 3px 9px;
    border-radius: 6px;
    background: #f0fdf4;
    color: #166534;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid #bbf7d0;
}
</style>

<!-- Hero Section -->
<div class="dpt-hero">
    <div>
        <h2 class="dpt-hero-title">Academic Departments & Directory</h2>
        <p class="dpt-hero-sub">Manage academic departments and inspect faculty assignments and student profiles per department.</p>
    </div>
    <div class="dpt-stats-row">
        <div class="dpt-stat-chip">
            <div class="dpt-stat-val"><?php echo $totalDepts; ?></div>
            <div class="dpt-stat-lbl">Departments</div>
        </div>
        <div class="dpt-stat-chip">
            <div class="dpt-stat-val"><?php echo $totalFaculty; ?></div>
            <div class="dpt-stat-lbl">Total Faculty</div>
        </div>
        <div class="dpt-stat-chip">
            <div class="dpt-stat-val"><?php echo $totalStudents; ?></div>
            <div class="dpt-stat-lbl">Total Students</div>
        </div>
    </div>
</div>

<!-- Top Section: Create Form & Department Cards Grid -->
<div class="dpt-top-grid">
    <!-- Form -->
    <div class="dpt-card">
        <h3 class="dpt-card-title">🏛️ Add New Department</h3>

        <form action="departments.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="dpt-form-group">
                <label class="dpt-label">Department Code *</label>
                <input type="text" name="code" required placeholder="e.g. AI" class="dpt-input">
            </div>

            <div class="dpt-form-group">
                <label class="dpt-label">Department Name *</label>
                <input type="text" name="name" required placeholder="e.g. Artificial Intelligence & Data Science" class="dpt-input">
            </div>

            <div class="dpt-form-group">
                <label class="dpt-label">Overview Description</label>
                <textarea name="description" rows="4" class="dpt-textarea" placeholder="Department specialization, labs, and research scope..."></textarea>
            </div>

            <button type="submit" class="dpt-submit-btn">✨ Save Department</button>
        </form>
    </div>

    <!-- Departments Grid Cards -->
    <div class="dpt-card">
        <h3 class="dpt-card-title">🏫 Registered Departments (<?php echo count($departments); ?>)</h3>
        
        <div class="dpt-grid-cards">
            <?php foreach ($departments as $d): 
                $isActive = ((int)$d['id'] === $selectedDeptId);
            ?>
                <a href="departments.php?dept_id=<?php echo $d['id']; ?>&tab=<?php echo $activeTab; ?>#directory" class="dpt-dept-card <?php echo $isActive ? 'active' : ''; ?>">
                    <div>
                        <div class="dpt-card-header-row">
                            <span class="dpt-badge-code"><?php echo htmlspecialchars($d['code']); ?></span>
                            <?php if ($isActive): ?>
                                <span style="font-size: 11px; font-weight: 800; color: #4f46e5; background: #ffffff; padding: 2px 8px; border-radius: 99px;">Active Selected</span>
                            <?php endif; ?>
                        </div>
                        <h4 class="dpt-card-dept-name"><?php echo htmlspecialchars($d['name']); ?></h4>
                        <p class="dpt-card-desc"><?php 
                            $descStr = $d['description'] ?? 'No description provided.';
                            echo htmlspecialchars(strlen($descStr) > 75 ? substr($descStr, 0, 75) . '…' : $descStr); 
                        ?></p>
                    </div>

                    <div class="dpt-card-stats">
                        <div class="dpt-card-stat-item">👨‍🏫 <strong><?php echo $d['faculty_count']; ?></strong> Faculty</div>
                        <div class="dpt-card-stat-item">🎓 <strong><?php echo $d['student_count']; ?></strong> Students</div>
                        <div class="dpt-card-stat-item">📖 <strong><?php echo $d['course_count']; ?></strong> Courses</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Bottom Section: Department Directory (Faculty, Students, Courses) -->
<?php if ($selectedDept): ?>
    <div class="dpt-directory-card" id="directory">
        <div class="dpt-dir-header">
            <div>
                <h3 class="dpt-dir-title">📂 <?php echo htmlspecialchars($selectedDept['name']); ?> (<?php echo htmlspecialchars($selectedDept['code']); ?>)</h3>
                <p class="dpt-dir-sub">Complete Directory & Enrolment Profiles for this Department</p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="faculty.php" class="pf-btn" style="background: rgba(255,255,255,0.15); color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 700;">+ Manage Faculty</a>
                <a href="students.php" class="pf-btn" style="background: #4f46e5; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 10px; font-size: 12.5px; font-weight: 700;">+ Add Student</a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="dpt-dir-tabs">
            <a href="departments.php?dept_id=<?php echo $selectedDeptId; ?>&tab=faculty#directory" class="dpt-tab-btn <?php echo ($activeTab === 'faculty') ? 'active' : ''; ?>">
                <span>👨‍🏫 Department Faculty</span>
                <span class="dpt-tab-count"><?php echo count($deptFaculty); ?></span>
            </a>
            <a href="departments.php?dept_id=<?php echo $selectedDeptId; ?>&tab=students#directory" class="dpt-tab-btn <?php echo ($activeTab === 'students') ? 'active' : ''; ?>">
                <span>🎓 Enrolled Student Profiles</span>
                <span class="dpt-tab-count"><?php echo count($deptStudents); ?></span>
            </a>
            <a href="departments.php?dept_id=<?php echo $selectedDeptId; ?>&tab=courses#directory" class="dpt-tab-btn <?php echo ($activeTab === 'courses') ? 'active' : ''; ?>">
                <span>📖 Offered Courses</span>
                <span class="dpt-tab-count"><?php echo count($deptCourses); ?></span>
            </a>
        </div>

        <!-- TAB 1: FACULTY DIRECTORY -->
        <?php if ($activeTab === 'faculty'): ?>
            <?php if (empty($deptFaculty)): ?>
                <div style="padding: 48px; text-align: center; color: #94a3b8;">
                    <div style="font-size: 38px; margin-bottom: 8px;">👨‍🏫</div>
                    <p style="margin: 0; font-size: 14.5px;">No faculty members currently assigned to <?php echo htmlspecialchars($selectedDept['name']); ?>.</p>
                </div>
            <?php else: ?>
                <div class="dpt-table-wrap">
                    <table class="dpt-dir-table">
                        <thead>
                            <tr>
                                <th>Faculty Member</th>
                                <th>Faculty ID</th>
                                <th>Designation</th>
                                <th>Contact Information</th>
                                <th>Office Room</th>
                                <th>Account Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptFaculty as $f): 
                                $fInitials = strtoupper(substr($f['first_name'], 0, 1) . substr($f['last_name'], 0, 1));
                                $fPhoto = !empty($f['avatar']) ? '../' . $f['avatar'] : null;
                            ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if ($fPhoto): ?>
                                                <img src="<?php echo htmlspecialchars($fPhoto); ?>" class="dpt-user-avatar" alt="Faculty avatar">
                                            <?php else: ?>
                                                <div class="dpt-user-avatar"><?php echo $fInitials; ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="font-size: 14.5px; color: #0f172a; display: block;">Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></strong>
                                                <span style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($f['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="dpt-id-tag"><?php echo htmlspecialchars($f['faculty_id_number'] ?: 'FAC-' . $f['id']); ?></span></td>
                                    <td><strong style="color: #4f46e5;"><?php echo htmlspecialchars($f['designation'] ?: 'Assistant Professor'); ?></strong></td>
                                    <td>
                                        <div style="font-size: 12.5px; color: #334155;"><?php echo htmlspecialchars($f['phone'] ?: 'N/A'); ?></div>
                                    </td>
                                    <td><span style="font-size: 12px; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars(!empty($f['office_room']) ? $f['office_room'] : ($f['office_location'] ?? 'Faculty Block')); ?></span></td>
                                    <td><span class="dpt-status-active"><?php echo ucfirst($f['status'] ?? 'active'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB 2: STUDENT PROFILES DIRECTORY -->
        <?php if ($activeTab === 'students'): ?>
            <?php if (empty($deptStudents)): ?>
                <div style="padding: 48px; text-align: center; color: #94a3b8;">
                    <div style="font-size: 38px; margin-bottom: 8px;">🎓</div>
                    <p style="margin: 0; font-size: 14.5px;">No students currently enrolled in <?php echo htmlspecialchars($selectedDept['name']); ?>.</p>
                </div>
            <?php else: ?>
                <div class="dpt-table-wrap">
                    <table class="dpt-dir-table">
                        <thead>
                            <tr>
                                <th>Student Profile</th>
                                <th>Student ID</th>
                                <th>Program</th>
                                <th>Year & Semester</th>
                                <th>CGPA</th>
                                <th>Contact & City</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptStudents as $s): 
                                $sInitials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
                                $sPhoto = !empty($s['avatar']) ? '../' . $s['avatar'] : null;
                                $sRoll = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                            ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <?php if ($sPhoto): ?>
                                                <img src="<?php echo htmlspecialchars($sPhoto); ?>" class="dpt-user-avatar" alt="Student avatar">
                                            <?php else: ?>
                                                <div class="dpt-user-avatar" style="background: #0e7c86;"><?php echo $sInitials; ?></div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="font-size: 14.5px; color: #0f172a; display: block;"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                                                <span style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars($s['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="dpt-id-tag"><?php echo htmlspecialchars($sRoll); ?></span></td>
                                    <td><strong style="color: #0f172a;"><?php echo htmlspecialchars($s['program'] ?: 'B.Tech CS'); ?></strong></td>
                                    <td>Year <?php echo (int)($s['year_of_study'] ?? 1); ?>, Sem <?php echo (int)($s['semester'] ?? 1); ?></td>
                                    <td><strong style="color: #059669; font-size: 14px;"><?php echo number_format((float)($s['cgpa'] ?? 8.5), 2); ?></strong> / 10</td>
                                    <td>
                                        <div style="font-size: 12px; color: #334155;"><?php echo htmlspecialchars($s['phone'] ?: 'No Phone'); ?></div>
                                        <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($s['city'] ?: 'Campus'); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB 3: OFFERED COURSES DIRECTORY -->
        <?php if ($activeTab === 'courses'): ?>
            <?php if (empty($deptCourses)): ?>
                <div style="padding: 48px; text-align: center; color: #94a3b8;">
                    <div style="font-size: 38px; margin-bottom: 8px;">📖</div>
                    <p style="margin: 0; font-size: 14.5px;">No courses registered for <?php echo htmlspecialchars($selectedDept['name']); ?> yet.</p>
                </div>
            <?php else: ?>
                <div class="dpt-table-wrap">
                    <table class="dpt-dir-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Credits</th>
                                <th>Semester</th>
                                <th>Syllabus / Overview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptCourses as $c): ?>
                                <tr>
                                    <td><span class="dpt-id-tag"><?php echo htmlspecialchars($c['code']); ?></span></td>
                                    <td><strong style="font-size: 14px; color: #0f172a;"><?php echo htmlspecialchars($c['name']); ?></strong></td>
                                    <td><strong style="color: #4f46e5;"><?php echo (int)$c['credits']; ?></strong> Credits</td>
                                    <td>Semester <?php echo (int)$c['semester']; ?></td>
                                    <td><span style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($c['description'] ?: 'No description added.'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
