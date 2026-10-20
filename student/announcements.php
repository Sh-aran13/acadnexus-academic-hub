<?php
// student/announcements.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'] ?? 0;

// Fetch campus-wide announcements OR course-specific announcements where student is enrolled
$stmt = $pdo->prepare("
    SELECT a.*, u.username as author_name,
           c.code as course_code, c.name as course_name
    FROM announcements a
    LEFT JOIN users u ON a.author_id = u.id
    LEFT JOIN courses c ON a.target_course_id = c.id
    WHERE a.target_course_id IS NULL
       OR a.target_course_id IN (
           SELECT course_id FROM course_enrollments WHERE student_id = ?
       )
    ORDER BY a.created_at DESC
");
$stmt->execute([$studentId]);
$announcements = $stmt->fetchAll();

// Count stats
$totalAnnouncements = count($announcements);
$urgentCount = 0;
$importantCount = 0;
$normalCount = 0;

foreach ($announcements as $a) {
    $p = strtolower($a['priority'] ?? 'normal');
    if ($p === 'urgent') $urgentCount++;
    elseif ($p === 'important') $importantCount++;
    else $normalCount++;
}

$pageTitle = 'Campus Bulletins & Announcements';
$pageHeading = 'Campus Bulletins & Notices';
$activeNav = 'announcements';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.anc-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    border-radius: 20px;
    padding: 32px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(67, 56, 202, 0.2);
    position: relative;
    overflow: hidden;
}
.anc-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}
.anc-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin-top: 24px;
}
.anc-stat-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.anc-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: rgba(255, 255, 255, 0.2);
}
.anc-stat-val {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
}
.anc-stat-lbl {
    font-size: 12px;
    opacity: 0.85;
    font-weight: 500;
    margin-top: 2px;
}

.anc-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.anc-tabs {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
}
.anc-tab-btn {
    border: none;
    background: transparent;
    padding: 8px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}
.anc-tab-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}
.anc-search {
    position: relative;
    min-width: 280px;
}
.anc-search input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}
.anc-search input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.anc-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 15px;
}

.anc-feed {
    display: flex;
    flex-direction: column;
    gap: 18px;
}
.anc-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #e2e8f0;
    border-left: 6px solid #6366f1;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
    position: relative;
}
.anc-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.07);
}
.anc-card.priority-urgent {
    border-left-color: #ef4444;
}
.anc-card.priority-important {
    border-left-color: #f59e0b;
}
.anc-card.priority-normal {
    border-left-color: #6366f1;
}

.anc-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 14px;
}
.anc-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.anc-badge-urgent {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.anc-badge-important {
    background: #fffbeb;
    color: #d97706;
    border: 1px solid #fde68a;
}
.anc-badge-normal {
    background: #eef2ff;
    color: #4f46e5;
    border: 1px solid #c7d2fe;
}
.anc-title {
    font-size: 19px;
    font-weight: 700;
    color: #0f172a;
    margin-top: 8px;
    line-height: 1.35;
}
.anc-date {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
    background: #f8fafc;
    padding: 6px 12px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}
.anc-body {
    font-size: 14px;
    color: #334155;
    line-height: 1.65;
    margin-bottom: 20px;
}
.anc-footer {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
}
.anc-author-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #4338ca);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}
.anc-author-info {
    font-size: 13px;
    color: #64748b;
}
.anc-author-info strong {
    color: #1e293b;
}

@media (max-width: 768px) {
    .anc-hero {
        padding: 24px 20px;
        border-radius: 20px;
        margin-bottom: 20px;
    }
    .anc-hero h2 {
        font-size: 22px !important;
    }
    .anc-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-top: 18px;
    }
    .anc-controls {
        flex-direction: column;
        align-items: stretch;
    }
    .anc-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
        padding: 4px;
    }
    .anc-tab-btn {
        flex: 1;
        padding: 8px 12px;
        font-size: 12px;
        text-align: center;
    }
    .anc-search {
        min-width: 100%;
    }
    .anc-card {
        padding: 18px 16px;
        border-radius: 16px;
    }
    .anc-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .anc-title {
        font-size: 17px;
    }
}
</style>

