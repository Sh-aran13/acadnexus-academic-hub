<?php
// student/assignment_view.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

$assignmentId = (int)($_GET['id'] ?? 0);

// Fetch assignment details and verify student course enrollment
$stmt = $pdo->prepare("
    SELECT a.*, c.name as course_name, c.code as course_code,
           f.first_name as fac_fn, f.last_name as fac_ln,
           f.designation as fac_designation
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN course_enrollments ce ON a.course_id = ce.course_id
    LEFT JOIN faculty_profiles f ON a.faculty_id = f.id
    WHERE a.id = ? AND ce.student_id = ? AND a.is_published = TRUE
");
$stmt->execute([$assignmentId, $studentId]);
$assignment = $stmt->fetch();

if (!$assignment) {
    setToast('error', 'Assignment not found or you are not enrolled in this course.');
    header('Location: assignments.php');
    exit();
}

// Student ID number & Branch formatting for uploads path
// uploads/student/{branch}/{student_id}/assignments/
$studentIdCode = strtoupper(trim($student['student_id_number'] ?? ('ANX' . str_pad($studentId, 4, '0', STR_PAD_LEFT))));
$departmentName = !empty($student['department_name']) ? $student['department_name'] : 'Computer Science & Engineering';
$cleanDeptName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $departmentName);
$cleanStudentId = preg_replace('/[^A-Za-z0-9\-\_]/', '', $studentIdCode);
$submissionSubDir = 'student/' . $cleanDeptName . '/' . $cleanStudentId . '/assignments';

// Check existing submission
$subStmt = $pdo->prepare("SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
$subStmt->execute([$assignmentId, $studentId]);
$submission = $subStmt->fetch();

// Status & Deadline checks
$dueTimestamp = strtotime($assignment['due_date']);
$isPastDue = time() > $dueTimestamp;
$isGraded = ($submission && $submission['status'] === 'Graded');

// Handle Submission Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    if ($isGraded) {
        setToast('error', 'This assignment submission is already graded and locked.');
        header("Location: assignment_view.php?id=" . $assignmentId);
        exit();
    }

    $submissionText = trim($_POST['submission_text'] ?? '');
    $oldFilePath = $submission['file_path'] ?? null;
    $filePath = $oldFilePath;
    $newFileUploaded = false;

    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = FileService::upload($_FILES['submission_file'], 'submission', $submissionSubDir);
        if ($uploadResult['success']) {
            $filePath = $uploadResult['file_path'];
            $newFileUploaded = true;
        } else {
            setToast('error', 'File upload error: ' . $uploadResult['error']);
        }
    }

    if (empty($submissionText) && empty($filePath)) {
        setToast('error', 'Please provide submission notes or upload a document file.');
    } else {
        try {
            $pdo->beginTransaction();

            if ($submission) {
                // Update existing submission
                $uStmt = $pdo->prepare("
                    UPDATE assignment_submissions 
                    SET submission_text = ?, file_path = ?, submitted_at = NOW(), status = 'Submitted'
                    WHERE id = ? AND student_id = ?
                ");
                $uStmt->execute([$submissionText, $filePath, $submission['id'], $studentId]);
                
                // If a new file replaced an old file, safely unlink the old one
                if ($newFileUploaded && !empty($oldFilePath) && $oldFilePath !== $filePath) {
                    FileService::delete($oldFilePath);
                }

                $pdo->commit();
                setToast('success', 'Assignment submission updated successfully!');
            } else {
                // Insert new submission
                $iStmt = $pdo->prepare("
                    INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, status)
                    VALUES (?, ?, ?, ?, 'Submitted')
                ");
                $iStmt->execute([$assignmentId, $studentId, $submissionText, $filePath]);
                $pdo->commit();
                setToast('success', 'Assignment submitted successfully!');
            }

            logActivity($userId, 'Submitted assignment ID ' . $assignmentId);
            header("Location: assignment_view.php?id=" . $assignmentId);
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Clean up newly uploaded file if DB failed
            if ($newFileUploaded && !empty($filePath)) {
                FileService::delete($filePath);
            }
            error_log("Submission DB error: " . $e->getMessage());
            setToast('error', 'Failed to save submission. Please try again.');
        }
    }
}

$pageTitle = 'Assignment Workspace';
$pageHeading = 'Assignment Workspace';
$activeNav = 'assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* Modern Glassmorphic Workspace Theme */
.asv-wrapper {
    max-width: 1140px;
    margin: 0 auto;
    padding-bottom: 50px;
}

