<?php
// faculty/projects.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Handle Student Assignment & Mentorship Review Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    if ($_POST['action'] === 'assign_student') {
        $projId = (int)($_POST['project_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);

        if ($projId > 0 && $studentId > 0) {
            try {
                // Assign student to project under faculty mentorship
                $upd = $pdo->prepare("
                    UPDATE projects 
                    SET student_id = ?, status = 'Approved' 
                    WHERE id = ? AND faculty_advisor_id = ?
                ");
                $upd->execute([$studentId, $projId, $facultyId]);

                // Notify Student
                $stuUserStmt = $pdo->prepare("SELECT user_id FROM student_profiles WHERE id = ?");
                $stuUserStmt->execute([$studentId]);
                $stuUser = $stuUserStmt->fetch();

                if ($stuUser) {
                    $pDataStmt = $pdo->prepare("SELECT title FROM projects WHERE id = ?");
                    $pDataStmt->execute([$projId]);
                    $pData = $pDataStmt->fetch();

                    $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, 'student/projects.php')");
                    $nStmt->execute([
                        $stuUser['user_id'],
                        "Project Assigned by Faculty Advisor",
                        "Prof. " . $faculty['first_name'] . ' ' . $faculty['last_name'] . " assigned you to project '" . ($pData['title'] ?? 'Capstone') . "'!"
                    ]);
                }

                setToast('success', 'Student assigned to project successfully!');
                logActivity($userId, "Assigned student ID $studentId to project ID $projId");
                header("Location: projects.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Failed to assign student: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'update_review') {
        $pId = (int)$_POST['project_id'];
        $allowedStatuses = ['Proposed', 'Approved', 'In Progress', 'Completed', 'Rejected'];
        $status = in_array($_POST['status'] ?? '', $allowedStatuses) ? $_POST['status'] : 'Approved';
        $progress = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $feedback = trim($_POST['feedback'] ?? '');

        try {
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET status = ?, progress_percent = ?, feedback = ? 
                WHERE id = ? AND faculty_advisor_id = ?
            ");
            $stmt->execute([$status, $progress, $feedback, $pId, $facultyId]);

            setToast('success', 'Project mentorship feedback updated successfully!');
            logActivity($userId, "Updated project ID $pId status to $status");
            header("Location: projects.php");
            exit();
        } catch (Exception $e) {
            setToast('error', 'Failed to update project: ' . $e->getMessage());
        }
    }
}

// Fetch enrolled students for dropdown assignment (students taught by or in same department)
$deptId = $faculty['department_id'];
$studentsStmt = $pdo->prepare("
    SELECT DISTINCT sp.id, sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number
    FROM student_profiles sp
    WHERE sp.department_id = ?
    ORDER BY sp.first_name ASC
");
$studentsStmt->execute([$deptId ?: 0]);
$departmentStudents = $studentsStmt->fetchAll();

// Fetch projects mentored by this faculty
$pStmt = $pdo->prepare("
    SELECT p.*, sp.first_name as stu_fn, sp.last_name as stu_ln, sp.student_id_number, sp.reg_number, sp.program
    FROM projects p
    LEFT JOIN student_profiles sp ON p.student_id = sp.id
    WHERE p.faculty_advisor_id = ?
    ORDER BY p.created_at DESC
");
$pStmt->execute([$facultyId]);
$projects = $pStmt->fetchAll();

$pageTitle = 'Project Mentorship & Student Allocation';
$pageHeading = 'Capstone & Major Project Guidance';
$activeNav = 'projects';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fproj-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #6366f1 100%);
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
.fproj-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.fproj-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.fproj-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    padding: 28px;
    margin-bottom: 24px;
    transition: all 0.25s ease;
}
.fproj-card:hover {
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

.fproj-progress-bar {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    margin-top: 8px;
}
.fproj-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #4f46e5);
    border-radius: 6px;
    transition: width 0.4s ease;
}

.fproj-form-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    margin-top: 20px;
}
.fproj-label {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    display: block;
    margin-bottom: 4px;
}
.fproj-input, .fproj-select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    background: #ffffff;
}
.fproj-input:focus, .fproj-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}
.fproj-submit-btn {
    width: 100%;
    padding: 11px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 13.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}
.fproj-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
}

.fproj-assign-box {
    background: #fffbebfb;
    border: 1px solid #fef3c7;
    border-radius: 14px;
    padding: 16px;
    margin-top: 16px;
}
</style>

<div class="fproj-hero">
    <div>
        <h2>Project Mentorship & Student Allocation</h2>
        <p>Manage projects assigned by Admin, allocate students to projects, and provide progress feedback.</p>
    </div>
    <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">
        ?? <?php echo count($projects); ?> Assigned Projects
    </span>
