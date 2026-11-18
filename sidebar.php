<?php
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}
if (!isset($role_slug)) {
    $role_slug = isset($_SESSION['role']) ? $_SESSION['role'] : 'employee';
}
if ($role_slug === 'qa') {
    $role_slug = 'employee';
}

$is_employee_open = in_array($current_page, [$role_slug . '_employees.php', 'import_employees.php']);
$lunch_pages = ['employee_lunchtime_history.php', 'lunchtime_report.php', 'admin_alerts.php', 'tl_alerts.php', 'gl_alerts.php', 'hr_alerts.php', 'manage_requests.php', 'manage_overrides.php'];
$is_lunch_open = in_array($current_page, $lunch_pages);

$break_pages = ['employee_breaktime_history.php', 'breaktime_report.php', 'admin_breaktime_alerts.php', 'tl_breaktime_alerts.php', 'gl_breaktime_alerts.php', 'hr_breaktime_alerts.php'];
$is_break_open = in_array($current_page, $break_pages);

$pending_req_count = 0;
if (isset($conn) && $role_slug !== 'employee') {
    $gl_filter = $role_slug === 'gl' ? "AND e.group_leader_id = '{$_SESSION['emp_id']}'" : "";
    $req_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM break_change_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.status = 'pending' $gl_filter");
    if ($req_res) {
        $pending_req_count = mysqli_fetch_assoc($req_res)['cnt'];
    }
}

$pending_override_count = 0;
if (isset($conn) && $role_slug !== 'employee') {
    $gl_filter = $role_slug === 'gl' ? "AND e.group_leader_id = '{$_SESSION['emp_id']}'" : "";
    $override_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM break_override_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.status = 'pending' $gl_filter");
    if ($override_res) {
        $pending_override_count = mysqli_fetch_assoc($override_res)['cnt'];
    }
}