<div class="anc-hero">
    <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 6px;">📢 Official Campus Broadcast</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 8px 0; letter-spacing: -0.5px;">Bulletins & Announcements</h2>
    <p style="font-size: 14px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Stay up to date with official university declarations, exam schedules, policy changes, and urgent notifications.</p>

    <div class="anc-stats-grid">
        <div class="anc-stat-card">
            <div class="anc-stat-icon">📑</div>
            <div>
                <div class="anc-stat-val"><?php echo $totalAnnouncements; ?></div>
                <div class="anc-stat-lbl">Total Bulletins</div>
            </div>
        </div>
        <div class="anc-stat-card">
            <div class="anc-stat-icon" style="background: rgba(239, 68, 68, 0.25);">🚨</div>
            <div>
                <div class="anc-stat-val"><?php echo $urgentCount; ?></div>
                <div class="anc-stat-lbl">Urgent Alerts</div>
            </div>
        </div>
        <div class="anc-stat-card">
            <div class="anc-stat-icon" style="background: rgba(245, 158, 11, 0.25);">⚠️</div>
            <div>
                <div class="anc-stat-val"><?php echo $importantCount; ?></div>
                <div class="anc-stat-lbl">Important Notices</div>
            </div>
        </div>
        <div class="anc-stat-card">
            <div class="anc-stat-icon" style="background: rgba(99, 102, 241, 0.25);">📢</div>
            <div>
                <div class="anc-stat-val"><?php echo $normalCount; ?></div>
                <div class="anc-stat-lbl">General Bulletins</div>
            </div>
        </div>
    </div>
</div>

<div class="anc-toolbar">
    <div class="anc-tabs">
        <button class="anc-tab-btn active" onclick="filterPriority('all', this)">All Bulletins</button>
        <button class="anc-tab-btn" onclick="filterPriority('urgent', this)">🚨 Urgent (<?php echo $urgentCount; ?>)</button>
        <button class="anc-tab-btn" onclick="filterPriority('important', this)">⚠️ Important (<?php echo $importantCount; ?>)</button>
        <button class="anc-tab-btn" onclick="filterPriority('normal', this)">📢 Normal (<?php echo $normalCount; ?>)</button>
    </div>

    <div class="anc-search">
        <span class="anc-search-icon">🔍</span>
        <input type="text" id="ancSearchInput" onkeyup="searchAnnouncements()" placeholder="Search title, content or author...">
    </div>
</div>

