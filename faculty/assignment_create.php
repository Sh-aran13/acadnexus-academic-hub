<?php
// faculty/assignment_create.php
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

// Prepare faculty directory subpath for assignments
$deptName = !empty($faculty['department_name']) ? $faculty['department_name'] : 'General Department';
$facultyName = preg_replace('/[^A-Za-z0-9]/', '', ($faculty['first_name'] ?? '') . ($faculty['last_name'] ?? ''));
if (empty($facultyName)) $facultyName = 'Faculty_' . $faculty['id'];
$cleanDeptName = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $deptName);
$assignmentsSubDir = 'faculty/' . $cleanDeptName . '/' . $facultyName . '/assignments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $courseId = (int)($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $maxMarks = (int)($_POST['max_marks'] ?? 100);
    $dueDate = $_POST['due_date'] ?? '';

    if (!isFacultyAssignedToCourse($facultyId, $courseId)) {
        setToast('error', 'You are not assigned to instruct this course.');
    } elseif ($courseId <= 0 || empty($title) || empty($description) || empty($dueDate) || $maxMarks <= 0) {
        setToast('error', 'Please fill in all required fields with valid values.');
    } else {
        $attachment = null;
        $uploadError = false;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = FileService::upload($_FILES['attachment'], 'assignment', $facultyId);
            if ($uploadResult['success']) {
                $attachment = $uploadResult['file_path'];
            } else {
                setToast('error', $uploadResult['error']);
                $uploadError = true;
            }
        }

        if (!$uploadError) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assignments (course_id, faculty_id, title, description, max_marks, due_date, attachment, is_published)
                    VALUES (?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $stmt->execute([$courseId, $facultyId, $title, $description, $maxMarks, $dueDate, $attachment]);
                setToast('success', 'Assignment published successfully!');
                logActivity($userId, 'Created assignment: ' . $title);
                header('Location: assignments.php');
                exit();
            } catch (Exception $e) {
                if ($attachment) {
                    FileService::delete($attachment);
                }
                setToast('error', 'Database error: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Create Assignment';
$pageHeading = 'Publish Course Assignment';
$activeNav = 'assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fac-form-card {
    max-width: 860px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    padding: 36px 40px;
}
.fac-form-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 20px;
    margin-bottom: 28px;
    border-bottom: 1px solid #f1f5f9;
}
.fac-form-title {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.fac-form-group {
    margin-bottom: 22px;
}
.fac-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 8px;
}
.fac-input, .fac-select, .fac-textarea {
    width: 100%;
    padding: 13px 18px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
    color: #0f172a;
    box-sizing: border-box;
    font-family: inherit;
}
.fac-input:focus, .fac-select:focus, .fac-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

/* DRAG & DROP ZONE UI MATCHING COURSE MATERIALS */
.asgn-drop-zone {
    border: 2.5px dashed #cbd5e1;
    border-radius: 18px;
    padding: 26px 20px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.25s ease;
    cursor: pointer;
    position: relative;
}
.asgn-drop-zone.dragover {
    border-color: #6366f1;
    background: #eef2ff;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}
.asgn-drop-icon {
    font-size: 38px;
    margin-bottom: 6px;
    display: block;
}
.asgn-drop-title {
    font-size: 14.5px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}
.asgn-drop-sub {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
    line-height: 1.4;
}
.asgn-browse-btn {
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
.asgn-browse-btn:hover {
    background: #4338ca;
}

.fac-submit-btn {
    width: 100%;
    padding: 15px;
    border-radius: 14px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 15px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    transition: all 0.2s ease;
}
.fac-submit-btn:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 8px 22px rgba(79, 70, 229, 0.45);
    transform: translateY(-2px);
}
</style>

<div class="fac-form-card">
    <div class="fac-form-header">
        <h3 class="fac-form-title">✨ Create & Publish Assignment</h3>
        <a href="assignments.php" style="color: #64748b; font-size: 13px; font-weight: 700; text-decoration: none;">&larr; Back to Assignments</a>
    </div>

    <form action="assignment_create.php" method="POST" enctype="multipart/form-data">
        <?php echo csrfField(); ?>
        <div class="fac-form-group">
            <label class="fac-label">Target Course *</label>
            <select name="course_id" class="fac-select" required>
                <option value="">-- Choose Course Allocation --</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="fac-form-group">
            <label class="fac-label">Assignment Title *</label>
            <input type="text" name="title" class="fac-input" required placeholder="e.g. Problem Set 2: Relational Algebra & SQL Views">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;" class="fac-form-group">
            <div>
                <label class="fac-label">Maximum Evaluation Marks *</label>
                <input type="number" name="max_marks" value="100" class="fac-input" required>
            </div>
            <div>
                <label class="fac-label">Submission Deadline *</label>
                <input type="datetime-local" name="due_date" class="fac-input" required>
            </div>
        </div>

        <div class="fac-form-group">
            <label class="fac-label">Instructions & Detailed Problem Statement *</label>
            <textarea name="description" rows="5" required class="fac-textarea" placeholder="Detail the submission requirements, format rules, and grading rubrics..."></textarea>
        </div>

        <!-- Styled Drag & Drop Attachment Area matching Course Materials -->
        <div class="fac-form-group">
            <label class="fac-label">Reference Attachment (Optional)</label>
            <div class="asgn-drop-zone" id="asgnDropZone">
                <span class="asgn-drop-icon">☁️</span>
                <div class="asgn-drop-title">Drag & Drop assignment attachment here</div>
                <div class="asgn-drop-sub">
                    Saved inside: <strong>uploads/<?php echo htmlspecialchars($assignmentsSubDir); ?>/</strong> (Max file size: 25MB)
                </div>
                <button type="button" class="asgn-browse-btn">Browse Document File</button>

                <input type="file" id="asgnFileInput" name="attachment" style="display: none;">
            </div>
            <div id="asgnFileNotice" style="display: none; font-size: 13px; color: #4f46e5; font-weight: 700; margin-top: 10px; text-align: center;">
                📄 Selected: <span id="asgnFileNameLabel">document.pdf</span>
                <span onclick="clearAsgnFile()" style="margin-left: 10px; color: #ef4444; cursor: pointer; text-decoration: underline; font-size: 12px;">Remove</span>
            </div>
        </div>

        <button type="submit" class="fac-submit-btn">🚀 Publish Assignment to Students</button>
    </form>
</div>

<script>
// Drag and Drop Script matching Course Materials
const dropZone = document.getElementById('asgnDropZone');
const fileInput = document.getElementById('asgnFileInput');
const fileNotice = document.getElementById('asgnFileNotice');
const fileNameLabel = document.getElementById('asgnFileNameLabel');

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

function clearAsgnFile() {
    fileInput.value = '';
    fileNotice.style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
