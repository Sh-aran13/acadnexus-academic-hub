<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/functions.php';

$currentRole = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$unreadCount = isset($_SESSION['user_id']) ? getUnreadNotificationCount($_SESSION['user_id']) : 0;

$basePath = (strpos($_SERVER['PHP_SELF'], '/student/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/faculty/') !== false || 
            strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? '../' : './';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | AcadNexus' : 'AcadNexus – Unified Academic Hub'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --secondary: #1e40af;
            --accent: #2563eb;
            --accent-gradient: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            --accent-glow: rgba(124, 58, 237, 0.25);
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --sidebar-width: 270px;
            --header-height: 70px;
            --radius-lg: 16px;
            --radius-md: 12px;
            --shadow-sm: 0 2px 4px rgba(15, 23, 42, 0.04);
            --shadow-md: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
            --shadow-lg: 0 20px 35px -10px rgba(15, 23, 42, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        html {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            -webkit-text-size-adjust: 100%;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            position: relative;
        }

        /* Layout Structure */
        .app-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }

        /* Sidebar Styles */
        .app-sidebar {
            width: var(--sidebar-width);
            background: #0f172a;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-logo-icon {
            width: 38px;
            height: 38px;
            background: var(--accent-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px var(--accent-glow);
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .brand-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 20px;
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            border: 1px solid rgba(129, 140, 248, 0.3);
            margin-left: auto;
        }

        .sidebar-menu {
            flex: 1;
            padding: 20px 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .menu-section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 12px 12px 6px 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-item:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.06);
            transform: translateX(3px);
        }

        .nav-item.active {
            color: #ffffff;
            background: var(--accent-gradient);
            font-weight: 600;
            box-shadow: 0 4px 15px var(--accent-glow);
        }

        .nav-item svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .nav-badge {
            margin-left: auto;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 10px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .user-panel {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .user-info {
            flex: 1;
            overflow: hidden;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 11px;
            color: #94a3b8;
            text-transform: capitalize;
        }

        /* Main Workspace Content Area */
        .app-main {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            position: relative;
        }

        /* Top Header Navigation */
        .app-header {
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-main);
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
        }

        .page-header-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .header-action-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            text-decoration: none;
            position: relative;
            transition: all 0.2s;
        }

        .header-action-btn:hover {
            background: #e2e8f0;
            color: var(--primary);
        }

        .notification-indicator {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: #ffffff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #ffffff;
        }

        .content-body {
            padding: 32px;
            flex: 1;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }

        /* Mobile Drawer Backdrop */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 99;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sidebar-backdrop.active {
            display: block;
            opacity: 1;
        }

        /* Mobile Bottom App Navigation Bar (Android & Mobile browsers) */
        .mobile-bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100%;
            height: 64px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 -4px 20px rgba(15, 23, 42, 0.08);
            z-index: 100;
            padding: 4px 6px;
            padding-bottom: max(6px, env(safe-area-inset-bottom));
            box-sizing: border-box;
        }

        .mobile-nav-items {
            display: flex;
            align-items: center;
            justify-content: space-around;
            height: 100%;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 0;
            box-sizing: border-box;
        }

        .mob-nav-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            color: #64748b;
            text-decoration: none;
            font-size: 10.5px;
            font-weight: 700;
            padding: 5px 4px;
            border-radius: 12px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            flex: 1;
            min-width: 0;
            max-width: 68px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            box-sizing: border-box;
        }

        .mob-nav-link span {
            font-size: 10.5px;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mob-nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .mob-nav-link:active {
            transform: scale(0.92);
        }

        .mob-nav-link.active {
            color: #4338ca;
            background: rgba(79, 70, 229, 0.1);
        }

        .mob-nav-link.active svg {
            transform: translateY(-2px);
            color: #4338ca;
            stroke-width: 2.4;
        }

        .mob-nav-badge {
            position: absolute;
            top: 2px;
            right: 14px;
            background: #ef4444;
            color: #ffffff;
            font-size: 9px;
            font-weight: 800;
            padding: 1px 5px;
            border-radius: 10px;
            line-height: 1.2;
        }

        /* Responsive Layout Overrides */
        @media (max-width: 992px) {
            .app-sidebar {
                transform: translateX(-100%);
                box-shadow: 14px 0 40px rgba(0, 0, 0, 0.35);
            }
            .app-sidebar.open {
                transform: translateX(0);
            }
            .app-main {
                margin-left: 0;
            }
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 38px;
                height: 38px;
                border-radius: 10px;
                background: #f1f5f9;
                color: #0f172a;
            }
            .mobile-toggle:active {
                background: #e2e8f0;
            }
            .content-body {
                padding: 16px 14px;
                padding-bottom: 90px; /* Space for bottom mobile nav */
            }
            .app-header {
                padding: 0 16px;
            }
            .mobile-bottom-nav {
                display: block;
            }
        }

        @media (max-width: 640px) {
            :root {
                --header-height: 62px;
            }
            .page-header-title {
                font-size: 16.5px;
                max-width: 175px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .header-right {
                gap: 8px;
            }
            .header-action-btn {
                width: 36px;
                height: 36px;
            }
            .content-body {
                padding: 12px 10px;
                padding-bottom: 90px;
            }
            /* Universal Mobile Card & Table Enhancements */
            .table-responsive-wrapper {
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                margin-bottom: 16px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Mobile Drawer Backdrop Overlay -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <!-- Sidebar Navigation -->
    <aside class="app-sidebar" id="appSidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
            </div>
            <div class="brand-title">AcadNexus</div>
            <span class="brand-badge"><?php echo strtoupper($currentRole); ?></span>
        </div>

        <nav class="sidebar-menu">
            <?php if ($currentRole === 'student'): ?>
                <div class="menu-section-label">Main Academic Menu</div>
                <a href="<?php echo $basePath; ?>student/dashboard.php" class="nav-item <?php echo ($activeNav === 'dashboard') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 00-1 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $basePath; ?>student/courses.php" class="nav-item <?php echo ($activeNav === 'courses') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <span>My Courses</span>
                </a>
                <a href="<?php echo $basePath; ?>student/assignments.php" class="nav-item <?php echo ($activeNav === 'assignments') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Assignments</span>
                </a>
                <a href="<?php echo $basePath; ?>student/materials.php" class="nav-item <?php echo ($activeNav === 'materials') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>Course Materials</span>
                </a>
                <a href="<?php echo $basePath; ?>student/attendance.php" class="nav-item <?php echo ($activeNav === 'attendance') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Attendance</span>
                </a>
                <a href="<?php echo $basePath; ?>student/results.php" class="nav-item <?php echo ($activeNav === 'results') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Results & Marks</span>
                </a>
                
                <div class="menu-section-label">Collaboration & Tools</div>
                <a href="<?php echo $basePath; ?>student/announcements.php" class="nav-item <?php echo ($activeNav === 'announcements') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    <span>Announcements</span>
                </a>
                <a href="<?php echo $basePath; ?>student/faculty.php" class="nav-item <?php echo ($activeNav === 'faculty') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <span>Faculty Directory</span>
                </a>
                <a href="<?php echo $basePath; ?>student/appointments.php" class="nav-item <?php echo ($activeNav === 'appointments') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Appointments</span>
                </a>
                <a href="<?php echo $basePath; ?>student/projects.php" class="nav-item <?php echo ($activeNav === 'projects') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    <span>Projects</span>
                </a>
                <a href="<?php echo $basePath; ?>student/notifications.php" class="nav-item <?php echo ($activeNav === 'notifications') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?><span class="nav-badge"><?php echo $unreadCount; ?></span><?php endif; ?>
                </a>

            <?php elseif ($currentRole === 'faculty'): ?>
                <div class="menu-section-label">Faculty Management</div>
                <a href="<?php echo $basePath; ?>faculty/dashboard.php" class="nav-item <?php echo ($activeNav === 'dashboard') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 00-1 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/courses.php" class="nav-item <?php echo ($activeNav === 'courses') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <span>My Courses</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/students.php" class="nav-item <?php echo ($activeNav === 'students') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>Students</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/assignments.php" class="nav-item <?php echo ($activeNav === 'assignments') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Assignments</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/materials.php" class="nav-item <?php echo ($activeNav === 'materials') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    <span>Course Materials</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/attendance.php" class="nav-item <?php echo ($activeNav === 'attendance') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    <span>Mark Attendance</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/marks.php" class="nav-item <?php echo ($activeNav === 'marks') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 30v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    <span>Enter Marks</span>
                </a>

                <div class="menu-section-label">Communication & Guidance</div>
                <a href="<?php echo $basePath; ?>faculty/announcements.php" class="nav-item <?php echo ($activeNav === 'announcements') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    <span>Announcements</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/appointments.php" class="nav-item <?php echo ($activeNav === 'appointments') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span>Appointments</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/projects.php" class="nav-item <?php echo ($activeNav === 'projects') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    <span>Project Mentorship</span>
                </a>
                <a href="<?php echo $basePath; ?>faculty/notifications.php" class="nav-item <?php echo ($activeNav === 'notifications') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?><span class="nav-badge"><?php echo $unreadCount; ?></span><?php endif; ?>
                </a>

            <?php elseif ($currentRole === 'admin'): ?>
                <div class="menu-section-label">System Administration</div>
                <a href="<?php echo $basePath; ?>admin/dashboard.php" class="nav-item <?php echo ($activeNav === 'dashboard') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 00-1 1m-6 0h6"></path></svg>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/users.php" class="nav-item <?php echo ($activeNav === 'users') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <span>User Accounts</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/students.php" class="nav-item <?php echo ($activeNav === 'students') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <span>Student Database</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/faculty.php" class="nav-item <?php echo ($activeNav === 'faculty') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <span>Faculty Database</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/departments.php" class="nav-item <?php echo ($activeNav === 'departments') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <span>Departments</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/courses.php" class="nav-item <?php echo ($activeNav === 'courses') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    <span>Courses</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/announcements.php" class="nav-item <?php echo ($activeNav === 'announcements') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    <span>Announcements</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/projects.php" class="nav-item <?php echo ($activeNav === 'projects') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    <span>Project Allocations</span>
                </a>
                <a href="<?php echo $basePath; ?>admin/reports.php" class="nav-item <?php echo ($activeNav === 'reports') ? 'active' : ''; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Reports & Analytics</span>
                </a>
            <?php endif; ?>

            <div class="menu-section-label">Account Settings</div>
            <a href="<?php echo $basePath . $currentRole; ?>/profile.php" class="nav-item <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <span>My Profile</span>
            </a>
            <a href="<?php echo $basePath . $currentRole; ?>/settings.php" class="nav-item <?php echo ($activeNav === 'settings') ? 'active' : ''; ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                <span>Settings</span>
            </a>
            <a href="<?php echo $basePath; ?>logout.php" onclick="return confirmSignOut(event, '<?php echo $basePath; ?>logout.php');" class="nav-item" style="color: #f87171;">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span>Sign Out</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-panel">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($userName, 0, 1)); ?>
                </div>
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($currentRole); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Workspace Container -->
    <div class="app-main">
        <header class="app-header">
            <div class="header-left">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                </button>
                <div class="page-header-title"><?php echo isset($pageHeading) ? htmlspecialchars($pageHeading) : 'Academic Workspace'; ?></div>
            </div>

            <div class="header-right">
                <?php if ($currentRole !== 'guest'): ?>
                <a href="<?php echo $basePath . $currentRole; ?>/notifications.php" class="header-action-btn" title="Notifications">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <?php if ($unreadCount > 0): ?>
                        <span class="notification-indicator"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo $basePath . $currentRole; ?>/profile.php" class="header-action-btn" title="Profile Settings">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                </a>
                <?php endif; ?>
            </div>
        </header>

        <main class="content-body">
            <?php require_once __DIR__ . '/toast.php'; ?>