.asv-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.asv-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: #475569;
    text-decoration: none;
    background: #ffffff;
    padding: 9px 18px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    transition: all 0.2s ease;
}
.asv-back-btn:hover {
    color: #4f46e5;
    border-color: #cbd5e1;
    transform: translateX(-2px);
}

.asv-status-badge {
    padding: 7px 16px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.asv-status-pending { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
.asv-status-submitted { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.asv-status-graded { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.asv-status-overdue { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* Grid Layout */
.asv-grid {
    display: grid;
    grid-template-columns: 1.45fr 1fr;
    gap: 28px;
    align-items: start;
}
@media (max-width: 992px) {
    .asv-grid { grid-template-columns: 1fr; }
}

/* Cards */
.asv-card {
    background: #ffffff;
    border-radius: 22px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 6px 25px rgba(15, 23, 42, 0.04);
    padding: 30px;
    position: relative;
    overflow: hidden;
}

/* Hero Section */
.asv-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    color: #ffffff;
    border-radius: 22px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(30, 27, 75, 0.25);
    position: relative;
}
.asv-hero-tag {
    display: inline-block;
    padding: 5px 12px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(8px);
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 800;
    color: #c7d2fe;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}
.asv-hero-title {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.35;
    margin: 0 0 12px 0;
}
.asv-hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    font-size: 13px;
    color: #cbd5e1;
}
.asv-hero-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Instruction box */
.asv-section-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 14px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.asv-content-box {
    background: #f8fafc;
    border-radius: 16px;
    padding: 22px;
    border: 1px solid #e2e8f0;
    color: #334155;
    font-size: 14.5px;
    line-height: 1.7;
    margin-bottom: 22px;
}

/* Reference Attachment Box */
.asv-ref-card {
    background: #f1f5f9;
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border: 1px solid #e2e8f0;
    margin-top: 14px;
}
.asv-ref-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.asv-ref-icon {
    font-size: 26px;
}
.asv-ref-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.asv-btn-view {
    padding: 8px 16px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: #ffffff;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 3px 8px rgba(79, 70, 229, 0.22);
}
.asv-btn-view:hover {
    background: linear-gradient(135deg, #4338ca 0%, #312e81 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.32);
}
.asv-btn-download {
    padding: 8px 16px;
    border-radius: 10px;
    background: #e0e7ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
    font-size: 12.5px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.asv-btn-download:hover {
    background: #c7d2fe;
    color: #1e1b4b;
    transform: translateY(-1px);
}

/* Graded Banner */
.asv-grade-box {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    border: 1.5px solid #a7f3d0;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 4px 18px rgba(16, 185, 129, 0.08);
}
.asv-grade-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 12px;
}
.asv-score-pill {
    font-size: 26px;
    font-weight: 800;
    color: #047857;
}

/* Submission Form Components */
.asv-form-group {
    margin-bottom: 20px;
}
.asv-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 8px;
}
.asv-textarea {
    width: 100%;
    padding: 14px 16px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
    font-family: inherit;
    transition: all 0.2s ease;
}
.asv-textarea:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

/* DRAG & DROP ZONE UI IDENTICAL TO FACULTY MATERIALS */
.asv-drop-zone {
    border: 2.5px dashed #cbd5e1;
    border-radius: 18px;
    padding: 24px 18px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}
.asv-drop-zone.dragover {
    border-color: #4f46e5;
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.15);
}
.asv-drop-icon {
    font-size: 36px;
    margin-bottom: 4px;
    display: block;
}
.asv-drop-title {
    font-size: 14px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}
.asv-drop-sub {
    font-size: 11.5px;
    color: #64748b;
    margin-bottom: 10px;
    line-height: 1.4;
}
.asv-browse-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 18px;
    border-radius: 10px;
    background: #4f46e5;
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: background 0.2s ease;
}
.asv-browse-btn:hover {
    background: #4338ca;
}

.asv-submit-btn {
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
.asv-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.35);
    transform: translateY(-1px);
}
.asv-submit-btn.disabled {
    background: #94a3b8;
    box-shadow: none;
    cursor: not-allowed;
    transform: none;
}

@media (max-width: 768px) {
    .asv-topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 18px;
    }
    .asv-back-btn {
        width: 100%;
        justify-content: center;
    }
    .asv-hero {
        padding: 22px 18px;
        border-radius: 18px;
        margin-bottom: 20px;
    }
    .asv-hero-title {
        font-size: 20px;
    }
    .asv-card {
        padding: 20px 16px;
        border-radius: 18px;
    }
    .asv-ref-card {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
        padding: 14px;
    }
    .asv-ref-actions {
        width: 100%;
    }
    .asv-btn-view, .asv-btn-download {
        flex: 1;
        justify-content: center;
        padding: 10px;
    }
}
</style>

