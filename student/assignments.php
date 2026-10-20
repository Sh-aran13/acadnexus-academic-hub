<?php
// student/assignments.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

// Filter by status if requested
$statusFilter = $_GET['status'] ?? 'all';

$sql = "
    SELECT a.*, c.name as course_name, c.code as course_code,
           f.first_name as fac_fn, f.last_name as fac_ln,
           s.id as submission_id, s.status as sub_status, s.marks_obtained, s.submitted_at
    FROM assignments a
    JOIN course_enrollments ce ON a.course_id = ce.course_id
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN faculty_profiles f ON a.faculty_id = f.id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE ce.student_id = ? AND a.is_published = TRUE
";

if ($statusFilter === 'pending') {
    $sql .= " AND s.id IS NULL";
} elseif ($statusFilter === 'submitted') {
    $sql .= " AND s.status = 'Submitted'";
} elseif ($statusFilter === 'graded') {
    $sql .= " AND s.status = 'Graded'";
}

$sql .= " ORDER BY a.due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$studentId, $studentId]);
$assignments = $stmt->fetchAll();

// Counts for filter tabs
$countPending = 0; $countSubmitted = 0; $countGraded = 0;
$allStmt = $pdo->prepare("
    SELECT s.status
    FROM assignments a
    JOIN course_enrollments ce ON a.course_id = ce.course_id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    WHERE ce.student_id = ? AND a.is_published = TRUE
");
$allStmt->execute([$studentId, $studentId]);
$allSubmissions = $allStmt->fetchAll();

foreach ($allSubmissions as $sub) {
    if (!$sub['status']) $countPending++;
    elseif ($sub['status'] === 'Submitted') $countSubmitted++;
    elseif ($sub['status'] === 'Graded') $countGraded++;
}
$countAll = count($allSubmissions);

$pageTitle = 'Assignments';
$pageHeading = 'Course Assignments & Deliverables';
$activeNav = 'assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .assign-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #7c3aed 100%);
        border-radius: 24px;
        padding: 32px 40px;
        color: #ffffff;
        margin-bottom: 32px;
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.2);
    }

    .assign-hero h2 {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }

    .assign-hero p {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Filter Bar & Search */
    .controls-hub {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .filter-tabs {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #ffffff;
        padding: 6px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }

    .tab-pill {
        padding: 8px 18px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-muted);
        text-decoration: none;
        transition: all 0.25s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-pill:hover {
        color: var(--text-main);
    }

    .tab-pill.active {
        background: var(--accent-gradient);
        color: #ffffff;
        box-shadow: 0 4px 12px var(--accent-glow);
    }

    .pill-count {
        font-size: 11px;
        padding: 2px 7px;
        border-radius: 10px;
        background: rgba(0, 0, 0, 0.1);
    }

    .tab-pill.active .pill-count {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }

    /* Assignment Grid */
    .assignments-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
    }

    .assign-card {
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
    }

    .assign-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.1);
    }

    .card-border-pending { border-left: 6px solid #f59e0b; }
    .card-border-submitted { border-left: 6px solid #6366f1; }
    .card-border-graded { border-left: 6px solid #10b981; }

    .acard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .acard-course-tag {
        font-size: 12px;
        font-weight: 800;
        color: var(--accent);
        background: rgba(37, 99, 235, 0.08);
        padding: 4px 12px;
        border-radius: 8px;
    }

    .acard-marks-badge {
        font-size: 12px;
        font-weight: 800;
        color: #0f172a;
        background: #f1f5f9;
        padding: 4px 12px;
        border-radius: 20px;
    }

    .acard-title {
        font-size: 18px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.35;
        margin-bottom: 10px;
        letter-spacing: -0.3px;
    }

    .acard-prof {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .acard-meta-box {
        background: #f8fafc;
        border-radius: 14px;
        padding: 14px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .meta-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 13px;
    }

    .meta-label { color: #64748b; font-weight: 600; }
    .meta-value { font-weight: 700; color: var(--text-main); }

    .acard-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .btn-acard {
        width: 100%;
        padding: 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
        text-align: center;
        transition: all 0.25s;
    }

    .btn-acard-submit {
        background: var(--accent-gradient);
        color: #ffffff;
        box-shadow: 0 4px 12px var(--accent-glow);
    }
    .btn-acard-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px var(--accent-glow); }

    .btn-acard-view {
        background: #ffffff;
        color: var(--text-main);
        border: 1px solid var(--border-color);
    }
    .btn-acard-view:hover { border-color: var(--accent); color: var(--accent); }

    @media (max-width: 900px) {
        .assignments-grid { grid-template-columns: 1fr; }
        .controls-hub { flex-direction: column; align-items: stretch; }
        .filter-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            padding: 4px;
        }
        .tab-pill {
            padding: 8px 14px;
            font-size: 12px;
        }
    }

    @media (max-width: 640px) {
        .assign-hero {
            padding: 24px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
        .assign-hero h2 {
            font-size: 22px;
        }
        .assign-card {
            padding: 18px 16px;
            border-radius: 18px;
        }
    }
</style>

<!-- Hero Section -->
<div class="assign-hero">
    <h2>Course Assignments & Tasks 📝</h2>
    <p>View assignment specifications, track deadlines, submit your work, and read faculty grading feedback.</p>
</div>

<!-- Controls & Filter Hub -->
<div class="controls-hub">
    <div class="filter-tabs">
        <a href="assignments.php?status=all" class="tab-pill <?php echo ($statusFilter === 'all') ? 'active' : ''; ?>">
            <span>All Tasks</span>
            <span class="pill-count"><?php echo $countAll; ?></span>
        </a>
        <a href="assignments.php?status=pending" class="tab-pill <?php echo ($statusFilter === 'pending') ? 'active' : ''; ?>">
            <span>Pending</span>
            <span class="pill-count"><?php echo $countPending; ?></span>
        </a>
        <a href="assignments.php?status=submitted" class="tab-pill <?php echo ($statusFilter === 'submitted') ? 'active' : ''; ?>">
            <span>Submitted</span>
            <span class="pill-count"><?php echo $countSubmitted; ?></span>
        </a>
        <a href="assignments.php?status=graded" class="tab-pill <?php echo ($statusFilter === 'graded') ? 'active' : ''; ?>">
            <span>Graded</span>
            <span class="pill-count"><?php echo $countGraded; ?></span>
        </a>
    </div>

    <input type="text" id="assignSearchInput" onkeyup="filterAssignCards()" placeholder="🔍 Search assignment..." style="padding: 12px 18px; border-radius: 12px; border: 1px solid var(--border-color); outline: none; width: 280px;">
</div>

<!-- Assignments Grid -->
<div class="assignments-grid" id="assignCardsGrid">
    <?php if (empty($assignments)): ?>
        <div style="grid-column: span 2; background: #ffffff; border-radius: 22px; padding: 60px; text-align: center; color: var(--text-muted); border: 1px solid var(--border-color);">
            <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin-bottom: 12px; color: #cbd5e1;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-main);">No assignments found</h3>
            <p>You have no active assignments under this filter view.</p>
        </div>
    <?php else: ?>
        <?php foreach ($assignments as $a): ?>
            <?php 
                $statusClass = 'card-border-pending';
                if ($a['sub_status'] === 'Graded') $statusClass = 'card-border-graded';
                elseif ($a['sub_status'] === 'Submitted') $statusClass = 'card-border-submitted';

                $isOverdue = (strtotime($a['due_date']) < time() && !$a['sub_status']);
            ?>
            <div class="assign-card <?php echo $statusClass; ?>" data-search="<?php echo strtolower(htmlspecialchars($a['title'] . ' ' . $a['course_code'] . ' ' . $a['course_name'])); ?>">
                <div>
                    <div class="acard-header">
                        <span class="acard-course-tag"><?php echo htmlspecialchars($a['course_code']); ?></span>
                        <span class="acard-marks-badge"><?php echo $a['max_marks']; ?> Marks</span>
                    </div>

                    <h3 class="acard-title"><?php echo htmlspecialchars($a['title']); ?></h3>
                    <div class="acard-prof">
                        <span>👨‍🏫 Prof. <?php echo htmlspecialchars($a['fac_fn'] . ' ' . $a['fac_ln']); ?></span>
                    </div>

                    <div class="acard-meta-box">
                        <div class="meta-row" style="margin-bottom: 8px;">
                            <span class="meta-label">Deadline:</span>
                            <span class="meta-value" style="<?php echo $isOverdue ? 'color: #ef4444;' : ''; ?>">
                                <?php echo date('M d, Y H:i', strtotime($a['due_date'])); ?>
                                <?php if ($isOverdue): ?><span class="badge badge-urgent" style="margin-left: 4px;">Overdue</span><?php endif; ?>
                            </span>
                        </div>

                        <div class="meta-row">
                            <span class="meta-label">Status & Score:</span>
                            <span class="meta-value">
                                <?php if ($a['sub_status'] === 'Graded'): ?>
                                    <span class="badge badge-graded">Graded: <?php echo $a['marks_obtained']; ?>/<?php echo $a['max_marks']; ?></span>
                                <?php elseif ($a['sub_status'] === 'Submitted'): ?>
                                    <span class="badge badge-submitted">Submitted</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Pending Submission</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="acard-footer">
                    <?php if ($a['sub_status']): ?>
                        <a href="assignment_view.php?id=<?php echo $a['id']; ?>" class="btn-acard btn-acard-view">View Submission & Feedback &rarr;</a>
                    <?php else: ?>
                        <a href="assignment_view.php?id=<?php echo $a['id']; ?>" class="btn-acard btn-acard-submit">Submit Assignment Now &rarr;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function filterAssignCards() {
    const input = document.getElementById('assignSearchInput');
    const filter = input.value.toLowerCase();
    const cards = document.querySelectorAll('#assignCardsGrid .assign-card');

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