$pending_leave_count = 0;
$pending_shift_count = 0;
$unread_alerts_count = 0;
if (isset($conn) && $role_slug !== 'employee') {
    $gl_filter = $role_slug === 'gl' ? "AND e.group_leader_id = '{$_SESSION['emp_id']}'" : "";
    
    $leave_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM leave_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.status IN ('pending', 'pending_hr') $gl_filter");
    if ($leave_res) $pending_leave_count = mysqli_fetch_assoc($leave_res)['cnt'];
    
    $shift_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM shift_change_requests r JOIN employees e ON r.emp_id = e.emp_id WHERE r.status = 'pending' $gl_filter");
    if ($shift_res) $pending_shift_count = mysqli_fetch_assoc($shift_res)['cnt'];
    
    $alerts_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM system_alerts WHERE is_read = 0");
    if ($alerts_res) $unread_alerts_count = mysqli_fetch_assoc($alerts_res)['cnt'];
}
// Global Topbar Notification Bell for all pages
$global_notif_emp_id = $_SESSION['emp_id'] ?? '';
$global_notifs = [];
$global_unread_notifs_cnt = 0;
if (!empty($global_notif_emp_id) && isset($conn) && $conn instanceof mysqli) {
    $safe_global_emp_id = mysqli_real_escape_string($conn, $global_notif_emp_id);
    $global_notif_q = @mysqli_query($conn, "SELECT id, message, is_read, created_at FROM employee_notifications WHERE emp_id = '$safe_global_emp_id' ORDER BY created_at DESC LIMIT 20");
    if ($global_notif_q) {
        while ($gn = mysqli_fetch_assoc($global_notif_q)) {
            $global_notifs[] = $gn;
            if ((int)$gn['is_read'] === 0) $global_unread_notifs_cnt++;
        }
    }
    // Cache profile picture in session if not set yet
    if (!isset($_SESSION['profile_picture'])) {
        $pic_res = @mysqli_query($conn, "SELECT profile_picture FROM employees WHERE emp_id = '$safe_global_emp_id' LIMIT 1");
        if ($pic_res && $pr = mysqli_fetch_assoc($pic_res)) {
            $_SESSION['profile_picture'] = $pr['profile_picture'];
        } else {
            $_SESSION['profile_picture'] = null;
        }
    }
}
if (!function_exists('get_sidebar_notif_time')) {
    function get_sidebar_notif_time($datetime) {
        $time = strtotime($datetime);
        $diff = time() - $time;
        if ($diff < 60) return "Just now";
        if ($diff < 3600) return floor($diff / 60) . "m ago";
        if ($diff < 86400) return floor($diff / 3600) . "h ago";
        if ($diff < 604800) return floor($diff / 86400) . "d ago";
        return date("M j, Y", $time);
    }
}
?>
<style>
    /* Base Sidebar Overrides */
    .sidebar * {
        box-sizing: border-box;
    }

    .sidebar ul {
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .sidebar {
        width: 280px;
        min-height: 100vh;
        height: 100%;
        background-color: #ffffff !important;
        display: flex;
        flex-direction: column;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.02);
        z-index: 100;
        flex-shrink: 0;
    }

    /* Mobile Responsive Sidebar */
    .mobile-toggle {
        display: none;
        background: transparent;
        color: #0f172a;
        border: none;
        font-size: 28px;
        cursor: pointer;
        margin-right: 15px;
        padding: 0;
        transition: 0.2s;
    }

    body.dark-mode .mobile-toggle {
        color: #f8fafc;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 95;
        backdrop-filter: blur(2px);
        opacity: 0;
        transition: opacity 0.3s;
    }

    @media (max-width: 1024px), (pointer: coarse) {
        .mobile-toggle {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 40px !important;
            height: 40px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            margin-right: 12px !important;
            flex-shrink: 0 !important;
            background: #f1f5f9 !important;
            color: #0f172a !important;
            border: 1px solid #e2e8f0 !important;
        }

        body.dark-mode .mobile-toggle {
            background: #1e293b !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }

        /* NEVER show desktop toggle on mobile/tablet */
        .desktop-toggle {
            display: none !important;
            visibility: hidden !important;
            pointer-events: none !important;
        }

        /* Sidebar is ALWAYS an off-canvas drawer on mobile */
        .sidebar {
            position: fixed !important;
            left: -320px !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 280px !important;
            max-width: 80vw !important;
            height: 100vh !important;
            z-index: 99999 !important;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            transform: translateX(0) !important;
            box-shadow: none !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch !important;
            padding-bottom: calc(100px + env(safe-area-inset-bottom, 0px)) !important;
        }

        body.sidebar-open .sidebar {
            transform: translateX(320px) !important;
            box-shadow: 15px 0 50px rgba(0, 0, 0, 0.3) !important;
        }

        body.sidebar-open .sidebar-overlay {
            display: block !important;
            opacity: 1 !important;
            z-index: 99998 !important;
        }

        .topbar {
            padding: calc(14px + env(safe-area-inset-top, 0px)) 16px 12px 16px !important;
            justify-content: space-between !important;
            align-items: center !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }

        .topbar .page-title {
            font-size: 17px !important;
            margin-left: 0 !important;
        }

        .content {
            padding: 12px 14px 40px 14px !important;
        }

        .sidebar .sidebar-logout-btn {
            background: #ef4444 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
            font-weight: 700 !important;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35) !important;
            margin-top: 12px !important;
        }
        .sidebar .sidebar-logout-btn svg {
            stroke: #ffffff !important;
        }

        .mobile-topbar-logout {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            background: #fee2e2 !important;
            color: #ef4444 !important;
            border: 1px solid #fecaca !important;
            text-decoration: none !important;
            flex-shrink: 0 !important;
            margin-left: 6px !important;
            cursor: pointer !important;
            transition: 0.2s !important;
        }
        body.dark-mode .mobile-topbar-logout {
            background: #450a0a !important;
            color: #f87171 !important;
            border-color: #7f1d1d !important;
        }
    }

    .mobile-topbar-logout {
        display: none;
    }

    /* Fallback for JS-flagged mobile mode regardless of screen resolution */
    body.mobile-mode .mobile-toggle {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 40px !important;
        height: 40px !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        margin-right: 12px !important;
        flex-shrink: 0 !important;
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #e2e8f0 !important;
    }
    body.mobile-mode.dark-mode .mobile-toggle {
        background: #1e293b !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }
    body.mobile-mode .desktop-toggle {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }
    body.mobile-mode .sidebar {
        position: fixed !important;
        left: -320px !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 280px !important;
        max-width: 80vw !important;
        height: 100vh !important;
        z-index: 99999 !important;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        transform: translateX(0) !important;
        box-shadow: none !important;
        overflow-y: auto !important;
    }
    body.mobile-mode.sidebar-open .sidebar {
        transform: translateX(320px) !important;
        box-shadow: 15px 0 50px rgba(0, 0, 0, 0.3) !important;
    }
    body.mobile-mode.sidebar-open .sidebar-overlay {
        display: block !important;
        opacity: 1 !important;
        z-index: 99998 !important;
    }
    body.mobile-mode .topbar {
        padding: calc(14px + env(safe-area-inset-top, 0px)) 16px 12px 16px !important;
        justify-content: space-between !important;
        align-items: center !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    body.mobile-mode .content {
        padding: 12px 14px 40px 14px !important;
    }
    body.mobile-mode .mobile-bottom-nav {
        display: flex !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: calc(60px + env(safe-area-inset-bottom, 0px)) !important;
        padding-bottom: env(safe-area-inset-bottom, 0px) !important;
        background: #ffffff !important;
        border-top: 1px solid #e2e8f0 !important;
        z-index: 99990 !important;
        justify-content: space-around !important;
        align-items: center !important;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.06) !important;
    }

    /* Global Layout Constraints to prevent mobile browsers from zooming out */
    body {
        overflow-x: hidden;
        max-width: 100%;
    }
    @media (max-width: 1024px) {
        body {
            width: 100% !important;
            max-width: 100vw !important;
            overflow-x: hidden !important;
        }
    }

    .main-content {
        min-width: 0 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        height: 100vh;
        width: 100%;
    }

    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        width: 100%;
        padding-bottom: 15px;
    }

    table {
        min-width: 700px;
    }

    td,
    th,
    .badge {
        white-space: nowrap;
    }

    .sidebar-header {
        padding: 30px 24px;
        font-size: 20px;
        font-weight: 800;
        color: #0f172a;
        text-transform: capitalize;
    }

    .nav-menu {
        list-style: none;
        padding: 10px 0;
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
        overflow-y: auto;
    }
    
    /* Custom Scrollbar for Sidebar */
    .nav-menu::-webkit-scrollbar {
        width: 6px;
    }
    .nav-menu::-webkit-scrollbar-track {
        background: transparent;
    }
    .nav-menu::-webkit-scrollbar-thumb {
        background-color: rgba(100, 116, 139, 0.3);
        border-radius: 10px;
    }
    body.dark-mode .nav-menu::-webkit-scrollbar-thumb {
        background-color: rgba(148, 163, 184, 0.2);
    }

    .nav-item {
        padding: 15px 24px;
        cursor: pointer;
        color: #64748b;
        font-weight: 600;
        margin: 0 20px;
        border-radius: 12px;
        transition: 0.2s;
        text-decoration: none;
        display: block;
        font-size: 15px;
    }

    .nav-item:hover {
        background-color: #f1f5f9;
    }

    .nav-item.active {
        background-color: #eff6ff;
        color: #3b82f6;
    }

    /* CSS for Dropdowns */
    .nav-group {
        margin: 0 20px;
        border-radius: 12px;
        transition: 0.3s;
    }

    .nav-group-title {
        padding: 15px 24px;
        cursor: pointer;
        color: #64748b;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: 0.2s;
        user-select: none;
        border-radius: 12px;
        font-size: 15px;
    }

    .nav-group-title:hover {
        background-color: #f1f5f9;
    }

    .nav-group.open>.nav-group-title {
        color: #3b82f6;
        background: transparent;
    }

    /* Dark mode overrides for sidebar */
    body.dark-mode .sidebar {
        background-color: #1e293b !important;
        box-shadow: 4px 0 24px rgba(0, 0, 0, 0.2) !important;
    }

    body.dark-mode .sidebar-header {
        color: #f8fafc !important;
    }

    body.dark-mode .nav-item {
        color: #94a3b8;
    }

    body.dark-mode .nav-item:hover {
        background-color: #334155;
        color: #f8fafc;
    }

    body.dark-mode .nav-item.active {
        background-color: #1e3a8a;
        color: #60a5fa;
    }

    body.dark-mode .nav-group-title {
        color: #94a3b8;
    }

    body.dark-mode .nav-group-title:hover {
        background-color: #334155;
        color: #f8fafc;
    }

    body.dark-mode .nav-group.open>.nav-group-title {
        color: #60a5fa;
    }

    .dropdown-icon {
        font-size: 10px;
        transition: transform 0.3s;
    }

    .nav-group.open .dropdown-icon {
        transform: rotate(180deg);
    }

    .nav-dropdown {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    .nav-group.open .nav-dropdown {
        max-height: 800px;
    }

    .nav-dropdown .nav-item {
        padding: 10px 24px;
        margin: 4px 24px;
        border-radius: 8px;
        font-size: 14px;
    }

    /* Switch Styles */
    .switch input:checked+.slider {
        background-color: #3b82f6;
    }

    .switch input:checked+.slider:before {
        transform: translateX(20px);
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    /* ------------------------------------------- */
    /* DESKTOP POPOUT STYLES (Document Bubble)     */
    /* ------------------------------------------- */
    @media (min-width: 769px) {
        .nav-group {
            overflow: visible; /* Prevent clipping */
        }

        /* On desktop, nav-dropdown inside nav-group is ALWAYS hidden.
           The JS portal moves it to <body> with .nav-dropdown-portal to show it. */
        .nav-dropdown {
            display: none !important;
        }

        /* Adjust nav item spacing inside the popout */
        .nav-dropdown .nav-item {
            margin: 4px 12px !important;
        }
    }

    /* Portal dropdown — appended to <body> by JS on desktop */
    .nav-dropdown.nav-dropdown-portal {
        display: block !important;
        position: fixed !important;
        width: 240px;
        background-color: #ffffff;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        border: 1px solid #e2e8f0;
        padding: 8px 0;
        z-index: 99999;
        max-height: none !important;
        overflow: visible !important;
        animation: portalFadeIn 0.15s ease forwards;
    }

    .nav-dropdown.nav-dropdown-portal .nav-item {
        font-size: 14px;
        padding: 10px 20px;
        margin: 4px 12px;
    }

    body.dark-mode .nav-dropdown.nav-dropdown-portal {
        background-color: #1e293b;
        border-color: #334155;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    }

    @keyframes portalFadeIn {
        from { opacity: 0; transform: translateX(-8px); }
        to   { opacity: 1; transform: translateX(0); }
    }
    
    .desktop-toggle {
        display: none;
    }
    
    @media (min-width: 1025px) {
        body:not(.mobile-mode) .sidebar {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1000;
        }
        
        body:not(.mobile-mode) .desktop-toggle {
            display: flex;
            position: absolute;
            top: 25px;
            right: -15px;
            width: 30px;
            height: 30px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            border: 2px solid #f8fafc;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        body.dark-mode:not(.mobile-mode) .desktop-toggle {
            border-color: #0f172a;
        }
        
        /* Collapsed state */
        body.sidebar-collapsed:not(.mobile-mode) .sidebar {
            width: 0;
            padding: 0;
        }
        
        body.sidebar-collapsed:not(.mobile-mode) .sidebar > *:not(.desktop-toggle) {
            display: none !important;
        }
        
        body.sidebar-collapsed:not(.mobile-mode) .desktop-toggle {
            transform: rotate(180deg);
            right: -40px;
        }
    }

    /* Modern Logout Button */
    .sidebar-logout-btn {
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: #fef2f2;
        color: #ef4444;
        border: 1px solid #fee2e2;
        padding: 12px 16px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        box-sizing: border-box;
    }

    .sidebar-logout-btn:hover {
        background: #ef4444;
        color: #ffffff !important;
        border-color: #ef4444;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
    }

    .sidebar-logout-btn svg {
        transition: transform 0.2s ease-in-out;
        flex-shrink: 0;
    }

    .sidebar-logout-btn:hover svg {
        transform: translateX(3px);
    }

    /* Dark mode overrides for logout button */
    body.dark-mode .sidebar-logout-btn {
        background: rgba(239, 68, 68, 0.12);
        color: #f87171;
        border-color: rgba(239, 68, 68, 0.25);
    }

    body.dark-mode .sidebar-logout-btn:hover {
        background: #ef4444;
        color: #ffffff !important;
        border-color: #ef4444;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }

    /* =======================================================
       NATIVE MOBILE BOTTOM NAVIGATION BAR
       ======================================================= */
    .mobile-bottom-nav {
        display: none;
    }

    @media (max-width: 1024px) {
        .mobile-bottom-nav {
            display: flex !important;
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            height: calc(60px + env(safe-area-inset-bottom, 0px)) !important;
            padding-bottom: env(safe-area-inset-bottom, 0px) !important;
            background: #ffffff !important;
            border-top: 1px solid #e2e8f0 !important;
            z-index: 99990 !important;
            justify-content: space-around !important;
            align-items: center !important;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.06) !important;
        }

        body.dark-mode .mobile-bottom-nav {
            background: #1e293b !important;
            border-top-color: #334155 !important;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4) !important;
        }

        .mobile-nav-link {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
            color: #64748b !important;
            padding: 8px 2px !important;
            transition: color 0.15s, transform 0.1s !important;
            -webkit-tap-highlight-color: transparent !important;
        }

        .mobile-nav-link:active {
            transform: scale(0.92) !important;
        }

        .mobile-nav-icon {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 24px !important;
            height: 24px !important;
            margin-bottom: 3px !important;
        }

        .mobile-nav-icon svg {
            width: 22px !important;
            height: 22px !important;
        }

        .mobile-nav-label {
            font-size: 11px !important;
            font-weight: 600 !important;
            letter-spacing: -0.2px !important;
            white-space: nowrap !important;
        }

        .mobile-nav-link.active {
            color: #3b82f6 !important;
        }

        .mobile-nav-link.active .mobile-nav-icon svg {
            stroke-width: 2.4 !important;
        }

        body.dark-mode .mobile-nav-link {
            color: #94a3b8 !important;
        }

        body.dark-mode .mobile-nav-link.active {
            color: #60a5fa !important;
        }

        /* Pad all content so bottom navigation never covers buttons */
        .main-content, .content, body {
            padding-bottom: calc(75px + env(safe-area-inset-bottom, 0px)) !important;
        }
    }
</style>

<!-- Mobile Overlay -->
<div class="sidebar-overlay no-print" onclick="toggleMobileSidebar()"></div>

<aside class="sidebar no-print">
    <!-- Desktop Toggle Button -->
    <div class="desktop-toggle" onclick="toggleDesktopSidebar()" title="Toggle Sidebar">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 16px; height: 16px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
    </div>
    <a href="<?php echo $role_slug; ?>_dashboard.php" style="text-decoration: none; color: inherit;">
        <div class="sidebar-header" style="text-align: center; padding: 25px 0;">
            <img src="pic/WeRide_logo.png" alt="WeRide Logo"
                style="max-height: 45px; width: auto; object-fit: contain;">
        </div>
    </a>

    <ul class="nav-menu">
        <!-- Dashboard -->
        <a href="<?php echo $role_slug; ?>_dashboard.php" style="text-decoration: none;">
            <li class="nav-item <?php echo strpos($current_page, 'dashboard') !== false ? 'active' : ''; ?>">Dashboard
            </li>
        </a>

        <?php if ($role_slug === 'gl' || $role_slug === 'tl'): ?>
            <!-- Live Attendance (GL & TL) -->
            <a href="<?php echo $role_slug; ?>_live_attendance.php" style="text-decoration: none;">
                <li class="nav-item <?php echo strpos($current_page, 'live_attendance') !== false ? 'active' : ''; ?>">Live Attendance</li>
            </a>
        <?php endif; ?>

        <!-- Employee Dropdown (Admin/HR/TL only) -->
        <?php if ($role_slug !== 'employee'): ?>
            <li class="nav-group <?php echo $is_employee_open ? 'open' : ''; ?>" onclick="toggleDropdown(this)">
                <div class="nav-group-title">
                    <span>Employee</span>
                    <span class="dropdown-icon">▼</span>
                </div>
                <ul class="nav-dropdown" onclick="event.stopPropagation()">
                    <a href="<?php echo $role_slug; ?>_employees.php" style="text-decoration: none;">
                        <li
                            class="nav-item <?php echo strpos($current_page, 'employees') !== false && $current_page !== 'import_employees.php' ? 'active' : ''; ?>">
                            Employee Directory</li>
                    </a>
                    <a href="import_employees.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'import_employees.php' ? 'active' : ''; ?>">Import
                            Employee</li>
                    </a>
                </ul>
            </li>
        <?php endif; ?>

        <!-- Lunchtime Dropdown -->
        <li class="nav-group <?php echo $is_lunch_open ? 'open' : ''; ?>" onclick="toggleDropdown(this)">
            <div class="nav-group-title">
                <span>
                    Lunchtime
                    <?php if ((isset($pending_req_count) && $pending_req_count > 0) || (isset($pending_override_count) && $pending_override_count > 0)): ?>
                        <span
                            class="text-red-500" style="display:inline-block; width:8px; height:8px; background-color:#ef4444; border-radius:50%; margin-left:8px; vertical-align:middle; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);"></span>
                    <?php endif; ?>
                </span>
                <span class="dropdown-icon">▼</span>
            </div>
            <ul class="nav-dropdown" onclick="event.stopPropagation()">
                <!-- History -->
                <?php if ($role_slug === 'employee' || $role_slug === 'gl'): ?>
                <a href="employee_lunchtime_history.php" style="text-decoration: none;">
                    <li class="nav-item <?php echo strpos($current_page, 'lunchtime_history') !== false ? 'active' : ''; ?>">
                        <?php echo $role_slug === 'employee' ? 'History' : 'My History'; ?>
                    </li>
                </a>
                <?php endif; ?>
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="lunchtime_report.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'lunchtime_report.php' ? 'active' : ''; ?>">Team History</li>
                    </a>
                <?php endif; ?>

                <!-- Violation (Admin/HR/TL only) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="<?php echo $role_slug; ?>_alerts.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo strpos($current_page, 'alerts') !== false && strpos($current_page, 'breaktime') === false && strpos($current_page, 'attendance') === false ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Violation
                            <?php if (isset($unread_alerts_count) && $unread_alerts_count > 0): ?>
                                <span class="text-white" style="background-color: #ef4444; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $unread_alerts_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>
                <?php endif; ?>

                <!-- Request (Admin/HR/TL only) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="manage_requests.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_requests.php' ? 'active' : ''; ?>"
                            style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Lunch Requests
                            <?php if (isset($pending_req_count) && $pending_req_count > 0): ?>
                                <span
                                    class="text-red-500 text-white" style="background-color: #ef4444; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_req_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>

                    <a href="manage_overrides.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_overrides.php' ? 'active' : ''; ?>"
                            style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Override Request
                            <?php if (isset($pending_override_count) && $pending_override_count > 0): ?>
                                <span
                                    class="text-red-500 text-white" style="background-color: #ef4444; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_override_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>
                <?php endif; ?>
            </ul>
        </li>

        <!-- Breaktime Dropdown -->
        <li class="nav-group <?php echo $is_break_open ? 'open' : ''; ?>" onclick="toggleDropdown(this)">
            <div class="nav-group-title">
                <span>Breaktime</span>
                <span class="dropdown-icon">▼</span>
            </div>
            <ul class="nav-dropdown" onclick="event.stopPropagation()">
                <!-- History -->
                <?php if ($role_slug === 'employee' || $role_slug === 'gl'): ?>
                <a href="employee_breaktime_history.php" style="text-decoration: none;">
                    <li class="nav-item <?php echo strpos($current_page, 'breaktime_history') !== false ? 'active' : ''; ?>">
                        <?php echo $role_slug === 'employee' ? 'History' : 'My History'; ?>
                    </li>
                </a>
                <?php endif; ?>
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="breaktime_report.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'breaktime_report.php' ? 'active' : ''; ?>">Team History</li>
                    </a>
                <?php endif; ?>

                <!-- Violation (Admin/HR/TL only) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="<?php echo $role_slug; ?>_breaktime_alerts.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo strpos($current_page, 'breaktime_alerts') !== false ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Violation
                            <?php if (isset($unread_alerts_count) && $unread_alerts_count > 0): ?>
                                <span class="text-white" style="background-color: #ef4444; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $unread_alerts_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>
                <?php endif; ?>
            </ul>
        </li>

        <!-- Attendance Dropdown -->
        <?php
        $attendance_pages = ['admin_attendance.php', 'attendance_report.php', 'public_holidays.php', 'leave_application.php', 'employee_stats.php', 'manage_attendance_alerts.php', 'work_hours_report.php', 'employee_leave_history.php', 'manage_leave_requests.php', 'manage_shift_requests.php', 'override_attendance.php'];
        $is_attendance_open = in_array($current_page, $attendance_pages);
        ?>
        <li class="nav-group <?php echo $is_attendance_open ? 'open' : ''; ?>" onclick="toggleDropdown(this)">
            <div class="nav-group-title">
                <span>
                    Attendance
                    <?php if ($pending_leave_count > 0 || $pending_shift_count > 0 || (isset($unread_alerts_count) && $unread_alerts_count > 0)): ?>
                        <span class="text-red-500" style="display:inline-block; width:8px; height:8px; background-color:#ef4444; border-radius:50%; margin-left:8px; vertical-align:middle; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);"></span>
                    <?php endif; ?>
                </span>
                <span class="dropdown-icon">▼</span>
            </div>
            <ul class="nav-dropdown" onclick="event.stopPropagation()">
                <!-- My Statistics (Employee and GL) -->
                <?php if ($role_slug === 'employee' || $role_slug === 'gl'): ?>
                    <a href="employee_stats.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'employee_stats.php' ? 'active' : ''; ?>">My Statistics</li>
                    </a>
                <?php endif; ?>

                <!-- History (Employee and GL) -->
                <?php if ($role_slug === 'employee' || $role_slug === 'gl'): ?>
                    <a href="employee_attendance_history.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo strpos($current_page, 'attendance_history') !== false ? 'active' : ''; ?>">
                            <?php echo $role_slug === 'employee' ? 'History' : 'My History'; ?>
                        </li>
                    </a>
                    <a href="employee_leave_history.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo strpos($current_page, 'leave_history') !== false ? 'active' : ''; ?>">
                            <?php echo $role_slug === 'employee' ? 'Leave History' : 'My Leave History'; ?>
                        </li>
                    </a>
                <?php endif; ?>



                <!-- Team History (non-employee) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="attendance_report.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'attendance_report.php' ? 'active' : ''; ?>">Team History</li>
                    </a>
                <?php endif; ?>

                <!-- Violation (non-employee) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="manage_attendance_alerts.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_attendance_alerts.php' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Violation
                            <?php if (isset($unread_alerts_count) && $unread_alerts_count > 0): ?>
                                <span class="text-white" style="background-color: #ef4444; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $unread_alerts_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>

                    <a href="work_hours_report.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'work_hours_report.php' ? 'active' : ''; ?>">Work Hours Report</li>
                    </a>
                <?php endif; ?>

                <!-- Calendar (all roles) -->
                <a href="admin_attendance.php" style="text-decoration: none;">
                    <li class="nav-item <?php echo $current_page == 'admin_attendance.php' ? 'active' : ''; ?>">Calendar</li>
                </a>

                <!-- Public Holidays + Manage items (non-employee) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="public_holidays.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'public_holidays.php' ? 'active' : ''; ?>">Public Holidays</li>
                    </a>

                    <a href="override_attendance.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'override_attendance.php' ? 'active' : ''; ?>">Override Attendance</li>
                    </a>

                    <a href="manage_leave_requests.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_leave_requests.php' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Manage Leaves
                            <?php if ($pending_leave_count > 0): ?>
                                <span class="text-red-500 text-white" style="background-color: #ef4444; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_leave_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>

                    <a href="manage_shift_requests.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_shift_requests.php' ? 'active' : ''; ?>" style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Manage Shifts
                            <?php if ($pending_shift_count > 0): ?>
                                <span class="text-red-500 text-white" style="background-color: #ef4444; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_shift_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>
                <?php endif; ?>
            </ul>
        </li>



        <!-- WeRide Insides -->
        <a href="weride_insides.php" style="text-decoration: none;">
            <li class="nav-item <?php echo $current_page == 'weride_insides.php' ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <span>WeRide Insides</span>
                <span style="font-size: 10px; background: linear-gradient(135deg, #00a6d6, #0284c7); color: white; padding: 2px 7px; border-radius: 999px; font-weight: 700; letter-spacing: 0.5px;">FEED</span>
            </li>
        </a>

        <!-- Profile -->
        <li class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <a href="profile.php"
                style="text-decoration: none; color: inherit; display: block; width: 100%; height: 100%;">Profile</a>
        </li>

    </ul>

    <div style="padding: 20px; border-top: 1px solid rgba(0,0,0,0.05);">
        <?php if ($current_page !== 'admin_attendance.php'): ?>
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 0 10px;">
            <div id="theme-text-container" style="display: flex; align-items: center; gap: 10px; color: #64748b; font-weight: 600;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                    </path>
                </svg> Dark mode
            </div>
            <button id="theme-toggle-btn" onclick="toggleTheme()" style="width: 36px; height: 36px; border-radius: 50%; border: none; background: #e2e8f0; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #475569; transition: 0.2s;">
                <svg id="theme-btn-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('theme');
            const body = document.body;
            const themeBtn = document.getElementById('theme-toggle-btn');
            const themeTextContainer = document.getElementById('theme-text-container');
            const themeBtnIcon = document.getElementById('theme-btn-icon');

            let isCurrentlyDark = body.classList.contains('dark-mode');
            if (currentTheme === 'dark' || (!currentTheme && window.location.pathname.includes('dashboard.php'))) {
                body.classList.add('dark-mode');
                isCurrentlyDark = true;
            }

            if (themeBtn) {
                updateToggleUI(isCurrentlyDark);

                window.toggleTheme = function() {
                    isCurrentlyDark = !isCurrentlyDark;
                    updateToggleUI(isCurrentlyDark);
                    
                    if (isCurrentlyDark) {
                        body.classList.add('dark-mode');
                    } else {
                        body.classList.remove('dark-mode');
                    }
                    
                    localStorage.setItem('theme', isCurrentlyDark ? 'dark' : 'light');
                    document.cookie = "theme=" + (isCurrentlyDark ? "dark" : "light") + "; path=/; max-age=31536000";
                    
                    if (typeof updateChartTheme === 'function') {
                        updateChartTheme(isCurrentlyDark);
                    }
                };

                function updateToggleUI(isDark) {
                    if (themeTextContainer) {
                        if (isDark) {
                            themeTextContainer.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line></svg> Light mode`;
                        } else {
                            themeTextContainer.innerHTML = `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg> Dark mode`;
                        }
                    }
                    if (themeBtnIcon) {
                        if (isDark) {
                            themeBtnIcon.innerHTML = `<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"></line>`;
                        } else {
                            themeBtnIcon.innerHTML = `<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>`;
                        }
                    }
                }
            }
        });
        </script>
        <?php else: ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.remove('dark-mode');
        });
        </script>
        <?php endif; ?>

        <?php if (!isset($pwa_head_included)): $pwa_head_included = true; ?>
            <?php include_once __DIR__ . '/pwa_head.php'; ?>
        <?php endif; ?>
        <button type="button" onclick="promptPwaInstall()" class="pwa-install-btn sidebar-logout-btn" style="display:none; margin-bottom: 8px; border: 1px solid rgba(56,189,248,0.3); color: #38bdf8; background: rgba(56,189,248,0.08); cursor: pointer; align-items: center; gap: 8px; width: 100%; border-radius: 8px; padding: 10px 14px; font-weight: 600; font-size: 13px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect>
                <line x1="12" y1="18" x2="12.01" y2="18"></line>
            </svg>
            <span>Install App</span>
        </button>
        <a href="logout.php" class="sidebar-logout-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Native Mobile App Bottom Navigation Bar -->
