<?php
// faculty/appointments.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$faculty = getUserProfile($userId, 'faculty');
$facultyId = $faculty['id'];

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id'])) {
    requireCSRF();
    $appId = (int)$_POST['appointment_id'];
    $allowedStatuses = ['Pending', 'Confirmed', 'Rejected', 'Completed', 'Cancelled'];
    $newStatus = in_array($_POST['status'] ?? '', $allowedStatuses) ? $_POST['status'] : 'Confirmed';
    $notes = trim($_POST['faculty_notes'] ?? '');

    try {
        // Check current appointment status
        $chkStmt = $pdo->prepare("SELECT status FROM appointments WHERE id = ? AND faculty_id = ?");
        $chkStmt->execute([$appId, $facultyId]);
        $currentApp = $chkStmt->fetch();

        if (!$currentApp) {
            setToast('error', 'Appointment not found or unauthorized.');
        } elseif ($currentApp['status'] === 'Completed') {
            // Once completed, it cannot be modified
            setToast('error', 'This appointment has already been completed and its status cannot be changed.');
        } else {
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = ?, faculty_notes = ? 
                WHERE id = ? AND faculty_id = ? AND status != 'Completed'
            ");
            $stmt->execute([$newStatus, $notes, $appId, $facultyId]);

            // Notify Student with parameterized query
            $appStmt = $pdo->prepare("
                SELECT a.student_id, sp.user_id, a.appointment_date 
                FROM appointments a 
                JOIN student_profiles sp ON a.student_id = sp.id 
                WHERE a.id = ?
            ");
            $appStmt->execute([$appId]);
            $appInfo = $appStmt->fetch();

            if ($appInfo) {
                $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, 'student/appointments.php')");
                $nStmt->execute([
                    $appInfo['user_id'],
                    "Appointment Update ($newStatus)",
                    "Professor updated your appointment request for " . date('M d', strtotime($appInfo['appointment_date'])) . " to $newStatus."
                ]);
            }

            setToast('success', "Appointment status updated to $newStatus!");
            logActivity($userId, "Updated appointment ID $appId status to $newStatus");
        }
    } catch (Exception $e) {
        setToast('error', 'Failed to update appointment: ' . $e->getMessage());
    }
}

// Fetch faculty's appointments
$aStmt = $pdo->prepare("
    SELECT a.*, sp.first_name, sp.last_name, sp.student_id_number, sp.reg_number, sp.program, u.email
    FROM appointments a
    JOIN student_profiles sp ON a.student_id = sp.id
    JOIN users u ON sp.user_id = u.id
    WHERE a.faculty_id = ?
    ORDER BY a.appointment_date DESC
");
$aStmt->execute([$facultyId]);
$appointments = $aStmt->fetchAll();

$pageTitle = 'Faculty Appointments';
$pageHeading = 'Student Office Hours & Appointments';
$activeNav = 'appointments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fapp-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4338ca 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fapp-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.fapp-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.fapp-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.fapp-table {
    width: 100%;
    border-collapse: collapse;
}
.fapp-table th {
    background: #f8fafc;
    padding: 14px 18px;
    text-align: left;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}
.fapp-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13.5px;
    color: #334155;
    vertical-align: middle;
}
.fapp-table tr:last-child td {
    border-bottom: none;
}

.fapp-badge-confirmed {
    padding: 4px 10px;
    border-radius: 8px;
    background: #ecfdf5;
    color: #059669;
    font-weight: 800;
    font-size: 12px;
    border: 1px solid #a7f3d0;
}
.fapp-badge-pending {
    padding: 4px 10px;
    border-radius: 8px;
    background: #fffbebfb;
    color: #b45309;
    font-weight: 800;
    font-size: 12px;
    border: 1px solid #fef3c7;
}
.fapp-badge-rejected {
    padding: 4px 10px;
    border-radius: 8px;
    background: #fef2f2;
    color: #dc2626;
    font-weight: 800;
    font-size: 12px;
    border: 1px solid #fca5a5;
}

.fapp-input {
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    outline: none;
    width: 170px;
}
.fapp-select {
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    outline: none;
    background: #ffffff;
}
.fapp-btn {
    padding: 8px 14px;
    border-radius: 10px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-weight: 800;
    font-size: 12.5px;
    border: none;
    cursor: pointer;
}
</style>

