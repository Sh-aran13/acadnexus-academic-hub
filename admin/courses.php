<?php
// admin/courses.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

// Fetch all active faculty members for assignment dropdown
$facultyMembers = $pdo->query("
    SELECT f.id, f.first_name, f.last_name, f.faculty_id_number, f.department_id, f.designation, d.name as dept_name
    FROM faculty_profiles f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN departments d ON f.department_id = d.id
    WHERE u.status = 'active'
    ORDER BY f.first_name ASC, f.last_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$courseId]);
            setToast('success', 'Course deleted successfully.');
            logActivity($_SESSION['user_id'], "Admin deleted course ID: $courseId");
        } catch (Exception $e) {
            setToast('error', 'Cannot delete course (active enrollments or materials exist): ' . $e->getMessage());
        }
        header('Location: courses.php');
        exit();
    }

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $deptId = (int)($_POST['department_id'] ?? 0);
    $facultyId = (int)($_POST['faculty_id'] ?? 0);
    $credits = (int)($_POST['credits'] ?? 3);
    $semester = (int)($_POST['semester'] ?? 1);
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name) || $deptId <= 0) {
        setToast('error', 'Course code, name, and department are required.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO courses (code, name, department_id, credits, semester, description) VALUES (?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$code, $name, $deptId, $credits, $semester, $description]);
            $newCourseId = (int)$stmt->fetchColumn();

            // Assign course to faculty member if selected
            $assignedFacultyName = '';
            if ($facultyId > 0 && $newCourseId > 0) {
                // Verify faculty exists
                $facCheck = $pdo->prepare("SELECT id, user_id, first_name, last_name FROM faculty_profiles WHERE id = ?");
                $facCheck->execute([$facultyId]);
                $fac = $facCheck->fetch();

                if ($fac) {
                    $assignStmt = $pdo->prepare("
                        INSERT INTO faculty_courses (faculty_id, course_id, semester, academic_year)
                        VALUES (?, ?, ?, '2025-2026')
                        ON CONFLICT (faculty_id, course_id, academic_year) DO UPDATE SET semester = EXCLUDED.semester
                    ");
                    $assignStmt->execute([$facultyId, $newCourseId, $semester]);
                    $assignedFacultyName = $fac['first_name'] . ' ' . $fac['last_name'];

                    // Notify Faculty of Course Allocation
                    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, 'faculty/courses.php')");
                    $notif->execute([
                        $fac['user_id'],
                        "New Course Allocated: $code",
                        "You have been assigned to instruct '$name' ($code) for Semester $semester."
                    ]);
                }
            }

            $pdo->commit();

            $successMsg = 'Course created successfully!';
            if (!empty($assignedFacultyName)) {
                $successMsg .= " Allocated to Prof. $assignedFacultyName.";
            }
            setToast('success', $successMsg);
            logActivity($_SESSION['user_id'], "Admin created course: $code - $name" . (!empty($assignedFacultyName) ? " (Assigned to: $assignedFacultyName)" : ""));
            header('Location: courses.php');
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setToast('error', 'Failed to create course: ' . $e->getMessage());
        }
    }
}

$courses = $pdo->query("
    SELECT c.*, d.name as dept_name, d.code as dept_code,
           STRING_AGG(DISTINCT fp.first_name || ' ' || fp.last_name, ', ') as assigned_faculty,
           COUNT(DISTINCT fc.faculty_id) as faculty_count
    FROM courses c 
    LEFT JOIN departments d ON c.department_id = d.id 
    LEFT JOIN faculty_courses fc ON c.id = fc.course_id
    LEFT JOIN faculty_profiles fp ON fc.faculty_id = fp.id
    GROUP BY c.id, d.name, d.code
    ORDER BY c.code ASC
")->fetchAll();

$totalCourses = count($courses);
$totalDepartments = count($departments);
$totalCredits = array_sum(array_column($courses, 'credits'));

$pageTitle = 'Course Catalogue Management';
$pageHeading = 'Academic Course Catalogue';
$activeNav = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.crs-hero {
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
.crs-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.crs-hero-sub {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}
.crs-stats-row {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
}
.crs-stat-chip {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    padding: 10px 18px;
    border-radius: 14px;
    text-align: center;
    min-width: 100px;
}
.crs-stat-val {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.crs-stat-lbl {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.75);
    text-transform: uppercase;
    font-weight: 700;
    margin-top: 2px;
}

.crs-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 28px;
    align-items: start;
}
@media (max-width: 1024px) {
    .crs-layout { grid-template-columns: 1fr; }
}

.crs-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.03);
    padding: 24px;
}
.crs-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 18px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 8px;
}

