<?php
// admin/announcements.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$adminUser = getUserProfile($userId, 'admin');

// Upload destination path:
// G:\Projects\AcadNexus\uploads\admin\announcements\
$adminSubDir = 'admin/announcements';

// Handle Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    requireCSRF();
    $delId = (int)($_POST['delete_id'] ?? 0);
    $fetchStmt = $pdo->prepare("SELECT circular_image FROM announcements WHERE id = ?");
    $fetchStmt->execute([$delId]);
    $circ = $fetchStmt->fetchColumn();
    if (!empty($circ)) {
        FileService::delete($circ);
    }
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$delId]);
    setToast('success', 'System announcement deleted successfully.');
    logActivity($userId, "Deleted announcement ID $delId");
    header("Location: announcements.php");
    exit();
}

// Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    requireCSRF();
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $allowedPriorities = ['Normal', 'Important', 'Urgent'];
    $priority = in_array($_POST['priority'] ?? '', $allowedPriorities) ? $_POST['priority'] : 'Normal';
    $circularPath = null;
    $uploadError = false;

    // 1. Check for Camera Captured Photo (Base64 data URL)
    $cameraImageData = $_POST['camera_image_data'] ?? '';
    if (!empty($cameraImageData) && strpos($cameraImageData, 'data:image/') === 0) {
        $uploadResult = FileService::uploadBase64Image($cameraImageData, 'announcement', $userId);
        if ($uploadResult['success']) {
            $circularPath = $uploadResult['file_path'];
        } else {
            setToast('error', 'Camera snapshot error: ' . $uploadResult['error']);
            $uploadError = true;
        }
    }

    // 2. Check for File Upload from Device (PDF & Images supported)
    if (!$uploadError && empty($circularPath) && isset($_FILES['circular_file']) && $_FILES['circular_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = FileService::upload($_FILES['circular_file'], 'announcement', $userId);
        if ($uploadResult['success']) {
            $circularPath = $uploadResult['file_path'];
        } else {
            setToast('error', 'Circular upload failed: ' . $uploadResult['error']);
            $uploadError = true;
        }
    }

    if (!$uploadError) {
        if (empty($title)) {
            setToast('error', 'Announcement title is required.');
        } elseif (empty($content) && empty($circularPath)) {
            setToast('error', 'Please provide either announcement description or attach a circular document/image.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (author_id, title, content, priority, circular_image, publish_date) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_DATE)
                ");
                $stmt->execute([$userId, $title, $content, $priority, $circularPath]);
                setToast('success', 'Global campus announcement published successfully!');
                logActivity($userId, 'Published admin announcement: ' . $title);
                header("Location: announcements.php");
                exit();
            } catch (Exception $e) {
                if ($circularPath) {
                    FileService::delete($circularPath);
                }
                setToast('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

// Fetch all announcements with author and course info
$announcements = $pdo->query("
    SELECT a.*, u.username, u.role, c.code as course_code, c.name as course_name
    FROM announcements a 
    JOIN users u ON a.author_id = u.id 
    LEFT JOIN courses c ON a.target_course_id = c.id
    ORDER BY a.created_at DESC
")->fetchAll();

$totalCount = count($announcements);
$urgentCount = 0;
$importantCount = 0;
$normalCount = 0;
foreach ($announcements as $a) {
    $p = strtolower($a['priority'] ?? 'normal');
    if ($p === 'urgent alert' || $p === 'urgent') $urgentCount++;
    elseif ($p === 'important') $importantCount++;
    else $normalCount++;
}

$pageTitle = 'Campus Broadcasts & Circulars';
$pageHeading = 'Global Announcements & Circulars';
$activeNav = 'announcements';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Elegant Admin Announcements System */
.adm-anc-hero {
    background: linear-gradient(135deg, #090d16 0%, #1e1b4b 45%, #312e81 80%, #4338ca 100%);
    border-radius: 24px;
    padding: 34px 40px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 16px 36px -10px rgba(15, 23, 42, 0.4);
    position: relative;
    overflow: hidden;
}
.adm-anc-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.28) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.adm-anc-hero-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    position: relative;
    z-index: 1;
}
.adm-anc-title {
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px 0;
    letter-spacing: -0.5px;
}
.adm-anc-sub {
    font-size: 14px;
    color: #c7d2fe;
    margin: 0;
    max-width: 620px;
    line-height: 1.5;
}

/* Quick Metrics in Hero */
.adm-anc-metrics {
    display: flex;
    gap: 12px;
    margin-top: 24px;
    position: relative;
    z-index: 1;
    flex-wrap: wrap;
}
.adm-anc-metric-pill {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 14px;
    padding: 10px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.adm-anc-metric-pill strong {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
}
.adm-anc-metric-pill span {
    font-size: 12.5px;
    color: #cbd5e1;
    font-weight: 600;
}

/* Grid Layout */
.adm-anc-grid {
    display: grid;
    grid-template-columns: 460px 1fr;
    gap: 28px;
    align-items: start;
}
@media (max-width: 1080px) {
    .adm-anc-grid { grid-template-columns: 1fr; }
}

/* Glassmorphic White Container Cards */
.adm-card {
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 24px rgba(15, 23, 42, 0.035);
    padding: 28px;
    position: relative;
}
.adm-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    margin-bottom: 22px;
    border-bottom: 1px solid #f1f5f9;
}
.adm-card-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.adm-card-icon {
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
.adm-form-group {
    margin-bottom: 18px;
}
.adm-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 7px;
}
.adm-input, .adm-select, .adm-textarea {
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
.adm-input:focus, .adm-select:focus, .adm-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

/* Source Selection Segmented Tabs */
.adm-tab-bar {
    display: flex;
    gap: 6px;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 14px;
    margin-bottom: 14px;
}
.adm-tab-btn {
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
.adm-tab-btn.active {
    background: #ffffff;
    color: #4f46e5;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
}

/* DRAG & DROP ZONE (Supports Images AND PDFs) */
.adm-drop-zone {
    border: 2.5px dashed #cbd5e1;
    border-radius: 18px;
    padding: 24px 18px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}
.adm-drop-zone.dragover {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
}
.adm-drop-icon {
    font-size: 34px;
    margin-bottom: 6px;
    display: block;
}
.adm-drop-title {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}
.adm-drop-sub {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
    line-height: 1.4;
}
.adm-browse-btn {
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
.adm-browse-btn:hover {
    background: #4338ca;
}

/* Attachment Selected Preview Box */
.adm-file-badge {
    display: none;
    background: #eef2ff;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    padding: 12px 16px;
    margin-top: 12px;
    align-items: center;
    justify-content: space-between;
}
.adm-file-badge-left {
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}
.adm-file-badge-icon {
    font-size: 22px;
}
.adm-file-badge-name {
    font-size: 13px;
    font-weight: 800;
    color: #1e1b4b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 250px;
}
.adm-file-badge-type {
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
.adm-submit-btn {
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
.adm-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
}

/* Bulletins Feed Items */
.adm-feed-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.adm-item {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    margin-bottom: 18px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.025);
    transition: all 0.2s ease;
    position: relative;
}
.adm-item:hover {
    box-shadow: 0 8px 26px rgba(15, 23, 42, 0.06);
    border-color: #cbd5e1;
    transform: translateY(-2px);
}

.adm-badge-urgent {
    padding: 4px 12px;
    border-radius: 20px;
    background: #fef2f2;
    color: #dc2626;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #fecaca;
    letter-spacing: 0.3px;
}
.adm-badge-important {
    padding: 4px 12px;
    border-radius: 20px;
    background: #fffbeb;
    color: #b45309;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #fde68a;
    letter-spacing: 0.3px;
}
.adm-badge-normal {
    padding: 4px 12px;
    border-radius: 20px;
    background: #eef2ff;
    color: #4f46e5;
    font-weight: 800;
    font-size: 11px;
    border: 1px solid #c7d2fe;
    letter-spacing: 0.3px;
}

/* Attachment Display in List (Supports PDF & Image) */
.adm-circular-card {
    margin-top: 16px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    max-width: 520px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}
.adm-circular-image {
    width: 100%;
    max-height: 250px;
    object-fit: cover;
    display: block;
    cursor: pointer;
    transition: transform 0.2s ease;
}
.adm-circular-image:hover {
    transform: scale(1.01);
}

.adm-pdf-banner {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%);
    border-bottom: 1px solid #fecdd3;
}
.adm-pdf-icon {
    font-size: 28px;
}
.adm-pdf-info strong {
    font-size: 14px;
    color: #9f1239;
    display: block;
}
.adm-pdf-info span {
    font-size: 12px;
    color: #be123c;
}

.adm-circular-actions {
    display: flex;
    gap: 8px;
    padding: 10px 14px;
    background: #f8fafc;
    border-top: 1px solid #f1f5f9;
    align-items: center;
}
.adm-btn-view {
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
.adm-btn-view:hover {
    background: #3730a3;
}
.adm-btn-download {
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
.adm-btn-download:hover {
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
<div class="adm-anc-hero">
    <div class="adm-anc-hero-top">
        <div>
            <h1 class="adm-anc-title">University Broadcasts & Circulars 📣</h1>
            <p class="adm-anc-sub">Publish high-priority campus notices, examination schedules, and official university circulars (PDF or Images) visible to all students and faculty members.</p>
        </div>
    </div>

    <div class="adm-anc-metrics">
        <div class="adm-anc-metric-pill">
            <strong><?php echo $totalCount; ?></strong>
            <span>Active Broadcasts</span>
        </div>
        <div class="adm-anc-metric-pill">
            <strong style="color: #ef4444;"><?php echo $urgentCount; ?></strong>
            <span>Urgent Alerts</span>
        </div>
        <div class="adm-anc-metric-pill">
            <strong style="color: #f59e0b;"><?php echo $importantCount; ?></strong>
            <span>Important Notices</span>
        </div>
        <div class="adm-anc-metric-pill">
            <strong style="color: #6366f1;"><?php echo $normalCount; ?></strong>
            <span>General Bulletins</span>
        </div>
    </div>
</div>

<div class="adm-anc-grid">
    <!-- Left Column: Create Form -->
    <div class="adm-card">
        <div class="adm-card-header">
            <h2 class="adm-card-title">
                <span class="adm-card-icon">📢</span>
                <span>Publish Announcement</span>
            </h2>
            <span style="font-size: 11.5px; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 8px;">
                PDF & Image Ready
            </span>
        </div>

        <form action="announcements.php" method="POST" enctype="multipart/form-data" id="adminAnnouncementForm">
            <?php echo csrfField(); ?>
            <!-- Hidden input to store base64 captured photo from camera -->
            <input type="hidden" name="camera_image_data" id="cameraImageData" value="">

            <div class="adm-form-group">
                <label class="adm-label">Broadcast Headline / Title *</label>
                <input type="text" name="title" class="adm-input" required placeholder="e.g. End-Semester Examination Schedule & Regulations">
            </div>

            <div class="adm-form-group">
                <label class="adm-label">Priority Alert Level *</label>
                <select name="priority" class="adm-select" required>
                    <option value="Normal">📢 Normal Bulletin</option>
                    <option value="Important">⚠️ Important Notice</option>
                    <option value="Urgent Alert">🚨 Urgent Alert</option>
                </select>
            </div>

            <div class="adm-form-group">
                <label class="adm-label">Broadcast Text Content (Optional if attaching circular)</label>
                <textarea name="content" id="textContent" rows="4" class="adm-textarea" placeholder="Detail the official guidelines, circular contents, or instructions..."></textarea>
            </div>

            <!-- Official Circular Attachment: Drag & Drop Zone + Camera -->
            <div class="adm-form-group">
                <label class="adm-label">Official Circular Attachment (PDF, JPG, PNG)</label>

                <div class="adm-tab-bar">
                    <button type="button" class="adm-tab-btn active" id="tabUploadBtn" onclick="switchAttachmentSource('upload')">
                        📁 Drag & Drop / Device
                    </button>
                    <button type="button" class="adm-tab-btn" id="tabCameraBtn" onclick="switchAttachmentSource('camera')">
                        📷 Take via Camera
                    </button>
                </div>

                <!-- 1. Drag & Drop Upload Zone (Images & PDF) -->
                <div id="deviceUploadSection">
                    <div class="adm-drop-zone" id="circularDropZone">
                        <span class="adm-drop-icon">☁️</span>
                        <div class="adm-drop-title">Drag & drop circular PDF or Image here</div>
                        <div class="adm-drop-sub">
                            Saved inside: <strong>uploads/<?php echo htmlspecialchars($adminSubDir); ?>/</strong><br>
                            Supports <strong>PDF, JPG, PNG, WEBP</strong> (Max 25MB)
                        </div>
                        <button type="button" class="adm-browse-btn">Browse Document / Image</button>

                        <input type="file" name="circular_file" id="circularFileInput" accept="application/pdf,image/*" style="display: none;">
                    </div>

                    <!-- Selected Attachment Badge -->
                    <div class="adm-file-badge" id="fileSelectedBadge">
                        <div class="adm-file-badge-left">
                            <span class="adm-file-badge-icon" id="fileBadgeIcon">📄</span>
                            <div>
                                <div class="adm-file-badge-name" id="fileBadgeName">circular.pdf</div>
                                <div class="adm-file-badge-type" id="fileBadgeType">PDF Document</div>
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

            <button type="submit" class="adm-submit-btn" id="submitBtn">
                <span>📣 Broadcast System Announcement</span>
            </button>
        </form>
    </div>

    <!-- Right Column: Broadcast Feed -->
    <div>
        <div class="adm-feed-header">
            <h2 class="adm-card-title" style="margin: 0;">
                <span class="adm-card-icon">📰</span>
                <span>Active University Bulletins (<?php echo count($announcements); ?>)</span>
            </h2>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="adm-card" style="text-align: center; padding: 56px 20px; color: #94a3b8;">
                <div style="font-size: 46px; margin-bottom: 12px;">📭</div>
                <h3 style="margin: 0 0 6px 0; color: #475569; font-size: 17px; font-weight: 800;">No Bulletins Published Yet</h3>
                <p style="margin: 0; font-size: 14px;">Publish an announcement on the left to broadcast it university-wide.</p>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($announcements as $a): 
                    $prio = strtolower($a['priority'] ?? 'normal');
                    $badgeClass = 'adm-badge-normal';
                    $prioIcon = '📢';
                    if ($prio === 'urgent' || $prio === 'urgent alert') {
                        $badgeClass = 'adm-badge-urgent';
                        $prioIcon = '🚨';
                    } elseif ($prio === 'important') {
                        $badgeClass = 'adm-badge-important';
                        $prioIcon = '⚠️';
                    }

                    $hasCircular = !empty($a['circular_image']);
                    $circularPath = $hasCircular ? ('../' . htmlspecialchars($a['circular_image'])) : '';
                    $circularExt = $hasCircular ? strtolower(pathinfo($a['circular_image'], PATHINFO_EXTENSION)) : '';
                    $isPdf = ($circularExt === 'pdf');
                ?>
                    <div class="adm-item">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 8px;">
                                    <span class="<?php echo $badgeClass; ?>">
                                        <?php echo $prioIcon . ' ' . htmlspecialchars($a['priority']); ?>
                                    </span>

                                    <?php if (!empty($a['course_code'])): ?>
                                        <span style="padding: 4px 12px; border-radius: 20px; background: #ecfdf5; color: #059669; font-weight: 800; font-size: 11px; border: 1px solid #a7f3d0;">
                                            🔒 <?php echo htmlspecialchars($a['course_code']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #64748b; font-weight: 700; background: #f8fafc; padding: 4px 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                            🌐 Campus-Wide Broadcast
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($a['title']); ?></h3>
                            </div>

                            <div style="text-align: right;">
                                <div style="font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
                                    📅 <?php echo date('M d, Y', strtotime($a['created_at'])); ?>
                                </div>
                                <form method="POST" action="announcements.php" style="display:inline; margin:0;" onsubmit="return confirm('Delete this announcement permanently?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_id" value="<?php echo $a['id']; ?>">
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
                            <div class="adm-circular-card">
                                <?php if ($isPdf): ?>
                                    <div class="adm-pdf-banner">
                                        <span class="adm-pdf-icon">📕</span>
                                        <div class="adm-pdf-info">
                                            <strong>Official Circular Document (PDF)</strong>
                                            <span><?php echo htmlspecialchars(basename($a['circular_image'])); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <img src="<?php echo $circularPath; ?>" alt="Circular Notice" class="adm-circular-image" onclick="openModalView('../download.php?type=announcement&id=<?php echo $a['id']; ?>')">
                                <?php endif; ?>

                                <div class="adm-circular-actions">
                                    <?php if ($isPdf): ?>
                                        <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" target="_blank" class="adm-btn-view">
                                            👁️ Open PDF in New Tab
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="adm-btn-view" onclick="openModalView('../download.php?type=announcement&id=<?php echo $a['id']; ?>')">
                                            🔍 Zoom Image
                                        </button>
                                        <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" target="_blank" class="adm-btn-view" style="background: #0f172a;">
                                            ↗ Open in Tab
                                        </a>
                                    <?php endif; ?>

                                    <a href="../download.php?type=announcement&id=<?php echo $a['id']; ?>" download class="adm-btn-download">
                                        📥 Download
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; flex-wrap: wrap; gap: 8px;">
                            <span>Issued By: <strong><?php echo ucfirst($a['role']); ?> (<?php echo htmlspecialchars($a['username']); ?>)</strong></span>
                            <span>Target: <strong><?php echo !empty($a['course_code']) ? 'Course Students' : 'All Students & Faculty'; ?></strong></span>
                        </div>
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