<nav class="mobile-bottom-nav no-print">
    <?php if ($role_slug === 'employee'): ?>
        <a href="employee_dashboard.php" class="mobile-nav-link <?php echo strpos($current_page, 'dashboard') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <span class="mobile-nav-label">Home</span>
        </a>
        <a href="weride_insides.php" class="mobile-nav-link <?php echo strpos($current_page, 'weride_insides') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </span>
            <span class="mobile-nav-label">Feed</span>
        </a>
        <a href="employee_lunchtime_history.php" class="mobile-nav-link <?php echo (strpos($current_page, 'lunchtime') !== false || strpos($current_page, 'breaktime') !== false) ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </span>
            <span class="mobile-nav-label">Breaks</span>
        </a>
        <a href="employee_attendance_history.php" class="mobile-nav-link <?php echo strpos($current_page, 'attendance_history') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </span>
            <span class="mobile-nav-label">Attendance</span>
        </a>
        <a href="javascript:void(0)" onclick="toggleMobileSidebar()" class="mobile-nav-link <?php echo (strpos($current_page, 'profile') !== false || strpos($current_page, 'leave') !== false) ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </span>
            <span class="mobile-nav-label">Menu</span>
        </a>
    <?php elseif ($role_slug === 'gl' || $role_slug === 'tl'): ?>
        <a href="<?php echo $role_slug; ?>_dashboard.php" class="mobile-nav-link <?php echo strpos($current_page, 'dashboard') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <span class="mobile-nav-label">Home</span>
        </a>
        <a href="weride_insides.php" class="mobile-nav-link <?php echo strpos($current_page, 'weride_insides') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </span>
            <span class="mobile-nav-label">Feed</span>
        </a>
        <a href="<?php echo $role_slug; ?>_live_attendance.php" class="mobile-nav-link <?php echo strpos($current_page, 'live_attendance') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </span>
            <span class="mobile-nav-label">Live</span>
        </a>
        <a href="<?php echo $role_slug; ?>_employees.php" class="mobile-nav-link <?php echo strpos($current_page, 'employees') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span class="mobile-nav-label">Team</span>
        </a>
        <a href="javascript:void(0)" onclick="toggleMobileSidebar()" class="mobile-nav-link <?php echo (strpos($current_page, 'profile') !== false || strpos($current_page, 'leave') !== false) ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </span>
            <span class="mobile-nav-label">Menu</span>
        </a>
    <?php else: /* Admin / HR */ ?>
        <a href="<?php echo $role_slug; ?>_dashboard.php" class="mobile-nav-link <?php echo strpos($current_page, 'dashboard') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <span class="mobile-nav-label">Home</span>
        </a>
        <a href="weride_insides.php" class="mobile-nav-link <?php echo strpos($current_page, 'weride_insides') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 20H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1m2 13a2 2 0 0 1-2-2V7m2 13a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
            </span>
            <span class="mobile-nav-label">Feed</span>
        </a>
        <a href="admin_attendance.php" class="mobile-nav-link <?php echo strpos($current_page, 'attendance') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </span>
            <span class="mobile-nav-label">Attendance</span>
        </a>
        <a href="<?php echo $role_slug; ?>_employees.php" class="mobile-nav-link <?php echo strpos($current_page, 'employees') !== false ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            <span class="mobile-nav-label">Staff</span>
        </a>
        <a href="javascript:void(0)" onclick="toggleMobileSidebar()" class="mobile-nav-link <?php echo (strpos($current_page, 'profile') !== false || strpos($current_page, 'leave') !== false) ? 'active' : ''; ?>">
            <span class="mobile-nav-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </span>
            <span class="mobile-nav-label">Menu</span>
        </a>
    <?php endif; ?>
