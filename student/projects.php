<?php
// student/projects.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

// Handle Project Creation Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $technologies = trim($_POST['technologies'] ?? '');
    $advisorId = (int)($_POST['faculty_advisor_id'] ?? 0);

    if (empty($title) || empty($description)) {
        setToast('error', 'Project title and description are required.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO projects (student_id, faculty_advisor_id, title, description, technologies, status, progress_percent)
                VALUES (?, ?, ?, ?, ?, 'Proposed', 10)
            ");
            $stmt->execute([$studentId, ($advisorId > 0 ? $advisorId : null), $title, $description, $technologies]);
            setToast('success', 'Academic project proposal submitted successfully!');
            logActivity($userId, 'Created project proposal: ' . $title);
            header("Location: projects.php");
            exit();
        } catch (Exception $e) {
            setToast('error', 'Database error: ' . $e->getMessage());
        }
    }
}

// Fetch faculty dropdown
$facList = $pdo->query("SELECT id, first_name, last_name FROM faculty_profiles ORDER BY first_name ASC")->fetchAll();

// Fetch student's projects
$pStmt = $pdo->prepare("
    SELECT p.*, f.first_name as fac_fn, f.last_name as fac_ln
    FROM projects p
    LEFT JOIN faculty_profiles f ON p.faculty_advisor_id = f.id
    WHERE p.student_id = ?
    ORDER BY p.created_at DESC
");
$pStmt->execute([$studentId]);
$projects = $pStmt->fetchAll();

// Statistics
$totalProjects = count($projects);
$approvedCount = 0;
$totalProgress = 0;

foreach ($projects as $p) {
    if (strtolower($p['status']) === 'approved' || strtolower($p['status']) === 'in progress') {
        $approvedCount++;
    }
    $totalProgress += (int)$p['progress_percent'];
}
$avgProgress = $totalProjects > 0 ? round($totalProgress / $totalProjects) : 0;

$pageTitle = 'Academic Projects';
$pageHeading = 'Project Mentorship & Progress';
$activeNav = 'projects';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.prj-hero {
    background: linear-gradient(135deg, #065f46 0%, #047857 40%, #10b981 100%);
    border-radius: 20px;
    padding: 30px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(4, 120, 87, 0.2);
    position: relative;
    overflow: hidden;
}
.prj-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}
.prj-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-top: 22px;
}
.prj-stat-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.prj-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: rgba(255, 255, 255, 0.2);
}
.prj-stat-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
}
.prj-stat-lbl {
    font-size: 11.5px;
    opacity: 0.88;
    font-weight: 500;
    margin-top: 2px;
}

.prj-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 1024px) {
    .prj-grid {
        grid-template-columns: 1fr;
    }
}

.prj-card-box {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}

.prj-form-group {
    margin-bottom: 18px;
}
.prj-form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}
.prj-form-input, .prj-form-select, .prj-form-textarea {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
}
.prj-form-input:focus, .prj-form-select:focus, .prj-form-textarea:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15);
}

.prj-btn-submit {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #059669, #047857);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.25);
    transition: all 0.2s ease;
}
.prj-btn-submit:hover {
    background: linear-gradient(135deg, #047857, #065f46);
    box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
}

.prj-item-card {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 22px;
    margin-bottom: 18px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.prj-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.06);
}

.prj-tech-tag {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 11.5px;
    font-weight: 600;
    margin-right: 6px;
    margin-bottom: 6px;
}

@media (max-width: 768px) {
    .prj-hero {
        padding: 24px 20px;
        border-radius: 20px;
        margin-bottom: 20px;
    }
    .prj-hero h2 {
        font-size: 22px !important;
    }
    .prj-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-top: 18px;
    }
    .prj-stat-card {
        padding: 12px 16px;
    }
    .prj-card-box {
        padding: 20px 16px;
        border-radius: 18px;
    }
    .prj-form-input, .prj-form-select, .prj-form-textarea {
        font-size: 14px;
        padding: 12px 14px;
    }
    .prj-btn-submit {
        padding: 14px;
    }
    .prj-item-card {
        padding: 18px 16px;
        border-radius: 16px;
    }
}
</style>

