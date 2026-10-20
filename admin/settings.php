<?php
// admin/settings.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('admin');

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
            setToast('success', 'Admin password updated successfully!');
            logActivity($userId, 'Changed administrator password');
        } else {
            setToast('error', 'Current password is incorrect.');
        }
    }
}

$pageTitle = 'System Settings';
$pageHeading = 'Administrator Settings';
$activeNav = 'settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card-box" style="max-width: 600px; margin: 0 auto;">
    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;">Change Administrator Password</h3>

    <form action="settings.php" method="POST">
        <?php echo csrfField(); ?>
        <div style="margin-bottom: 16px;">
            <label style="font-size: 13px; font-weight: 700; display: block; margin-bottom: 6px;">Current Password *</label>
            <input type="password" name="current_password" required class="form-input" style="width:100%; padding:12px; border-radius:10px; border:1px solid var(--border-color);">
        </div>

        <div style="margin-bottom: 16px;">
            <label style="font-size: 13px; font-weight: 700; display: block; margin-bottom: 6px;">New Password *</label>
            <input type="password" name="new_password" required class="form-input" style="width:100%; padding:12px; border-radius:10px; border:1px solid var(--border-color);">
        </div>

        <div style="margin-bottom: 24px;">
            <label style="font-size: 13px; font-weight: 700; display: block; margin-bottom: 6px;">Confirm New Password *</label>
            <input type="password" name="confirm_password" required class="form-input" style="width:100%; padding:12px; border-radius:10px; border:1px solid var(--border-color);">
        </div>

        <button type="submit" style="padding: 14px 28px; background: var(--accent-gradient); color:#fff; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Update Admin Password</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