<div class="anc-feed" id="ancFeed">
    <?php if (empty($announcements)): ?>
        <div class="card-box" style="text-align: center; padding: 48px; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 12px;">📭</div>
            <h4 style="margin: 0 0 6px 0; color: #475569; font-size: 18px;">No Bulletins Posted</h4>
            <p style="margin: 0; font-size: 14px;">There are no active campus announcements at this moment.</p>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $a): 
            $priority = strtolower($a['priority'] ?? 'normal');
            $author = $a['author_name'] ?? 'Academic Admin';
            $authorInitial = strtoupper(substr($author, 0, 1));
        ?>
            <div class="anc-card priority-<?php echo $priority; ?>" data-priority="<?php echo $priority; ?>" data-search="<?php echo htmlspecialchars(strtolower($a['title'] . ' ' . $a['content'] . ' ' . $author)); ?>">
                <div class="anc-card-header">
                    <div>
                        <?php if ($priority === 'urgent'): ?>
                            <span class="anc-badge anc-badge-urgent">🚨 Urgent Announcement</span>
                        <?php elseif ($priority === 'important'): ?>
                            <span class="anc-badge anc-badge-important">⚠️ Important Notice</span>
                        <?php else: ?>
                            <span class="anc-badge anc-badge-normal">📢 General Bulletin</span>
                        <?php endif; ?>
                        
                        <?php if (!empty($a['course_code'])): ?>
                            <span class="anc-badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; margin-left: 6px;">
                                🔒 Course: <?php echo htmlspecialchars($a['course_code']); ?>
                            </span>
                        <?php endif; ?>
                        
                        <h3 class="anc-title"><?php echo htmlspecialchars($a['title']); ?></h3>
                    </div>

                    <div class="anc-date">
                        📅 <?php echo date('M d, Y', strtotime($a['publish_date'])); ?>
                    </div>
                </div>

                <?php if (!empty($a['content'])): ?>
                    <div class="anc-body">
                        <?php echo nl2br(htmlspecialchars($a['content'])); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($a['circular_image'])): 
                    $imgWebPath = '../' . htmlspecialchars($a['circular_image']);
                    $circExt = strtolower(pathinfo($a['circular_image'], PATHINFO_EXTENSION));
                    $isCircPdf = ($circExt === 'pdf');
                ?>
                    <div style="margin-bottom: 20px;">
                        <div style="border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0; background: #f8fafc; max-width: 520px; box-shadow: 0 4px 14px rgba(0,0,0,0.03);">
                            <?php if ($isCircPdf): ?>
                                <div style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border-bottom: 1px solid #fecdd3;">
                                    <span style="font-size: 28px;">📕</span>
                                    <div>
                                        <strong style="font-size: 14px; color: #9f1239; display: block;">Official Circular Document (PDF)</strong>
                                        <span style="font-size: 12px; color: #be123c;"><?php echo htmlspecialchars(basename($a['circular_image'])); ?></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?php echo $imgWebPath; ?>" alt="Circular Notice" style="width: 100%; max-height: 280px; object-fit: cover; display: block; cursor: pointer;" onclick="openImageModal('<?php echo $imgWebPath; ?>')">
                            <?php endif; ?>

                            <div style="display: flex; gap: 8px; padding: 10px 14px; background: #f8fafc; border-top: 1px solid #f1f5f9;">
                                <?php if ($isCircPdf): ?>
                                    <a href="<?php echo $imgWebPath; ?>" target="_blank" style="padding: 6px 14px; border-radius: 8px; background: #4f46e5; color: white; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                        👁️ Open PDF in New Tab
                                    </a>
                                <?php else: ?>
                                    <button type="button" onclick="openImageModal('<?php echo $imgWebPath; ?>')" style="padding: 6px 14px; border-radius: 8px; background: #4f46e5; color: white; font-size: 12.5px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                        🔍 Zoom Notice
                                    </button>
                                    <a href="<?php echo $imgWebPath; ?>" target="_blank" style="padding: 6px 14px; border-radius: 8px; background: #0f172a; color: white; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                        ↗ Open in Tab
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo $imgWebPath; ?>" download style="padding: 6px 14px; border-radius: 8px; background: #ffffff; color: #334155; border: 1px solid #cbd5e1; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                    📥 Download
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="anc-footer">
                    <div class="anc-author-avatar">
                        <?php echo $authorInitial; ?>
                    </div>
                    <div class="anc-author-info">
                        Issued by: <strong><?php echo htmlspecialchars($author); ?></strong>
                        <?php if (!empty($a['course_name'])): ?>
                            <span style="color: #64748b; font-size: 12px;"> &bull; <?php echo htmlspecialchars($a['course_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal for full size view -->
<div class="img-modal-backdrop" id="imgModalBackdrop" onclick="closeImageModal(event)" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="max-width: 90vw; max-height: 90vh; background: white; border-radius: 16px; padding: 12px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);">
        <button type="button" onclick="closeImageModal(null)" style="position: absolute; top: -14px; right: -14px; width: 34px; height: 34px; background: #ef4444; color: white; border-radius: 50%; border: 2px solid white; font-size: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">&times;</button>
        <img id="modalEnlargedImg" src="" alt="Enlarged Circular Image" style="max-width: 100%; max-height: 80vh; display: block; border-radius: 10px;">
    </div>
</div>

<script>
function filterPriority(priority, btn) {
    // Update active tab styling
    document.querySelectorAll('.anc-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Filter cards
    const cards = document.querySelectorAll('.anc-card');
    cards.forEach(card => {
        const cardPriority = card.getAttribute('data-priority');
        if (priority === 'all' || cardPriority === priority) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function searchAnnouncements() {
    const term = document.getElementById('ancSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.anc-card');

    cards.forEach(card => {
        const searchText = card.getAttribute('data-search');
        if (term === '' || searchText.includes(term)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Modal view
function openImageModal(imgSrc) {
    const modal = document.getElementById('imgModalBackdrop');
    const img = document.getElementById('modalEnlargedImg');
    img.src = imgSrc;
    modal.style.display = 'flex';
}

function closeImageModal(e) {
    if (!e || e.target.id === 'imgModalBackdrop' || e.target.tagName === 'BUTTON') {
        document.getElementById('imgModalBackdrop').style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

