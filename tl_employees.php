<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tl') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

// 1. ADD EMPLOYEE LOGIC
if (isset($_POST['add_employee'])) {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $welabel_id = mysqli_real_escape_string($conn, $_POST['welabel_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $employment_type = mysqli_real_escape_string($conn, $_POST['employment_type']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $lunch_window = mysqli_real_escape_string($conn, $_POST['lunch_window']);
    $password = password_hash('password123', PASSWORD_DEFAULT);

    // Check for duplicates
    $check_query = "SELECT id FROM employees WHERE emp_id='$emp_id' OR name='$name'";
    if (!empty($welabel_id)) {
        $check_query .= " OR welabel_id='$welabel_id'";
    }
    $check = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check) > 0) {
        header("Location: hr_employees.php?error=" . urlencode("Employee ID, WeLabel ID, or Name already exists!"));
        exit();
    }

    $sql = "INSERT INTO employees (emp_id, welabel_id, name, password, employment_type, role, lunch_window) VALUES ('$emp_id', '$welabel_id', '$name', '$password', '$employment_type', '$role', '$lunch_window')";
    mysqli_query($conn, $sql);
    
    header("Location: hr_employees.php");
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
    $lunch_window = mysqli_real_escape_string($conn, $_POST['lunch_window']);

    // Check for duplicates
    $check_query = "SELECT id FROM employees WHERE (emp_id='$emp_id' OR name='$name') AND id != '$edit_id'";
    if (!empty($welabel_id)) {
        $check_query = "SELECT id FROM employees WHERE (emp_id='$emp_id' OR welabel_id='$welabel_id' OR name='$name') AND id != '$edit_id'";
    }
    $check = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check) > 0) {
        header("Location: hr_employees.php?error=" . urlencode("Employee ID, WeLabel ID, or Name already exists!"));
        exit();
    }

    $sql = "UPDATE employees SET emp_id='$emp_id', welabel_id='$welabel_id', name='$name', employment_type='$employment_type', role='$role', lunch_window='$lunch_window' WHERE id='$edit_id'";
    mysqli_query($conn, $sql);
    
    header("Location: hr_employees.php");
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
    
    header("Location: hr_employees.php");
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
    
    header("Location: hr_employees.php");
    exit();
}

