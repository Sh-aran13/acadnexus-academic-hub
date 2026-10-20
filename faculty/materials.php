<?php
// faculty/materials.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Get faculty courses
$cStmt = $pdo->prepare("
    SELECT c.id, c.code, c.name 
    FROM faculty_courses fc 
    JOIN courses c ON fc.course_id = c.id 
    WHERE fc.faculty_id = ?
");
$cStmt->execute([$facultyId]);
$courses = $cStmt->fetchAll();

// Handle Delete Material via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    requireCSRF();
    $delId = (int)($_POST['material_id'] ?? 0);

    // Retrieve file_path before deleting record
    $findStmt = $pdo->prepare("SELECT file_path FROM materials WHERE id = ? AND faculty_id = ?");
    $findStmt->execute([$delId, $facultyId]);
    $filePath = $findStmt->fetchColumn();

    if ($filePath) {
        $delStmt = $pdo->prepare("DELETE FROM materials WHERE id = ? AND faculty_id = ?");
        $delStmt->execute([$delId, $facultyId]);

        // Unlink physical file safely
        FileService::delete($filePath);

        setToast('success', 'Course material deleted successfully.');
        logActivity($userId, "Deleted course material ID $delId");
    } else {
        setToast('error', 'Material not found or unauthorized delete attempt.');
    }
    header('Location: materials.php');
    exit();
}

// Handle File Upload Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete_material')) {
    requireCSRF();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Authorization check: faculty must be allocated to course
    if (!isFacultyAssignedToCourse($facultyId, $courseId)) {
        setToast('error', 'Access denied: You are not assigned to manage materials for this course.');
        header('Location: materials.php');
        exit();
    }

    if ($courseId <= 0 || empty($title) || !isset($_FILES['material_file'])) {
        setToast('error', 'Please fill in all required fields and select a file.');
    } else {
        $deptName = !empty($faculty['department_name']) ? $faculty['department_name'] : 'General Department';
        $facultyName = preg_replace('/[^A-Za-z0-9]/', '', $faculty['first_name'] . $faculty['last_name']);
        if (empty($facultyName)) $facultyName = 'Faculty_' . $faculty['id'];
        $cleanDeptName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $deptName);
        $materialsSubDir = 'faculty/' . $cleanDeptName . '/' . $facultyName . '/materials';

        $uploadResult = FileService::upload($_FILES['material_file'], 'material', $materialsSubDir);
        if ($uploadResult['success']) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO materials (course_id, faculty_id, title, description, file_path, file_type, file_size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $courseId, $facultyId, $title, $description,
                    $uploadResult['file_path'], $uploadResult['file_type'], $uploadResult['file_size']
                ]);
                setToast('success', 'Study material uploaded successfully!');
                logActivity($userId, 'Uploaded material: ' . $title);
                header('Location: materials.php');
                exit();
            } catch (Exception $e) {
                // Remove uploaded file if DB insert fails
                FileService::delete($uploadResult['file_path']);
                error_log("Material DB insert error: " . $e->getMessage());
                setToast('error', 'Database error while saving material. Upload aborted.');
            }
        } else {
            setToast('error', $uploadResult['error']);
        }
    }
}

// Fetch uploaded materials
$mStmt = $pdo->prepare("
    SELECT m.*, c.code as course_code 
    FROM materials m 
    JOIN courses c ON m.course_id = c.id 
    WHERE m.faculty_id = ? 
    ORDER BY m.uploaded_at ASC, m.id ASC
");
$mStmt->execute([$facultyId]);
$materials = $mStmt->fetchAll();

$deptName = !empty($faculty['department_name']) ? $faculty['department_name'] : 'General Department';
$facultyName = preg_replace('/[^A-Za-z0-9]/', '', $faculty['first_name'] . $faculty['last_name']);
if (empty($facultyName)) $facultyName = 'Faculty_' . $faculty['id'];
$cleanDeptName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $deptName);

$pageTitle = 'Upload Study Materials';
$pageHeading = 'Course Materials Manager';
$activeNav = 'materials';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fm-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    align-items: start;
    width: 100%;
}
@media (max-width: 1024px) {
    .fm-grid {
        grid-template-columns: 1fr;
    }
}

.fm-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    padding: 24px;
    width: 100%;
    box-sizing: border-box;
}
.fm-card-header {
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f5f9;
}
.fm-card-title {
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.fm-form-group {
    margin-bottom: 16px;
}
.fm-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.fm-input, .fm-select {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    outline: none;
    transition: all 0.2s ease;
    box-sizing: border-box;
}
.fm-input:focus, .fm-select:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

/* DRAG & DROP ZONE UI MATCHING PROFILE PHOTO UPLOAD */
.fm-drop-zone {
    border: 2.5px dashed #cbd5e1;
    border-radius: 18px;
    padding: 22px 16px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}
.fm-drop-zone.dragover {
    border-color: #6366f1;
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.fm-drop-icon {
    font-size: 34px;
    margin-bottom: 4px;
    display: block;
}
.fm-drop-title {
    font-size: 13.5px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}
.fm-drop-sub {
    font-size: 11.5px;
    color: #64748b;
    margin-bottom: 10px;
    line-height: 1.3;
}
.fm-browse-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 10px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    border: none;
    cursor: pointer;
}

.fm-submit-btn {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 13.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transition: all 0.2s ease;
}
.fm-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
}

