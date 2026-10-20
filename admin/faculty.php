<?php
// admin/faculty.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// Fetch Departments
$deptStmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name ASC");
$allDepartments = $deptStmt->fetchAll();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    if ($_POST['action'] === 'delete') {
        $delUserId = (int)($_POST['delete_user_id'] ?? 0);
        try {
            // Delete avatar if exists
            $avStmt = $pdo->prepare("SELECT avatar FROM faculty_profiles WHERE user_id = ?");
            $avStmt->execute([$delUserId]);
            $av = $avStmt->fetchColumn();
            if (!empty($av)) {
                FileService::delete($av);
            }

            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'")->execute([$delUserId]);
            setToast('success', 'Faculty record deleted permanently.');
            logActivity($_SESSION['user_id'], "Admin deleted faculty user ID $delUserId");
        } catch (Exception $e) {
            setToast('error', 'Failed to delete faculty record: ' . $e->getMessage());
        }
        header("Location: faculty.php");
        exit();
    } elseif ($_POST['action'] === 'add_faculty') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $facultyIdInput = trim($_POST['faculty_id_number'] ?? '');
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $designation = trim($_POST['designation'] ?? 'Assistant Professor');
        $qualification = trim($_POST['qualification'] ?? 'Ph.D. in Computer Science');
        $officeRoom = trim($_POST['office_room'] ?? 'Block A, Room 101');
        $specialization = trim($_POST['specialization'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || $departmentId <= 0) {
            setToast('error', 'Please fill in all required faculty fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setToast('error', 'Please provide a valid email address.');
        } elseif (strlen($password) < 8) {
            setToast('error', 'Password must be at least 8 characters long.');
        } else {
            try {
                // Check email uniqueness
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetch()) {
                    setToast('error', 'Email is already registered.');
                } else {
                    $pdo->beginTransaction();

                    // Generate unique 7-char faculty ID if not provided
                    $facIdCode = !empty($facultyIdInput) ? strtoupper($facultyIdInput) : ('FAC' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT));
                    $username = strtolower('prof.' . $firstName . '.' . $lastName);
                    $pwdHash = password_hash($password, PASSWORD_BCRYPT);

                    $insUser = $pdo->prepare("INSERT INTO users (email, username, password_hash, role, status) VALUES (?, ?, ?, 'faculty', 'active') RETURNING id");
                    $insUser->execute([$email, $username, $pwdHash]);
                    $newUserId = $insUser->fetchColumn();

                    $insProfile = $pdo->prepare("
                        INSERT INTO faculty_profiles (
                            user_id, faculty_id_number, first_name, last_name, 
                            department_id, designation, qualification, specialization, office_room, joining_date
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_DATE)
                    ");
                    $insProfile->execute([
                        $newUserId, $facIdCode, $firstName, $lastName,
                        $departmentId, $designation, $qualification, $specialization, $officeRoom
                    ]);

                    $pdo->commit();
                    setToast('success', "Faculty profile created successfully! Username: '$username'");
                    logActivity($_SESSION['user_id'], "Admin created faculty member: Prof. $firstName $lastName");
                    header("Location: faculty.php");
                    exit();
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                setToast('error', 'Failed to create faculty: ' . $e->getMessage());
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
                    setToast('success', "Faculty username updated to '$newUsername'!");
                }
                header("Location: faculty.php");
                exit();
            } catch (Exception $e) {
                setToast('error', 'Failed to update username: ' . $e->getMessage());
            }
        }
    }
}