</nav>

<script>
    // ─── PORTAL DROPDOWN (Desktop only) ────────────────────────────────────────
    // position:fixed won't escape overflow-y:auto on .nav-menu, so we "portal"
    // the dropdown into <body> on open and return it on close.
    let activePortal = null;    // { group, dropdown, placeholder }

    function closePortalDropdown() {
        if (!activePortal) return;
        const { group, dropdown, placeholder } = activePortal;
        // Remove portal class before moving back
        dropdown.classList.remove('nav-dropdown-portal');
        // Clear all inline styles set by portal
        dropdown.removeAttribute('style');
        // Return dropdown to its original nav-group
        placeholder.parentNode.insertBefore(dropdown, placeholder);
        placeholder.remove();
        group.classList.remove('open');
        activePortal = null;
    }

    function toggleDropdown(element) {
        // ── MOBILE: simple accordion ──────────────────────────────────────────
        if (window.innerWidth <= 1024 || document.body.classList.contains('mobile-mode')) {
            element.classList.toggle('open');
            return;
        }

        // ── DESKTOP: portal popup ─────────────────────────────────────────────
        // If clicking the already-open group, just close it
        if (activePortal && activePortal.group === element) {
            closePortalDropdown();
            return;
        }

        // Close any currently open portal first
        closePortalDropdown();

        const dropdown = element.querySelector('.nav-dropdown');
        if (!dropdown) return;

        // Mark group as open (for the arrow icon rotation)
        element.classList.add('open');

        // Leave a placeholder where the dropdown was so DOM stays intact
        const placeholder = document.createElement('li');
        placeholder.style.display = 'none';
        placeholder.className = 'nav-dropdown-placeholder';
        dropdown.parentNode.insertBefore(placeholder, dropdown);

        // Move dropdown to body and add portal class (CSS does the rest)
        document.body.appendChild(dropdown);
        dropdown.classList.add('nav-dropdown-portal');

        // Position it to the right of the sidebar group
        const rect = element.getBoundingClientRect();
        dropdown.style.top  = rect.top + 'px';
        dropdown.style.left = (rect.right + 12) + 'px';

        activePortal = { group: element, dropdown, placeholder };
    }

    // Close when clicking outside the popup or sidebar
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 1024 || !activePortal) return;
        const { group, dropdown } = activePortal;
        if (!group.contains(e.target) && !dropdown.contains(e.target)) {
            closePortalDropdown();
        }
    });

    // Close when sidebar scrolls (so it doesn't drift)
    document.addEventListener('DOMContentLoaded', function () {
        const navMenu = document.querySelector('.nav-menu');
        if (navMenu) {
            navMenu.addEventListener('scroll', function () {
                if (window.innerWidth > 1024) closePortalDropdown();
            });
        }
    });

    function toggleMobileSidebar() {
        document.body.classList.toggle('sidebar-open');
    }

    // Auto Device Detection: Guarantees ANY mobile phone formats like a native app
    function checkDeviceMode() {
        const isMobile = window.innerWidth <= 1024;
        if (isMobile) {
            document.body.classList.add('mobile-mode');
            document.body.classList.remove('sidebar-collapsed');
        } else {
            document.body.classList.remove('mobile-mode');
        }
    }
    checkDeviceMode();
    window.addEventListener('resize', checkDeviceMode);
    window.addEventListener('orientationchange', checkDeviceMode);

    // Inject mobile hamburger button into every topbar (avoids editing 35+ PHP files)
    document.addEventListener('DOMContentLoaded', function() {
        checkDeviceMode();
        var topbar = document.querySelector('.topbar');
        if (topbar && !topbar.querySelector('.mobile-toggle')) {
            var btn = document.createElement('button');
            btn.className = 'mobile-toggle';
            btn.setAttribute('aria-label', 'Open navigation menu');
            btn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
            btn.onclick = function(e) {
                e.stopPropagation();
                toggleMobileSidebar();
            };
            topbar.insertBefore(btn, topbar.firstChild);
        }

        var topbarRight = document.querySelector('.topbar-right');
        if (topbarRight && !topbarRight.querySelector('.mobile-topbar-logout')) {
            var logoutLink = document.createElement('a');
            logoutLink.href = 'logout.php';
            logoutLink.className = 'mobile-topbar-logout';
            logoutLink.setAttribute('title', 'Log Out');
            logoutLink.setAttribute('aria-label', 'Log Out');
            logoutLink.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>';
            topbarRight.appendChild(logoutLink);
        }

        // Auto close mobile drawer when any link is tapped
        document.querySelectorAll('.sidebar .nav-item, .sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (document.body.classList.contains('sidebar-open')) {
                    document.body.classList.remove('sidebar-open');
                }
            });
        });
    });

    // Mobile: auto-open the active group on page load
    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth <= 1024 || document.body.classList.contains('mobile-mode')) {
            document.querySelectorAll('.nav-dropdown .nav-item.active').forEach(item => {
                const parentGroup = item.closest('.nav-group');
                if (parentGroup) parentGroup.classList.add('open');
            });
        } else {
            // Desktop: restore collapsed state
            if (localStorage.getItem('sidebar-collapsed') === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }
        }
    });
    
    function toggleDesktopSidebar() {
        if (window.innerWidth <= 1024 || document.body.classList.contains('mobile-mode')) {
            toggleMobileSidebar();
            return;
        }
        document.body.classList.toggle('sidebar-collapsed');
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar-collapsed', isCollapsed ? 'true' : 'false');
    }
