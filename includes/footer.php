<?php
// includes/footer.php
?>
        </main>
    </div>
</div>

<?php if (($currentRole ?? '') === 'student'): 
    $mBase = $basePath ?? '../';
    $mNav = $activeNav ?? '';
?>
<!-- Android / Mobile Browser Modern Bottom Navigation Bar -->
<nav class="mobile-bottom-nav" aria-label="Mobile Navigation">
    <div class="mobile-nav-items">
        <a href="<?php echo $mBase; ?>student/dashboard.php" class="mob-nav-link <?php echo ($mNav === 'dashboard') ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 00-1 1m-6 0h6"></path></svg>
            <span>Home</span>
        </a>
        <a href="<?php echo $mBase; ?>student/courses.php" class="mob-nav-link <?php echo ($mNav === 'courses') ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
            <span>Courses</span>
        </a>
        <a href="<?php echo $mBase; ?>student/assignments.php" class="mob-nav-link <?php echo ($mNav === 'assignments') ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            <span>Tasks</span>
        </a>
        <a href="<?php echo $mBase; ?>student/attendance.php" class="mob-nav-link <?php echo ($mNav === 'attendance') ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span>Attendance</span>
        </a>
        <a href="<?php echo $mBase; ?>student/profile.php" class="mob-nav-link <?php echo ($mNav === 'profile') ? 'active' : ''; ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
            <span>ID Pass</span>
        </a>
    </div>
</nav>
<?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar) {
        sidebar.classList.toggle('open');
        const isOpen = sidebar.classList.contains('open');
        if (backdrop) {
            backdrop.classList.toggle('active', isOpen);
        }
        document.body.style.overflow = (isOpen && window.innerWidth <= 992) ? 'hidden' : '';
    }
}

// Close sidebar on click outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.querySelector('.mobile-toggle');
    if (window.innerWidth <= 992 && sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
});

// Generic Table Filter Utility
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toLowerCase();
    const table = document.getElementById(tableId);
    if (!table) return;
    const trs = table.getElementsByTagName('tr');
    
    for (let i = 1; i < trs.length; i++) {
        let text = trs[i].textContent || trs[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            trs[i].style.display = "";
        } else {
            trs[i].style.display = "none";
        }
    }
}
</script>
</body>
</html>