// Fetch all faculty staff
$stmt = $pdo->query("
    SELECT f.*, u.id as user_id, u.username, u.email, u.status as user_status, d.name as dept_name
    FROM faculty_profiles f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN departments d ON f.department_id = d.id
    ORDER BY f.first_name ASC
");
$facultyList = $stmt->fetchAll();

$pageTitle = 'Admin Faculty Staff Database';
$pageHeading = 'Faculty Roster & Staff Directory';
$activeNav = 'faculty';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.afac-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #7c3aed 100%);
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
.afac-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.afac-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.afac-add-btn {
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
.afac-add-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(255,255,255,0.4);
}

.afac-search-bar {
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
.afac-search-input {
    flex: 1;
    max-width: 400px;
    padding: 12px 20px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
}
.afac-search-input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
}

.afac-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.afac-table {
    width: 100%;
    border-collapse: collapse;
}
.afac-table th {
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
.afac-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
    vertical-align: middle;
}
.afac-table tr:last-child td {
    border-bottom: none;
}
</style>

<div class="afac-hero">
    <div>
        <h2>Master Faculty Staff Database</h2>
        <p>Register new faculty members, view digital passes, edit system usernames, and purge staff records.</p>
    </div>
    <button type="button" class="afac-add-btn" onclick="document.getElementById('addFacultyModal').style.display='flex'">
        <span>➕ Add New Faculty</span>
    </button>
</div>

<div class="afac-search-bar">
    <input type="text" id="admFacSearch" class="afac-search-input" onkeyup="filterTable('admFacSearch', 'admFacTable')" placeholder="🔍 Search faculty by name, ID, or username...">
    <span style="font-size: 13.5px; font-weight: 700; color: #64748b;">Showing <?php echo count($facultyList); ?> Faculty Staff</span>
</div>

<div class="afac-table-card">
    <table class="afac-table" id="admFacTable">
        <thead>
            <tr>
                <th>Faculty Staff & Username</th>
                <th>Faculty ID</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Office Room</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($facultyList as $f): 
                $facIdCode = htmlspecialchars($f['faculty_id_number']);
                $initials = strtoupper(substr($f['first_name'], 0, 1) . substr($f['last_name'], 0, 1));
                $hasAvatar = !empty($f['avatar']) && file_exists(__DIR__ . '/../' . $f['avatar']);
                $avatarUrl = $hasAvatar ? ('../' . htmlspecialchars($f['avatar'])) : '';

                $qrText = "AcadNexus Verified Faculty Pass\nID: $facIdCode\nName: Prof. {$f['first_name']} {$f['last_name']}\nDesignation: {$f['designation']}";
                $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=0&data=" . urlencode($qrText);
            ?>
                <tr>
                    <td>
                        <strong style="color: #0f172a; font-size: 14.5px;">Prof. <?php echo htmlspecialchars($f['first_name'] . ' ' . $f['last_name']); ?></strong>
                        <div style="font-size: 12px; color: #64748b;">
                            Username: <strong>@<?php echo htmlspecialchars($f['username']); ?></strong>
                            <button type="button" onclick="openEditFacUsernameModal(<?php echo $f['user_id']; ?>, '<?php echo htmlspecialchars($f['username']); ?>')" style="background: none; border: none; cursor: pointer; font-size: 11px;" title="Edit Username">✏️</button>
                        </div>
                    </td>
                    <td><span style="padding: 4px 10px; border-radius: 8px; background: #f3e8ff; color: #6d28d9; font-size: 12px; font-weight: 800;"><?php echo $facIdCode; ?></span></td>
                    <td style="font-weight: 600; color: #334155;"><?php echo htmlspecialchars($f['dept_name'] ?? 'Unassigned'); ?></td>
                    <td><?php echo htmlspecialchars($f['designation']); ?></td>
                    <td><?php echo htmlspecialchars($f['office_room'] ?? 'Office'); ?></td>
                    <td>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" onclick="openFacultyViewModal(<?php echo htmlspecialchars(json_encode([
                                'name' => 'Prof. ' . $f['first_name'] . ' ' . $f['last_name'],
                                'facId' => $facIdCode,
                                'designation' => $f['designation'],
                                'qualification' => !empty($f['qualification']) ? $f['qualification'] : 'Ph.D. Computer Science',
                                'dept' => $f['dept_name'] ?? 'General',
                                'office' => $f['office_room'] ?? 'Main Block',
                                'email' => $f['email'],
                                'phone' => !empty($f['phone']) ? $f['phone'] : '+91 98765 12345',
                                'avatar' => $avatarUrl,
                                'initials' => $initials,
                                'qr' => $qrApiUrl
                            ])); ?>)" style="padding: 4px 10px; border-radius: 8px; background: #e0e7ff; color: #4338ca; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">👁️ Profile</button>
                            
                            <form method="POST" action="faculty.php" style="display:inline; margin:0;" onsubmit="return confirm('Permanently delete faculty staff record and user account?');">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_user_id" value="<?php echo $f['user_id']; ?>">
                                <button type="submit" style="padding: 4px 10px; border-radius: 8px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ADD FACULTY MODAL -->
<div id="addFacultyModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 999; align-items: center; justify-content: center; padding: 20px; overflow-y: auto;">
    <div style="background: #ffffff; border-radius: 24px; width: 100%; max-width: 620px; padding: 32px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9;">
            <h3 style="font-size: 20px; font-weight: 800; margin: 0; color: #0f172a;">➕ Add New Faculty Staff Member</h3>
            <button type="button" onclick="document.getElementById('addFacultyModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">✕</button>
        </div>

        <form action="faculty.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add_faculty">

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
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Faculty ID (7-Chars)</label>
                    <input type="text" name="faculty_id_number" placeholder="e.g. FAC4829 (Auto if blank)" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px;">
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Designation</label>
                    <input type="text" name="designation" value="Assistant Professor" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
                <div>
                    <label style="font-size: 12.5px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Office Room Number</label>
                    <input type="text" name="office_room" placeholder="Block A, Room 302" style="width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 13.5px; outline: none;">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('addFacultyModal').style.display='none'" style="padding: 11px 20px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px; color: #475569;">Cancel</button>
                <button type="submit" style="padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px;">Register Faculty Member</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USERNAME MODAL -->
<div id="editFacUsernameModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 24px; width: 100%; max-width: 460px; padding: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 19px; font-weight: 800; margin: 0; color: #0f172a;">✏️ Edit Faculty Username</h3>
            <button type="button" onclick="document.getElementById('editFacUsernameModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">✕</button>
        </div>

        <form action="faculty.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="edit_username">
            <input type="hidden" name="user_id" id="modalFacUserId">

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">New Username *</label>
                <input type="text" name="username" id="modalFacUsernameInput" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 14px; outline: none;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('editFacUsernameModal').style.display='none'" style="padding: 11px 20px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px; color: #475569;">Cancel</button>
                <button type="submit" style="padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px;">Save Username</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW PROFILE MODAL -->
<div id="facViewModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="width: 100%; max-width: 780px; margin: auto; position: relative;">
        <button type="button" onclick="document.getElementById('facViewModal').style.display='none'" style="position: absolute; top: 16px; right: 24px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 36px; height: 36px; font-size: 20px; cursor: pointer; color: #475569; z-index: 10;">✕</button>

        <div style="background: #ffffff; border-radius: 28px; border: 1px solid #e2e8f0; padding: 32px 36px; box-shadow: 0 20px 50px rgba(0,0,0,0.15);">
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px;">
                <div style="width: 90px; height: 90px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; overflow: hidden;" id="fvAvatarInner">
                    <span id="fvInitials">FP</span>
                </div>
                <div>
                    <h2 id="fvName" style="font-size: 24px; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">Prof. Name</h2>
                    <div style="font-size: 12px; font-weight: 800; color: #7c3aed;" id="fvIdTag">FACULTY ID: FAC001</div>
                    <div style="font-size: 14px; color: #64748b; margin-top: 2px;" id="fvDesignation">Senior Professor</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px;">
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Department</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a;" id="fvDept">CSE</div>
                </div>
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1px solid #f1f5f9;">
                    <div style="font-size: 11px; font-weight: 700; color: #64748b;">Office Room</div>
                    <div style="font-size: 14px; font-weight: 800; color: #0f172a;" id="fvOffice">Block A, Room 302</div>
                </div>
            </div>

            <div style="text-align: center;">
                <img id="fvQr" src="" alt="QR" style="width: 110px; height: 110px; border-radius: 10px; border: 2px solid #7c3aed; padding: 4px;">
            </div>
        </div>
    </div>
</div>

<script>
function openEditFacUsernameModal(id, username) {
    document.getElementById('modalFacUserId').value = id;
    document.getElementById('modalFacUsernameInput').value = username;
    document.getElementById('editFacUsernameModal').style.display = 'flex';
}

function openFacultyViewModal(data) {
    document.getElementById('fvName').textContent = data.name;
    document.getElementById('fvIdTag').textContent = 'FACULTY ID: ' + data.facId;
    document.getElementById('fvDesignation').textContent = data.designation;
    document.getElementById('fvDept').textContent = data.dept;
    document.getElementById('fvOffice').textContent = data.office;
    document.getElementById('fvQr').src = data.qr;

    const av = document.getElementById('fvAvatarInner');
    if (data.avatar) {
        av.innerHTML = '<img src="' + data.avatar + '" style="width:100%; height:100%; object-fit:cover;">';
    } else {
        av.innerHTML = '<span id="fvInitials">' + data.initials + '</span>';
    }

    document.getElementById('facViewModal').style.display = 'flex';
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