</script>

<!-- Tom Select CSS & JS for Searchable Employee Dropdowns -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.default.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<!-- Global Realtime Alerts for Overtime and Missed Breaks -->
<?php if (in_array($_SESSION['role'] ?? '', ['admin', 'hr', 'tl', 'gl'])): ?>
    <script src="assets/js/realtime_alerts.js"></script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const employeeSelectNames = [
        'select[name="filter_emp_id"]',
        'select[name="force_emp_id"]',
        'select[name="emp_id"]',
        'select[name="employees[]"]',
        'select[name="employee_id"]'
    ];
    
    document.querySelectorAll(employeeSelectNames.join(',')).forEach((el) => {
        // Exclude role/department dropdowns just in case they share a name
        if(el.name === 'emp_id' && document.querySelector('input[name="emp_id"]')) {
           // Skip if it's somehow conflicting, but typically emp_id is the input text in add employee.
           // Wait, in add employee form, emp_id is a text input, not a select. So select[name="emp_id"] is safe.
        }
        
        new TomSelect(el, {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            },
            placeholder: el.getAttribute('placeholder') || "Search for an employee..."
        });
    });
});
</script>
<style>
/* Tom Select Theme Overrides to match the Dashboard */
.ts-control {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 10px 15px;
    font-size: 14px;
    background: #ffffff;
    box-shadow: none;
    transition: all 0.2s;
}
.ts-control:focus, .ts-control.focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.ts-dropdown {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    font-size: 14px;
}
.ts-dropdown .option {
    padding: 10px 15px;
}
.ts-dropdown .active {
    background-color: #f1f5f9;
    color: #0f172a;
}
.ts-control > input {
    font-size: 14px;
}
body.dark-mode .ts-control {
    background: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}