.crs-form-group {
    margin-bottom: 16px;
}
.crs-label {
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.crs-input, .crs-select, .crs-textarea {
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
.crs-input:focus, .crs-select:focus, .crs-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.12);
}

.crs-submit-btn {
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
.crs-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
}

.crs-search-box {
    padding: 10px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    outline: none;
    width: 260px;
    transition: all 0.2s;
}
.crs-search-box:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.crs-table-container {
    width: 100%;
    overflow-x: auto;
}
.crs-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 620px;
}
.crs-table th {
    background: #f8fafc;
    padding: 14px 20px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.crs-table td {
    padding: 14px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.crs-table tr:last-child td {
    border-bottom: none;
}

.crs-code-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    font-size: 12.5px;
    letter-spacing: 0.3px;
}
.crs-dept-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 11.5px;
    font-weight: 700;
}
.crs-pill {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 99px;
    background: #f0fdf4;
    color: #166534;
    font-size: 11.5px;
    font-weight: 700;
    border: 1px solid #bbf7d0;
}
</style>

<!-- Hero Section -->
<div class="crs-hero">
    <div>
        <h2 class="crs-hero-title">Academic Course Catalogue</h2>
        <p class="crs-hero-sub">Configure university degree programs, course codes, syllabus details, and credits pool.</p>
    </div>
    <div class="crs-stats-row">
        <div class="crs-stat-chip">
            <div class="crs-stat-val"><?php echo $totalCourses; ?></div>
            <div class="crs-stat-lbl">Active Courses</div>
        </div>
        <div class="crs-stat-chip">
            <div class="crs-stat-val"><?php echo $totalDepartments; ?></div>
            <div class="crs-stat-lbl">Departments</div>
        </div>
        <div class="crs-stat-chip">
            <div class="crs-stat-val"><?php echo $totalCredits; ?></div>
            <div class="crs-stat-lbl">Total Credits</div>
        </div>
    </div>
</div>

