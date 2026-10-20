<?php
// faculty/assignments.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Handle assignment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    requireCSRF();
    $assignmentId = (int)($_POST['assignment_id'] ?? 0);
    
    // Verify faculty owns this assignment
    $checkStmt = $pdo->prepare("SELECT id, attachment, title FROM assignments WHERE id = ? AND faculty_id = ?");
    $checkStmt->execute([$assignmentId, $facultyId]);
    $assign = $checkStmt->fetch();
    
    if (!$assign) {
        setToast('error', 'Assignment not found or access denied.');
    } else {
        try {
            // Delete attachment if present
            if (!empty($assign['attachment'])) {
                FileService::delete($assign['attachment']);
            }
            // Delete submissions attachments
            $subStmt = $pdo->prepare("SELECT file_path FROM assignment_submissions WHERE assignment_id = ?");
            $subStmt->execute([$assignmentId]);
            $subs = $subStmt->fetchAll();
            foreach ($subs as $sub) {
                if (!empty($sub['file_path'])) {
                    FileService::delete($sub['file_path']);
                }
            }
            
            $delStmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
            $delStmt->execute([$assignmentId]);
            setToast('success', 'Assignment "' . htmlspecialchars($assign['title']) . '" and related files deleted successfully.');
            logActivity($userId, 'Deleted assignment ID: ' . $assignmentId);
        } catch (Exception $e) {
            setToast('error', 'Failed to delete assignment: ' . $e->getMessage());
        }
    }
    header('Location: assignments.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, c.code as course_code, c.name as course_name,
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as total_submissions,
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'Submitted') as pending_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.faculty_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$facultyId]);
$assignments = $stmt->fetchAll();

$pageTitle = 'Manage Assignments';
$pageHeading = 'Assignment Management Hub';
$activeNav = 'assignments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fa-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4338ca 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}
.fa-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.fa-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}
.fa-create-btn {
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
.fa-create-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(255,255,255,0.4);
}

.fa-search-bar {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 16px 24px;
    margin-bottom: 28px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.02);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.fa-search-input {
    flex: 1;
    max-width: 400px;
    padding: 12px 20px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
}
.fa-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.fa-card-table {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.fa-table {
    width: 100%;
    border-collapse: collapse;
}
.fa-table th {
    background: #f8fafc;
    padding: 14px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.fa-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
    vertical-align: middle;
}
.fa-table tr:last-child td {
    border-bottom: none;
}

.fa-badge-code {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 12px;
    font-weight: 800;
}
.fa-badge-sub {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #059669;
    font-size: 12px;
    font-weight: 800;
}
.fa-badge-pending {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    background: #fffbebfb;
    color: #b45309;
    font-size: 12px;
    font-weight: 800;
    border: 1px solid #fef3c7;
}
</style>

<div class="fa-hero">
    <div>
        <h2>Faculty Assignments Hub</h2>
        <p>Create new coursework assignments, monitor student submissions, and grade problem sets.</p>
    </div>
    <a href="assignment_create.php" class="fa-create-btn">
        <span>➕ Create New Assignment</span>
    </a>
</div>

<div class="fa-search-bar">
    <input type="text" id="facAssignSearch" class="fa-search-input" onkeyup="filterTable('facAssignSearch', 'facAssignTable')" placeholder="🔍 Search assignments by title or course...">
    <span style="font-size: 13.5px; font-weight: 700; color: #64748b;">Total Published: <?php echo count($assignments); ?></span>
</div>

<div class="fa-card-table">
    <?php if (empty($assignments)): ?>
        <div style="padding: 50px; text-align: center; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 8px;">📝</div>
            <h4 style="margin: 0 0 4px 0; color: #475569; font-size: 18px;">No Assignments Created Yet</h4>
            <p style="margin: 0; font-size: 14px;">Click "Create New Assignment" to publish coursework for your students.</p>
        </div>
    <?php else: ?>
        <table class="fa-table" id="facAssignTable">
            <thead>
                <tr>
                    <th>Assignment Title & Details</th>
                    <th>Course Code</th>
                    <th>Deadline Date</th>
                    <th>Max Points</th>
                    <th>Submissions Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td>
                            <strong style="font-size: 15px; color: #0f172a;"><?php echo htmlspecialchars($a['title']); ?></strong>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars(substr($a['description'], 0, 75)) . '...'; ?></div>
                        </td>
                        <td><span class="fa-badge-code"><?php echo htmlspecialchars($a['course_code']); ?></span></td>
                        <td style="font-weight: 600; color: #334155;"><?php echo date('M d, Y • H:i', strtotime($a['due_date'])); ?></td>
                        <td><span style="font-weight: 800; color: #0f172a;"><?php echo (int)$a['max_marks']; ?> pts</span></td>
                        <td>
                            <span class="fa-badge-sub"><?php echo (int)$a['total_submissions']; ?> Received</span>
                            <?php if ($a['pending_count'] > 0): ?>
                                <span class="fa-badge-pending" style="margin-left: 6px;"><?php echo (int)$a['pending_count']; ?> Pending Review</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <a href="assignment_submissions.php?assignment_id=<?php echo $a['id']; ?>" style="padding: 7px 12px; border-radius: 8px; background: linear-gradient(135deg, #4f46e5, #4338ca); color: #ffffff; font-weight: 700; font-size: 12px; text-decoration: none;">
                                    Submissions
                                </a>
                                <?php if (!empty($a['attachment'])): ?>
                                    <a href="../download.php?type=assignment&id=<?php echo $a['id']; ?>" title="Download Assignment Reference" style="padding: 7px 10px; border-radius: 8px; background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: 12px; text-decoration: none;">
                                        📎
                                    </a>
                                <?php endif; ?>
                                <form method="POST" action="assignments.php" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this assignment and all submissions?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="assignment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" style="padding: 7px 10px; border-radius: 8px; background: #fee2e2; color: #dc2626; font-weight: 700; font-size: 12px; border: none; cursor: pointer;">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function filterTable(inputId, tableId) {
    var input = document.getElementById(inputId);
    var filter = input.value.toUpperCase();
    var table = document.getElementById(tableId);
    var tr = table.getElementsByTagName("tr");
    for (var i = 1; i < tr.length; i++) {
        var txtValue = tr[i].textContent || tr[i].innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
        } else {
            tr[i].style.display = "none";
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