body.dark-mode .ts-dropdown {
    background: #1e293b;
    border-color: #334155;
    color: #f8fafc;
}
body.dark-mode .ts-dropdown .active {
    background-color: #334155;
    color: #ffffff;
}
body.dark-mode .ts-control input {
    color: #f8fafc;
}
body.dark-mode #theme-toggle-btn {
    background: #334155 !important;
    color: #cbd5e1 !important;
    border-color: #475569 !important;
}

/* ====================================================================
   GLOBAL TOPBAR NOTIFICATIONS (Available on all pages)
   ==================================================================== */
.global-notif-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    z-index: 1000;
}
.global-notif-btn {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
    padding: 0;
    outline: none;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.global-notif-btn:hover {
    background: #f8fafc;
    border-color: #0284c7;
    color: #0284c7;
    transform: translateY(-1px);
}
.global-notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #ef4444;
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    height: 18px;
    min-width: 18px;
    padding: 0 4px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-sizing: border-box;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.25);
}
.global-notif-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 330px;
    max-width: calc(100vw - 32px);
    max-height: 420px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.15);
    z-index: 999999;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    text-align: left;
    animation: globalNotifFadeIn 0.16s ease-out;
}
@keyframes globalNotifFadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
.global-notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
    background: #ffffff;
}
.global-notif-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}
.global-notif-mark {
    background: none;
    border: none;
    color: #0284c7;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    transition: color 0.15s ease;
}
.global-notif-mark:hover {
    text-decoration: underline;
    color: #0369a1;
}
.global-notif-list {
    overflow-y: auto;
    max-height: 350px;
    background: #ffffff;
}
.global-notif-list::-webkit-scrollbar {
    width: 6px;
}
.global-notif-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
.global-notif-item {
    padding: 11px 15px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
    cursor: pointer;
}
.global-notif-item:last-child {
    border-bottom: none;
}
.global-notif-item:hover {
    background: rgba(2, 132, 199, 0.06);
}
.global-notif-item.unread {
    background: #f0f9ff;
    border-left: 3px solid #0284c7;
}
.global-notif-item.unread:hover {
    background: #e0f2fe;
}
.global-notif-msg {
    font-size: 12px;
    line-height: 1.45;
    color: #1e293b;
    margin-bottom: 3px;
    word-break: break-word;
}
.global-notif-time {
    font-size: 10px;
    color: #64748b;
}
.global-notif-empty {
    padding: 24px;
    text-align: center;
    font-size: 13px;
    color: #94a3b8;
}