// 4. RESET PASSWORD LOGIC
if (isset($_POST['reset_password'])) {
    $reset_id = mysqli_real_escape_string($conn, $_POST['reset_id']);
    $new_password = password_hash('password123', PASSWORD_DEFAULT);
    
    $sql = "UPDATE employees SET password='$new_password' WHERE id='$reset_id'";
    mysqli_query($conn, $sql);
    
    header("Location: hr_employees.php?msg=" . urlencode("Password reset to 'password123'"));
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
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

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
                <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">+ Add Member</button>
            </div>
        </header>

        <div class="content">
            <?php
            $stats_query = mysqli_query($conn, "SELECT employment_type, COUNT(*) as count FROM employees GROUP BY employment_type");
            $stats = ['Full time' => 0, 'Intern' => 0];
            $total = 0;
            while($row = mysqli_fetch_assoc($stats_query)){
                if(isset($stats[$row['employment_type']])) {
                    $stats[$row['employment_type']] = $row['count'];
                }
                $total += $row['count'];
            }
            ?>
                                    <div class="emp-grid">
                <div class="emp-card" onclick="window.location.href='?type='">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div class="emp-icon bg-slate-100 text-slate-500" >👥</div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="emp-title">Total Employees</div>
                        <div class="emp-val"><?php echo $total; ?></div>
                    </div>
                </div>
                <div class="emp-card" onclick="window.location.href='?type=Full+time'">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <div class="emp-icon bg-emerald-50 text-emerald-600" >💼</div>

                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div class="emp-title">Full Time</div>
                        <div class="emp-val"><?php echo $stats['Full time']; ?></div>
                    </div>
                </div>
                <div class="emp-card" onclick="window.location.href='?type=Intern'">
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
                <div style="margin-bottom: 15px; display: flex; justify-content: flex-end;">
                    <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Search employees by ID, WeLabel ID, or Name..." style="padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 24px; width: 350px; font-size: 14px; outline: none; transition: border-color 0.2s;">
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
                        
                        
                        $first_day = date("Y-m-01");
                        $today = date("Y-m-d");
                        $workdays_passed = 0;
                        $current = strtotime($first_day);
                        $end = strtotime($today);
                        while ($current <= $end) {
                            if (date("N", $current) < 6) { $workdays_passed++; }
                            $current = strtotime("+1 day", $current);
                        }
                        
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $emp_att_id = $row["emp_id"];
                                $cur_month = date("Y-m");
                                
                                $days_present_query = mysqli_query($conn, "SELECT COUNT(DISTINCT date) as present FROM attendance_logs WHERE emp_id = '$emp_att_id' AND DATE_FORMAT(date, '%Y-%m') = '$cur_month' AND clock_in IS NOT NULL");
                                $days_present = mysqli_fetch_assoc($days_present_query)["present"] ?? 0;
                                
                                $leave_query = mysqli_query($conn, "SELECT SUM(DATEDIFF(LEAST(end_date, '$today'), GREATEST(start_date, '$first_day')) + 1) as leave_days FROM leave_requests WHERE emp_id = '$emp_att_id' AND status = 'approved' AND start_date <= '$today' AND end_date >= '$first_day'");
                                $leave_days = mysqli_fetch_assoc($leave_query)["leave_days"] ?? 0;
                                
                                $absence = max(0, $workdays_passed - $days_present - $leave_days);
                                
                                $type_colors = [
                                    'Full time' => ['bg' => '#ecfdf5', 'text' => '#059669'],
                                    'Intern' => ['bg' => '#fdf4ff', 'text' => '#a855f7'],
                                ];
                                $color = $type_colors[$row['employment_type']] ?? ['bg' => '#f1f5f9', 'text' => '#475569'];
                                
                                echo "<tr>";
                                echo "<td style='padding-right: 10px;'><input type='checkbox' name='selected_ids[]' value='" . $row['id'] . "' class='rowCheckbox' style='transform: scale(1.2); cursor: pointer;' onclick='updateBulkDeleteBtn()'></td>";
                                echo "<td style='font-weight: 600; color: #0f172a;'>#" . htmlspecialchars($row['emp_id']) . "</td>";
                                echo "<td style='font-weight: 600; color: #059669;'>" . htmlspecialchars($row['welabel_id'] ?? '') . "</td>";
                                echo "<td style='font-weight: 500;'>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td><span style='background: {$color['bg']}; padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; color: {$color['text']}; text-transform: uppercase;'>" . htmlspecialchars($row['employment_type']) . "</span></td>";
                                
                                echo "<td>
                                        <div class='actions-cell'>
                                            <button type='button' onclick='openDetailsModal(\"" . htmlspecialchars($row['emp_id'], ENT_QUOTES) . "\", \"" . htmlspecialchars(addslashes($row['name'])) . "\", \"" . htmlspecialchars($row['employment_type'], ENT_QUOTES) . "\", \"" . htmlspecialchars($row['onboarding_date'] ?? '-', ENT_QUOTES) . "\", " . ($row['annual_leave_balance'] ?? 8) . ", " . ($row['sick_leave_balance'] ?? 14) . ", \"" . htmlspecialchars($row['role'] ?? '') . "\", \"" . htmlspecialchars($row['lunch_window'] ?? '') . "\", \"" . htmlspecialchars($row['shift_hour'] ?? '09:00-18:00') . "\")' class='btn-edit' style='background-color:#e0e7ff; color:#4338ca;'>Details</button>
                                            
                                            <form method='POST' action='' style='margin:0;' onsubmit='return confirm(\"Are you sure you want to reset this employee\\'s password to \\'password123\\'?\");'>
                                                <input type='hidden' name='reset_id' value='" . $row['id'] . "'>
                                                <button type='submit' name='reset_password' class='btn-edit' style='background-color:#fef3c7; color:#d97706;'>Reset Pwd</button>
                                            </form>
                                            <form method='POST' action='' style='margin:0;' onsubmit='return confirm(\"Are you sure you want to delete this employee?\");'>
                                                <input type='hidden' name='delete_id' value='" . $row['id'] . "'>
                                                <button type='submit' name='delete_employee' class='btn-delete'>Delete</button>
                                            </form>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='8'>
                                    <div class='empty-state'>
                                        <div class='empty-state-icon'>👥</div>
                                        <div class='empty-state-text'>No employees found.</div>
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
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            table = document.querySelector(".table-container table");
            tr = table.getElementsByTagName("tr");
            for (i = 1; i < tr.length; i++) {
                let match = false;
                for (let j = 1; j <= 3; j++) {
                    td = tr[i].getElementsByTagName("td")[j];
                    if (td) {
                        txtValue = td.textContent || td.innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            match = true;
                            break;
                        }
                    }
                }
                if (tr[i].getElementsByTagName("td").length > 1) {
                    tr[i].style.display = match ? "" : "none";
                }
            }
        }
    </script>

    <!-- ADD EMPLOYEE MODAL -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <button class="close-btn" onclick="document.getElementById('addModal').style.display='none'">&times;</button>
            <h2 class="text-slate-900" style="margin-bottom: 25px; ">Add New Employee</h2>
            <form method="POST" action="">
                <label class="form-label">Employee ID</label>
                <input type="text" name="emp_id" class="form-input" required placeholder="e.g., FT-2606-001">
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

                <label class="form-label">Lunchtime Window</label>
                <select name="lunch_window" class="form-input" required>
                    <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                    <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                </select>
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

                <label class="form-label">Lunchtime Window</label>
                <select name="lunch_window" id="edit_lunch_window" class="form-input" required>
                    <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                    <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                </select>
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
                <div style="font-size: 14px; margin-bottom: 8px;"><strong>Lunch Limit:</strong> <span id="det_lunch">-</span></div>
                <div style="font-size: 14px;"><strong>Onboarding Date:</strong> <span id="det_onboarding">-</span></div>
            </div>

            <h3 class="text-slate-900" style="font-size: 16px; margin-bottom: 12px; ">Remaining Leave Balances</h3>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; text-align: center;">
                <div class="bg-blue-50" style=" padding:12px; border-radius:10px; border:1px solid #bfdbfe;">
                    <div style="font-size:12px; color:#1e40af; font-weight:600;">Annual</div>
                    <div style="font-size:22px; font-weight:800; color:#1d4ed8;" id="det_al">14</div>
                </div>
                <div class="bg-green-50" style=" padding:12px; border-radius:10px; border:1px solid #bbf7d0;">
                    <div style="font-size:12px; color:#166534; font-weight:600;">Sick</div>
                    <div style="font-size:22px; font-weight:800; color:#15803d;" id="det_sl">14</div>
                </div>
                <div class="bg-purple-50" style=" padding:12px; border-radius:10px; border:1px solid #f5d0fe;">
                    <div style="font-size:12px; color:#86198f; font-weight:600;">Casual</div>
                    <div style="font-size:22px; font-weight:800; color:#a21caf;" id="det_cl">7</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDetailsModal(empId, name, type, onboarding, al, sl, role, lunch, shift) {
            document.getElementById('det_emp_id').innerText = empId;
            document.getElementById('det_name').innerText = name;
            document.getElementById('det_type').innerText = type;
            document.getElementById('det_role').innerText = role || '-';
            document.getElementById('det_shift').innerText = shift || '-';
            document.getElementById('det_lunch').innerText = lunch || '-';
            document.getElementById('det_onboarding').innerText = onboarding;
            document.getElementById('det_al').innerText = al;
            document.getElementById('det_sl').innerText = sl;
            document.getElementById('detailsModal').style.display = 'flex';
        }

        function openEditModal(id, empId, welabelId, name, type, role, lunchWindow) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_emp_id').value = empId;
            document.getElementById('edit_welabel_id').value = welabelId;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_employment_type').value = type;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_lunch_window').value = lunchWindow;
            document.getElementById('editModal').style.display = 'flex';
        }
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