<div class="fapp-hero">
    <div>
        <h2>Student Appointment Requests</h2>
        <p>Review appointment bookings, manage office hours schedules, and leave meeting notes for students.</p>
    </div>
    <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">
        📅 <?php echo count($appointments); ?> Total Requests
    </span>
</div>

<div class="fapp-table-card">
    <?php if (empty($appointments)): ?>
        <div style="padding: 45px; text-align: center; color: #94a3b8;">
            <div style="font-size: 38px; margin-bottom: 8px;">📅</div>
            <p style="margin: 0; font-size: 14px;">No student appointment requests scheduled.</p>
        </div>
    <?php else: ?>
        <table class="fapp-table">
            <thead>
                <tr>
                    <th>Student Details</th>
                    <th>Date & Time Slot</th>
                    <th>Reason / Purpose</th>
                    <th>Status</th>
                    <th>Manage & Response Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $a): 
                    $roll = !empty($a['reg_number']) ? $a['reg_number'] : $a['student_id_number'];
                ?>
                    <tr>
                        <td>
                            <strong style="font-size: 14.5px; color: #0f172a;"><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></strong>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><?php echo htmlspecialchars($roll); ?></div>
                        </td>
                        <td>
                            <strong style="color: #0f172a;"><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></strong>
                            <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($a['time_slot']); ?></div>
                        </td>
                        <td>
                            <div style="max-width: 260px; line-height: 1.4; color: #334155; font-size: 13.5px;">
                                <?php echo htmlspecialchars($a['reason']); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'Confirmed'): ?>
                                <span class="fapp-badge-confirmed">✓ Confirmed</span>
                            <?php elseif ($a['status'] === 'Pending'): ?>
                                <span class="fapp-badge-pending">⏳ Pending</span>
                            <?php elseif ($a['status'] === 'Rejected'): ?>
                                <span class="fapp-badge-rejected">✕ Declined</span>
                            <?php elseif ($a['status'] === 'Completed'): ?>
                                <span style="padding: 4px 10px; border-radius: 8px; background: #ecfdf5; color: #047857; font-weight: 800; font-size: 12px; border: 1px solid #a7f3d0;">
                                    ✓ Completed
                                </span>
                            <?php else: ?>
                                <span style="padding: 4px 10px; border-radius: 8px; background: #eef2ff; color: #4f46e5; font-weight: 800; font-size: 12px;"><?php echo htmlspecialchars($a['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['status'] === 'Completed'): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 12.5px; color: #059669; font-weight: 700; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 12px; border-radius: 8px; display: inline-flex; align-items: center; gap: 5px;">
                                        <span>🔒</span> Completed (Locked)
                                    </span>
                                    <?php if (!empty($a['faculty_notes'])): ?>
                                        <span style="font-size: 12px; color: #64748b; font-style: italic;" title="Remarks: <?php echo htmlspecialchars($a['faculty_notes']); ?>">
                                            "<?php echo htmlspecialchars(strlen($a['faculty_notes']) > 30 ? substr($a['faculty_notes'], 0, 30) . '...' : $a['faculty_notes']); ?>"
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($a['status'] === 'Cancelled'): ?>
                                <div style="font-size: 12.5px; color: #94a3b8; font-style: italic;">
                                    Cancelled by student
                                </div>
                            <?php else: ?>
                                <form action="appointments.php" method="POST" style="display: flex; gap: 8px; align-items: center;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <input type="text" name="faculty_notes" value="<?php echo htmlspecialchars($a['faculty_notes'] ?? ''); ?>" placeholder="Meeting notes..." class="fapp-input">
                                    <select name="status" class="fapp-select">
                                        <option value="Confirmed" <?php echo ($a['status'] === 'Confirmed') ? 'selected' : ''; ?>>Confirm</option>
                                        <option value="Rejected" <?php echo ($a['status'] === 'Rejected') ? 'selected' : ''; ?>>Decline</option>
                                        <option value="Completed" <?php echo ($a['status'] === 'Completed') ? 'selected' : ''; ?>>Complete</option>
                                    </select>
                                    <button type="submit" class="fapp-btn">Save</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
