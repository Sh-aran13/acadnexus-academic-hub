<?php
// includes/toast.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
?>

<style>
.toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    animation: toastSlideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.toast-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 320px;
    max-width: 440px;
    padding: 16px 20px;
    background: rgba(15, 23, 42, 0.95);
    backdrop-filter: blur(12px);
    color: #ffffff;
    border-radius: 14px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.toast-success { border-left: 4px solid #10b981; }
.toast-success .toast-icon { color: #10b981; background: rgba(16, 185, 129, 0.15); }

.toast-error { border-left: 4px solid #ef4444; }
.toast-error .toast-icon { color: #ef4444; background: rgba(239, 68, 68, 0.15); }

.toast-warning { border-left: 4px solid #f59e0b; }
.toast-warning .toast-icon { color: #f59e0b; background: rgba(245, 158, 11, 0.15); }

.toast-info { border-left: 4px solid #3b82f6; }
.toast-info .toast-icon { color: #3b82f6; background: rgba(59, 130, 246, 0.15); }

.toast-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    flex-shrink: 0;
}

.toast-content {
    flex: 1;
}

.toast-title {
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 2px;
    letter-spacing: 0.3px;
}

.toast-msg {
    font-size: 13px;
    color: #cbd5e1;
    line-height: 1.4;
}

.toast-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 22px;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    transition: color 0.2s;
}
.toast-close:hover { color: #fff; }

.toast-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 100%;
    background: linear-gradient(90deg, #6366f1, #a855f7);
    animation: toastProgress 4s linear forwards;
}

/* Custom Modal Styles for Signout confirmation */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-box {
    background: #ffffff;
    border-radius: 20px;
    width: 90%;
    max-width: 420px;
    padding: 28px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.9);
    transition: transform 0.3s ease;
    text-align: center;
}

.modal-overlay.active .modal-box {
    transform: scale(1);
}

.modal-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px auto;
}

.modal-title {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}

.modal-message {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 24px;
    line-height: 1.5;
}

.modal-actions {
    display: flex;
    gap: 12px;
}

.btn-modal {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-modal-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.btn-modal-cancel:hover { background: #e2e8f0; }

.btn-modal-confirm {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}
.btn-modal-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4); }

@keyframes toastSlideIn {
    from { opacity: 0; transform: translateY(-20px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes toastProgress {
    from { width: 100%; }
    to { width: 0%; }
}
</style>

<?php if ($toast): ?>
<div id="toast-container" class="toast-container">
    <div class="toast-card toast-<?php echo htmlspecialchars($toast['type']); ?>">
        <div class="toast-icon">
            <?php if ($toast['type'] === 'success'): ?>
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
            <?php elseif ($toast['type'] === 'error'): ?>
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
            <?php elseif ($toast['type'] === 'warning'): ?>
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <?php else: ?>
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?php endif; ?>
        </div>
        <div class="toast-content">
            <div class="toast-title"><?php echo ucfirst($toast['type']); ?></div>
            <div class="toast-msg"><?php echo htmlspecialchars($toast['message']); ?></div>
        </div>
        <button type="button" class="toast-close" onclick="closeToast()">&times;</button>
        <div class="toast-progress"></div>
    </div>
</div>
<?php endif; ?>

<!-- Custom Sign Out Modal -->
<div id="signOutModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-icon">
            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        </div>
        <div class="modal-title">Sign Out Confirmation</div>
        <div class="modal-message">Are you sure you want to sign out of your AcadNexus workspace?</div>
        <div class="modal-actions">
            <button type="button" class="btn-modal btn-modal-cancel" onclick="cancelSignOut()">Cancel</button>
            <button type="button" class="btn-modal btn-modal-confirm" onclick="proceedSignOut()">Yes, Sign Out</button>
        </div>
    </div>
</div>

<script>
let pendingLogoutUrl = '';

function closeToast() {
    const toast = document.getElementById('toast-container');
    if (toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }
}
if (document.getElementById('toast-container')) {
    setTimeout(closeToast, 4000);
}

function showJsToast(type, message) {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    let iconSvg = '<svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>';
    if (type === 'error') {
        iconSvg = '<svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>';
    }

    container.innerHTML = `
        <div class="toast-card toast-${type}">
            <div class="toast-icon">${iconSvg}</div>
            <div class="toast-content">
                <div class="toast-title">${type.charAt(0).toUpperCase() + type.slice(1)}</div>
                <div class="toast-msg">${message}</div>
            </div>
            <button type="button" class="toast-close" onclick="closeToast()">&times;</button>
            <div class="toast-progress"></div>
        </div>
    `;
    setTimeout(closeToast, 3500);
}

function confirmSignOut(e, logoutUrl) {
    e.preventDefault();
    pendingLogoutUrl = logoutUrl;
    const modal = document.getElementById('signOutModal');
    if (modal) {
        modal.classList.add('active');
    } else {
        if (confirm("Are you sure you want to sign out?")) {
            showJsToast('success', 'Logged out successfully! Redirecting...');
            setTimeout(() => { window.location.href = logoutUrl; }, 1000);
        }
    }
    return false;
}

function cancelSignOut() {
    const modal = document.getElementById('signOutModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

function proceedSignOut() {
    cancelSignOut();
    showJsToast('success', 'Logged out successfully! Redirecting to login...');
    setTimeout(() => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = pendingLogoutUrl || 'logout.php';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?php echo generateCSRFToken(); ?>';
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    }, 400);
}
</script>