.fm-table-container {
    width: 100%;
    overflow-x: auto;
}
.fm-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 560px;
}
.fm-table th {
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
.fm-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}
.fm-table tr:last-child td {
    border-bottom: none;
}

.fm-course-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 11.5px;
    font-weight: 800;
    letter-spacing: 0.3px;
}

.fm-type-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
}

.fm-action-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-end;
    width: 100%;
}

.fm-btn-act {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
    white-space: nowrap;
    width: 96px;
    box-sizing: border-box;
}

.fm-btn-act.view {
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.22);
}
.fm-btn-act.view:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.32);
}

.fm-btn-act.download {
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
}
.fm-btn-act.download:hover {
    transform: translateY(-1px);
    background: #c7d2fe;
    color: #1e1b4b;
}

.fm-btn-act.delete {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.fm-btn-act.delete:hover {
    transform: translateY(-1px);
    background: #fee2e2;
    color: #991b1b;
}
</style>

<div class="fm-grid">
    <!-- Left Column: Upload Form -->
    <div class="fm-card">
        <div class="fm-card-header">
            <h3 class="fm-card-title">📤 Upload Course Material</h3>
        </div>

        <form action="materials.php" method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="fm-form-group">
                <label class="fm-label">Target Course *</label>
                <select name="course_id" class="fm-select" required>
                    <option value="">-- Choose Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fm-form-group">
                <label class="fm-label">Material Title *</label>
                <input type="text" name="title" class="fm-input" required placeholder="e.g. Lecture 04: Indexing & Query Optimization">
            </div>

            <div class="fm-form-group">
                <label class="fm-label">Short Summary</label>
                <input type="text" name="description" class="fm-input" placeholder="Overview of slides and topic references...">
            </div>

            <div class="fm-form-group">
                <label class="fm-label">Select Document File *</label>
                <div class="fm-drop-zone" id="fmDropZone">
                    <span class="fm-drop-icon">☁️</span>
                    <div class="fm-drop-title">Drag & Drop study material here</div>
                    <div class="fm-drop-sub">Saved inside: <strong>uploads/faculty/<?php echo htmlspecialchars($cleanDeptName . '/' . $facultyName . '/materials'); ?>/</strong> (Max file size: 25MB)</div>
                    <button type="button" class="fm-browse-btn">Browse Document File</button>

                    <input type="file" id="matFileInput" name="material_file" required style="display: none;">
                </div>
                <div id="fmFileNotice" style="display: none; font-size: 13px; color: #4f46e5; font-weight: 700; margin-top: 8px; text-align: center;">
                    📄 Selected: <span id="fmFileNameLabel">file.pdf</span>
                </div>
            </div>

            <button type="submit" class="fm-submit-btn">📂 Upload Material</button>
        </form>
    </div>

    <!-- Right Column: Published Materials List -->
    <div class="fm-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <h3 class="fm-card-title">📚 Published Materials (<?php echo count($materials); ?>)</h3>
        </div>

        <?php if (empty($materials)): ?>
            <div style="padding: 40px; text-align: center; color: #94a3b8;">
                <div style="font-size: 36px; margin-bottom: 8px;">📂</div>
                <p style="margin: 0; font-size: 14px;">No materials uploaded yet for your courses.</p>
            </div>
        <?php else: ?>
            <div class="fm-table-container">
                <table class="fm-table">
                    <thead>
                        <tr>
                            <th style="width: 42%;">Title & Notes</th>
                            <th style="width: 20%;">Course</th>
                            <th style="width: 13%;">Type</th>
                            <th style="width: 25%; text-align: right; padding-right: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $m): ?>
                            <tr>
                                <td>
                                    <strong style="font-size: 13.5px; color: #0f172a; display: block; margin-bottom: 2px;"><?php echo htmlspecialchars($m['title']); ?></strong>
                                    <div style="font-size: 11.5px; color: #64748b; line-height: 1.35;"><?php echo htmlspecialchars($m['description'] ?? 'No notes added.'); ?></div>
                                </td>
                                <td>
                                    <span class="fm-course-badge"><?php echo htmlspecialchars($m['course_code']); ?></span>
                                </td>
                                <td>
                                    <span class="fm-type-badge"><?php echo strtoupper($m['file_type'] ?? 'FILE'); ?></span>
                                </td>
                                <td style="text-align: right; padding-right: 20px;">
                                    <div class="fm-action-group">
                                        <a href="../download.php?type=material&id=<?php echo $m['id']; ?>&inline=1" target="_blank" rel="noopener noreferrer" class="fm-btn-act view">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            <span>View</span>
                                        </a>
                                        <form action="materials.php" method="POST" style="display: inline;" onsubmit="return confirm('Permanently delete this course material and file?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete_material">
                                            <input type="hidden" name="material_id" value="<?php echo $m['id']; ?>">
                                            <button type="submit" class="fm-btn-act delete" style="border: none; cursor: pointer; font-family: inherit;">
                                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
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
const dropZone = document.getElementById('fmDropZone');
const fileInput = document.getElementById('matFileInput');

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
            document.getElementById('fmFileNameLabel').textContent = fileInput.files[0].name;
            document.getElementById('fmFileNotice').style.display = 'block';
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
            document.getElementById('fmFileNameLabel').textContent = files[0].name;
            document.getElementById('fmFileNotice').style.display = 'block';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
