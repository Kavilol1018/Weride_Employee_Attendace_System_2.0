<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hr') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

// Fetch all Group Leaders for the dropdowns
$gl_res = mysqli_query($conn, "SELECT emp_id, name FROM employees WHERE role = 'gl' ORDER BY name ASC");
$all_gls = [];
if ($gl_res) {
    while($row = mysqli_fetch_assoc($gl_res)) {
        $all_gls[] = $row;
    }
}

// 1. ADD EMPLOYEE LOGIC
if (isset($_POST['add_employee'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $welabel_id = mysqli_real_escape_string($conn, $_POST['welabel_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $shift_hour = isset($_POST['shift_hour']) ? mysqli_real_escape_string($conn, $_POST['shift_hour']) : '09:00-18:00';
    $lunch_window = mysqli_real_escape_string($conn, $_POST['lunch_window']);
    $group_leader_id = isset($_POST['group_leader_id']) ? mysqli_real_escape_string($conn, $_POST['group_leader_id']) : '';
    $onboarding_date = !empty($_POST['onboarding_date']) ? mysqli_real_escape_string($conn, $_POST['onboarding_date']) : date('Y-m-d');
    $password = password_hash('password123', PASSWORD_DEFAULT);

    // Check for duplicates
    $check_query = "SELECT id FROM employees WHERE emp_id='$emp_id' OR name='$name'";
    if (!empty($welabel_id)) {
        $check_query .= " OR welabel_id='$welabel_id'";
    }
    $check = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check) > 0) {
        $qs = $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php?error=" . urlencode("Employee ID, WeLabel ID, or Name already exists!") . $qs);
        exit();
    }

    $is_intern = (stripos($employment_type, 'intern') !== false);
    $al_balance = $is_intern ? 0.0 : 8.0;
    $sl_balance = $is_intern ? 0.0 : 14.0;
    $hosp_balance = $is_intern ? 0.0 : 60.0;

    $sql = "INSERT INTO employees (emp_id, welabel_id, name, password, employment_type, role, shift_hour, lunch_window, group_leader_id, onboarding_date, annual_leave_balance, sick_leave_balance, hospitalization_leave_balance, unpaid_leave_balance) VALUES ('$emp_id', '$welabel_id', '$name', '$password', '$employment_type', '$role', '$shift_hour', '$lunch_window', '$group_leader_id', '$onboarding_date', $al_balance, $sl_balance, $hosp_balance, 0.0)";
    mysqli_query($conn, $sql);
    
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php" . $qs);
    exit();
}

// 2. EDIT EMPLOYEE LOGIC
if (isset($_POST['edit_employee'])) {
    $edit_id = mysqli_real_escape_string($conn, $_POST['edit_id']);
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $welabel_id = mysqli_real_escape_string($conn, $_POST['welabel_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $shift_hour = isset($_POST['shift_hour']) ? mysqli_real_escape_string($conn, $_POST['shift_hour']) : '09:00-18:00';
    $lunch_window = mysqli_real_escape_string($conn, $_POST['lunch_window']);
    $group_leader_id = isset($_POST['group_leader_id']) ? mysqli_real_escape_string($conn, $_POST['group_leader_id']) : '';
    $onboarding_date = !empty($_POST['onboarding_date']) ? mysqli_real_escape_string($conn, $_POST['onboarding_date']) : '';
    $onb_sql = !empty($onboarding_date) ? "'$onboarding_date'" : "NULL";

    // Check for duplicates
    $check_query = "SELECT id FROM employees WHERE (emp_id='$emp_id' OR name='$name') AND id != '$edit_id'";
    if (!empty($welabel_id)) {
        $check_query = "SELECT id FROM employees WHERE (emp_id='$emp_id' OR welabel_id='$welabel_id' OR name='$name') AND id != '$edit_id'";
    }
    $check = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check) > 0) {
        $qs = $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php?error=" . urlencode("Employee ID, WeLabel ID, or Name already exists!") . $qs);
        exit();
    }

    $is_intern = (stripos($employment_type, 'intern') !== false);
    $leave_sync_sql = "";
    if ($is_intern) {
        $leave_sync_sql = ", annual_leave_balance = 0.0, sick_leave_balance = 0.0, hospitalization_leave_balance = 0.0";
    }

    $sql = "UPDATE employees SET emp_id='$emp_id', welabel_id='$welabel_id', name='$name', employment_type='$employment_type', role='$role', shift_hour='$shift_hour', lunch_window='$lunch_window', group_leader_id='$group_leader_id', onboarding_date=$onb_sql $leave_sync_sql WHERE id='$edit_id'";
    mysqli_query($conn, $sql);
    
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php" . $qs);
    exit();
}

// 3. DELETE EMPLOYEE LOGIC
if (isset($_POST['delete_employee'])) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
    
    // Backup to deleted_employees table
    $sql_backup = "INSERT INTO deleted_employees SELECT *, CURRENT_TIMESTAMP FROM employees WHERE id = '$delete_id'";
    mysqli_query($conn, $sql_backup);

    $sql = "DELETE FROM employees WHERE id = '$delete_id'";
    mysqli_query($conn, $sql);
    
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php" . $qs);
    exit();
}

// 3.5 BULK DELETE EMPLOYEE LOGIC
if (isset($_POST['bulk_delete_employees']) && !empty($_POST['selected_ids'])) {
    $ids = array_map(function($id) use ($conn) {
        return mysqli_real_escape_string($conn, $id);
    }, $_POST['selected_ids']);
    
    $ids_string = implode("','", $ids);

    // Backup to deleted_employees table
    $sql_backup = "INSERT INTO deleted_employees SELECT *, CURRENT_TIMESTAMP FROM employees WHERE id IN ('$ids_string')";
    mysqli_query($conn, $sql_backup);

    $sql = "DELETE FROM employees WHERE id IN ('$ids_string')";
    mysqli_query($conn, $sql);
    
    $qs = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php" . $qs);
    exit();
}

// 4. RESET PASSWORD LOGIC
if (isset($_POST['reset_password'])) {
    $reset_id = mysqli_real_escape_string($conn, $_POST['reset_id']);
    $new_password = password_hash('password123', PASSWORD_DEFAULT);
    
    $sql = "UPDATE employees SET password='$new_password' WHERE id='$reset_id'";
    mysqli_query($conn, $sql);
    
    $qs = $_SERVER['QUERY_STRING'] ? '&' . $_SERVER['QUERY_STRING'] : ''; header("Location: hr_employees.php?msg=" . urlencode("Password reset to 'password123'") . $qs);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - TL Employees</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        /* Toast notification */
        .toast { position: fixed; top: 30px; right: 30px; padding: 16px 24px; border-radius: 12px; color: white; font-weight: 600; z-index: 2000; animation: toastSlideIn 0.4s ease, toastFadeOut 0.4s ease 2.6s forwards; box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        .toast-success { background: linear-gradient(135deg, #10b981, #059669); }
        .toast-error { background: linear-gradient(135deg, #ef4444, #b91c1c); }

        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastSlideIn { from { opacity: 0; transform: translateX(40px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes toastFadeOut { to { opacity: 0; transform: translateX(40px); } }

        .btn-primary { background-color: #3b82f6; color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .btn-primary:hover { background-color: #2563eb; transform: translateY(-2px); }
        
        .btn-edit { background-color: #eff6ff; color: #3b82f6; border: none; padding: 8px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; margin-right: 6px; }
        .btn-edit:hover { background-color: #dbeafe; color: #1d4ed8; }

        .btn-delete { background-color: #fee2e2; color: #ef4444; border: none; padding: 8px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .btn-delete:hover { background-color: #fca5a5; color: #b91c1c; }

        .form-input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .form-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .form-label { font-size: 13px; font-weight: 600; color: #64748b; display: block; margin-bottom: 6px; }

        /* Employee Overview Cards */
        .emp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .emp-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .emp-card:hover { transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .emp-card.active { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25), 0 4px 15px rgba(0,0,0,0.05); background: #f8fafc; }
        .emp-title { color: #94a3b8; font-size: 11px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .emp-val { font-size: 24px; font-weight: 800; color: #0f172a; }
        .emp-icon { grid-column: 1; grid-row: 1 / span 2; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 0; }
        
        .table-container { background: transparent; padding: 10px 0; margin-top: 20px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; }
        td { padding: 20px; background: #ffffff; border-top: 1px solid rgba(226, 232, 240, 0.6); border-bottom: 1px solid rgba(226, 232, 240, 0.6); transition: all 0.3s ease; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226, 232, 240, 0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226, 232, 240, 0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.2); backdrop-filter: blur(6px); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: rgba(255, 255, 255, 0.95); padding: 40px; border-radius: 24px; width: 420px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.5); position: relative; animation: modalSlideIn 0.3s ease; }
        .close-btn { position: absolute; top: 20px; right: 25px; font-size: 28px; cursor: pointer; color: #94a3b8; transition: 0.2s; background: none; border: none; }
        .close-btn:hover { color: #ef4444; }

        .actions-cell { display: flex; align-items: center; gap: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state-text { font-size: 16px; font-weight: 600; }
        .employee-count { background: #f1f5f9; padding: 8px 16px; border-radius: 10px; font-size: 14px; font-weight: 600; color: #64748b; }
        /* Dropdown Actions */
        .dropdown { position: relative; display: inline-block; }
        .dropbtn { background-color: #f1f5f9; color: #475569; padding: 8px 16px; font-size: 14px; border: none; cursor: pointer; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 6px; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dropbtn:hover { background-color: #e2e8f0; color: #1e293b; }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: #ffffff; min-width: 170px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-radius: 12px; z-index: 100; overflow: hidden; border: 1px solid #e2e8f0; margin-top: 8px; }
        .dropdown-content.show { display: block; animation: dropdownFadeIn 0.2s ease; }
        .dropdown-content button { color: #334155; padding: 12px 16px; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; text-align: left; width: 100%; border: none; background: none; cursor: pointer; transition: 0.2s; border-bottom: 1px solid #f1f5f9; }
        .dropdown-content button:last-child { border-bottom: none; }
        .dropdown-content button:hover { background-color: #f8fafc; color: #0f172a; padding-left: 20px; }
        .dropdown-content .danger-item { color: #ef4444; }
        .dropdown-content .danger-item:hover { background-color: #fef2f2; color: #b91c1c; }
        .dropdown-content form { margin: 0; }
        
        @keyframes dropdownFadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div class="page-title">Team Directory</div>
                
            </div>
            <div style="display: flex; gap: 12px;">
                <button type="button" class="btn-delete" style="display: none; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); padding: 12px 24px; font-size: 14px;" id="bulkDeleteBtn" onclick="submitBulkDelete()">🗑️ Delete Selected</button>
                <a href="export_employees.php?<?php echo http_build_query($_GET); ?>" class="btn-primary" style="background-color: #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">📥 Export CSV</a>
                <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Employee</button>
            </div>
        </header>

                <div class="content">
            <?php
            $filter_type = isset($_GET['type']) ? trim($_GET['type']) : '';
            $stats_query = mysqli_query($conn, "SELECT employment_type, COUNT(*) as count FROM employees GROUP BY employment_type");
            $stats = ['Full time' => 0, 'Intern' => 0];
            $total = 0;
            while($row = mysqli_fetch_assoc($stats_query)){
                $type = $row['employment_type'];
                if(strpos(strtolower($type), 'full') !== false && strpos(strtolower($type), 'time') !== false) {
                    $stats['Full time'] += $row['count'];
                } elseif (strpos(strtolower($type), 'intern') !== false) {
                    $stats['Intern'] += $row['count'];
                } else {
                    if(isset($stats[$type])) {
                        $stats[$type] += $row['count'];
                    }
                }
                $total += $row['count'];
            }
            ?>
                                    <div class="emp-grid">
                <div class="emp-card <?php echo empty($filter_type) ? 'active' : ''; ?>" onclick="window.location.href='?type='">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div class="emp-icon bg-slate-100 text-slate-500" >👥</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="emp-title">Total Employees</div>
                        <div class="emp-val"><?php echo $total; ?></div>
                    </div>
                </div>
                <div class="emp-card <?php echo $filter_type === 'Full time' ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $filter_type === 'Full time' ? '?type=' : '?type=Full+time'; ?>'">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div class="emp-icon bg-emerald-50 text-emerald-600" >💼</div>

                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="emp-title">Full Time</div>
                        <div class="emp-val"><?php echo $stats['Full time']; ?></div>
                    </div>
                </div>
                <div class="emp-card <?php echo $filter_type === 'Intern' ? 'active' : ''; ?>" onclick="window.location.href='<?php echo $filter_type === 'Intern' ? '?type=' : '?type=Intern'; ?>'">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div class="emp-icon bg-purple-50" style=" color: #a855f7;">🎓</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="emp-title">Interns</div>
                        <div class="emp-val"><?php echo $stats['Intern'] ?? 0; ?></div>
                    </div>
                </div>
            </div>
            <div class="table-container" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <?php if (!empty($filter_type)): ?>
                            <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #475569; background: #f1f5f9; padding: 6px 14px; border-radius: 20px; border: 1px solid #cbd5e1;">
                                <span>Filter: <strong><?php echo htmlspecialchars($filter_type); ?></strong></span>
                                <a href="?type=" style="color: #ef4444; text-decoration: none; font-weight: 700; margin-left: 4px;" title="Clear filter">✕</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="text" id="searchInput" oninput="searchTable()" placeholder="Search employees by ID, WeLabel ID, or Name..." style="padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 24px; width: 350px; font-size: 14px; outline: none; transition: border-color 0.2s;">
                </div>
                <form id="bulkDeleteForm" method="POST" action="">
                    <input type="hidden" name="bulk_delete_employees" value="1">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px; padding-right: 10px;"><input type="checkbox" id="selectAllCheckbox" style="transform: scale(1.2); cursor: pointer;" onclick="toggleAllCheckboxes(this)"></th>
                                <th>Employee ID</th>
                                <th>WeLabel ID</th>
                                <th>Full Name</th>
                                <th>Employment Type</th>
                                <th>Days Present</th>
                                <th>Absence</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $filter_type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
                        $query = "SELECT * FROM employees";
                        $where_clauses = [];
                        
                        if ($filter_type) {
                            if ($filter_type == 'Full time') {
                                $where_clauses[] = "employment_type LIKE 'Full%time'";
                            } elseif ($filter_type == 'Intern') {
                                $where_clauses[] = "employment_type LIKE '%Intern%'";
                            } else {
                                $where_clauses[] = "employment_type = '$filter_type'";
                            }
                        }

                        if (!empty($where_clauses)) {
                            $query .= " WHERE " . implode(" AND ", $where_clauses);
                        }
                        $query .= " ORDER BY id DESC";
                        $result = mysqli_query($conn, $query);
                        
                        $selected_month = (int)date('n');
                        $selected_year = (int)date('Y');
                        $today_day = (int)date('j');

                        // Pre-fetch attendance logs for current month
                        $monthly_attendance = [];
                        $att_q = mysqli_query($conn, "SELECT emp_id, DAY(date) as day, status, clock_in FROM attendance_logs WHERE MONTH(date) = $selected_month AND YEAR(date) = $selected_year");
                        if ($att_q) {
                            while ($ar = mysqli_fetch_assoc($att_q)) {
                                $monthly_attendance[$ar['emp_id']][$ar['day']] = [
                                    'status' => $ar['status'],
                                    'clock_in' => $ar['clock_in']
                                ];
                            }
                        }

                        // Pre-fetch approved leaves for current month (including 0.5 half-day leaves)
                        $monthly_leaves = [];
                        $l_q = mysqli_query($conn, "SELECT emp_id, start_date, end_date, leave_type, duration FROM leave_requests WHERE status = 'approved' AND (MONTH(start_date) = $selected_month OR MONTH(end_date) = $selected_month) AND (YEAR(start_date) = $selected_year OR YEAR(end_date) = $selected_year)");
                        if ($l_q) {
                            while ($lr = mysqli_fetch_assoc($l_q)) {
                                $l_emp = $lr['emp_id'];
                                $l_start = strtotime($lr['start_date']);
                                $l_end = strtotime($lr['end_date']);
                                $l_dur = (float)($lr['duration'] ?? 1.0);
                                $is_half = ($l_dur == 0.5);
                                for ($ts = $l_start; $ts <= $l_end; $ts += 86400) {
                                    if ((int)date('n', $ts) === $selected_month && (int)date('Y', $ts) === $selected_year) {
                                        $ld = (int)date('j', $ts);
                                        $lt = strtoupper($lr['leave_type']);
                                        if (strpos($lt, 'ANNUAL') !== false) $abbr = 'AL';
                                        elseif (strpos($lt, 'SICK') !== false) $abbr = 'SL';
                                        elseif (strpos($lt, 'UNPAID') !== false) $abbr = 'UPL';
                                        elseif (strpos($lt, 'HOSPITAL') !== false) $abbr = 'HL';
                                        elseif (strpos($lt, 'EMERGENCY') !== false) $abbr = 'EMERGENCY';
                                        else $abbr = 'L';
                                        
                                        if ($is_half && in_array($abbr, ['AL', 'SL'])) {
                                            $abbr = '0.5 ' . $abbr;
                                        } elseif ($is_half && $abbr === 'UPL') {
                                            $abbr = '0.5UPL';
                                        } elseif ($is_half) {
                                            $abbr = 'HD';
                                        }
                                        $monthly_leaves[$l_emp][$ld] = $abbr;
                                    }
                                }
                            }
                        }

                        // Pre-fetch public holidays for current month
                        $monthly_holidays = [];
                        $ph_q = mysqli_query($conn, "SELECT DAY(holiday_date) as day FROM public_holidays WHERE MONTH(holiday_date) = $selected_month AND YEAR(holiday_date) = $selected_year");
                        if ($ph_q) {
                            while ($ph = mysqli_fetch_assoc($ph_q)) {
                                $monthly_holidays[$ph['day']] = true;
                            }
                        }
                        
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $emp_att_id = $row["emp_id"];
                                $onboarding_time = !empty($row['onboarding_date']) ? strtotime($row['onboarding_date']) : 0;
                                
                                $days_present = 0;
                                $absence = 0;
                                
                                for ($d = 1; $d <= $today_day; $d++) {
                                    $day_ts = mktime(0, 0, 0, $selected_month, $d, $selected_year);
                                    $is_weekend = ((int)date('N', $day_ts) >= 6);
                                    $is_before_onboarding = ($onboarding_time > 0 && $day_ts < $onboarding_time);
                                    
                                    if ($is_before_onboarding) {
                                        // Not joined yet: neither present nor absent
                                        continue;
                                    }
                                    
                                    if ($is_weekend || isset($monthly_holidays[$d])) {
                                        if (isset($monthly_attendance[$emp_att_id][$d])) {
                                            $att_st = strtoupper(trim($monthly_attendance[$emp_att_id][$d]['status'] ?? ''));
                                            $has_clock = !empty($monthly_attendance[$emp_att_id][$d]['clock_in']) && strpos($monthly_attendance[$emp_att_id][$d]['clock_in'], '0000-00-00') === false;
                                            if (in_array($att_st, ['P', 'PRESENT']) || $has_clock) {
                                                $days_present += 1;
                                            }
                                        }
                                        continue;
                                    }
                                    
                                    $is_half_leave = false;
                                    $is_full_leave = false;

                                    if (isset($monthly_leaves[$emp_att_id][$d])) {
                                        $lst = $monthly_leaves[$emp_att_id][$d];
                                        if (in_array($lst, ['0.5 AL', '0.5 SL', '0.5UPL', 'HD'])) {
                                            $is_half_leave = true;
                                        } else {
                                            $is_full_leave = true;
                                        }
                                    }

                                    $att_st = '';
                                    $has_clock = false;
                                    if (isset($monthly_attendance[$emp_att_id][$d])) {
                                        $att_st = strtoupper(trim($monthly_attendance[$emp_att_id][$d]['status'] ?? ''));
                                        $has_clock = !empty($monthly_attendance[$emp_att_id][$d]['clock_in']) && strpos($monthly_attendance[$emp_att_id][$d]['clock_in'], '0000-00-00') === false;

                                        if (in_array($att_st, ['0.5 AL', '0.5AL', '0.5 SL', '0.5SL', '0.5UPL', '0.5 UPL', 'HD', '0.5'])) {
                                            $is_half_leave = true;
                                        } elseif (in_array($att_st, ['AL', 'SL', 'UPL', 'HL', 'HOSP', 'EMERGENCY'])) {
                                            $is_full_leave = true;
                                        }
                                    }

                                    if ($is_half_leave) {
                                        $days_present += 0.5;
                                    } elseif ($is_full_leave) {
                                        // Approved full leave: neither present nor absent
                                    } elseif (in_array($att_st, ['P', 'PRESENT', 'LATE']) || $has_clock) {
                                        $days_present += 1;
                                    } elseif (in_array($att_st, ['A', 'ABSENT'])) {
                                        $absence += 1;
                                    } else {
                                        // Unexcused weekday absence
                                        $absence += 1;
                                    }
                                }
                                
                                $present_display = (floor($days_present) == $days_present) ? number_format($days_present, 0) : number_format($days_present, 1);
                                $absence_display = (floor($absence) == $absence) ? number_format($absence, 0) : number_format($absence, 1);
                                
                                $is_intern = (stripos($row['employment_type'] ?? '', 'intern') !== false);
                                $color = $is_intern 
                                    ? ['bg' => '#f3e8ff', 'text' => '#7e22ce'] 
                                    : ['bg' => '#eff6ff', 'text' => '#2563eb'];
                                $display_type = $is_intern ? 'Intern' : 'Full Time';
                                
                                echo "<tr>";
                                echo "<td style='padding-right: 10px;'><input type='checkbox' name='selected_ids[]' value='" . $row['id'] . "' class='rowCheckbox' style='transform: scale(1.2); cursor: pointer;' onclick='updateBulkDeleteBtn()'></td>";
                                echo "<td style='font-weight: 600; color: #0f172a;'>#" . htmlspecialchars($row['emp_id']) . "</td>";
                                echo "<td style='font-weight: 600; color: #059669;'>" . htmlspecialchars($row['welabel_id'] ?? '') . "</td>";
                                echo "<td style='font-weight: 500; text-transform: uppercase;'>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td><span style='background: {$color['bg']}; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: {$color['text']};'>$display_type</span></td>";
                                echo "<td><span style='background: #ecfdf5; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #059669;'>" . $present_display . " Days</span></td>";
                                echo "<td><span style='background: #fdf2f8; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; color: #db2777;'>" . $absence_display . " Days</span></td>";
                                
                                echo "<td class='actions-cell'>
                                    <div class='dropdown'>
                                        <button type='button' class='dropbtn' onclick='toggleActionDropdown(this, event)'>
                                            Actions ▼
                                        </button>
                                        <div class='dropdown-content'>
                                            <button type='button' onclick='openDetailsModal(\"" . htmlspecialchars($row['emp_id'], ENT_QUOTES) . "\", \"" . htmlspecialchars(addslashes($row['name'])) . "\", \"" . htmlspecialchars($row['employment_type'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['onboarding_date'] ?? '-', ENT_QUOTES) . "\", " . ($row['annual_leave_balance'] ?? 8) . ", " . ($row['sick_leave_balance'] ?? 14) . ", \"" . htmlspecialchars(['employee'=>'Data Annotator','qa'=>'Quality Assurance','tl'=>'Team Leader','gl'=>'Group Leader','hr'=>'Human Resources','admin'=>'Administrator'][strtolower($row['role'] ?? '')] ?? ($row['role'] ?? '')) . "\", \"" . htmlspecialchars($row['lunch_window'] ?? '') . "\", \"" . htmlspecialchars($row['shift_hour'] ?? '09:00-18:00') . "\", " . ($row['unpaid_leave_balance'] ?? 0) . ", " . ($row['hospitalization_leave_balance'] ?? 60) . ")'>ℹ️ Details</button>
                                            <button type='button' onclick='openEditModal(" . $row['id'] . ", \"" . htmlspecialchars($row['emp_id'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['welabel_id'] ?? '', ENT_QUOTES) . "\", \"" . htmlspecialchars(addslashes($row['name'])) . "\", \"" . htmlspecialchars($row['employment_type'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['role'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['lunch_window'] ?? '', ENT_QUOTES) . "\", \"" . htmlspecialchars($row['group_leader_id'] ?? '', ENT_QUOTES) . "\", \"" . htmlspecialchars($row['shift_hour'] ?? '09:00-18:00', ENT_QUOTES) . "\", \"" . htmlspecialchars($row['onboarding_date'] ?? '', ENT_QUOTES) . "\")'>✏️ Edit</button>
                                            
                                            <button type='button' onclick='resetPassword(" . $row['id'] . ", \"" . htmlspecialchars(addslashes($row['name'])) . "\")'>🔑 Reset Pwd</button>
                                            <button type='button' class='danger-item' onclick='deleteEmployee(" . $row['id'] . ", \"" . htmlspecialchars(addslashes($row['name'])) . "\")'>🗑️ Delete</button>
                                        </div>
                                    </div>
                                </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>
                                    <div class='empty-state'>
                                        <div class='empty-state-icon'>👥</div>
                                        <div class='empty-state-text'>No employees yet. Click \"+ Add Employee\" to get started.</div>
                                    </div>
                                  </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleAllCheckboxes(source) {
            let checkboxes = document.querySelectorAll('.rowCheckbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            updateBulkDeleteBtn();
        }

        function updateBulkDeleteBtn() {
            let anyChecked = document.querySelectorAll('.rowCheckbox:checked').length > 0;
            document.getElementById('bulkDeleteBtn').style.display = anyChecked ? 'inline-block' : 'none';
        }

        function submitBulkDelete() {
            if(confirm("Are you sure you want to delete all selected employees? This cannot be undone.")) {
                document.getElementById('bulkDeleteForm').submit();
            }
        }

        function searchTable() {
            try {
                let input = document.getElementById("searchInput").value.toLowerCase();
                let rows = document.querySelectorAll("tbody tr");
                rows.forEach(row => {
                    if(row.cells && row.cells.length > 1) { 
                        let textToSearch = "";
                        for(let i = 1; i <= 4; i++) {
                            if(row.cells[i]) textToSearch += (row.cells[i].textContent || "").toLowerCase() + " ";
                        }
                        if(textToSearch.indexOf(input) > -1) {
                            row.style.setProperty("display", "", "important");
                        } else {
                            row.style.setProperty("display", "none", "important");
                        }
                    }
                });
            } catch(e) {
                console.error("Search failed:", e);
            }
        }
        function resetPassword(id, name) {
            if (confirm("Reset password to default for " + name + "?")) {
                document.getElementById('reset_id_input').value = id;
                document.getElementById('singleResetForm').submit();
            }
        }

        function deleteEmployee(id, name) {
            if (confirm("Delete employee " + name + "?")) {
                document.getElementById('delete_id_input').value = id;
                document.getElementById('singleDeleteForm').submit();
            }
        }
    </script>

    <form id="singleResetForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="reset_id" id="reset_id_input">
        <input type="hidden" name="reset_password" value="1">
    </form>
    <form id="singleDeleteForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="delete_id" id="delete_id_input">
        <input type="hidden" name="delete_employee" value="1">
    </form>

    <!-- ADD EMPLOYEE MODAL -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            <h2 class="text-slate-900" style="margin-bottom: 25px; ">Add New Employee</h2>
            <form method="POST" action="">
                <label class="form-label">Employee ID</label>
                <input type="text" name="emp_id" class="form-input" required placeholder="e.g., JSMY044">
                <label class="form-label">WeLabel ID</label>
                <input type="text" name="welabel_id" class="form-input" placeholder="e.g., KL_JohnDoe@welabel.ai">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-input" required placeholder="e.g., Jane Doe">
                
                <label class="form-label">Employment Type</label>
                <select name="employment_type" class="form-input" required>
                    <option value="Intern">Intern</option>
                    <option value="Full time">Full time (Old)</option>
                    <option value="Full-Time">Full-Time</option>
                </select>

                <label class="form-label">System Role</label>
                <select name="role" class="form-input" required>
                    <option value="qa">QA</option>
                    <option value="employee">Annotator</option>
                    <option value="tl">Team Lead</option>
                    <option value="gl">Group Lead</option>
                    <option value="hr">HR</option>
                    <option value="admin">Admin</option>
                </select>

                <label class="form-label">Work Hours Window (Shift Hour)</label>
                <select name="shift_hour" class="form-input shift-hour-select" required>
                    <option value="08:00-17:00">08:00 AM - 05:00 PM</option>
                    <option value="08:30-17:30">08:30 AM - 05:30 PM</option>
                    <option value="09:00-18:00">09:00 AM - 06:00 PM</option>
                    <option value="10:00-19:00">10:00 AM - 07:00 PM</option>
                    <option value="11:00-20:00">11:00 AM - 08:00 PM</option>
                    <option value="12:00-21:00">12:00 PM - 09:00 PM</option>
                    <option value="13:00-22:00">01:00 PM - 10:00 PM</option>
                </select>

                <label class="form-label">Lunchtime Window</label>
                <select name="lunch_window" class="form-input" required>
                    <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                    <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                </select>

                <label class="form-label" id="add_gl_label">Group Leader (Optional)</label>
                <select name="group_leader_id" id="add_gl_select" class="form-input">
                    <option value="">-- None --</option>
                    <?php foreach($all_gls as $gl): ?>
                        <option value="<?php echo htmlspecialchars($gl['emp_id']); ?>"><?php echo htmlspecialchars($gl['name']) . " (" . htmlspecialchars($gl['emp_id']) . ")"; ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label">Onboarding Date</label>
                <input type="date" name="onboarding_date" id="add_onboarding_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">

                <button type="submit" name="add_employee" class="btn-primary" style="width: 100%; margin-top: 10px;">Save Employee</button>
            </form>
        </div>
    </div>

    <!-- EDIT EMPLOYEE MODAL -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="document.getElementById('editModal').style.display='none'">&times;</button>
            <h2 class="text-slate-900" style="margin-bottom: 25px; ">Edit Employee</h2>
            <form method="POST" action="">
                <input type="hidden" name="edit_id" id="edit_id">
                <label class="form-label">Employee ID</label>
                <input type="text" name="emp_id" id="edit_emp_id" class="form-input" required>
                <label class="form-label">WeLabel ID</label>
                <input type="text" name="welabel_id" id="edit_welabel_id" class="form-input">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" id="edit_name" class="form-input" required>

                <label class="form-label">Employment Type</label>
                <select name="employment_type" id="edit_employment_type" class="form-input" required>
                    <option value="Intern">Intern</option>
                    <option value="Full time">Full time (Old)</option>
                    <option value="Full-Time">Full-Time</option>
                </select>

                <label class="form-label">System Role</label>
                <select name="role" id="edit_role" class="form-input" required>
                    <option value="qa">QA</option>
                    <option value="employee">Annotator</option>
                    <option value="tl">Team Lead</option>
                    <option value="gl">Group Lead</option>
                    <option value="hr">HR</option>
                    <option value="admin">Admin</option>
                </select>

                <label class="form-label">Work Hours Window (Shift Hour)</label>
                <select name="shift_hour" class="form-input shift-hour-select" required>
                    <option value="08:00-17:00">08:00 AM - 05:00 PM</option>
                    <option value="08:30-17:30">08:30 AM - 05:30 PM</option>
                    <option value="09:00-18:00">09:00 AM - 06:00 PM</option>
                    <option value="10:00-19:00">10:00 AM - 07:00 PM</option>
                    <option value="11:00-20:00">11:00 AM - 08:00 PM</option>
                    <option value="12:00-21:00">12:00 PM - 09:00 PM</option>
                    <option value="13:00-22:00">01:00 PM - 10:00 PM</option>
                </select>

                <label class="form-label">Lunchtime Window</label>
                <select name="lunch_window" id="edit_lunch_window" class="form-input" required>
                    <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                    <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                </select>

                <label class="form-label" id="edit_gl_label">Group Leader (Optional)</label>
                <select name="group_leader_id" id="edit_group_leader_id" class="form-input">
                    <option value="">-- None --</option>
                    <?php foreach($all_gls as $gl): ?>
                        <option value="<?php echo htmlspecialchars($gl['emp_id']); ?>"><?php echo htmlspecialchars($gl['name']) . " (" . htmlspecialchars($gl['emp_id']) . ")"; ?></option>
                    <?php endforeach; ?>
                </select>

                <label class="form-label">Onboarding Date</label>
                <input type="date" name="onboarding_date" id="edit_onboarding_date" class="form-input">

                <button type="submit" name="edit_employee" class="btn-primary" style="width: 100%; margin-top: 10px;">Update Employee</button>
            </form>
        </div>
    </div>

    <!-- EMPLOYEE DETAILS MODAL (FEATURE 7) -->
    <div id="detailsModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 500px;">
            <button class="close-btn" onclick="document.getElementById('detailsModal').style.display='none'">&times;</button>
            <h2 class="text-slate-900" style="margin-bottom: 20px; " id="det_title">Employee Statistics & Info</h2>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Employee ID:</strong> <span id="det_emp_id">-</span></div>
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Full Name:</strong> <span id="det_name">-</span></div>
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Employment Type:</strong> <span id="det_type">-</span></div>
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Role:</strong> <span id="det_role">-</span></div>
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Shift Hour:</strong> <span id="det_shift">-</span></div>
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Lunch Time:</strong> <span id="det_lunch">-</span></div>
                <div style="font-size: 14px;"><strong>Onboarding Date:</strong> <span id="det_onboarding">-</span></div>
            </div>

            <h3 class="text-slate-900 dark:text-slate-100" style="font-size: 16px; margin-bottom: 12px; ">Remaining Leave Balances</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; text-align: center;">
                <div class="bg-blue-50 dark:bg-slate-700" style="padding:12px; border-radius:10px; border:1px solid #bfdbfe;">
                    <div style="font-size:12px; font-weight:600;" class="text-blue-800 dark:text-blue-300">Annual Leave</div>
                    <div style="font-size:22px; font-weight:800;" class="text-blue-700 dark:text-blue-400" id="det_al">0 / 8</div>
                </div>
                <div class="bg-green-50 dark:bg-slate-700" style="padding:12px; border-radius:10px; border:1px solid #bbf7d0;">
                    <div style="font-size:12px; font-weight:600;" class="text-green-800 dark:text-green-300">Sick Leave</div>
                    <div style="font-size:22px; font-weight:800;" class="text-green-700 dark:text-green-400" id="det_sl">0 / 14</div>
                </div>
                <div class="bg-orange-50 dark:bg-slate-700" style="padding:12px; border-radius:10px; border:1px solid #fed7aa;">
                    <div style="font-size:12px; font-weight:600;" class="text-orange-800 dark:text-orange-300">Unpaid Leave</div>
                    <div style="font-size:22px; font-weight:800;" class="text-orange-700 dark:text-orange-400" id="det_upl">0</div>
                </div>
                <div class="bg-purple-50 dark:bg-slate-700" style="padding:12px; border-radius:10px; border:1px solid #e9d5ff;">
                    <div style="font-size:12px; font-weight:600;" class="text-purple-800 dark:text-purple-300">Hospitalization</div>
                    <div style="font-size:22px; font-weight:800;" class="text-purple-700 dark:text-purple-400" id="det_hosp">0</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleActionDropdown(btn, event) {
            event.stopPropagation();
            document.querySelectorAll('.dropdown-content').forEach(d => {
                if (d !== btn.nextElementSibling) {
                    d.classList.remove('show');
                    if (d.closest('td')) { d.closest('td').style.zIndex = ''; d.closest('td').style.position = ''; }
                    if (d.closest('tr')) { d.closest('tr').style.zIndex = ''; d.closest('tr').style.position = ''; }
                }
            });
            const content = btn.nextElementSibling;
            content.classList.toggle('show');
            if (content.classList.contains('show')) {
                if (content.closest('td')) {
                    content.closest('td').style.position = 'relative';
                    content.closest('td').style.zIndex = '999';
                    if (content.closest('tr')) { content.closest('tr').style.zIndex = '999'; content.closest('tr').style.position = 'relative'; }
                }
            } else {
                if (content.closest('td')) {
                    content.closest('td').style.zIndex = '';
                    content.closest('td').style.position = '';
                    if (content.closest('tr')) { content.closest('tr').style.zIndex = ''; content.closest('tr').style.position = ''; }
                }
            }
        }

        window.onclick = function(event) {
            if (!event.target.matches('.dropbtn')) {
                document.querySelectorAll('.dropdown-content').forEach(d => {
                    d.classList.remove('show');
                    if (d.closest('td')) { d.closest('td').style.zIndex = ''; d.closest('td').style.position = ''; }
                    if (d.closest('tr')) { d.closest('tr').style.zIndex = ''; d.closest('tr').style.position = ''; }
                });
            }
        }

        function openDetailsModal(empId, name, type, onboarding, al, sl, role, lunch, shift, upl = 0, hosp = 60) {
            document.getElementById('det_emp_id').innerText = empId;
            document.getElementById('det_name').innerText = name;
            document.getElementById('det_type').innerText = type;
            document.getElementById('det_role').innerText = role || '-';
            document.getElementById('det_shift').innerText = shift || '-';
            document.getElementById('det_lunch').innerText = lunch || '-';
            document.getElementById('det_onboarding').innerText = onboarding;
            
            let al_quota = (type && type.toLowerCase() === 'intern') ? 0 : 8;
            let sl_quota = (type && type.toLowerCase() === 'intern') ? 0 : 14;
            let hosp_quota = (type && type.toLowerCase() === 'intern') ? 0 : 60;
            if (type && type.toLowerCase() === 'intern') hosp = 0;
            
            document.getElementById('det_al').innerText = al + ' / ' + al_quota;
            document.getElementById('det_sl').innerText = sl + ' / ' + sl_quota;
            document.getElementById('det_upl').innerText = upl;
            document.getElementById('det_hosp').innerText = hosp + ' / ' + hosp_quota;
            
            document.getElementById('detailsModal').style.display = 'flex';
        }

        function openEditModal(id, empId, welabelId, name, type, roleOrEmpData, lunchWindow, groupLeaderId, shiftHour, onboardingDate = "") {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_emp_id').value = empId;
            document.getElementById('edit_welabel_id').value = welabelId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_employment_type').value = type;
            
            let empData = typeof roleOrEmpData === 'object' ? roleOrEmpData : {
                role: roleOrEmpData,
                lunch_window: lunchWindow,
                group_leader_id: groupLeaderId,
                shift_hour: shiftHour
            };

            document.getElementById('edit_role').value = empData.role;
            document.getElementById('edit_lunch_window').value = empData.lunch_window;
            let shiftSelects = document.querySelectorAll('#editModal .shift-hour-select');
            if (shiftSelects.length > 0) shiftSelects[0].value = empData.shift_hour || '09:00-18:00';
            document.getElementById('edit_group_leader_id').value = empData.group_leader_id || '';
            let onbVal = typeof roleOrEmpData === 'object' ? (roleOrEmpData.onboarding_date || '') : (onboardingDate || '');
            let onbInput = document.getElementById('edit_onboarding_date');
            if (onbInput) onbInput.value = onbVal;
            document.getElementById('editModal').style.display = 'flex';
            toggleEditGLDropdown(); // Initial check for edit modal
        }

        function toggleAddGLDropdown() {
            const role = document.getElementById('add_role').value;
            const glLabel = document.getElementById('add_gl_label');
            const glSelect = document.getElementById('add_gl_select');
            if (role === 'employee' || role === 'qa') {
                glLabel.style.display = 'block';
                glSelect.style.display = 'block';
            } else {
                glLabel.style.display = 'none';
                glSelect.style.display = 'none';
                glSelect.value = '';
            }
        }

        function toggleEditGLDropdown() {
            const role = document.getElementById('edit_role').value;
            const glLabel = document.getElementById('edit_gl_label');
            const glSelect = document.getElementById('edit_group_leader_id');
            if (role === 'employee' || role === 'qa') {
                glLabel.style.display = 'block';
                glSelect.style.display = 'block';
            } else {
                glLabel.style.display = 'none';
                glSelect.style.display = 'none';
                glSelect.value = '';
            }
        }

        document.getElementById('add_role')?.addEventListener('change', toggleAddGLDropdown);
        document.getElementById('edit_role')?.addEventListener('change', toggleEditGLDropdown);
        
        // Initial call for add modal if it exists
        if(document.getElementById('add_role')) toggleAddGLDropdown();

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.style.display = 'none';
            });
        });
    </script>

    <?php if (isset($_GET['error'])): ?>
        <div class="toast toast-error" id="errorToastMsg"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <script>
            setTimeout(() => { document.getElementById('errorToastMsg').style.display = 'none'; }, 3000);
        </script>
    <?php endif; ?>

</body>
</html>