<div class="prj-hero">
    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 4px;">💡 Final-Year Capstone Hub</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 6px 0;">Academic Projects & Mentorship</h2>
    <p style="font-size: 13.5px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Submit research project proposals, assign faculty advisors, track development milestones, and receive mentor feedback.</p>

    <div class="prj-stats-grid">
        <div class="prj-stat-card">
            <div class="prj-stat-icon">💡</div>
            <div>
                <div class="prj-stat-val"><?php echo $totalProjects; ?></div>
                <div class="prj-stat-lbl">Total Proposals</div>
            </div>
        </div>
        <div class="prj-stat-card">
            <div class="prj-stat-icon" style="background: rgba(255, 255, 255, 0.2);">🚀</div>
            <div>
                <div class="prj-stat-val"><?php echo $approvedCount; ?></div>
                <div class="prj-stat-lbl">Active Projects</div>
            </div>
        </div>
        <div class="prj-stat-card">
            <div class="prj-stat-icon" style="background: rgba(255, 255, 255, 0.2);">📈</div>
            <div>
                <div class="prj-stat-val"><?php echo $avgProgress; ?>%</div>
                <div class="prj-stat-lbl">Avg Completion</div>
            </div>
        </div>
    </div>
</div>

<div class="prj-grid">
    <!-- CREATE PROPOSAL FORM -->
    <div class="prj-card-box">
        <h3 style="font-size: 17px; font-weight: 800; margin: 0 0 18px 0; color: #0f172a;">➕ Submit Project Proposal</h3>

        <form action="projects.php" method="POST">
            <?php echo csrfField(); ?>
            <div class="prj-form-group">
                <label>Project Title *</label>
                <input type="text" name="title" class="prj-form-input" required placeholder="e.g. AI-Powered Campus Scheduling System">
            </div>

            <div class="prj-form-group">
                <label>Technologies / Tools</label>
                <input type="text" name="technologies" class="prj-form-input" placeholder="e.g. PHP 8, PostgreSQL, JavaScript, Chart.js">
            </div>

            <div class="prj-form-group">
                <label>Faculty Advisor / Mentor</label>
                <select name="faculty_advisor_id" class="prj-form-select">
                    <option value="">-- Choose Mentor --</option>
                    <?php foreach ($facList as $f): ?>
                        <option value="<?php echo $f['id']; ?>">Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="prj-form-group">
                <label>Description & Scope *</label>
                <textarea name="description" rows="5" class="prj-form-textarea" required placeholder="Detailed objective, key features, methodology, and expected outcomes..."></textarea>
            </div>

            <button type="submit" class="prj-btn-submit">Submit Proposal →</button>
        </form>
    </div>

    <!-- MY PROJECTS LIST -->
    <div>
        <h3 style="font-size: 17px; font-weight: 800; margin: 0 0 18px 0; color: #0f172a;">🎓 My Capstone Projects</h3>

        <?php if (empty($projects)): ?>
            <div class="prj-card-box" style="text-align: center; padding: 44px; color: #94a3b8;">
                <div style="font-size: 38px; margin-bottom: 10px;">💡</div>
                <h4 style="margin: 0 0 4px 0; color: #475569;">No Projects Submitted</h4>
                <p style="margin: 0; font-size: 13.5px;">Submit your project proposal using the form to start tracking progress.</p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): 
                $prog = (int)$p['progress_percent'];
                $mentorName = !empty($p['fac_fn']) ? 'Prof. ' . $p['fac_fn'] . ' ' . $p['fac_ln'] : 'Pending Assignment';
            ?>
                <div class="prj-item-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 12px;">
                        <div>
                            <span class="badge badge-submitted" style="font-size: 11px; padding: 4px 10px; border-radius: 6px; margin-bottom: 6px; text-transform: uppercase;">
                                <?php echo htmlspecialchars($p['status']); ?>
                            </span>
                            <h4 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 4px 0 2px 0;"><?php echo htmlspecialchars($p['title']); ?></h4>
                            <div style="font-size: 12.5px; color: #64748b;">👨‍🏫 Advisor: <strong><?php echo htmlspecialchars($mentorName); ?></strong></div>
                        </div>

                        <div style="text-align: right;">
                            <div style="font-size: 22px; font-weight: 800; color: #059669; line-height: 1;"><?php echo $prog; ?>%</div>
                            <div style="font-size: 11px; color: #94a3b8; font-weight: 600; margin-top: 2px;">Completed</div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 14px;">
                        <div style="width: <?php echo $prog; ?>%; height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 10px;"></div>
                    </div>

                    <p style="font-size: 13.5px; color: #334155; line-height: 1.6; margin-bottom: 14px;"><?php echo nl2br(htmlspecialchars($p['description'])); ?></p>

                    <?php if (!empty($p['technologies'])): ?>
                        <div style="margin-bottom: 12px;">
                            <?php foreach (explode(',', $p['technologies']) as $tech): ?>
                                <span class="prj-tech-tag">🛠️ <?php echo htmlspecialchars(trim($tech)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($p['feedback']): ?>
                        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px 16px; border-radius: 12px; font-size: 13px; color: #166534; margin-top: 10px;">
                            💬 <strong>Advisor Feedback:</strong> <?php echo htmlspecialchars($p['feedback']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

