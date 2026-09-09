<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$current_employee_id = $_SESSION['emp_id'];
$user_id = $_SESSION['user_id'];
$status_message = "";
$error_message = "";

// Friday Rule: shift/lunch change requests only allowed on Fridays
$is_friday = (date('N') == 5); // 5 = Friday

// Fetch user data
$sql = "SELECT * FROM employees WHERE id = '$user_id'";
$result = mysqli_query($conn, $sql);
$user_data = mysqli_fetch_assoc($result);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['change_password'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (password_verify($old_password, $user_data['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE employees SET password = '$hashed_password' WHERE id = '$user_id'";
                if (mysqli_query($conn, $update_sql)) {
                    session_destroy();
                    header("Location: index.php?msg=" . urlencode("Password changed successfully. Please log in again."));
                    exit();
                } else {
                    $error_message = "Database error. Please try again later.";
                }
            } else {
                $error_message = "New password and confirm password do not match.";
            }
        } else {
            $error_message = "Incorrect old password.";
        }
    } elseif (isset($_POST['update_profile'])) {
        $new_name = mysqli_real_escape_string($conn, $_POST['new_name']);
        $old_emp_id = $user_data['emp_id'];
        
        $emp_id_sql_part = "";
        // Only HR/Admin/TL can change employment type
        if (!in_array($user_data['role'], ['employee', 'qa', 'gl']) && !empty($_POST['emp_id'])) {
            $employment_type = mysqli_real_escape_string($conn, trim($_POST['employment_type']));
            $requested_emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
            if ($requested_emp_id !== $old_emp_id) {
                $check_dup = mysqli_query($conn, "SELECT id FROM employees WHERE emp_id = '$requested_emp_id'");
                if (mysqli_num_rows($check_dup) > 0) {
                    $error_message = "This Employee ID is already in use.";
                } else {
                    $new_emp_id = $requested_emp_id;
                    $emp_id_sql_part = ", emp_id = '$new_emp_id'";
                }
            }
        }
        
        if (empty($error_message)) {
            $update_sql = "UPDATE employees SET name = '$new_name' $emp_id_sql_part WHERE id = '$user_id'";
            if (mysqli_query($conn, $update_sql)) {
                $status_message = "Profile updated successfully!";
                $user_data['name'] = $new_name;
                $_SESSION['name'] = $new_name;
                
                if (isset($new_emp_id) && $new_emp_id !== $old_emp_id) {
                    mysqli_query($conn, "UPDATE lunch_breaks SET employee_id = '$new_emp_id' WHERE employee_id = '$old_emp_id'");
                    mysqli_query($conn, "UPDATE break_change_requests SET emp_id = '$new_emp_id' WHERE emp_id = '$old_emp_id'");
                    mysqli_query($conn, "UPDATE break_override_requests SET emp_id = '$new_emp_id' WHERE emp_id = '$old_emp_id'");
                    
                    $user_data['emp_id'] = $new_emp_id;
                    $_SESSION['emp_id'] = $new_emp_id;
                    $current_employee_id = $new_emp_id;
                }
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
    } elseif (isset($_POST['request_break_change'])) {
        if (!$is_friday) {
            $error_message = "Lunch change requests can only be submitted on Fridays.";
        } else {
        $requested_window = mysqli_real_escape_string($conn, $_POST['requested_window']);
        $current_window = $user_data['lunch_window'];
        
        $check_sql = "SELECT id FROM break_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending'";
        $check_res = mysqli_query($conn, $check_sql);
        
        $cooldown_sql = "SELECT created_at FROM break_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 1";
        $cooldown_res = mysqli_query($conn, $cooldown_sql);

        if (mysqli_num_rows($check_res) > 0) {
            $error_message = "You already have a pending break change request.";
        } elseif (mysqli_num_rows($cooldown_res) > 0) {
            $row = mysqli_fetch_assoc($cooldown_res);
            $last_request_time = strtotime($row['created_at']);
            $next_allowed_time = $last_request_time + (7 * 24 * 60 * 60);
            $error_message = "You can only change your lunchtime once a week. You can request again on " . date('M d, Y', $next_allowed_time) . ".";
        } else {
            $insert_sql = "INSERT INTO break_change_requests (emp_id, current_window, requested_window) VALUES ('{$user_data['emp_id']}', '$current_window', '$requested_window')";
            if (mysqli_query($conn, $insert_sql)) {
                $status_message = "Lunch break change requested successfully. Pending approval.";
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
        }
    } elseif (isset($_POST['request_shift_change'])) {
        if (!$is_friday) {
            $error_message = "Shift change requests can only be submitted on Fridays.";
        } else {
        $requested_shift = mysqli_real_escape_string($conn, $_POST['requested_shift']);
        $current_shift = $user_data['shift_hour'] ?? '09:00-18:00';
        
        $check_sql = "SELECT id FROM shift_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending'";
        $check_res = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_res) > 0) {
            $error_message = "You already have a pending shift change request.";
        } else {
            $insert_sql = "INSERT INTO shift_change_requests (emp_id, current_shift, requested_shift) VALUES ('{$user_data['emp_id']}', '$current_shift', '$requested_shift')";
            if (mysqli_query($conn, $insert_sql)) {
                $emp_name_val = mysqli_real_escape_string($conn, $user_data['full_name']);
                $emp_id_val = mysqli_real_escape_string($conn, $user_data['emp_id']);
                $alert_msg = mysqli_real_escape_string($conn, "$emp_name_val ($emp_id_val) requested a shift change from $current_shift to $requested_shift.");
                mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
                
                $status_message = "Shift hour change requested successfully. Pending approval.";
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
        } // end else (is_friday)
    } // end elseif request_shift_change
} // end if POST

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Profile & Settings</title>
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

        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; align-items: start; max-width: 1400px; }
        .profile-card { background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); border: 1px solid rgba(226,232,240,0.5); width: 100%; }
        .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .info-label { color: #64748b; font-weight: 600; font-size: 14px; }
        .info-value { color: #0f172a; font-weight: 700; font-size: 15px; }

        .form-group { margin-bottom: 20px; }
        .form-label { font-size: 13px; font-weight: 600; color: #64748b; display: block; margin-bottom: 8px; }
        .form-input { width: 100%; padding: 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 15px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; background: #f8fafc; color: #334155; }
        .form-input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        
        .btn-submit { background-color: #1e293b; color: white; border: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .btn-submit:hover { background-color: #0f172a; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15); }
        
        .btn-outline { background: transparent; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        body.dark-mode .btn-outline { color: #f8fafc !important; border-color: rgba(255, 255, 255, 0.2) !important; background: rgba(255,255,255,0.05); }

        .custom-select {
            appearance: none;
            background-color: #fff;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2352525b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        body.dark-mode .custom-select {
            background-color: #0f172a !important;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2394a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 14px center !important;
            background-size: 16px !important;
        }
        
        body.dark-mode .info-value { color: #f8fafc !important; }
        body.dark-mode .info-label { color: #94a3b8 !important; }

        .toast-success { background: #ecfdf5; color: #059669; padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #a7f3d0; }
        .toast-error { background: #fef2f2; color: #ef4444; padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #fecaca; }
        

    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Profile & Settings</div>
        </header>

        <div class="content">
            
            <?php if ($status_message): ?>
                <div class="toast-success"><?php echo $status_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="toast-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <div class="profile-card">
                <div class="card-title">Personal Information</div>
                <form method="POST" action="">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-group">
                        <?php if (in_array($user_data['role'], ['employee', 'qa', 'gl'])): ?>
                            <label class="form-label">Employee ID (Fixed)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['emp_id']); ?>" class="form-input" disabled style="background: rgba(0,0,0,0.02); color: #94a3b8; cursor: not-allowed; border: 1px solid rgba(0,0,0,0.05);">
                        <?php else: ?>
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="emp_id" class="form-input" required value="<?php echo htmlspecialchars($user_data['emp_id']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="new_name" class="form-input" required value="<?php echo htmlspecialchars($user_data['name']); ?>">
                    </div>

                    <div class="info-row" style="margin-top: 10px;">
                        <span class="info-label">Role / Position</span>
                        <span class="info-value text-slate-600" style="text-transform: capitalize; background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05);  padding: 4px 10px; border-radius: 6px; font-size: 13px;"><?php echo htmlspecialchars($user_data['role']); ?></span>
                    </div>

                    <button type="submit" class="btn-submit" style="margin-top: 15px;">Save Changes</button>
                </form>
            </div>

            <?php if (in_array($user_data['role'], ['employee', 'qa', 'gl'])): ?>
            <div class="profile-card">
                <div class="card-title">Lunchtime Settings</div>
                <div class="info-row" style="margin-bottom: 20px;">
                    <span class="info-label">Current Assigned Window</span>
                    <span class="info-value" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: inherit; padding: 6px 12px; border-radius: 8px; font-weight: 700;"><?php echo htmlspecialchars($user_data['lunch_window']); ?></span>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="request_break_change" value="1">
                    <div class="form-group">
                        <label class="form-label">Request New Lunchtime Window</label>
                        <select name="requested_window" class="form-input custom-select" required>
                            <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                            <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                        </select>
                    </div>
                    <?php if ($is_friday): ?>
                    <button type="submit" class="btn-submit btn-outline">Request Change</button>
                    <?php else: ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.5; cursor:not-allowed;">Request Change</button>
                    <p style="font-size:12px; color:#ef4444; margin-top:8px;">⚠️ Change requests can only be submitted on <strong>Fridays</strong>.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="profile-card">
                <div class="card-title">Shift Hour Settings</div>
                <div class="info-row" style="margin-bottom: 20px;">
                    <span class="info-label">Current Assigned Window</span>
                    <span class="info-value" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: inherit; padding: 6px 12px; border-radius: 8px; font-weight: 700;"><?php echo htmlspecialchars($user_data['shift_hour'] ?? '09:00-18:00'); ?></span>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="request_shift_change" value="1">
                    <div class="form-group">
                        <label class="form-label">Request New Shift Hour</label>
                        <select name="requested_shift" class="form-input custom-select" required>
                            <option value="08:00-17:00">08:00-17:00</option>
                            <option value="08:30-17:30">08:30-17:30</option>
                            <option value="09:00-18:00">09:00-18:00</option>
                        </select>
                    </div>
                    <?php if ($is_friday): ?>
                    <button type="submit" class="btn-submit btn-outline">Request Change</button>
                    <?php else: ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.5; cursor:not-allowed;">Request Change</button>
                    <p style="font-size:12px; color:#ef4444; margin-top:8px;">⚠️ Change requests can only be submitted on <strong>Fridays</strong>.</p>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <div class="profile-card">
                <div class="card-title">Change Password</div>
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="old_password" class="form-input" required placeholder="Enter current password">
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required placeholder="Enter new password" minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required placeholder="Confirm new password" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            </div>
            
            </div> <!-- End of profile-grid -->

        </div>
    </main>

</body>
</html>
