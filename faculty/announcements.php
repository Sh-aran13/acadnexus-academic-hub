<?php
// faculty/announcements.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'] ?? 0;

// Fetch faculty courses for targeted audience selection
$cStmt = $pdo->prepare("
    SELECT c.id, c.code, c.name, d.code as dept_code
    FROM faculty_courses fc
    JOIN courses c ON fc.course_id = c.id
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE fc.faculty_id = ?
    ORDER BY c.code ASC
");
$cStmt->execute([$facultyId]);
$facultyCourses = $cStmt->fetchAll();

// Handle Delete Announcement via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    requireCSRF();
    $delId = (int)($_POST['id'] ?? 0);
    $fetchStmt = $pdo->prepare("SELECT circular_image FROM announcements WHERE id = ? AND author_id = ?");
    $fetchStmt->execute([$delId, $userId]);
    $ann = $fetchStmt->fetch();
    if ($ann) {
        if (!empty($ann['circular_image'])) {
            FileService::delete($ann['circular_image']);
        }
        $delStmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND author_id = ?");
        $delStmt->execute([$delId, $userId]);
        setToast('success', 'Announcement deleted successfully.');
    } else {
        setToast('error', 'Announcement not found or access denied.');
    }
    header("Location: announcements.php");
    exit();
}

// Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    requireCSRF();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority = in_array($_POST['priority'] ?? '', ['Normal', 'Important', 'Urgent']) ? $_POST['priority'] : 'Normal';
    $targetCourseId = !empty($_POST['target_course_id']) ? (int)$_POST['target_course_id'] : null;
    $circularImagePath = null;

    // Verify course belongs to this faculty if specified
    if ($targetCourseId !== null) {
        $allowedCourseIds = array_column($facultyCourses, 'id');
        if (!in_array($targetCourseId, $allowedCourseIds)) {
            $targetCourseId = null;
        }
    }

    $uploadError = false;
    // 1. Check for Camera Captured Photo (Base64 data URL)
    $cameraImageData = $_POST['camera_image_data'] ?? '';
    if (!empty($cameraImageData) && strpos($cameraImageData, 'data:image/') === 0) {
        $uploadResult = FileService::uploadBase64Image($cameraImageData, 'announcement', $userId);
        if ($uploadResult['success']) {
            $circularImagePath = $uploadResult['file_path'];
        } else {
            setToast('error', 'Camera snapshot error: ' . $uploadResult['error']);
            $uploadError = true;
        }
    }

    // 2. Check for File Upload from Device (Images AND PDF supported)
    if (!$uploadError && empty($circularImagePath) && isset($_FILES['circular_file']) && $_FILES['circular_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = FileService::upload($_FILES['circular_file'], 'announcement', $userId);
        if ($uploadResult['success']) {
            $circularImagePath = $uploadResult['file_path'];
        } else {
            setToast('error', 'Attachment upload failed: ' . $uploadResult['error']);
            $uploadError = true;
        }
    }

    if (!$uploadError) {
        if (empty($title)) {
            setToast('error', 'Announcement title is required.');
        } elseif (empty($content) && empty($circularImagePath)) {
            setToast('error', 'Please provide either announcement description or attach a circular document/image.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (author_id, title, content, priority, target_course_id, circular_image, publish_date)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE)
                ");
                $stmt->execute([
                    $userId,
                    $title,
                    $content,
                    $priority,
                    $targetCourseId,
                    $circularImagePath
                ]);

                setToast('success', 'Announcement published successfully!');
                logActivity($userId, 'Posted announcement: ' . $title);
                header("Location: announcements.php");
                exit();
            } catch (Exception $e) {
                if ($circularImagePath) {
                    FileService::delete($circularImagePath);
                }
                setToast('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch announcements posted by this faculty with Course info
$aStmt = $pdo->prepare("
    SELECT a.*, c.code as course_code, c.name as course_name
    FROM announcements a
    LEFT JOIN courses c ON a.target_course_id = c.id
    WHERE a.author_id = ?
    ORDER BY a.created_at DESC
");
$aStmt->execute([$userId]);
$myAnnouncements = $aStmt->fetchAll();

// Calculate Stats
$totalAnnouncements = count($myAnnouncements);
$urgentCount = 0;
$importantCount = 0;
$normalCount = 0;
foreach ($myAnnouncements as $a) {
    $p = strtolower($a['priority'] ?? 'normal');
    if ($p === 'urgent') $urgentCount++;
    elseif ($p === 'important') $importantCount++;
    else $normalCount++;
}

$pageTitle = 'Faculty Bulletins & Circulars';
$pageHeading = 'Post Announcements & Circulars';
$activeNav = 'announcements';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Elegant Faculty Announcements System */
.fanc-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #312e81 80%, #4338ca 100%);
    border-radius: 24px;
    padding: 34px 40px;
    color: #ffffff;
    margin-bottom: 30px;
    box-shadow: 0 15px 35px -10px rgba(30, 27, 75, 0.35);
    position: relative;
    overflow: hidden;
}
.fanc-hero::before {
    content: '';
    position: absolute;
    top: -40%;
    right: -10%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.fanc-hero-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    z-index: 1;
}
.fanc-hero-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.fanc-hero-sub {
    font-size: 14px;
    color: #c7d2fe;
    margin: 0;
    max-width: 600px;
    line-height: 1.5;
}

/* Quick Metrics in Hero */
.fanc-metrics {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.fanc-metric-pill {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fanc-metric-pill strong {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.fanc-metric-pill span {
    font-size: 12.5px;
    color: #cbd5e1;
    font-weight: 600;
}

/* Layout Grid */
.fanc-grid {
    display: grid;
    grid-template-columns: 460px 1fr;
    gap: 28px;
    align-items: start;
}
@media (max-width: 1080px) {
    .fanc-grid { grid-template-columns: 1fr; }
}

/* Glassmorphic White Container Cards */
.fanc-card {
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.035);
    padding: 28px;
    position: relative;
}
.fanc-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    margin-bottom: 22px;
    border-bottom: 1px solid #f1f5f9;
}
.fanc-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fanc-card-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

/* Form Styles */
.fanc-form-group {
    margin-bottom: 18px;
}
.fanc-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 7px;
}
.fanc-input, .fanc-select, .fanc-textarea {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    box-sizing: border-box;
    font-family: inherit;
    transition: all 0.2s ease;
    background: #ffffff;
    color: #0f172a;
}
.fanc-input:focus, .fanc-select:focus, .fanc-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

/* Source Selection Segmented Tabs */
.fanc-tab-bar {
    display: flex;
    gap: 6px;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 14px;
    margin-bottom: 14px;
}
.fanc-tab-btn {
    flex: 1;
    padding: 9px 12px;
    border: none;
    background: transparent;
    border-radius: 10px;
    font-size: 12.5px;
    font-weight: 700;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.fanc-tab-btn.active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
}

/* DRAG & DROP ZONE (Supports Images AND PDFs) */
.fanc-drop-zone {
    border: 2.5px dashed #cbd5e1;
    border-radius: 18px;
    padding: 24px 18px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}
.fanc-drop-zone.dragover {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
}
.fanc-drop-icon {
    font-size: 34px;
    margin-bottom: 6px;
    display: block;
}
.fanc-drop-title {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}
.fanc-drop-sub {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
    line-height: 1.4;
}
.fanc-browse-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 10px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 12.5px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: background 0.2s ease;
}
.fanc-browse-btn:hover {
    background: #4338ca;
}

/* Attachment Selected Preview Box */
.fanc-file-badge {
    display: none;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 12px;
    align-items: center;
    justify-content: space-between;
}
.fanc-file-badge-left {
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}
.fanc-file-badge-icon {
    font-size: 22px;
}
.fanc-file-badge-name {
    font-size: 13px;
    font-weight: 800;
    color: #1e1b4b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 250px;
}
.fanc-file-badge-type {
    font-size: 11px;
    color: #6366f1;
    font-weight: 700;
    text-transform: uppercase;
}

/* Live Camera Box */
.camera-box-wrap {
    display: none;
    border: 1px solid #cbd5e1;
    border-radius: 16px;
    overflow: hidden;
    background: #0f172a;
    position: relative;
}
#cameraVideo {
    width: 100%;
    height: 220px;
    object-fit: cover;
    display: block;
    background: #000;
}
.camera-controls {
    display: flex;
    gap: 8px;
    padding: 10px 14px;
    background: #1e293b;
    justify-content: center;
}
.cam-btn {
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cam-btn-capture {
    background: #10b981;
    color: white;
}
.cam-btn-stop {
    background: #ef4444;
    color: white;
}

/* Image Snapshot Preview */
.camera-preview-box {
    display: none;
    margin-top: 12px;
    position: relative;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}
.camera-preview-img {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
    display: block;
}

/* Submit Button */
.fanc-submit-btn {
    width: 100%;
    padding: 14px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 14.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.fanc-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
}

/* Published Bulletins Feed */
.fanc-feed-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.fanc-item {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    margin-bottom: 18px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.025);
    transition: all 0.2s ease;
    position: relative;
}
.fanc-item:hover {
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
    transform: translateY(-2px);
}

.fanc-badge-urgent {
    padding: 4px 12px;
    border-radius: 20px;
    background: #fef2f2;
    color: #dc2626;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #fecaca;
    letter-spacing: 0.3px;
}
.fanc-badge-important {
    padding: 4px 12px;
    border-radius: 20px;
    background: #fffbeb;
    color: #b45309;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #fde68a;
    letter-spacing: 0.3px;
}
.fanc-badge-normal {
    padding: 4px 12px;
    border-radius: 20px;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #c7d2fe;
    letter-spacing: 0.3px;
}
.fanc-target-pill {
    padding: 4px 12px;
    border-radius: 20px;
    background: #ecfdf5;
    color: #059669;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #a7f3d0;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Attachment Display in List (Supports PDF & Image) */
.circular-attachment-card {
    margin-top: 16px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    max-width: 520px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}
.circular-preview-image {
    width: 100%;
    max-height: 250px;
    object-fit: cover;
    display: block;
    cursor: pointer;
    transition: transform 0.2s ease;
}
.circular-preview-image:hover {
    transform: scale(1.01);
}

.pdf-attachment-banner {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border-bottom: 1px solid #fecdd3;
}
.pdf-attachment-icon {
    font-size: 28px;
}
.pdf-attachment-info strong {
    font-size: 14px;
    color: #9f1239;
    display: block;
}
.pdf-attachment-info span {
    font-size: 12px;
    color: #be123c;
}

.circular-card-actions {
    display: flex;
    gap: 8px;
    padding: 10px 14px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    align-items: center;
}
.btn-act-view {
    padding: 6px 14px;
    border-radius: 8px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: none;
    cursor: pointer;
}
.btn-act-view:hover {
    background: #3730a3;
}
.btn-act-download {
    padding: 6px 14px;
    border-radius: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-act-download:hover {
    background: #f1f5f9;
}

/* Modal for full screen preview */
.modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(5px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-box {
    max-width: 90vw;
    max-height: 90vh;
    background: white;
    border-radius: 20px;
    padding: 14px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
}
.modal-box img {
    max-width: 100%;
    max-height: 82vh;
    display: block;
    border-radius: 12px;
}
.modal-close-btn {
    position: absolute;
    top: -14px;
    right: -14px;
    width: 36px;
    height: 36px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    border: 2px solid white;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 10px rgba(0,0,0,0.25);
}
</style>

<!-- Hero Section -->
<div class="fanc-hero">
    <div class="fanc-hero-top">
        <div>
            <h1 class="fanc-hero-title">Campus & Course Notice Board 📢</h1>
            <p class="fanc-hero-sub">Publish targeted course announcements, academic notices, and official circular documents (PDF or High-Resolution Images) to your students.</p>
        </div>
    </div>

    <div class="fanc-metrics">
        <div class="fanc-metric-pill">
            <strong><?php echo $totalAnnouncements; ?></strong>
            <span>Total Published</span>
        </div>
        <div class="fanc-metric-pill">
            <strong style="color: #ef4444;"><?php echo $urgentCount; ?></strong>
            <span>Urgent Bulletins</span>
        </div>
        <div class="fanc-metric-pill">
            <strong style="color: #f59e0b;"><?php echo $importantCount; ?></strong>
            <span>Important Notices</span>
        </div>
        <div class="fanc-metric-pill">
            <strong style="color: #6366f1;"><?php echo count($facultyCourses); ?></strong>
            <span>Allocated Courses</span>
        </div>
    </div>
</div>

<div class="fanc-grid">
    <!-- Left Column: Create Form -->
    <div class="fanc-card">
        <div class="fanc-card-header">
            <h2 class="fanc-card-title">
                <span class="fanc-card-icon">✍️</span>
                <span>Create Announcement</span>
            </h2>
            <span style="font-size: 11.5px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 8px;">
                PDF & Image Ready
            </span>
        </div>

        <form action="announcements.php" method="POST" enctype="multipart/form-data" id="announcementForm">
            <?php echo csrfField(); ?>
            <!-- Hidden input to store base64 captured photo from camera -->
            <input type="hidden" name="camera_image_data" id="cameraImageData" value="">

            <div class="fanc-form-group">
                <label class="fanc-label">Target Audience (Course Restricted) *</label>
                <select name="target_course_id" class="fanc-select" id="targetCourseSelect" required>
                    <option value="">-- Select Allocated Course --</option>
                    <?php if (empty($facultyCourses)): ?>
                        <option value="" disabled>No teaching courses allocated yet</option>
                    <?php else: ?>
                        <?php foreach ($facultyCourses as $fc): ?>
                            <option value="<?php echo $fc['id']; ?>">
                                [<?php echo htmlspecialchars($fc['code']); ?>] <?php echo htmlspecialchars($fc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 5px;">
                    🔒 Secure Access: Only students enrolled in this course can view this circular.
                </small>
            </div>

            <div class="fanc-form-group">
                <label class="fanc-label">Notice Headline / Title *</label>
                <input type="text" name="title" class="fanc-input" required placeholder="e.g. End-Term Project Viva Schedule & Lab Guidelines">
            </div>

            <div class="fanc-form-group">
                <label class="fanc-label">Priority Alert Level *</label>
                <select name="priority" class="fanc-select">
                    <option value="Normal">📢 Normal Notice</option>
                    <option value="Important">⚠️ Important Notice</option>
                    <option value="Urgent">🚨 Urgent Alert</option>
                </select>
            </div>

            <!-- Content Option: Text -->
            <div class="fanc-form-group">
                <label class="fanc-label">Announcement Text Content (Optional if attaching circular)</label>
                <textarea name="content" id="textContent" rows="4" class="fanc-textarea" placeholder="Detail the announcement instructions, syllabus details, or exam rules..."></textarea>
            </div>

            <!-- Content Option: Circular Image or PDF -->
            <div class="fanc-form-group">
                <label class="fanc-label">Official Circular Attachment (PDF, JPG, PNG)</label>
                
                <div class="fanc-tab-bar">
                    <button type="button" class="fanc-tab-btn active" id="tabUploadBtn" onclick="switchAttachmentSource('upload')">
                        📁 Drag & Drop / Device
                    </button>
                    <button type="button" class="fanc-tab-btn" id="tabCameraBtn" onclick="switchAttachmentSource('camera')">
                        📷 Take via Camera
                    </button>
                </div>

                <!-- 1. Drag & Drop Upload Zone (Images & PDF) -->
                <div id="deviceUploadSection">
                    <div class="fanc-drop-zone" id="circularDropZone">
                        <span class="fanc-drop-icon">☁️</span>
                        <div class="fanc-drop-title">Drag & drop circular PDF or Image here</div>
                        <div class="fanc-drop-sub">
                            Saved inside: <strong>uploads/<?php echo htmlspecialchars($circularSubDir); ?>/</strong><br>
                            Supports <strong>PDF, JPG, PNG, WEBP</strong> (Max 25MB)
                        </div>
                        <button type="button" class="fanc-browse-btn">Browse Document / Image</button>

                        <input type="file" name="circular_file" id="circularFileInput" accept="application/pdf,image/*" style="display: none;">
                    </div>

                    <!-- Selected Attachment Badge -->
                    <div class="fanc-file-badge" id="fileSelectedBadge">
                        <div class="fanc-file-badge-left">
                            <span class="fanc-file-badge-icon" id="fileBadgeIcon">📄</span>
                            <div>
                                <div class="fanc-file-badge-name" id="fileBadgeName">circular.pdf</div>
                                <div class="fanc-file-badge-type" id="fileBadgeType">PDF Document</div>
                            </div>
                        </div>
                        <button type="button" onclick="clearSelectedAttachment()" style="background: none; border: none; color: #ef4444; font-size: 13px; font-weight: 700; cursor: pointer;">
                            ✕ Remove
                        </button>
                    </div>
                </div>

                <!-- 2. Live Camera Mode -->
                <div id="cameraSection" style="display: none;">
                    <div class="camera-box-wrap" id="cameraBoxWrap" style="display: block;">
                        <video id="cameraVideo" autoplay playsinline></video>
                        <canvas id="cameraCanvas" style="display: none;"></canvas>
                        <div class="camera-controls">
                            <button type="button" class="cam-btn cam-btn-capture" onclick="captureCameraPhoto()">
                                📸 Capture Photo
                            </button>
                            <button type="button" class="cam-btn cam-btn-stop" onclick="stopCameraStream()">
                                ⏹ Close Camera
                            </button>
                        </div>
                    </div>
                    <div id="cameraStatusMsg" style="margin-top: 6px; font-size: 12px; color: #64748b;">
                        Requesting camera permission...
                    </div>

                    <!-- Camera Snapshot Preview -->
                    <div class="camera-preview-box" id="cameraPreviewBox">
                        <img id="cameraPreviewImg" class="camera-preview-img" src="" alt="Captured Snapshot">
                        <div style="padding: 8px 12px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 12px; font-weight: 700; color: #10b981;">✅ Camera Snapshot Ready</span>
                            <button type="button" onclick="clearCameraSnapshot()" style="border: none; background: none; color: #ef4444; font-size: 12px; font-weight: 700; cursor: pointer;">
                                Remove
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="fanc-submit-btn" id="submitBtn">
                <span>🚀 Publish Notice to Students</span>
            </button>
        </form>
    </div>

    <!-- Right Column: Announcements Feed -->
    <div>
        <div class="fanc-feed-header">
            <h2 class="fanc-card-title" style="margin: 0;">
                <span class="fanc-card-icon">📰</span>
                <span>My Published Bulletins (<?php echo count($myAnnouncements); ?>)</span>
            </h2>
        </div>

        <?php if (empty($myAnnouncements)): ?>
            <div class="fanc-card" style="text-align: center; padding: 56px 20px; color: #94a3b8;">
                <div style="font-size: 46px; margin-bottom: 12px;">📭</div>
                <h3 style="margin: 0 0 6px 0; color: #475569; font-size: 17px; font-weight: 800;">No Bulletins Published Yet</h3>
                <p style="margin: 0; font-size: 14px;">Use the form on the left to issue your first academic announcement or circular PDF/image.</p>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($myAnnouncements as $a): 
                    $priority = $a['priority'] ?? 'Normal';
                    $hasCircular = !empty($a['circular_image']);
                    $circularPath = $hasCircular ? ('../' . htmlspecialchars($a['circular_image'])) : '';
                    $circularExt = $hasCircular ? strtolower(pathinfo($a['circular_image'], PATHINFO_EXTENSION)) : '';
                    $isPdf = ($circularExt === 'pdf');
                ?>
                    <div class="fanc-item">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 8px;">
                                    <?php if ($priority === 'Urgent'): ?>
                                        <span class="fanc-badge-urgent">🚨 URGENT</span>
                                    <?php elseif ($priority === 'Important'): ?>
                                        <span class="fanc-badge-important">⚠️ IMPORTANT</span>
                                    <?php else: ?>
                                        <span class="fanc-badge-normal">📢 BULLETIN</span>
                                    <?php endif; ?>

                                    <?php if (!empty($a['course_code'])): ?>
                                        <span class="fanc-target-pill">
                                            🔒 <?php echo htmlspecialchars($a['course_code']); ?>: <?php echo htmlspecialchars($a['course_name'] ?? ''); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #64748b; font-weight: 700;">🌐 University Wide</span>
                                    <?php endif; ?>
                                </div>
                                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($a['title']); ?></h3>
                            </div>

                            <div style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
                                    📅 <?php echo date('M d, Y', strtotime($a['created_at'])); ?>
                                </div>
                                <form method="POST" action="announcements.php" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this notice?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" style="background: none; border: none; font-size: 12px; color: #ef4444; font-weight: 700; cursor: pointer; padding: 0;">
                                        🗑️ Delete Notice
                                    </button>
                                </form>
                            </div>
                        </div>

                        <?php if (!empty($a['content'])): ?>
                            <p style="font-size: 14px; color: #334155; line-height: 1.6; margin: 0 0 14px 0;"><?php echo nl2br(htmlspecialchars($a['content'])); ?></p>
                        <?php endif; ?>

                        <!-- Attached Circular (PDF or Image) -->
                        <?php if ($hasCircular): ?>
                            <div class="circular-attachment-card">
                                <?php if ($isPdf): ?>
                                    <div class="pdf-attachment-banner">
                                        <span class="pdf-attachment-icon">📕</span>
                                        <div class="pdf-attachment-info">
                                            <strong>Official Circular Document (PDF)</strong>
                                            <span><?php echo htmlspecialchars(basename($a['circular_image'])); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo $circularPath; ?>" alt="Circular Notice" class="circular-preview-image" onclick="openModalView('<?php echo $circularPath; ?>')">
                                <?php endif; ?>

                                <div class="circular-card-actions">
                                    <?php if ($isPdf): ?>
                                        <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" target="_blank" class="btn-act-view">
                                            👁️ Open PDF in New Tab
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn-act-view" onclick="openModalView('../download.php?type=announcement&id=<?php echo $a['id']; ?>')">
                                            🔍 Zoom Image
                                        </button>
                                        <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" target="_blank" class="btn-act-view" style="background: #0f172a;">
                                            ↗ Open in Tab
                                        </a>
                                    <?php endif; ?>

                                    <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" download class="btn-act-download">
                                        📥 Download
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for full size view -->
<div class="modal-backdrop" id="imgModalBackdrop" onclick="closeModalView(event)">
    <div class="modal-box">
        <button type="button" class="modal-close-btn" onclick="closeModalView(null)">&times;</button>
        <img id="modalEnlargedImg" src="" alt="Enlarged Circular Image">
    </div>
</div>

<script>
// Tab switcher between drag & drop file upload vs camera
let videoStream = null;

function switchAttachmentSource(source) {
    const uploadBtn = document.getElementById('tabUploadBtn');
    const cameraBtn = document.getElementById('tabCameraBtn');
    const uploadSection = document.getElementById('deviceUploadSection');
    const cameraSection = document.getElementById('cameraSection');

    if (source === 'upload') {
        uploadBtn.classList.add('active');
        cameraBtn.classList.remove('active');
        uploadSection.style.display = 'block';
        cameraSection.style.display = 'none';
        stopCameraStream();
    } else {
        uploadBtn.classList.remove('active');
        cameraBtn.classList.add('active');
        uploadSection.style.display = 'none';
        cameraSection.style.display = 'block';
        startCameraStream();
    }
}

// Drag & Drop File Upload Implementation for Circulars
const dropZone = document.getElementById('circularDropZone');
const fileInput = document.getElementById('circularFileInput');
const fileBadge = document.getElementById('fileSelectedBadge');
const fileNameLabel = document.getElementById('fileBadgeName');
const fileTypeLabel = document.getElementById('fileBadgeType');
const fileBadgeIcon = document.getElementById('fileBadgeIcon');

if (dropZone && fileInput) {
    dropZone.addEventListener('click', (e) => {
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });

    fileInput.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
            renderSelectedFileInfo(fileInput.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files && files.length > 0) {
            fileInput.files = files;
            renderSelectedFileInfo(files[0]);
        }
    });
}

function renderSelectedFileInfo(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    fileNameLabel.textContent = file.name;

    if (ext === 'pdf') {
        fileBadgeIcon.textContent = '📕';
        fileBadgeType.textContent = 'PDF Document (' + (file.size / (1024*1024)).toFixed(2) + ' MB)';
    } else {
        fileBadgeIcon.textContent = '🖼️';
        fileBadgeType.textContent = 'Image File (' + (file.size / (1024*1024)).toFixed(2) + ' MB)';
    }

    fileBadge.style.display = 'flex';
    // Clear camera data
    document.getElementById('cameraImageData').value = '';
}

function clearSelectedAttachment() {
    if (fileInput) fileInput.value = '';
    if (fileBadge) fileBadge.style.display = 'none';
}

// Camera Capture Logic
async function startCameraStream() {
    const statusMsg = document.getElementById('cameraStatusMsg');
    const video = document.getElementById('cameraVideo');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusMsg.innerHTML = '<span style="color: #ef4444;">⚠️ Camera access is not supported by this browser.</span>';
        return;
    }

    try {
        statusMsg.innerText = 'Allow camera access in your browser prompt...';
        videoStream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width: { ideal: 1280 },
                height: { ideal: 720 }
            },
            audio: false
        });
        video.srcObject = videoStream;
        await video.play();
        statusMsg.innerHTML = '<span style="color: #10b981;">✅ Camera active. Click "Capture Photo" to snapshot.</span>';
    } catch (err) {
        console.error('Camera Error:', err);
        statusMsg.innerHTML = '<span style="color: #ef4444;">❌ Camera permission denied or device not found.</span>';
    }
}

function stopCameraStream() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
        videoStream = null;
    }
    const video = document.getElementById('cameraVideo');
    if (video) video.srcObject = null;
    const statusMsg = document.getElementById('cameraStatusMsg');
    if (statusMsg) statusMsg.innerText = 'Camera stopped.';
}

