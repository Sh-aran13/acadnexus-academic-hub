<?php
// student/appointments.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$student = getUserProfile($userId, 'student');
$studentId = $student['id'];

$preselectedFacultyId = (int)($_GET['faculty_id'] ?? 0);

// Handle Booking & Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $cancelId = (int)($_POST['appointment_id'] ?? 0);
        $checkStmt = $pdo->prepare("SELECT id, status FROM appointments WHERE id = ? AND student_id = ?");
        $checkStmt->execute([$cancelId, $studentId]);
        $appRecord = $checkStmt->fetch();
        if ($appRecord) {
            if ($appRecord['status'] === 'Completed') {
                setToast('error', 'Completed appointments cannot be cancelled.');
            } else {
                $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND status != 'Completed'")->execute([$cancelId]);
                setToast('success', 'Appointment cancelled successfully.');
                logActivity($userId, 'Cancelled appointment ID ' . $cancelId);
            }
        } else {
            setToast('error', 'Appointment not found.');
        }
        header("Location: appointments.php");
        exit();
    }

    $facId = (int)($_POST['faculty_id'] ?? 0);
    $appDate = $_POST['appointment_date'] ?? '';
    $timeSlot = trim($_POST['time_slot'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if ($facId <= 0 || empty($appDate) || empty($timeSlot) || empty($reason)) {
        setToast('error', 'Please fill in all booking fields.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (student_id, faculty_id, appointment_date, time_slot, reason, status)
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ");
            $stmt->execute([$studentId, $facId, $appDate, $timeSlot, $reason]);
            setToast('success', 'Faculty appointment request submitted successfully!');
            logActivity($userId, 'Booked appointment with faculty ID ' . $facId);
            header("Location: appointments.php");
            exit();
        } catch (Exception $e) {
            setToast('error', 'Database error: ' . $e->getMessage());
        }
    }
}

// Fetch faculty dropdown
$facList = $pdo->query("SELECT id, first_name, last_name, designation, office_room FROM faculty_profiles ORDER BY first_name ASC")->fetchAll();

// Fetch student's appointment history
$appStmt = $pdo->prepare("
    SELECT a.*, f.first_name as fac_fn, f.last_name as fac_ln, f.office_room
    FROM appointments a
    JOIN faculty_profiles f ON a.faculty_id = f.id
    WHERE a.student_id = ?
    ORDER BY a.appointment_date DESC
");
$appStmt->execute([$studentId]);
$myAppointments = $appStmt->fetchAll();

// Statistics
$totalApps = count($myAppointments);
$confirmedApps = 0;
$pendingApps = 0;
$rejectedApps = 0;

foreach ($myAppointments as $a) {
    $st = strtolower($a['status']);
    if ($st === 'confirmed') $confirmedApps++;
    elseif ($st === 'pending') $pendingApps++;
    elseif ($st === 'rejected') $rejectedApps++;
}

$pageTitle = 'Faculty Appointments';
$pageHeading = 'Book & Track Faculty Appointments';
$activeNav = 'appointments';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.apt-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
    border-radius: 20px;
    padding: 30px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.2);
    position: relative;
    overflow: hidden;
}
.apt-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0) 70%);
    border-radius: 50%;
}
.apt-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-top: 22px;
}
.apt-stat-card {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.apt-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: rgba(255, 255, 255, 0.15);
}
.apt-stat-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
}
.apt-stat-lbl {
    font-size: 11.5px;
    opacity: 0.85;
    font-weight: 500;
    margin-top: 2px;
}

.apt-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px;
    align-items: start;
}
@media (max-width: 1024px) {
    .apt-grid {
        grid-template-columns: 1fr;
    }
}

.apt-card-box {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}

.apt-form-group {
    margin-bottom: 18px;
}
.apt-form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}
.apt-form-input, .apt-form-select, .apt-form-textarea {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
}
.apt-form-input:focus, .apt-form-select:focus, .apt-form-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.apt-btn-submit {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
    transition: all 0.2s ease;
}
.apt-btn-submit:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
}

/* Tabs */
.apt-tabs {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 18px;
}
.apt-tab-btn {
    border: none;
    background: transparent;
    padding: 7px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
}
.apt-tab-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.apt-item-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 20px;
    margin-bottom: 14px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.apt-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0,0,0,0.06);
}