<div class="asv-wrapper">
    <!-- Top Bar Navigation -->
    <div class="asv-topbar">
        <a href="assignments.php" class="asv-back-btn">
            &larr; Back to Course Assignments
        </a>

        <div>
            <?php if ($submission && $submission['status'] === 'Graded'): ?>
                <span class="asv-status-badge asv-status-graded">🎯 Graded & Evaluated</span>
            <?php elseif ($submission): ?>
                <span class="asv-status-badge asv-status-submitted">✅ Submission Received</span>
            <?php elseif ($isPastDue): ?>
                <span class="asv-status-badge asv-status-overdue">⚠️ Past Deadline</span>
            <?php else: ?>
                <span class="asv-status-badge asv-status-pending">⏳ Pending Submission</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Overview -->
    <div class="asv-hero">
        <span class="asv-hero-tag"><?php echo htmlspecialchars($assignment['course_code']); ?> &bull; <?php echo htmlspecialchars($assignment['course_name']); ?></span>
        <h1 class="asv-hero-title"><?php echo htmlspecialchars($assignment['title']); ?></h1>
        <div class="asv-hero-meta">
            <span>👨‍🏫 Prof. <?php echo htmlspecialchars($assignment['fac_fn'] . ' ' . $assignment['fac_ln']); ?></span>
            <span>🏆 Max Marks: <strong><?php echo $assignment['max_marks']; ?></strong></span>
            <span>📅 Due Date: <strong><?php echo date('M d, Y – h:i A', $dueTimestamp); ?></strong></span>
        </div>
    </div>

    <!-- Main Workspace Layout -->
    <div class="asv-grid">
        <!-- Left: Assignment Instructions & Reference Materials -->
        <div>
            <div class="asv-card" style="margin-bottom: 24px;">
                <h3 class="asv-section-title">
                    <span>📋 Problem Statement & Instructions</span>
                </h3>
                <div class="asv-content-box">
                    <?php echo nl2br(htmlspecialchars($assignment['description'])); ?>
                </div>

                <?php if (!empty($assignment['attachment'])): 
                    $refPath = '../' . htmlspecialchars($assignment['attachment']);
                    $refName = basename($assignment['attachment']);
                ?>
                    <h3 class="asv-section-title" style="margin-top: 24px;">
                        <span>📎 Reference Document</span>
                    </h3>
                    <div class="asv-ref-card">
                        <div class="asv-ref-left">
                            <span class="asv-ref-icon">📄</span>
                            <div>
                                <strong style="font-size: 13.5px; color: #0f172a; display: block;"><?php echo htmlspecialchars($refName); ?></strong>
                                <span style="font-size: 11.5px; color: #64748b;">Provided by course instructor</span>
                            </div>
                        </div>
                        <div class="asv-ref-actions">
                            <a href="<?php echo $refPath; ?>" target="_blank" class="asv-btn-view" title="Open and View in new tab">
                                <span>👁️ View File</span>
                            </a>
                            <a href="<?php echo $refPath; ?>" download class="asv-btn-download" title="Download to device">
                                <span>📥 Download</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Graded Evaluation Box -->
            <?php if ($submission && $submission['status'] === 'Graded'): ?>
                <div class="asv-grade-box">
                    <div class="asv-grade-header">
                        <div>
                            <span style="font-size: 12px; font-weight: 800; color: #059669; text-transform: uppercase;">Final Score Awarded</span>
                            <div class="asv-score-pill">
                                <?php echo $submission['marks_obtained']; ?> <span style="font-size: 18px; color: #059669; font-weight: 600;">/ <?php echo $assignment['max_marks']; ?> Marks</span>
                            </div>
                        </div>
                        <span style="font-size: 12px; color: #065f46; font-weight: 700;">Verified By Faculty</span>
                    </div>

                    <div style="font-size: 13px; font-weight: 700; color: #065f46; margin-bottom: 4px;">Faculty Feedback Remarks:</div>
                    <div style="background: rgba(255,255,255,0.7); padding: 14px; border-radius: 12px; font-size: 13.5px; color: #064e3b; line-height: 1.6; border: 1px solid #a7f3d0;">
                        <?php echo nl2br(htmlspecialchars($submission['feedback'] ?? 'Great job completing this assignment.')); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Submission Form with Drag & Drop Upload Zone -->
        <div>
            <div class="asv-card">
                <h3 class="asv-section-title" style="margin-bottom: 18px;">
                    <span>📤 <?php echo ($submission) ? 'Your Submission' : 'Submit Assignment'; ?></span>
                </h3>

                <?php if ($submission): ?>
                    <div style="background: #f1f5f9; padding: 14px 16px; border-radius: 12px; margin-bottom: 20px; font-size: 12.5px; color: #334155;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                            <span>Last Submitted:</span>
                            <strong><?php echo date('M d, Y h:i A', strtotime($submission['submitted_at'])); ?></strong>
                        </div>
                        <?php if ($submission['file_path']): 
                            $subFileUrl = '../' . htmlspecialchars($submission['file_path']);
                            $subFileName = basename($submission['file_path']);
                        ?>
                            <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 170px;">
                                    📎 <strong><?php echo htmlspecialchars($subFileName); ?></strong>
                                </span>
                                <div style="display: flex; gap: 6px;">
                                    <a href="../download.php?type=submission&id=<?php echo $submission['id']; ?>&inline=1" target="_blank" style="padding: 4px 10px; background: #4f46e5; color: white; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none;">👁️ View</a>
                                    <a href="../download.php?type=submission&id=<?php echo $submission['id']; ?>" style="padding: 4px 10px; background: #e2e8f0; color: #334155; border-radius: 6px; font-size: 11.5px; font-weight: 700; text-decoration: none;">📥 Download</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form action="assignment_view.php?id=<?php echo $assignmentId; ?>" method="POST" enctype="multipart/form-data">
                    <?php echo csrfField(); ?>
                    <div class="asv-form-group">
                        <label class="asv-label">Solution Notes / Description</label>
                        <textarea name="submission_text" rows="4" class="asv-textarea" placeholder="Detail your solution, github repository links, or execution instructions..." <?php echo $isGraded ? 'disabled' : ''; ?>><?php echo htmlspecialchars($submission['submission_text'] ?? ''); ?></textarea>
                    </div>

                    <!-- Drag & Drop Upload Zone identical to Faculty Materials -->
                    <div class="asv-form-group">
                        <label class="asv-label">Attachment File (PDF, DOCX, ZIP)</label>
                        <div class="asv-drop-zone" id="asvDropZone">
                            <span class="asv-drop-icon">☁️</span>
                            <div class="asv-drop-title">Drag & Drop assignment file here</div>
                            <div class="asv-drop-sub">
                                Saved inside: <strong>uploads/<?php echo htmlspecialchars($submissionSubDir); ?>/</strong> (Max 25MB)
                            </div>
                            <button type="button" class="asv-browse-btn" <?php echo $isGraded ? 'disabled' : ''; ?>>Browse File</button>

                            <input type="file" id="asvFileInput" name="submission_file" style="display: none;" <?php echo $isGraded ? 'disabled' : ''; ?>>
                        </div>

                        <div id="asvFileNotice" style="display: none; font-size: 13px; color: #4f46e5; font-weight: 700; margin-top: 10px; text-align: center;">
                            📄 Selected: <span id="asvFileNameLabel">document.pdf</span>
                            <span onclick="clearAsvFile()" style="margin-left: 10px; color: #ef4444; cursor: pointer; text-decoration: underline; font-size: 12px;">Remove</span>
                        </div>
                    </div>

                    <?php if ($isGraded): ?>
                        <button type="button" class="asv-submit-btn disabled" disabled>
                            🔒 Submission Graded & Locked
                        </button>
                    <?php else: ?>
                        <button type="submit" class="asv-submit-btn">
                            🚀 <?php echo ($submission) ? 'Update Submission' : 'Submit Assignment'; ?>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Drag and Drop script matching Faculty Course Materials
const dropZone = document.getElementById('asvDropZone');
const fileInput = document.getElementById('asvFileInput');
const fileNotice = document.getElementById('asvFileNotice');
const fileNameLabel = document.getElementById('asvFileNameLabel');

if (dropZone && fileInput && !fileInput.disabled) {
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
            fileNameLabel.textContent = fileInput.files[0].name;
            fileNotice.style.display = 'block';
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
            fileNameLabel.textContent = files[0].name;
            fileNotice.style.display = 'block';
        }
    });
}

function clearAsvFile() {
    if (fileInput) {
        fileInput.value = '';
        fileNotice.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
