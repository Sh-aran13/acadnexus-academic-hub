<?php
// admin/students.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// Fetch Departments for dropdown
$deptStmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC");
$allDepartments = $deptStmt->fetchAll();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    if ($_POST['action'] === 'delete') {
        $delUserId = (int)($_POST['delete_user_id'] ?? 0);
        try {
            // Delete avatar if exists
            $avStmt = $pdo->prepare("SELECT avatar FROM student_profiles WHERE user_id = ?");
            $avStmt->execute([$delUserId]);
            $av = $avStmt->fetchColumn();
            if (!empty($av)) {
                FileService::delete($av);
            }

            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$delUserId]);
            setToast('success', 'Student record deleted permanently.');
            logActivity($_SESSION['user_id'], "Admin deleted student user ID $delUserId");
        } catch (Exception $e) {
            setToast('error', 'Failed to delete student record: ' . $e->getMessage());
        }
        header("Location: students.php");
        exit();
    } elseif ($_POST['action'] === 'add_student') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $regNumberInput = trim($_POST['reg_number'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $program = trim($_POST['program'] ?? 'B.Tech - Computer Science');
        $yearOfStudy = (int)($_POST['year_of_study'] ?? 1);
        $semester = (int)($_POST['semester'] ?? 1);

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || $departmentId <= 0) {
            setToast('error', 'Please fill in all required fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setToast('error', 'Please provide a valid email address.');
        } elseif (strlen($password) < 8) {
            setToast('error', 'Password must be at least 8 characters long.');
        } else {
            try {
                // Check email existence
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    setToast('error', 'Email is already registered.');
                } else {
                    $pdo->beginTransaction();

                    // Generate unique 7-char student ID if not provided
                    $studentIdCode = 'ANX' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                    $regNumber = !empty($regNumberInput) ? $regNumberInput : $studentIdCode;
                    $username = strtolower($firstName . '.' . $lastName . rand(10, 99));
                    $pwdHash = password_hash($password, PASSWORD_BCRYPT);

                    $insUser = $pdo->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (?, ?, ?, 'student', 'active') RETURNING id");
                    $insUser->execute([$email, $username, $pwdHash]);
                    $newUserId = $insUser->fetchColumn();

                    $insProfile = $pdo->prepare("
                        INSERT INTO student_profiles (
                            user_id, student_id_number, reg_number, first_name, last_name, 
                            department_id, program, year_of_study, semester, admission_year, graduation_year, cgpa
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 2023, 2027, 8.5)
                    ");
                    $insProfile->execute([
                        $newUserId, $studentIdCode, $regNumber, $firstName, $lastName,
                        $departmentId, $program, $yearOfStudy, $semester
                    ]);

                    $pdo->commit();
                    setToast('success', "Student profile created successfully! Username: '$username'");
                    logActivity($_SESSION['user_id'], "Admin created student: $firstName $lastName");
                    header("Location: students.php");
                    exit();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setToast('error', 'Failed to create student: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'edit_username') {
        $uId = (int)($_POST['user_id'] ?? 0);
        $newUsername = trim($_POST['username'] ?? '');

        if ($uId > 0 && !empty($newUsername)) {
            try {
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$newUsername, $uId]);
                if ($chk->fetch()) {
                    setToast('error', 'Username is already taken by another account.');
                } else {
                    $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $upd->execute([$newUsername, $uId]);
                    setToast('success', "Student username updated to '$newUsername'!");
                }
                header("Location: students.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Failed to update username: ' . $e->getMessage());
            }
        }
    }
}

// Fetch all students
$stmt = $pdo->query("
    SELECT s.*, u.id as user_id, u.username, u.email, u.status as user_status, d.name as dept_name
    FROM student_profiles s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.first_name ASC
");
$students = $stmt->fetchAll();

$pageTitle = 'Admin Student Database';
$pageHeading = 'Student Records & Profiles';
$activeNav = 'students';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HTML2PDF CDN for Instant Pass Download -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
.astu-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4f46e5 100%);
    border-radius: 24px;
    padding: 32px 40px;
    color: #ffffff;
    margin-bottom: 32px;
    box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
}
.astu-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.astu-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.astu-add-btn {
    padding: 12px 24px;
    border-radius: 14px;
    background: #ffffff;
    color: #1e1b4b;
    font-weight: 800;
    font-size: 14px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(255,255,255,0.25);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.astu-add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(255,255,255,0.4);
}

.astu-search-bar {
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
.astu-search-input {
    flex: 1;
    max-width: 400px;
    padding: 12px 20px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
}
.astu-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.astu-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.astu-table {
    width: 100%;
    border-collapse: collapse;
}
.astu-table th {
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
.astu-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
    vertical-align: middle;
}
.astu-table tr:last-child td {
    border-bottom: none;
}
</style>

<div class="astu-hero">
    <div>
        <h2>Master Student Database</h2>
        <p>Register new student profiles, view pass cards, edit system usernames, and purge student records.</p>
    </div>
    <button type="button" class="astu-add-btn" onclick="document.getElementById('addStudentModal').style.display='flex'">
        <span>➕ Add New Student</span>
    </button>
</div>

<div class="astu-search-bar">
    <input type="text" id="admStuSearch" class="astu-search-input" onkeyup="filterTable('admStuSearch', 'admStuTable')" placeholder="🔍 Search students by name, roll number, or username...">
    <span style="font-size: 13.5px; font-weight: 700; color: #64748b;">Showing <?php echo count($students); ?> Student Records</span>
</div>

<div class="astu-table-card">
    <table class="astu-table" id="admStuTable">
        <thead>
            <tr>
                <th>Student Profile & Username</th>
                <th>Reg / Roll No</th>
                <th>Department</th>
                <th>Program</th>
                <th>Year & Sem</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $s): 
                $roll = !empty($s['reg_number']) ? $s['reg_number'] : $s['student_id_number'];
                $initials = strtoupper(substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1));
                $startYr = !empty($s['admission_year']) ? (int)$s['admission_year'] : 2023;
                $endYr = !empty($s['graduation_year']) ? (int)$s['graduation_year'] : ($startYr + 2);
                $batchStr = $startYr . ' – ' . $endYr;
                $cgpaVal = !empty($s['cgpa']) ? number_format((float)$s['cgpa'], 1) : '8.5';
                $hasAvatar = !empty($s['avatar']) && file_exists(__DIR__ . '/../' . $s['avatar']);
                $avatarUrl = $hasAvatar ? ('../' . htmlspecialchars($s['avatar'])) : '';

                $qrText = "AcadNexus Verified Student Pass\nReg/Roll: $roll\nName: {$s['first_name']} {$s['last_name']}\nProgram: {$s['program']}";
                $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&data=" . urlencode($qrText);
            ?>
                <tr>
                    <td>
                        <strong style="color: #0f172a; font-size: 14.5px;"><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></strong>
                        <div style="font-size: 12px; color: #64748b;">
                            Username: <strong>@<?php echo htmlspecialchars($s['username']); ?></strong>
                            <button type="button" onclick="openEditUsernameModal(<?php echo $s['user_id']; ?>, '<?php echo htmlspecialchars($s['username']); ?>')" style="background: none; border: none; cursor: pointer; font-size: 11px;" title="Edit Username">✏️</button>
                        </div>
                    </td>
                    <td><span style="padding: 4px 10px; border-radius: 8px; background: #eef2ff; color: #4f46e5; font-size: 12px; font-weight: 800;"><?php echo htmlspecialchars($roll); ?></span></td>
                    <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($s['dept_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo htmlspecialchars($s['program']); ?></td>
                    <td>Year <?php echo (int)$s['year_of_study']; ?> <span style="color: #94a3b8;">(Sem <?php echo (int)$s['semester']; ?>)</span></td>
                    <td>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" onclick="openStudentViewModal(<?php echo htmlspecialchars(json_encode([
                                'name' => $s['first_name'] . ' ' . $s['last_name'],
                                'roll' => $roll,
                                'program' => $s['program'],
                                'dept' => $s['dept_name'] ?? 'Computer Science',
                                'batch' => $batchStr,
                                'cgpa' => $cgpaVal,
                                'email' => $s['email'],
                                'phone' => !empty($s['phone']) ? $s['phone'] : '+91 98765 43210',
                                'avatar' => $avatarUrl,
                                'initials' => $initials,
                                'qr' => $qrApiUrl
                            ])); ?>)" style="padding: 4px 10px; border-radius: 8px; background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">👁️ Profile</button>
                            
                            <form method="POST" action="students.php" style="display:inline; margin:0;" onsubmit="return confirm('Permanently delete student profile and user account?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_user_id" value="<?php echo $s['user_id']; ?>">
                                <button type="submit" style="padding: 4px 10px; border-radius: 8px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ADD STUDENT MODAL -->
<div id="addStudentModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 999; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
    <div style="background: #ffffff; border-radius: 24px; width: 100%; max-width: 620px; padding: 32px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 20px; font-weight: 800; margin: 0; color: #0f172a;">➕ Add New Student Record</h3>
            <button type="button" onclick="document.getElementById('addStudentModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">✕</button>
        </div>

        <form action="students.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add_student">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">First Name *</label>
                    <input type="text" name="first_name" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Last Name *</label>
                    <input type="text" name="last_name" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Email Address *</label>
                    <input type="email" name="email" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Account Password *</label>
                    <input type="password" name="password" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Department *</label>
                    <select name="department_id" required style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                        <option value="">-- Choose Department --</option>
                        <?php foreach ($allDepartments as $d): ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo $d['code']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Roll / Reg Number</label>
                    <input type="text" name="reg_number" placeholder="Auto-generated if empty" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 16px; margin-bottom: 22px;">
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Academic Program</label>
                    <input type="text" name="program" value="B.Tech - Computer Science" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Year</label>
                    <input type="number" name="year_of_study" value="1" min="1" max="5" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Semester</label>
                    <input type="number" name="semester" value="1" min="1" max="10" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('addStudentModal').style.display='none'" style="padding: 11px 20px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px; color: #475569;">Cancel</button>
                <button type="submit" style="padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #4f46e5, #4338ca); color: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px;">Register Student</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USERNAME MODAL -->
<div id="editStuUsernameModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 24px; width: 100%; max-width: 460px; padding: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 19px; font-weight: 800; margin: 0; color: #0f172a;">✏️ Edit Student Username</h3>
            <button type="button" onclick="document.getElementById('editStuUsernameModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">✕</button>
        </div>

        <form action="students.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="edit_username">
            <input type="hidden" name="user_id" id="modalStuUserId">

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">New Username *</label>
                <input type="text" name="username" id="modalStuUsernameInput" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 14px; outline: none;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('editStuUsernameModal').style.display='none'" style="padding: 11px 20px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px; color: #475569;">Cancel</button>
                <button type="submit" style="padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #4f46e5, #4338ca); color: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px;">Save Username</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW PROFILE MODAL -->
<div id="stuViewModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="width: 100%; max-width: 780px; margin: auto; position: relative;">
        <button type="button" onclick="document.getElementById('stuViewModal').style.display='none'" style="position: absolute; top: 16px; right: 24px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 36px; height: 36px; font-size: 20px; cursor: pointer; color: #475569; z-index: 10;">✕</button>

        <div style="background: #ffffff; border-radius: 28px; border: 1px solid #e2e8f0; padding: 32px 36px; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px;">
                <div style="width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5, #3730a3); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; overflow: hidden;" id="svAvatarInner">
                    <span id="svInitials">AS</span>
                </div>
                <div>
                    <h2 id="svName" style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">Student Name</h2>
                    <div style="font-size: 12px; font-weight: 800; color: #4f46e5;" id="svRoll">REG NO: STU001</div>
                    <div style="font-size: 14px; color: #64748b; margin-top: 2px;" id="svProgram">B.Tech CS</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px;">
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Department</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a;" id="svDept">CSE</div>
                </div>
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Email Address</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a;" id="svEmail">student@acadnexus.edu</div>
                </div>
            </div>

            <div style="text-align: center;">
                <img id="svQr" src="" alt="QR" style="width: 110px; height: 110px; border-radius: 10px; border: 2px solid #6366f1; padding: 4px;">
            </div>
        </div>
    </div>
</div>

<script>
function openEditUsernameModal(id, username) {
    document.getElementById('modalStuUserId').value = id;
    document.getElementById('modalStuUsernameInput').value = username;
    document.getElementById('editStuUsernameModal').style.display = 'flex';
}

function openStudentViewModal(data) {
    document.getElementById('svName').textContent = data.name;
    document.getElementById('svRoll').textContent = 'REG NO: ' + data.roll;
    document.getElementById('svProgram').textContent = data.program;
    document.getElementById('svDept').textContent = data.dept;
    document.getElementById('svEmail').textContent = data.email;
    document.getElementById('svQr').src = data.qr;

    const av = document.getElementById('svAvatarInner');
    if (data.avatar) {
        av.innerHTML = '<img src="' + data.avatar + '" style="width:100%; height:100%; object-fit:cover;">';
    } else {
        av.innerHTML = '<span id="svInitials">' + data.initials + '</span>';
    }

    document.getElementById('stuViewModal').style.display = 'flex';
}

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
