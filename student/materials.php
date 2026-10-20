<?php
// student/materials.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

$courseFilter = (int)($_GET['course_id'] ?? 0);

// Fetch student enrolled courses for filter dropdown
$coursesStmt = $pdo->prepare("
    SELECT c.id, c.code, c.name
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    WHERE ce.student_id = ?
");
$coursesStmt->execute([$studentId]);
$enrolledCourses = $coursesStmt->fetchAll();

$sql = "
    SELECT m.*, c.name as course_name, c.code as course_code,
           f.first_name as fac_fn, f.last_name as fac_ln
    FROM materials m
    JOIN course_enrollments ce ON m.course_id = ce.course_id
    JOIN courses c ON m.course_id = c.id
    LEFT JOIN faculty_profiles f ON m.faculty_id = f.id
    WHERE ce.student_id = ?
";
$params = [$studentId];

if ($courseFilter > 0) {
    $sql .= " AND m.course_id = ?";
    $params[] = $courseFilter;
}
$sql .= " ORDER BY m.uploaded_at ASC, m.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

$pageTitle = 'Course Materials';
$pageHeading = 'Study Materials & Lecture Library';
$activeNav = 'materials';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .materials-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #2563eb 100%);
        border-radius: 24px;
        padding: 32px 40px;
        color: #ffffff;
        margin-bottom: 32px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
    }

    .materials-hero h2 {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .materials-hero p {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Controls Hub */
    .mat-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .mat-filter-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .mat-select-filter {
        padding: 12px 18px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        background: #ffffff;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-main);
        outline: none;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        cursor: pointer;
    }

    /* Materials 3-Column Cards Grid */
    .materials-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }

    .mat-card {
        background: #ffffff;
        border-radius: 22px;
        border: 1px solid var(--border-color);
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.03);
        padding: 24px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .mat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.15);
        border-color: rgba(37, 99, 235, 0.3);
    }

    .mat-card-top {
        margin-bottom: 20px;
    }

    .mat-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .mat-type-badge {
        font-size: 11px;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .type-pdf { background: #fee2e2; color: #dc2626; }
    .type-ppt { background: #f3e8ff; color: #7c3aed; }
    .type-doc { background: #dbeafe; color: #2563eb; }
    .type-file { background: #e2e8f0; color: #475569; }

    .mat-course-code {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-muted);
    }

    .mat-file-icon-large {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        font-size: 24px;
    }

    .icon-bg-pdf { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    .icon-bg-ppt { background: rgba(124, 58, 237, 0.1); color: #7c3aed; }
    .icon-bg-doc { background: rgba(37, 99, 235, 0.1); color: #2563eb; }
    .icon-bg-file { background: rgba(100, 116, 139, 0.1); color: #64748b; }

    .mat-title {
        font-size: 17px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.35;
        margin-bottom: 8px;
        letter-spacing: -0.2px;
    }

    .mat-desc {
        font-size: 13px;
        color: #64748b;
        line-height: 1.5;
        margin-bottom: 16px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .mat-meta-info {
        font-size: 12px;
        color: #94a3b8;
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding-top: 12px;
        border-top: 1px solid #f1f5f9;
    }

    .mat-btn-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 14px;
        width: 100%;
    }

    .btn-mat-action {
        padding: 10px 14px;
        border-radius: 12px;
        font-size: 12.5px;
        font-weight: 800;
        text-decoration: none;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.25s ease;
        box-sizing: border-box;
        line-height: 1.2;
    }

    .btn-mat-action.view {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    }

    .btn-mat-action.view:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
        background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    }

    .btn-mat-action.download {
        background: #f1f5f9;
        color: #334155;
        border: 1.5px solid #cbd5e1;
    }

    .btn-mat-action.download:hover {
        transform: translateY(-2px);
        background: #e2e8f0;
        color: #0f172a;
        border-color: #94a3b8;
    }

    @media (max-width: 1100px) {
        .materials-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .materials-grid { grid-template-columns: 1fr; }
        .mat-controls { flex-direction: column; align-items: stretch; }
    }
</style>

<!-- Hero Section -->
<div class="materials-hero">
    <h2>Course Materials & Lecture Library 📖</h2>
    <p>Access lecture slides, pdf documents, reference guides, and course resources uploaded by your professors.</p>
</div>

<!-- Controls Hub -->
<div class="mat-controls">
    <div class="mat-filter-group">
        <select onchange="location = this.value;" class="mat-select-filter">
            <option value="materials.php">All Enrolled Courses</option>
            <?php foreach ($enrolledCourses as $c): ?>
                <option value="materials.php?course_id=<?php echo $c['id']; ?>" <?php echo ($courseFilter === $c['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <input type="text" id="matSearchInput" onkeyup="filterMatCards()" placeholder="🔍 Search material title or course..." style="padding: 12px 18px; border-radius: 12px; border: 1px solid var(--border-color); outline: none; width: 300px;">
</div>

<!-- Materials Cards Grid -->
<div class="materials-grid" id="matCardsGrid">
    <?php if (empty($materials)): ?>
        <div style="grid-column: span 3; background: #ffffff; border-radius: 22px; padding: 60px; text-align: center; color: var(--text-muted); border: 1px solid var(--border-color);">
            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom: 12px; color: #cbd5e1;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-main);">No study materials available</h3>
            <p>Your professors haven't uploaded course materials for this view yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($materials as $m): ?>
            <?php 
                $fileType = strtolower($m['file_type'] ?? 'file');
                $typeClass = 'type-file';
                $iconClass = 'icon-bg-file';
                $iconEmoji = '📄';

                if (in_array($fileType, ['pdf'])) {
                    $typeClass = 'type-pdf'; $iconClass = 'icon-bg-pdf'; $iconEmoji = '📕';
                } elseif (in_array($fileType, ['ppt', 'pptx'])) {
                    $typeClass = 'type-ppt'; $iconClass = 'icon-bg-ppt'; $iconEmoji = '📊';
                } elseif (in_array($fileType, ['doc', 'docx'])) {
                    $typeClass = 'type-doc'; $iconClass = 'icon-bg-doc'; $iconEmoji = '📘';
                }
            ?>
            <div class="mat-card" data-search="<?php echo strtolower(htmlspecialchars($m['title'] . ' ' . $m['course_code'] . ' ' . $m['course_name'])); ?>">
                <div class="mat-card-top">
                    <div class="mat-card-header">
                        <span class="mat-type-badge <?php echo $typeClass; ?>"><?php echo strtoupper($fileType); ?></span>
                        <span class="mat-course-code"><?php echo htmlspecialchars($m['course_code']); ?></span>
                    </div>

                    <div class="mat-file-icon-large <?php echo $iconClass; ?>">
                        <?php echo $iconEmoji; ?>
                    </div>

                    <h3 class="mat-title"><?php echo htmlspecialchars($m['title']); ?></h3>
                    <p class="mat-desc"><?php echo htmlspecialchars($m['description'] ?? 'No description provided.'); ?></p>

                    <div class="mat-meta-info">
                        <div>👨‍🏫 Uploaded by: <strong>Prof. <?php echo htmlspecialchars($m['fac_fn'] . ' ' . $m['fac_ln']); ?></strong></div>
                        <div>📅 Date: <?php echo date('M d, Y', strtotime($m['uploaded_at'])); ?> • Size: <strong><?php echo round(($m['file_size'] ?? 0) / 1024 / 1024, 2); ?> MB</strong></div>
                    </div>
                </div>

                <div class="mat-btn-grid">
                    <a href="../download.php?type=material&id=<?php echo $m['id']; ?>&inline=1" target="_blank" rel="noopener noreferrer" class="btn-mat-action view">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        <span>View Document</span>
                    </a>
                    <a href="../download.php?type=material&id=<?php echo $m['id']; ?>" class="btn-mat-action download">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>Download</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function filterMatCards() {
    const input = document.getElementById('matSearchInput');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('#matCardsGrid .mat-card');

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search');
        if (searchText.includes(filter)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
