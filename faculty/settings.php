<?php
// faculty/settings.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        setToast('error', 'Please fill in all password fields.');
    } elseif ($newPassword !== $confirmPassword) {
        setToast('error', 'New passwords do not match.');
    } elseif (strlen($newPassword) < 8) {
        setToast('error', 'New password must be at least 8 characters long.');
    } else {
        $user = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $user->execute([$userId]);
        $u = $user->fetch();

        if ($u && password_verify($currentPassword, $u['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $userId]);
            session_regenerate_id(true);
            setToast('success', 'Password updated successfully!');
            logActivity($userId, 'Changed faculty password');
        } else {
            setToast('error', 'Current password is incorrect.');
        }
    }
}

$pageTitle = 'Faculty Settings';
$pageHeading = 'Security & Password Settings';
$activeNav = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fset-card {
    max-width: 620px;
    margin: 20px auto;
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    padding: 36px 40px;
}
.fset-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    padding-bottom: 18px;
    border-bottom: 1px solid #f1f5f9;
}
.fset-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #f3e8ff;
    color: #6d28d9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.fset-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.fset-form-group {
    margin-bottom: 20px;
}
.fset-label {
    font-size: 13.5px;
    font-weight: 700;
    color: #334155;
    display: block;
    margin-bottom: 6px;
}
.fset-input {
    width: 100%;
    padding: 13px 16px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}
.fset-input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
}
.fset-submit-btn {
    width: 100%;
    padding: 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, #7c3aed, #6d28d9);
    color: #ffffff;
    font-weight: 800;
    font-size: 14.5px;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3);
    transition: all 0.2s ease;
}
.fset-submit-btn:hover {
    background: linear-gradient(135deg, #6d28d9, #5b21b6);
    box-shadow: 0 6px 20px rgba(124, 58, 237, 0.45);
}
</style>

<div class="fset-card">
    <div class="fset-header">
        <div class="fset-icon">🔒</div>
        <div>
            <h3 class="fset-title">Faculty Security & Password</h3>
            <p style="margin: 2px 0 0 0; font-size: 13px; color: #64748b;">Ensure your account remains secure by updating your password regularly.</p>
        </div>
    </div>

    <form action="settings.php" method="POST">
        <?php echo csrfField(); ?>
        <div class="fset-form-group">
            <label class="fset-label">Current Password *</label>
            <input type="password" name="current_password" required class="fset-input" placeholder="Enter your current password">
        </div>

        <div class="fset-form-group">
            <label class="fset-label">New Password *</label>
            <input type="password" name="new_password" required class="fset-input" placeholder="Enter new password (min. 6 characters)">
        </div>

        <div class="fset-form-group" style="margin-bottom: 28px;">
            <label class="fset-label">Confirm New Password *</label>
            <input type="password" name="confirm_password" required class="fset-input" placeholder="Re-enter new password">
        </div>

        <button type="submit" class="fset-submit-btn">🔐 Update Password</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