function captureCameraPhoto() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    if (!video || !video.videoWidth) {
        alert('Camera stream is not active or ready.');
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const base64Data = canvas.toDataURL('image/jpeg', 0.9);
    document.getElementById('cameraImageData').value = base64Data;

    // Reset file input to avoid conflict
    clearSelectedAttachment();

    // Show preview
    document.getElementById('cameraPreviewImg').src = base64Data;
    document.getElementById('cameraPreviewBox').style.display = 'block';

    // Stop camera stream after capture
    stopCameraStream();
    document.getElementById('cameraStatusMsg').innerHTML = '<span style="color: #10b981;">✅ Snapshot captured!</span>';
}

function clearCameraSnapshot() {
    document.getElementById('cameraImageData').value = '';
    document.getElementById('cameraPreviewImg').src = '';
    document.getElementById('cameraPreviewBox').style.display = 'none';
}

// Modal View
function openModalView(imgSrc) {
    const modal = document.getElementById('imgModalBackdrop');
    const img = document.getElementById('modalEnlargedImg');
    img.src = imgSrc;
    modal.style.display = 'flex';
}

function closeModalView(e) {
    if (!e || e.target.id === 'imgModalBackdrop' || e.target.classList.contains('modal-close-btn')) {
        document.getElementById('imgModalBackdrop').style.display = 'none';
    }
}

window.addEventListener('beforeunload', () => {
    stopCameraStream();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
