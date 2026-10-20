<?php
// student/settings.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

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
            logActivity($userId, 'Changed account password');
        } else {
            setToast('error', 'Current password is incorrect.');
        }
    }
}

$pageTitle = 'Account Settings';
$pageHeading = 'Account & Security Settings';
$activeNav = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.stg-hero {
    background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
    border-radius: 20px;
    padding: 30px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(30, 41, 59, 0.2);
    position: relative;
    overflow: hidden;
}
.stg-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
    border-radius: 50%;
}

.stg-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 900px) {
    .stg-grid {
        grid-template-columns: 1fr;
    }
}

.stg-card {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 26px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.03);
}

.stg-form-group {
    margin-bottom: 18px;
}
.stg-form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 6px;
}
.stg-form-input {
    width: 100%;
    padding: 11px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    background: #ffffff;
}
.stg-form-input:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
}

.stg-btn-submit {
    padding: 13px 28px;
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
.stg-btn-submit:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
}

.stg-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.stg-info-item:last-child {
    border-bottom: none;
}
</style>

<div class="stg-hero" style="max-width: 1100px; margin: 0 auto 28px auto;">
    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 4px;">🔒 Account Protection & Privacy</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 6px 0;">Security Settings</h2>
    <p style="font-size: 13.5px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Manage your password, account credentials, active sessions, and security preferences.</p>
</div>

<div class="stg-grid">
    <!-- PASSWORD CHANGE FORM -->
    <div class="stg-card">
        <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 6px 0;">🔑 Update Password</h3>
        <p style="font-size: 13px; color: #64748b; margin-bottom: 22px;">Choose a strong password with at least 6 characters.</p>

        <form action="settings.php" method="POST">
            <?php echo csrfField(); ?>
            <div class="stg-form-group">
                <label>Current Password *</label>
                <input type="password" name="current_password" required class="stg-form-input" placeholder="••••••••">
            </div>

            <div class="stg-form-group">
                <label>New Password *</label>
                <input type="password" name="new_password" required class="stg-form-input" placeholder="••••••••">
            </div>

            <div class="stg-form-group" style="margin-bottom: 24px;">
                <label>Confirm New Password *</label>
                <input type="password" name="confirm_password" required class="stg-form-input" placeholder="••••••••">
            </div>

            <button type="submit" class="stg-btn-submit">Update Password →</button>
        </form>
    </div>

    <!-- SECURITY SUMMARY -->
    <div class="stg-card">
        <h3 style="font-size: 17px; font-weight: 800; color: #0f172a; margin: 0 0 16px 0;">🛡️ Security Overview</h3>

        <div class="stg-info-item">
            <span style="color: #64748b;">Account Role:</span>
            <span class="badge badge-submitted" style="font-size: 11px;">STUDENT</span>
        </div>

        <div class="stg-info-item">
            <span style="color: #64748b;">Account Status:</span>
            <span style="color: #16a34a; font-weight: 700;">🟢 Active & Verified</span>
        </div>

        <div class="stg-info-item">
            <span style="color: #64748b;">Session Auth:</span>
            <span style="font-weight: 600; color: #334155;">PostgreSQL PDO / BCrypt</span>
        </div>

        <div class="stg-info-item">
            <span style="color: #64748b;">Current IP:</span>
            <span style="font-weight: 600; color: #334155;">127.0.0.1 (Localhost)</span>
        </div>

        <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 12px; padding: 14px; margin-top: 18px; font-size: 12.5px; color: #4338ca; line-height: 1.5;">
            💡 <strong>Security Tip:</strong> Avoid reusing passwords from other sites. Make sure your password contains numbers and symbols.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