/* Dark Mode Support */
body.dark-mode .global-notif-btn {
    background: #111c2e;
    border-color: #253347;
    color: #94a3b8;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}
body.dark-mode .global-notif-btn:hover {
    background: #1e293b;
    border-color: #38bdf8;
    color: #38bdf8;
}
body.dark-mode .global-notif-badge {
    border-color: #111c2e;
}
body.dark-mode .global-notif-dropdown {
    background: #111c2e;
    border-color: #253347;
    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.5);
}
body.dark-mode .global-notif-header {
    background: #111c2e;
    border-bottom-color: #253347;
}
body.dark-mode .global-notif-header h4 {
    color: #f8fafc;
}
body.dark-mode .global-notif-mark {
    color: #38bdf8;
}
body.dark-mode .global-notif-mark:hover {
    color: #7dd3fc;
}
body.dark-mode .global-notif-list {
    background: #111c2e;
}
body.dark-mode .global-notif-list::-webkit-scrollbar-thumb {
    background: #334155;
}
body.dark-mode .global-notif-item {
    border-bottom-color: #253347;
}
body.dark-mode .global-notif-item:hover {
    background: rgba(56, 189, 248, 0.08);
}
body.dark-mode .global-notif-item.unread {
    background: rgba(56, 189, 248, 0.12);
    border-left: 3px solid #38bdf8;
}
body.dark-mode .global-notif-item.unread:hover {
    background: rgba(56, 189, 248, 0.18);
}
body.dark-mode .global-notif-msg {
    color: #f8fafc;
}
body.dark-mode .global-notif-time {
    color: #94a3b8;
}
body.dark-mode .global-notif-empty {
    color: #94a3b8;
}

@media (max-width: 480px) {
    .global-notif-dropdown {
        position: fixed !important;
        top: 60px !important;
        right: 12px !important;
        left: 12px !important;
        width: auto !important;
        max-width: calc(100vw - 24px) !important;
    }
}
</style>

