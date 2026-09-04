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

    @media (max-width: 768px) {
        .mobile-toggle {
            display: block;
        }

        .sidebar {
            position: fixed;
            left: -280px;
            top: 0;
            bottom: 0;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(0);
        }

        body.sidebar-open .sidebar {
            transform: translateX(280px);
        }

        body.sidebar-open .sidebar-overlay {
            display: block;
            opacity: 1;
        }

        .topbar {
            padding: 15px 20px !important;
            justify-content: flex-start !important;
        }

        .topbar .page-title {
            font-size: 20px !important;
            margin-left: 10px;
        }

        .content {
            padding: 0 15px 40px 15px !important;
        }
    }

    /* Global Layout Constraints to prevent mobile browsers from zooming out */
    body {
        overflow-x: hidden !important;
        width: 100vw !important;
    }

    .main-content {
        min-width: 0 !important;
        overflow-x: hidden !important;
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
        max-height: 300px;
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
</style>

<!-- Mobile Overlay -->
<div class="sidebar-overlay no-print" onclick="toggleMobileSidebar()"></div>

<aside class="sidebar no-print">
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

        <?php if ($role_slug === 'gl'): ?>
            <!-- Live Attendance (GL only) -->
            <a href="gl_live_attendance.php" style="text-decoration: none;">
                <li class="nav-item <?php echo strpos($current_page, 'live_attendance') !== false ? 'active' : ''; ?>">Live
                    Attendance</li>
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
                            class="text-red-500" style="display:inline-block; width:8px; height:8px; background- border-radius:50%; margin-left:8px; vertical-align:middle; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);"></span>
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
                        <li class="nav-item <?php echo strpos($current_page, 'alerts') !== false ? 'active' : ''; ?>">
                            Violation</li>
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
                                    class="text-red-500 text-white" style="background-  font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_req_count; ?></span>
                            <?php endif; ?>
                        </li>
                    </a>

                    <a href="manage_overrides.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_overrides.php' ? 'active' : ''; ?>"
                            style="display: flex; justify-content: space-between; align-items: center; padding-right: 24px;">
                            Override Request
                            <?php if (isset($pending_override_count) && $pending_override_count > 0): ?>
                                <span
                                    class="text-red-500 text-white" style="background-  font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: bold;"><?php echo $pending_override_count; ?></span>
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
                        <li
                            class="nav-item <?php echo strpos($current_page, 'breaktime_alerts') !== false ? 'active' : ''; ?>">
                            Violation</li>
                    </a>
                <?php endif; ?>
            </ul>
        </li>

        <!-- Attendance Dropdown -->
        <?php
        $attendance_pages = ['admin_attendance.php', 'attendance_report.php', 'public_holidays.php', 'leave_application.php', 'employee_stats.php', 'manage_attendance_alerts.php', 'work_hours_report.php', 'employee_leave_history.php', 'manage_leave_requests.php', 'manage_shift_requests.php'];
        $is_attendance_open = in_array($current_page, $attendance_pages);
        ?>
        <li class="nav-group <?php echo $is_attendance_open ? 'open' : ''; ?>" onclick="toggleDropdown(this)">
            <div class="nav-group-title">
                <span>Attendance</span>
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

                <!-- Leave Application (Employee and GL) -->
                <?php if ($role_slug === 'employee' || $role_slug === 'gl'): ?>
                    <a href="leave_application.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'leave_application.php' ? 'active' : ''; ?>">Leave Application</li>
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
                        <li class="nav-item <?php echo $current_page == 'manage_attendance_alerts.php' ? 'active' : ''; ?>">Violation</li>
                    </a>

                    <a href="work_hours_report.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'work_hours_report.php' ? 'active' : ''; ?>">Work Hours Report</li>
                    </a>
                <?php endif; ?>

                <!-- Full Statistics (non-employee) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="admin_attendance.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'admin_attendance.php' ? 'active' : ''; ?>">Full Statistics</li>
                    </a>
                <?php endif; ?>

                <!-- Public Holidays + Manage items (non-employee) -->
                <?php if ($role_slug !== 'employee'): ?>
                    <a href="public_holidays.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'public_holidays.php' ? 'active' : ''; ?>">Public Holidays</li>
                    </a>

                    <a href="manage_leave_requests.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_leave_requests.php' ? 'active' : ''; ?>">Manage Leaves</li>
                    </a>

                    <a href="manage_shift_requests.php" style="text-decoration: none;">
                        <li class="nav-item <?php echo $current_page == 'manage_shift_requests.php' ? 'active' : ''; ?>">Manage Shifts</li>
                    </a>
                <?php endif; ?>
            </ul>
        </li>



        <!-- Profile -->
        <li class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <a href="profile.php"
                style="text-decoration: none; color: inherit; display: block; width: 100%; height: 100%;">Profile</a>
        </li>

    </ul>

    <div style="padding: 20px; border-top: 1px solid rgba(0,0,0,0.05);">
        <div
            style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(0,0,0,0.02); border-radius: 12px; margin-bottom: 15px;">
            <div
                class="text-slate-500" style="display: flex; align-items: center; gap: 8px;  font-weight: 600; font-size: 14px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg> Dark mode
            </div>
            <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                <input type="checkbox" id="theme-toggle" onchange="toggleTheme()"
                    style="opacity: 0; width: 0; height: 0;">
                <span class="slider round"
                    style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 24px;"></span>
            </label>
        </div>
        <a href="logout.php"
            class="text-red-500" style="text-decoration: none; display: block; text-align: center; background: #fee2e2;  padding: 12px; border-radius: 12px; font-weight: 700; transition: 0.2s;">🚪
            Logout</a>
    </div>
</aside>

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
        if (window.innerWidth <= 768) {
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
        if (window.innerWidth <= 768 || !activePortal) return;
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
                if (window.innerWidth > 768) closePortalDropdown();
            });
        }
    });

    function toggleMobileSidebar() {
        document.body.classList.toggle('sidebar-open');
    }

    // Mobile: auto-open the active group on page load
    document.addEventListener('DOMContentLoaded', function () {
        if (window.innerWidth <= 768) {
            document.querySelectorAll('.nav-dropdown .nav-item.active').forEach(item => {
                const parentGroup = item.closest('.nav-group');
                if (parentGroup) parentGroup.classList.add('open');
            });
        }
    });
</script>