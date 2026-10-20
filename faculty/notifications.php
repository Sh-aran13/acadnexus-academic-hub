<?php
// faculty/notifications.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('faculty');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

if (isset($_POST['mark_read'])) {
    requireCSRF();
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")->execute([$userId]);
    setToast('success', 'All notifications marked as read.');
    header("Location: notifications.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Faculty Notifications';
$pageHeading = 'Notification Center';
$activeNav = 'notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fnotif-container {
    max-width: 860px;
    margin: 0 auto;
}
.fnotif-header {
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    padding: 20px 28px;
    margin-bottom: 24px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.02);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.fnotif-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.fnotif-mark-btn {
    padding: 9px 18px;
    border-radius: 12px;
    background: #f1f5f9;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    border: 1px solid #cbd5e1;
    cursor: pointer;
    transition: all 0.2s ease;
}
.fnotif-mark-btn:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.fnotif-item {
    background: #ffffff;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    padding: 22px;
    margin-bottom: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
}
.fnotif-item-unread {
    border-left: 5px solid #6366f1;
    background: #f8fafc;
}
.fnotif-item:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
</style>

<div class="fnotif-container">
    <div class="fnotif-header">
        <h3 class="fnotif-title">🔔 System Notifications & Alerts</h3>
        <form action="notifications.php" method="POST" style="margin: 0;">
            <?php echo csrfField(); ?>
            <button type="submit" name="mark_read" class="fnotif-mark-btn">✓ Mark All as Read</button>
        </form>
    </div>

    <div>
        <?php if (empty($notifications)): ?>
            <div class="fnotif-item" style="text-align: center; padding: 45px; color: #94a3b8;">
                <div style="font-size: 38px; margin-bottom: 8px;">🔔</div>
                <p style="margin: 0; font-size: 14px;">No new notifications in your inbox.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="fnotif-item <?php echo !$n['is_read'] ? 'fnotif-item-unread' : ''; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                        <h4 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0;"><?php echo htmlspecialchars($n['title']); ?></h4>
                        <span style="font-size: 12px; font-weight: 600; color: #64748b;"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></span>
                    </div>
                    <p style="font-size: 14px; color: #334155; line-height: 1.5; margin: 0;"><?php echo htmlspecialchars($n['message']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