.apt-badge-confirmed { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.apt-badge-pending { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
.apt-badge-rejected { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

@media (max-width: 768px) {
    .apt-hero {
        padding: 24px 20px;
        border-radius: 20px;
        margin-bottom: 20px;
    }
    .apt-hero h2 {
        font-size: 22px !important;
    }
    .apt-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
        margin-top: 18px;
    }
    .apt-stat-card {
        padding: 12px 16px;
    }
    .apt-card-box {
        padding: 20px 16px;
        border-radius: 18px;
    }
    .apt-tabs {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
        padding: 4px;
    }
    .apt-tab-btn {
        flex: 1;
        padding: 9px 12px;
        font-size: 12px;
        text-align: center;
    }
    .apt-form-input, .apt-form-select, .apt-form-textarea {
        font-size: 14px;
        padding: 12px 14px;
    }
    .apt-btn-submit {
        padding: 14px;
    }
}
</style>

<div class="apt-hero">
    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 4px;">🤝 Academic Advisory & Mentorship</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 6px 0;">Faculty Appointments</h2>
    <p style="font-size: 13.5px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Schedule 1-on-1 consultation sessions with faculty members, clear academic doubts, and track meeting confirmations in real time.</p>

    <div class="apt-stats-grid">
        <div class="apt-stat-card">
            <div class="apt-stat-icon">📅</div>
            <div>
                <div class="apt-stat-val"><?php echo $totalApps; ?></div>
                <div class="apt-stat-lbl">Total Requests</div>
            </div>
        </div>
        <div class="apt-stat-card">
            <div class="apt-stat-icon" style="background: rgba(16, 185, 129, 0.25);">✅</div>
            <div>
                <div class="apt-stat-val"><?php echo $confirmedApps; ?></div>
                <div class="apt-stat-lbl">Confirmed Sessions</div>
            </div>
        </div>
        <div class="apt-stat-card">
            <div class="apt-stat-icon" style="background: rgba(245, 158, 11, 0.25);">⏳</div>
            <div>
                <div class="apt-stat-val"><?php echo $pendingApps; ?></div>
                <div class="apt-stat-lbl">Pending Review</div>
            </div>
        </div>
    </div>
</div>

<div class="apt-grid">
    <!-- BOOKING FORM CARD -->
    <div class="apt-card-box">
        <h3 style="font-size: 17px; font-weight: 800; margin: 0 0 18px 0; color: #0f172a;">📝 Book Consultation</h3>

        <form action="appointments.php" method="POST">
            <?php echo csrfField(); ?>
            <div class="apt-form-group">
                <label>Select Professor *</label>
                <select name="faculty_id" class="apt-form-select" required>
                    <option value="">-- Choose Faculty --</option>
                    <?php foreach ($facList as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo ($preselectedFacultyId === $f['id']) ? 'selected' : ''; ?>>
                            Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?> (Room <?php echo htmlspecialchars($f['office_room']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="apt-form-group">
                <label>Meeting Date *</label>
                <input type="date" name="appointment_date" class="apt-form-input" min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="apt-form-group">
                <label>Preferred Time Slot *</label>
                <select name="time_slot" class="apt-form-select" required>
                    <option value="9:30 AM - 10:00 AM">9:30 AM - 10:00 AM</option>
                    <option value="10:00 AM - 10:30 AM">10:00 AM - 10:30 AM</option>
                    <option value="11:00 AM - 11:30 AM">11:00 AM - 11:30 AM</option>
                    <option value="11:30 AM - 12:00 PM">11:30 AM - 12:00 PM</option>
                    <option value="12:00 PM - 12:30 PM">12:00 PM - 12:30 PM</option>
                    <option value="02:00 PM - 02:30 PM">02:00 PM - 02:30 PM</option>
                    <option value="02:30 PM - 03:00 PM">02:30 PM - 03:00 PM</option>
                    <option value="03:00 PM - 03:30 PM">03:00 PM - 03:30 PM</option>
                    <option value="03:30 PM - 04:00 PM">03:30 PM - 04:00 PM</option>
                    <option value="04:00 PM - 04:30 PM">04:00 PM - 04:30 PM</option>
                </select>
            </div>

            <div class="apt-form-group">
                <label>Reason for Appointment *</label>
                <textarea name="reason" rows="4" class="apt-form-textarea" required placeholder="Academic doubt clearing, project mentorship, syllabus review..."></textarea>
            </div>

            <button type="submit" class="apt-btn-submit">Book Appointment Request →</button>
        </form>
    </div>

    <!-- MY APPOINTMENTS LIST -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
            <h3 style="font-size: 17px; font-weight: 800; margin: 0; color: #0f172a;">📋 Appointment History</h3>
        </div>

        <div class="apt-tabs">
            <button class="apt-tab-btn active" onclick="filterAptStatus('all', this)">All (<?php echo $totalApps; ?>)</button>
            <button class="apt-tab-btn" onclick="filterAptStatus('confirmed', this)">✅ Confirmed (<?php echo $confirmedApps; ?>)</button>
            <button class="apt-tab-btn" onclick="filterAptStatus('pending', this)">⏳ Pending (<?php echo $pendingApps; ?>)</button>
            <button class="apt-tab-btn" onclick="filterAptStatus('rejected', this)">❌ Rejected (<?php echo $rejectedApps; ?>)</button>
        </div>

        <div id="aptList">
            <?php if (empty($myAppointments)): ?>
                <div class="apt-card-box" style="text-align: center; padding: 44px; color: #94a3b8;">
                    <div style="font-size: 38px; margin-bottom: 10px;">📅</div>
                    <h4 style="margin: 0 0 4px 0; color: #475569;">No Appointments Booked</h4>
                    <p style="margin: 0; font-size: 13.5px;">You haven't requested any meetings with faculty members yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($myAppointments as $a): 
                    $st = strtolower($a['status']);
                    $facName = 'Prof. ' . $a['fac_fn'] . ' ' . $a['fac_ln'];
                    $facInitials = strtoupper(substr($a['fac_fn'], 0, 1) . substr($a['fac_ln'], 0, 1));
                ?>
                    <div class="apt-item-card" data-status="<?php echo $st; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #4338ca); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 15px;">
                                    <?php echo $facInitials; ?>
                                </div>
                                <div>
                                    <div style="font-size: 15px; font-weight: 800; color: #0f172a;"><?php echo htmlspecialchars($facName); ?></div>
                                    <div style="font-size: 12px; color: #64748b;">📍 Room <?php echo htmlspecialchars($a['office_room']); ?></div>
                                </div>
                            </div>

                            <span class="badge apt-badge-<?php echo $st; ?>" style="font-size: 11px; padding: 5px 12px; border-radius: 20px; font-weight: 700; text-transform: uppercase;">
                                <?php echo htmlspecialchars($a['status']); ?>
                            </span>
                        </div>

                        <div style="display: flex; gap: 16px; font-size: 12.5px; color: #475569; background: #f8fafc; padding: 10px 14px; border-radius: 10px; margin-bottom: 12px; border: 1px solid #f1f5f9;">
                            <div>📅 <strong>Date:</strong> <?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></div>
                            <div>⏰ <strong>Time:</strong> <?php echo htmlspecialchars($a['time_slot']); ?></div>
                        </div>

                        <div style="font-size: 13.5px; color: #334155; line-height: 1.5; margin-bottom: 8px;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($a['reason']); ?>
                        </div>

                        <?php if ($a['faculty_notes']): ?>
                            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px 14px; border-radius: 10px; font-size: 12.5px; color: #15803d; margin-top: 8px;">
                                💬 <strong>Faculty Remark:</strong> <?php echo htmlspecialchars($a['faculty_notes']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($a['status'] === 'Pending'): ?>
                            <div style="margin-top: 10px; text-align: right;">
                                <form method="POST" action="appointments.php" style="display: inline; margin: 0;" onsubmit="return confirm('Cancel this appointment request?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" style="padding: 5px 12px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-size: 11.5px; font-weight: 700; border: none; cursor: pointer;">
                                        Cancel Request
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function filterAptStatus(status, btn) {
    document.querySelectorAll('.apt-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const items = document.querySelectorAll('.apt-item-card');
    items.forEach(item => {
        const itemStatus = item.getAttribute('data-status');
        if (status === 'all' || itemStatus === status) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

