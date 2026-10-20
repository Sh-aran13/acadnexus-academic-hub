<?php
// student/notifications.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/auth.php';

requireRole('student');

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Handle Mark All as Read
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

// Statistics
$totalNotifs = count($notifications);
$unreadCount = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unreadCount++;
}

$pageTitle = 'Notification Center';
$pageHeading = 'Notification Center & Alerts';
$activeNav = 'notifications';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.ntf-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4338ca 100%);
    border-radius: 20px;
    padding: 30px 36px;
    color: #ffffff;
    margin-bottom: 28px;
    box-shadow: 0 10px 30px rgba(67, 56, 202, 0.2);
    position: relative;
    overflow: hidden;
}
.ntf-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
}
.ntf-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-top: 22px;
}
.ntf-stat-card {
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.ntf-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    background: rgba(255, 255, 255, 0.2);
}
.ntf-stat-val {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.1;
}
.ntf-stat-lbl {
    font-size: 11.5px;
    opacity: 0.88;
    font-weight: 500;
    margin-top: 2px;
}

.ntf-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.ntf-tabs {
    display: flex;
    gap: 8px;
    background: #f1f5f9;
    padding: 5px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
}
.ntf-tab-btn {
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
.ntf-tab-btn.active {
    background: #ffffff;
    color: #4338ca;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}

.ntf-btn-read {
    padding: 9px 18px;
    border-radius: 12px;
    background: linear-gradient(135deg, #4f46e5, #4338ca);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    transition: all 0.2s ease;
}
.ntf-btn-read:hover {
    background: linear-gradient(135deg, #4338ca, #3730a3);
    box-shadow: 0 6px 18px rgba(79, 70, 229, 0.3);
}

.ntf-feed {
    display: flex;
    flex-direction: column;
    gap: 14px;
    max-width: 900px;
    margin: 0 auto;
}
.ntf-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    padding: 20px 24px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.02);
    transition: all 0.2s ease;
    position: relative;
}
.ntf-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}
.ntf-card.unread {
    border-left: 5px solid #6366f1;
    background: #faf5ff;
}

.ntf-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 8px;
}
.ntf-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ntf-time {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
    background: #ffffff;
    padding: 4px 10px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    white-space: nowrap;
}
.ntf-msg {
    font-size: 13.5px;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 12px;
}
.ntf-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12.5px;
    font-weight: 700;
    color: #4338ca;
    text-decoration: none;
    transition: all 0.2s ease;
}
.ntf-link:hover {
    color: #312e81;
    text-decoration: underline;
}
</style>

<div class="ntf-hero">
    <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.9; margin-bottom: 4px;">🔔 Real-Time Academic Broadcasts</div>
    <h2 style="font-size: 26px; font-weight: 800; margin: 0 0 6px 0;">Notification Center</h2>
    <p style="font-size: 13.5px; opacity: 0.88; max-width: 650px; line-height: 1.5; margin: 0;">Stay informed about grade releases, assignment deadlines, appointment status changes, and campus alerts.</p>

    <div class="ntf-stats-grid">
        <div class="ntf-stat-card">
            <div class="ntf-stat-icon">🔔</div>
            <div>
                <div class="ntf-stat-val"><?php echo $totalNotifs; ?></div>
                <div class="ntf-stat-lbl">Total Alerts</div>
            </div>
        </div>
        <div class="ntf-stat-card">
            <div class="ntf-stat-icon" style="background: rgba(239, 68, 68, 0.25);">🔴</div>
            <div>
                <div class="ntf-stat-val"><?php echo $unreadCount; ?></div>
                <div class="ntf-stat-lbl">Unread Notifications</div>
            </div>
        </div>
    </div>
</div>

<div class="ntf-feed">
    <div class="ntf-toolbar">
        <div class="ntf-tabs">
            <button class="ntf-tab-btn active" onclick="filterNotifs('all', this)">All (<?php echo $totalNotifs; ?>)</button>
            <button class="ntf-tab-btn" onclick="filterNotifs('unread', this)">Unread (<?php echo $unreadCount; ?>)</button>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form action="notifications.php" method="POST" style="margin: 0;">
                <?php echo csrfField(); ?>
                <button type="submit" name="mark_read" class="ntf-btn-read">✓ Mark All as Read</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="card-box" style="text-align: center; padding: 48px; color: #94a3b8;">
            <div style="font-size: 40px; margin-bottom: 10px;">🔔</div>
            <h4 style="margin: 0 0 4px 0; color: #475569;">No Notifications Found</h4>
            <p style="margin: 0; font-size: 13.5px;">You're all caught up! Check back later for fresh updates.</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $n): 
            $isUnread = !$n['is_read'];
        ?>
            <div class="ntf-card <?php echo $isUnread ? 'unread' : ''; ?>" data-unread="<?php echo $isUnread ? '1' : '0'; ?>">
                <div class="ntf-card-header">
                    <div class="ntf-title">
                        <span><?php echo $isUnread ? '🔵' : '⚪'; ?></span>
                        <span><?php echo htmlspecialchars($n['title']); ?></span>
                    </div>

                    <div class="ntf-time">
                        🕒 <?php echo date('M d, Y · H:i', strtotime($n['created_at'])); ?>
                    </div>
                </div>

                <div class="ntf-msg">
                    <?php echo htmlspecialchars($n['message']); ?>
                </div>

                <?php if ($n['link']): ?>
                    <a href="<?php echo '../' . htmlspecialchars($n['link']); ?>" class="ntf-link">
                        <span>View Action Item</span>
                        <span>→</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function filterNotifs(type, btn) {
    document.querySelectorAll('.ntf-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    const cards = document.querySelectorAll('.ntf-card');
    cards.forEach(card => {
        const isUnread = card.getAttribute('data-unread') === '1';
        if (type === 'all' || (type === 'unread' && isUnread)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