</div>

<div style="display: flex; flex-direction: column;">
    <?php if (empty($projects)): ?>
        <div class="fproj-card" style="text-align: center; padding: 45px; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 8px;">??</div>
            <p style="margin: 0; font-size: 14px;">No student projects assigned under your mentorship yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($projects as $p): 
            $hasStudent = !empty($p['stu_fn']);
            $roll = !empty($p['reg_number']) ? $p['reg_number'] : ($p['student_id_number'] ?? '');
        ?>
            <div class="fproj-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <span style="display: inline-block; padding: 4px 10px; border-radius: 8px; background: #eef2ff; color: #4f46e5; font-size: 12px; font-weight: 800; margin-bottom: 6px;">
                            <?php echo htmlspecialchars($p['status']); ?>
                        </span>
                        <h3 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;"><?php echo htmlspecialchars($p['title']); ?></h3>
                        <div style="font-size: 13.5px; color: #64748b;">
                            <?php if ($hasStudent): ?>
                                Student Mentee: <strong style="color: #0f172a;"><?php echo htmlspecialchars($p['stu_fn'] . ' ' . $p['stu_ln']); ?></strong> (<?php echo htmlspecialchars($roll); ?>) • <?php echo htmlspecialchars($p['program'] ?? 'Academic Program'); ?>
                            <?php else: ?>
                                <span style="color: #b45309; font-weight: 700;">?? Unassigned: Please select a student below to assign this project.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right; min-width: 140px;">
                        <span style="font-size: 24px; font-weight: 800; color: #4f46e5;"><?php echo (int)$p['progress_percent']; ?>%</span>
                        <div class="fproj-progress-bar">
                            <div class="fproj-progress-fill" style="width: <?php echo (int)$p['progress_percent']; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <p style="font-size: 14px; color: #334155; line-height: 1.5; margin-bottom: 16px;"><?php echo nl2br(htmlspecialchars($p['description'])); ?></p>
                
                <?php if (!empty($p['technologies'])): ?>
                    <div style="font-size: 13px; color: #64748b; margin-bottom: 18px;">
                        ??? Tech Stack: <strong style="color: #0f172a;"><?php echo htmlspecialchars($p['technologies']); ?></strong>
                    </div>
                <?php endif; ?>

                <!-- Form 1: Assign Student to Project if not assigned or re-assigning -->
                <?php if (!$hasStudent): ?>
                    <form action="projects.php" method="POST" class="fproj-assign-box">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="assign_student">
                        <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">
                        
                        <div style="display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 260px;">
                                <label class="fproj-label" style="color: #b45309;">Select Student to Assign Project *</label>
                                <select name="student_id" class="fproj-select" required style="border-color: #fcd34d;">
                                    <option value="">-- Choose Department Student --</option>
                                    <?php foreach ($departmentStudents as $s): 
                                        $sRoll = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                                    ?>
                                        <option value="<?php echo $s['id']; ?>">
                                            <?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?> (<?php echo htmlspecialchars($sRoll); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="fproj-submit-btn" style="width: auto; padding: 10px 24px; background: linear-gradient(135deg, #d97706, #b45309);">
                                ????? Assign Student to Project
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Form 2: Mentor Guidance & Review Form -->
                    <form action="projects.php" method="POST" class="fproj-form-box">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update_review">
                        <input type="hidden" name="project_id" value="<?php echo $p['id']; ?>">

                        <div style="display: grid; grid-template-columns: 1fr 1fr 2fr 1fr; gap: 14px; align-items: end;">
                            <div>
                                <label class="fproj-label">Project Status</label>
                                <select name="status" class="fproj-select">
                                    <option value="Proposed" <?php echo ($p['status'] === 'Proposed') ? 'selected' : ''; ?>>Proposed</option>
                                    <option value="Approved" <?php echo ($p['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="In Progress" <?php echo ($p['status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo ($p['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div>
                                <label class="fproj-label">Progress %</label>
                                <input type="number" min="0" max="100" name="progress_percent" value="<?php echo (int)$p['progress_percent']; ?>" class="fproj-input">
                            </div>
                            <div>
                                <label class="fproj-label">Advisor Feedback / Guidance Notes</label>
                                <input type="text" name="feedback" value="<?php echo htmlspecialchars($p['feedback'] ?? ''); ?>" placeholder="Enter progress comments..." class="fproj-input">
                            </div>
                            <div>
                                <button type="submit" class="fproj-submit-btn">Save Review</button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