<!-- Global Topbar Notification Component -->
<div id="global-notif-wrapper" class="global-notif-wrapper" style="display: none;">
    <button type="button" class="global-notif-btn" id="global-notif-btn" onclick="toggleGlobalNotifDropdown(event)" title="Notifications" aria-label="Notifications">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="global-notif-badge" id="global-notif-badge" style="<?= $global_unread_notifs_cnt > 0 ? '' : 'display:none;' ?>"><?= $global_unread_notifs_cnt ?></span>
    </button>
    <div class="global-notif-dropdown" id="global-notif-dropdown" style="display: none;">
        <div class="global-notif-header">
            <h4>Notifications</h4>
            <button type="button" class="global-notif-mark" id="global-btn-mark-all" onclick="markAllGlobalNotifsRead(event)" style="<?= $global_unread_notifs_cnt > 0 ? '' : 'display:none;' ?>">Mark read</button>
        </div>
        <div class="global-notif-list" id="global-notif-list">
            <?php if (empty($global_notifs)): ?>
                <div class="global-notif-empty">No notifications yet.</div>
            <?php else: ?>
                <?php foreach ($global_notifs as $gnotif): ?>
                    <div class="global-notif-item <?= (int)$gnotif['is_read'] === 0 ? 'unread' : '' ?>" onclick="handleGlobalNotifClick(this, '<?= htmlspecialchars(addslashes($gnotif['message']), ENT_QUOTES) ?>')">
                        <div class="global-notif-msg"><?= htmlspecialchars($gnotif['message']) ?></div>
                        <div class="global-notif-time"><?= get_sidebar_notif_time($gnotif['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    function initGlobalNotificationPlacement() {
        if (window.location.pathname.indexOf('weride_insides.php') !== -1 || document.querySelector('.ci-notif-wrapper')) {
            const wrapper = document.getElementById('global-notif-wrapper');
            if (wrapper) wrapper.remove();
            return;
        }

        const wrapper = document.getElementById('global-notif-wrapper');
        if (!wrapper) return;

        const topbarRight = document.querySelector('.topbar-right');
        const refreshBar = document.querySelector('.topbar .refresh-bar, header.topbar .refresh-bar');
        const topbar = document.querySelector('.topbar, header.topbar');
        const genericHeader = document.querySelector('.main-content > .header, .main-content > .page-header, .header, .page-header');

        if (topbarRight) {
            wrapper.style.marginRight = '4px';
            const avatar = topbarRight.querySelector('.avatar');
            if (avatar) {
                topbarRight.insertBefore(wrapper, avatar);
            } else {
                topbarRight.appendChild(wrapper);
            }
        } else if (refreshBar && refreshBar.parentNode) {
            wrapper.style.marginLeft = 'auto';
            wrapper.style.marginRight = '14px';
            refreshBar.parentNode.insertBefore(wrapper, refreshBar);
        } else if (topbar) {
            const rightContainer = topbar.querySelector('div[style*="display: flex"], div[style*="gap:"], .action-buttons');
            if (rightContainer && rightContainer !== topbar) {
                wrapper.style.marginRight = '8px';
                rightContainer.prepend(wrapper);
            } else {
                wrapper.style.marginLeft = 'auto';
                topbar.appendChild(wrapper);
            }
        } else if (genericHeader) {
            wrapper.style.marginLeft = 'auto';
            genericHeader.appendChild(wrapper);
        } else {
            wrapper.style.position = 'fixed';
            wrapper.style.top = '16px';
            wrapper.style.right = '20px';
            wrapper.style.zIndex = '99999';
            document.body.appendChild(wrapper);
        }

        wrapper.style.display = 'inline-flex';
    }

    window.toggleGlobalNotifDropdown = function(e) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        const dropdown = document.getElementById('global-notif-dropdown');
        if (!dropdown) return;
        const isShown = dropdown.style.display === 'flex' || dropdown.style.display === 'block';
        dropdown.style.display = isShown ? 'none' : 'flex';
    };

    window.markAllGlobalNotifsRead = function(e) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        fetch('api_notifications.php?action=mark_read', {
            method: 'POST'
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                const badge = document.getElementById('global-notif-badge');
                if (badge) badge.style.display = 'none';
                const btnMark = document.getElementById('global-btn-mark-all');
                if (btnMark) btnMark.style.display = 'none';
                document.querySelectorAll('.global-notif-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
            }
        })
        .catch(err => console.error('Error marking notifications read:', err));
    };

    window.handleGlobalNotifClick = function(element, msg) {
        const m = (msg || '').toLowerCase();
        const role = '<?= $role_slug ?>';
        let url = null;
        if (m.includes('leave')) {
            url = (role === 'employee' || role === 'qa') ? 'employee_leave_history.php' : 'manage_leave_requests.php';
        } else if (m.includes('shift')) {
            url = (role === 'employee' || role === 'qa') ? 'profile.php' : 'manage_shift_requests.php';
        } else if (m.includes('break') || m.includes('lunch')) {
            url = (role === 'employee' || role === 'qa') ? 'employee_lunchtime_history.php' : 'manage_requests.php';
        } else if (m.includes('insides') || m.includes('mentioned') || m.includes('post') || m.includes('comment')) {
            url = 'weride_insides.php';
        }
        if (url) {
            window.location.href = url;
        }
    };

    function updateGlobalNotifUI(notifications, unreadCount) {
        const badge = document.getElementById('global-notif-badge');
        const btnMark = document.getElementById('global-btn-mark-all');
        const list = document.getElementById('global-notif-list');

        if (badge) {
            badge.innerText = unreadCount;
            badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }
        if (btnMark) {
            btnMark.style.display = unreadCount > 0 ? 'inline-block' : 'none';
        }

        if (list && notifications) {
            if (notifications.length === 0) {
                list.innerHTML = '<div class="global-notif-empty">No notifications yet.</div>';
            } else {
                let html = '';
                notifications.forEach(n => {
                    const unreadCls = parseInt(n.is_read) === 0 ? 'unread' : '';
                    const safeMsg = (n.message || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const time = n.time_formatted || n.created_at;
                    html += '<div class="global-notif-item ' + unreadCls + '" onclick="handleGlobalNotifClick(this, \'' + safeMsg + '\')">' +
                                '<div class="global-notif-msg">' + safeMsg + '</div>' +
                                '<div class="global-notif-time">' + time + '</div>' +
                            '</div>';
                });
                list.innerHTML = html;
            }
        }
    }

    function pollGlobalNotifications() {
        fetch('api_notifications.php?action=get')
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                updateGlobalNotifUI(res.notifications, res.unread_count);
            }
        })
        .catch(() => {});
    }

    document.addEventListener('DOMContentLoaded', function() {
        initGlobalNotificationPlacement();
        setInterval(pollGlobalNotifications, 45000);
    });

    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('global-notif-wrapper');
        const dropdown = document.getElementById('global-notif-dropdown');
        if (dropdown && dropdown.style.display !== 'none') {
            if (!wrapper || !wrapper.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        }
    });
})();
</script>
