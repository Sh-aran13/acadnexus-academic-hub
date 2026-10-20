<?php
// admin/users.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

$pdo = getDBConnection();

// Handle Actions: Edit Username, Toggle Status, Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();
    $currentAdminId = (int)$_SESSION['user_id'];

    if ($_POST['action'] === 'edit_username') {
        $uId = (int)($_POST['user_id'] ?? 0);
        $newUsername = trim($_POST['username'] ?? '');

        if ($uId > 0 && !empty($newUsername)) {
            try {
                // Check username uniqueness
                $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $chk->execute([$newUsername, $uId]);
                if ($chk->fetch()) {
                    setToast('error', 'Username is already taken by another user.');
                } else {
                    $upd = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $upd->execute([$newUsername, $uId]);
                    setToast('success', "Username updated successfully to '$newUsername'!");
                    logActivity($currentAdminId, "Admin updated user ID $uId username to $newUsername");
                }
                header('Location: users.php');
                exit();
            } catch (Exception $e) {
                setToast('error', 'Failed to update username: ' . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        $tId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';
        if ($tId === $currentAdminId) {
            setToast('error', 'Cannot change status of your own administrator account.');
        } elseif (in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $tId]);
            setToast('success', "User account status changed to $newStatus.");
            logActivity($currentAdminId, "Admin changed user ID $tId status to $newStatus");
        }
        header('Location: users.php');
        exit();
    } elseif ($_POST['action'] === 'delete') {
        $delId = (int)($_POST['delete_user_id'] ?? 0);
        if ($delId === $currentAdminId) {
            setToast('error', 'Cannot delete your own administrator account.');
        } else {
            try {
                $chkAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $chkAdmin->execute([$delId]);
                $role = $chkAdmin->fetchColumn();

                if ($role === 'admin') {
                    setToast('error', 'Cannot delete administrator account.');
                } else {
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delId]);
                    setToast('success', 'User account permanently deleted.');
                    logActivity($currentAdminId, "Deleted user ID $delId");
                }
            } catch (Exception $e) {
                setToast('error', 'Failed to delete user: ' . $e->getMessage());
            }
        }
        header('Location: users.php');
        exit();
    }
}

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$pageTitle = 'User Accounts Management';
$pageHeading = 'User Accounts & Credentials';
$activeNav = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.aus-hero {
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
    gap: 16px;
}
.aus-hero h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
}
.aus-hero p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

.aus-search-bar {
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
.aus-search-input {
    flex: 1;
    max-width: 400px;
    padding: 12px 20px;
    border-radius: 14px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
}
.aus-search-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.aus-table-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
    overflow: hidden;
}
.aus-table {
    width: 100%;
    border-collapse: collapse;
}
.aus-table th {
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
.aus-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    color: #334155;
    vertical-align: middle;
}
.aus-table tr:last-child td {
    border-bottom: none;
}

.aus-role-badge {
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 800;
    text-transform: uppercase;
}
.aus-role-admin { background: #fae8ff; color: #86198f; }
.aus-role-faculty { background: #ede9fe; color: #5b21b6; }
.aus-role-student { background: #e0e7ff; color: #3730a3; }

.aus-status-active { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 11.5px; }
.aus-status-inactive { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; padding: 4px 10px; border-radius: 8px; font-weight: 800; font-size: 11.5px; }
</style>

<div class="aus-hero">
    <div>
        <h2>User Accounts & Access Control</h2>
        <p>Manage system credentials, edit usernames, toggle account statuses, and purge user records.</p>
    </div>
    <span style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 14px;">
        👥 <?php echo count($users); ?> Total Accounts
    </span>
</div>

<div class="aus-search-bar">
    <input type="text" id="admUserSearch" class="aus-search-input" onkeyup="filterTable('admUserSearch', 'admUserTable')" placeholder="🔍 Search users by username or email...">
    <span style="font-size: 13.5px; font-weight: 700; color: #64748b;">Showing <?php echo count($users); ?> Active Records</span>
</div>

<div class="aus-table-card">
    <table class="aus-table" id="admUserTable">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email Address</th>
                <th>System Role</th>
                <th>Account Status</th>
                <th>Registered Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong style="color: #0f172a; font-size: 14.5px;"><?php echo htmlspecialchars($u['username']); ?></strong>
                        <button type="button" onclick="openEditUsernameModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['username']); ?>')" style="background: none; border: none; cursor: pointer; font-size: 12px; margin-left: 6px;" title="Edit Username">✏️</button>
                    </td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td>
                        <span class="aus-role-badge aus-role-<?php echo $u['role']; ?>"><?php echo strtoupper($u['role']); ?></span>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'active'): ?>
                            <span class="aus-status-active">Active</span>
                        <?php else: ?>
                            <span class="aus-status-inactive"><?php echo ucfirst($u['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12.5px; color: #64748b;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <?php if ($u['role'] !== 'admin'): ?>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <form method="POST" action="users.php" style="display:inline; margin:0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <?php if ($u['status'] === 'active'): ?>
                                        <input type="hidden" name="status" value="inactive">
                                        <button type="submit" style="padding: 4px 10px; border-radius: 8px; background: #fffbebfb; color: #b45309; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">Deactivate</button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="active">
                                        <button type="submit" style="padding: 4px 10px; border-radius: 8px; background: #ecfdf5; color: #059669; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">Activate</button>
                                    <?php endif; ?>
                                </form>
                                <form method="POST" action="users.php" style="display:inline; margin:0;" onsubmit="return confirm('Permanently delete this user account?');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" style="padding: 4px 10px; border-radius: 8px; background: #fef2f2; color: #dc2626; font-weight: 700; font-size: 11.5px; border: none; cursor: pointer;">Delete</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- EDIT USERNAME MODAL -->
<div id="editUsernameModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(5px); z-index: 999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #ffffff; border-radius: 24px; width: 100%; max-width: 460px; padding: 30px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); border: 1px solid #e2e8f0; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
            <h3 style="font-size: 19px; font-weight: 800; margin: 0; color: #0f172a;">✏️ Edit Account Username</h3>
            <button type="button" onclick="document.getElementById('editUsernameModal').style.display='none'" style="background: transparent; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">✕</button>
        </div>

        <form action="users.php" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="edit_username">
            <input type="hidden" name="user_id" id="modalUserId">

            <div style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">New Account Username *</label>
                <input type="text" name="username" id="modalUsernameInput" required style="width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; font-size: 14px; outline: none;">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('editUsernameModal').style.display='none'" style="padding: 11px 20px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px; color: #475569;">Cancel</button>
                <button type="submit" style="padding: 11px 22px; border-radius: 12px; border: none; background: linear-gradient(135deg, #6366f1, #4338ca); color: #ffffff; font-weight: 700; cursor: pointer; font-size: 13.5px;">Save Username</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUsernameModal(id, currentUsername) {
    document.getElementById('modalUserId').value = id;
    document.getElementById('modalUsernameInput').value = currentUsername;
    document.getElementById('editUsernameModal').style.display = 'flex';
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
