<?php
// admin/projects.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Handle Create / Assign Project to Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    if ($_POST['action'] === 'create_project') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $technologies = trim($_POST['technologies'] ?? '');
        $facultyAdvisorId = (int)($_POST['faculty_advisor_id'] ?? 0);

        if (empty($title) || empty($description) || $facultyAdvisorId <= 0) {
            setToast('error', 'Please fill in all required project fields and select a faculty mentor.');
        } else {
            try {
                // Insert project assigned to faculty advisor
                $stmt = $pdo->prepare("
                    INSERT INTO projects (title, description, technologies, faculty_advisor_id, status, progress_percent)
                    VALUES (?, ?, ?, ?, 'Proposed', 0)
                ");
                $stmt->execute([$title, $description, $technologies, $facultyAdvisorId]);
                $projId = $pdo->lastInsertId();

                // Notify Faculty
                $facUserStmt = $pdo->prepare("SELECT user_id, first_name FROM faculty_profiles WHERE id = ?");
                $facUserStmt->execute([$facultyAdvisorId]);
                $fac = $facUserStmt->fetch();

                if ($fac) {
                    $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, 'faculty/projects.php')");
                    $nStmt->execute([
                        $fac['user_id'],
                        "New Project Assigned by Admin",
                        "Admin assigned a new project '$title' to your mentorship. You can now assign students to this project."
                    ]);
                }

                setToast('success', "Project '$title' created and assigned to faculty member successfully!");
                logActivity($userId, "Admin created project '$title' for faculty ID $facultyAdvisorId");
                header("Location: projects.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Database error: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'reassign_faculty') {
        $projId = (int)($_POST['project_id'] ?? 0);
        $newFacultyId = (int)($_POST['faculty_advisor_id'] ?? 0);

        if ($projId > 0 && $newFacultyId > 0) {
            try {
                $upd = $pdo->prepare("UPDATE projects SET faculty_advisor_id = ? WHERE id = ?");
                $upd->execute([$newFacultyId, $projId]);

                setToast('success', 'Project mentor reassigned successfully.');
                header("Location: projects.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Failed to reassign faculty: ' . $e->getMessage());
            }
        }
    }
}

// Fetch all faculty for dropdown
$facStmt = $pdo->query("
    SELECT f.id, f.first_name, f.last_name, f.faculty_id_number, d.name as dept_name
    FROM faculty_profiles f
    LEFT JOIN departments d ON f.department_id = d.id
    ORDER BY f.first_name ASC
");
$allFaculty = $facStmt->fetchAll();

// Fetch projects with assigned faculty & student info
$projStmt = $pdo->query("
    SELECT p.*, 
           fp.first_name as fac_fn, fp.last_name as fac_ln, fp.faculty_id_number,
           sp.first_name as stu_fn, sp.last_name as stu_ln, sp.student_id_number, sp.reg_number
    FROM projects p
    LEFT JOIN faculty_profiles fp ON p.faculty_advisor_id = fp.id
    LEFT JOIN student_profiles sp ON p.student_id = sp.id
    ORDER BY p.created_at DESC
");
$projectsList = $projStmt->fetchAll();

$pageTitle = 'Admin Project Allocation';
$pageHeading = 'Faculty & Student Project Management';
$activeNav = 'projects';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.apro-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4f46e5 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.apro-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.apro-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.apro-grid {
    display: grid;
    grid-template-columns: 1fr 1.6fr;
    gap: 28px;
}
@media (max-width: 992px) {
    .apro-grid { grid-template-columns: 1fr; }
}

.apro-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    padding: 28px;
}
.apro-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}

.apro-form-group {
    margin-bottom: 18px;
}
.apro-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.apro-input, .apro-select, .apro-textarea {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
}
.apro-input:focus, .apro-select:focus, .apro-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.apro-submit-btn {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 14px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transition: all 0.2s ease;
}
.apro-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
}

.apro-table {
    width: 100%;
    border-collapse: collapse;
}
.apro-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.apro-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.apro-table tr:last-child td {
    border-bottom: none;
}
</style>

<div class="apro-hero">
    <div>
        <h2>Admin Project Allocation Hub</h2>
        <p>Create major capstone projects, allocate them to faculty advisors, and monitor student assignments.</p>
    </div>
    <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">
        💡 <?php echo count($projectsList); ?> Total System Projects
    </span>
</div>

<div class="apro-grid">
    <!-- Left Column: Create & Assign Form -->
    <div class="apro-card">
        <h3 class="apro-card-title">💡 Create & Assign Project to Faculty</h3>

        <form action="projects.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_project">

            <div class="apro-form-group">
                <label class="apro-label">Assign Faculty Mentor *</label>
                <select name="faculty_advisor_id" class="apro-select" required>
                    <option value="">-- Choose Faculty Member --</option>
                    <?php foreach ($allFaculty as $f): ?>
                        <option value="<?php echo $f['id']; ?>">
                            Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?> (<?php echo htmlspecialchars($f['faculty_id_number']); ?>) - <?php echo htmlspecialchars($f['dept_name'] ?? 'Dept'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="apro-form-group">
                <label class="apro-label">Project Title *</label>
                <input type="text" name="title" class="apro-input" required placeholder="e.g. AI-Powered Smart Campus Logistics">
            </div>

            <div class="apro-form-group">
                <label class="apro-label">Tech Stack Keywords</label>
                <input type="text" name="technologies" class="apro-input" placeholder="e.g. Python, PostgreSQL, TensorFlow, React">
            </div>

            <div class="apro-form-group">
                <label class="apro-label">Project Requirements & Scope *</label>
                <textarea name="description" rows="5" required class="apro-textarea" placeholder="Detail the core objectives, deliverables, and guidelines..."></textarea>
            </div>

            <button type="submit" class="apro-submit-btn">🚀 Allocate Project to Faculty</button>
        </form>
    </div>

    <!-- Right Column: Projects Allocation Table -->
    <div class="apro-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 22px 24px; border-bottom: 1px solid #f1f5f9;">
            <h3 class="apro-card-title" style="margin: 0; border: none; padding: 0;">📋 Master Project Ledger (<?php echo count($projectsList); ?>)</h3>
        </div>

        <?php if (empty($projectsList)): ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                <div style="font-size: 38px; margin-bottom: 8px;">💡</div>
                <p style="margin: 0; font-size: 14px;">No projects created yet in the system.</p>
            </div>
        <?php else: ?>
            <table class="apro-table">
                <thead>
                    <tr>
                        <th>Project Title & Scope</th>
                        <th>Assigned Faculty</th>
                        <th>Assigned Student</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectsList as $p): 
                        $facName = !empty($p['fac_fn']) ? ('Prof. ' . $p['fac_fn'] . ' ' . $p['fac_ln']) : 'Unassigned';
                        $stuName = !empty($p['stu_fn']) ? ($p['stu_fn'] . ' ' . $p['stu_ln']) : 'Pending Faculty Assignment';
                        $stuRoll = !empty($p['reg_number']) ? $p['reg_number'] : ($p['student_id_number'] ?? '');
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14.5px; color: #0f172a;"><?php echo htmlspecialchars($p['title']); ?></strong>
                                <div style="font-size: 11.5px; color: #64748b;"><?php echo htmlspecialchars(substr($p['description'], 0, 65)) . '...'; ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #4338ca; font-size: 13px;"><?php echo htmlspecialchars($facName); ?></div>
                                <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($p['faculty_id_number'] ?? ''); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($p['stu_fn'])): ?>
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;"><?php echo htmlspecialchars($stuName); ?></div>
                                    <div style="font-size: 11px; color: #4f46e5; font-weight: 700;"><?php echo htmlspecialchars($stuRoll); ?></div>
                                <?php else: ?>
                                    <span style="font-size: 11.5px; font-weight: 700; color: #b45309; background: #fffbebfb; padding: 3px 8px; border-radius: 6px;">⏳ Faculty Needs to Assign</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 4px 10px; border-radius: 8px; background: #eef2ff; color: #4f46e5; font-weight: 800; font-size: 11.5px;">
                                    <?php echo htmlspecialchars($p['status']); ?> (<?php echo (int)$p['progress_percent']; ?>%)
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
