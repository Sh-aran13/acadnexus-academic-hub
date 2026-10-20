<?php
// faculty/courses.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

$stmt = $pdo->prepare("
    SELECT c.*, d.name as dept_name, d.code as dept_code,
           (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.id) as enrolled_count,
           (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) as materials_count,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignments_count
    FROM faculty_courses fc
    JOIN courses c ON fc.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE fc.faculty_id = ?
    ORDER BY c.code ASC
");
$stmt->execute([$facultyId]);
$courses = $stmt->fetchAll();

$pageTitle = 'Assigned Teaching Courses';
$pageHeading = 'My Allocated Courses';
$activeNav = 'courses';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fc-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #311b92 50%, #4f46e5 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(30, 27, 75, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.fc-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.fc-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.fc-create-btn {
    padding: 12px 24px;
    border-radius: 14px;
    background: #ffffff;
    color: #1e1b4b;
    font-weight: 800;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(255,255,255,0.25);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.fc-create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(255,255,255,0.4);
}

.fc-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 24px;
    margin-bottom: 40px;
}

.fc-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 26px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.fc-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.08);
    border-color: #a5b4fc;
}

.fc-tag {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #e0e7ff;
    color: #4338ca;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.fc-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin: 12px 0 6px 0;
    line-height: 1.3;
}
.fc-desc {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 20px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.fc-stats {
    display: flex;
    gap: 16px;
    background: #f8fafc;
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 20px;
}
.fc-stat-item {
    flex: 1;
    text-align: center;
}
.fc-stat-num {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
}
.fc-stat-lbl {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
}

.fc-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.fc-btn {
    padding: 10px;
    border-radius: 12px;
    text-align: center;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}
.fc-btn-primary {
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
}
.fc-btn-primary:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
}
.fc-btn-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.fc-btn-secondary:hover {
    background: #e2e8f0;
    color: #0f172a;
}
</style>

<div class="fc-hero">
    <div>
        <h2>Assigned Teaching Courses</h2>
        <p>Manage curriculum materials, enter grades, and track student attendance for your active subjects.</p>
    </div>
    <a href="course_create.php" class="fc-create-btn">
        <span>➕ Create New Course</span>
    </a>
</div>

<div class="fc-grid">
    <?php if (empty($courses)): ?>
        <div class="card-box" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">
            <div style="font-size: 38px; margin-bottom: 8px;">📚</div>
            <h4 style="margin: 0 0 4px 0; color: #475569; font-size: 17px;">No Allocated Courses Assigned</h4>
            <p style="margin: 0; font-size: 13.5px;">Click "Create New Course" above to offer a new course to your department's students.</p>
        </div>
    <?php else: ?>
        <?php foreach ($courses as $c): ?>
            <div class="fc-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="fc-tag"><?php echo htmlspecialchars($c['code']); ?> • <?php echo (int)$c['credits']; ?> CREDITS</span>
                        <span style="font-size: 12px; font-weight: 700; color: #64748b;">Sem <?php echo (int)$c['semester']; ?></span>
                    </div>

                    <h3 class="fc-title"><?php echo htmlspecialchars($c['name']); ?></h3>
                    <p class="fc-desc"><?php echo htmlspecialchars($c['description'] ?? 'No detailed course description provided.'); ?></p>

                    <div class="fc-stats">
                        <div class="fc-stat-item">
                            <div class="fc-stat-num"><?php echo (int)$c['enrolled_count']; ?></div>
                            <div class="fc-stat-lbl">Students</div>
                        </div>
                        <div class="fc-stat-item" style="border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;">
                            <div class="fc-stat-num"><?php echo (int)$c['materials_count']; ?></div>
                            <div class="fc-stat-lbl">Materials</div>
                        </div>
                        <div class="fc-stat-item">
                            <div class="fc-stat-num"><?php echo (int)$c['assignments_count']; ?></div>
                            <div class="fc-stat-lbl">Tasks</div>
                        </div>
                    </div>
                </div>

                <div class="fc-actions">
                    <a href="attendance.php?course_id=<?php echo $c['id']; ?>" class="fc-btn fc-btn-primary">📋 Attendance</a>
                    <a href="marks.php?course_id=<?php echo $c['id']; ?>" class="fc-btn fc-btn-secondary">💯 Enter Marks</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