<div class="crs-layout">
    <!-- Left Column: Add Course Form -->
    <div class="crs-card">
        <h3 class="crs-card-title">📚 Add New Course</h3>

        <form action="courses.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create">
            <div class="crs-form-group">
                <label class="crs-label">Course Code *</label>
                <input type="text" name="code" required placeholder="e.g. CS-401" class="crs-input">
            </div>

            <div class="crs-form-group">
                <label class="crs-label">Course Name *</label>
                <input type="text" name="name" required placeholder="e.g. Cloud Computing & DevOps" class="crs-input">
            </div>

            <div class="crs-form-group">
                <label class="crs-label">Department *</label>
                <select name="department_id" id="courseDeptSelect" class="crs-select" required onchange="filterFacultyByDept()">
                    <option value="">-- Choose Department --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['code']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="crs-form-group">
                <label class="crs-label">Assign Faculty Instructor</label>
                <select name="faculty_id" id="courseFacultySelect" class="crs-select">
                    <option value="">-- Select Faculty Instructor (Optional) --</option>
                    <?php foreach ($facultyMembers as $f): ?>
                        <option value="<?php echo $f['id']; ?>" data-dept="<?php echo $f['department_id']; ?>">
                            Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?> 
                            (<?php echo htmlspecialchars($f['faculty_id_number'] . ($f['dept_name'] ? ' &bull; ' . $f['dept_name'] : '')); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 11.5px; color: #64748b; margin-top: 4px;">
                    💡 Assigning faculty will automatically allocate this course to them and notify them in their portal.
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" class="crs-form-group">
                <div>
                    <label class="crs-label">Credits *</label>
                    <input type="number" name="credits" value="4" min="1" max="10" class="crs-input" required>
                </div>
                <div>
                    <label class="crs-label">Semester *</label>
                    <input type="number" name="semester" value="1" min="1" max="8" class="crs-input" required>
                </div>
            </div>

            <div class="crs-form-group">
                <label class="crs-label">Syllabus / Overview</label>
                <textarea name="description" rows="3" class="crs-textarea" placeholder="Brief course objectives and syllabus topics..."></textarea>
            </div>

            <button type="submit" class="crs-submit-btn">✨ Create Course</button>
        </form>
    </div>

    <!-- Right Column: Course Catalogue Table -->
    <div class="crs-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <h3 class="crs-card-title" style="margin: 0; padding: 0; border: none;">📖 Course Catalogue (<?php echo $totalCourses; ?>)</h3>
            <input type="text" id="crsSearchInput" onkeyup="filterCoursesTable()" placeholder="🔍 Search code or course name..." class="crs-search-box">
        </div>

        <?php if (empty($courses)): ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                <div style="font-size: 36px; margin-bottom: 8px;">📚</div>
                <p style="margin: 0; font-size: 14px;">No courses registered yet. Add your first course using the form.</p>
            </div>
        <?php else: ?>
            <div class="crs-table-container">
                <table class="crs-table" id="crsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name & Overview</th>
                            <th>Department</th>
                            <th>Assigned Faculty</th>
                            <th>Credits</th>
                            <th>Semester</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td>
                                    <span class="crs-code-badge"><?php echo htmlspecialchars($c['code']); ?></span>
                                </td>
                                <td>
                                    <strong style="font-size: 14px; color: #0f172a; display: block; margin-bottom: 2px;"><?php echo htmlspecialchars($c['name']); ?></strong>
                                    <?php if (!empty($c['description'])): ?>
                                        <div style="font-size: 11.5px; color: #64748b; line-height: 1.35; max-width: 380px;"><?php echo htmlspecialchars(strlen($c['description']) > 90 ? substr($c['description'], 0, 90) . '…' : $c['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="crs-dept-badge"><?php echo htmlspecialchars($c['dept_name'] ?? 'General'); ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($c['assigned_faculty'])): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 700; color: #1e1b4b; background: #e0e7ff; padding: 4px 10px; border-radius: 8px;">
                                            <span>👨‍🏫</span> Prof. <?php echo htmlspecialchars($c['assigned_faculty']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #94a3b8; font-style: italic;">Unallocated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #4f46e5; font-size: 13.5px;"><?php echo (int)$c['credits']; ?></strong> <span style="font-size: 11px; color: #64748b;">Credits</span>
                                </td>
                                <td>
                                    <span class="crs-pill">Semester <?php echo (int)$c['semester']; ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="courses.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this course?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" style="padding: 5px 10px; border-radius: 8px; background: #fee2e2; color: #dc2626; border: none; font-size: 12px; font-weight: 700; cursor: pointer;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterFacultyByDept() {
    const deptSelect = document.getElementById('courseDeptSelect');
    const facultySelect = document.getElementById('courseFacultySelect');
    if (!deptSelect || !facultySelect) return;

    const selectedDept = deptSelect.value;
    const options = facultySelect.options;

    for (let i = 1; i < options.length; i++) {
        const opt = options[i];
        const optDept = opt.getAttribute('data-dept');
        if (!selectedDept || !optDept || optDept === selectedDept) {
            opt.style.display = '';
            opt.disabled = false;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    }

    // Reset selection if current selection is disabled
    if (facultySelect.selectedIndex > 0 && options[facultySelect.selectedIndex].disabled) {
        facultySelect.selectedIndex = 0;
    }
}

function filterCoursesTable() {
    const input = document.getElementById('crsSearchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('crsTable');
    if (!table) return;
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const rowText = tr[i].textContent.toLowerCase();
        tr[i].style.display = rowText.includes(filter) ? '' : 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
