<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$current_employee_id = $_SESSION['emp_id'];
$role_slug = $_SESSION['role'];

// Handle CSV Export for Admin
if (isset($_POST['export_leaves']) && $role_slug !== 'employee') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leave_applications.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Employee ID', 'Employee Name', 'Leave Type', 'Start Date', 'Start Time', 'End Date', 'End Time', 'Duration', 'Reason', 'Application Date', 'Status']);
    
    $query = mysqli_query($conn, "SELECT r.*, e.name as emp_name FROM leave_requests r JOIN employees e ON r.emp_id = e.emp_id ORDER BY r.id DESC");
    while ($row = mysqli_fetch_assoc($query)) {
        fputcsv($output, [
            $row['id'], $row['emp_id'], $row['emp_name'], $row['leave_type'], 
            $row['start_date'], $row['start_time'], $row['end_date'], $row['end_time'], 
            $row['duration'], $row['reason'], $row['application_date'], $row['status']
        ]);
    }
    fclose($output);
    exit();
}

// Fetch all employees for dropdown
$all_emp_query = mysqli_query($conn, "SELECT emp_id, name FROM employees ORDER BY name ASC");
$all_employees = [];
while ($row = mysqli_fetch_assoc($all_emp_query)) {
    $all_employees[] = $row;
}

$msg = "";
$error = "";

// Handle Leave Approval/Rejection on this page
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['leave_action']) && $role_slug !== 'employee') {
    $leave_id = (int)$_POST['leave_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? mysqli_real_escape_string($conn, $_POST['end_date']) : $start_date;

    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE leave_requests SET status = 'approved' WHERE id = $leave_id");
        
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        $interval = $start_dt->diff($end_dt);
        $days = $interval->days + 1;
        
        $abbr = 'L';
        $lt = strtoupper($leave_type);
        if (strpos($lt, 'HALF') !== false) { $abbr = 'HD'; $days = 0.5; }
        elseif (strpos($lt, 'EARLY') !== false) { $abbr = 'EARLY'; $days = 0; }
        elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
        elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
        
        elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }

        $current_dt = clone $start_dt;
        while ($current_dt <= $end_dt) {
            $date_str = $current_dt->format('Y-m-d');
            mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$abbr') ON DUPLICATE KEY UPDATE status = '$abbr'");
            $current_dt->modify('+1 day');
        }

        if ($abbr === 'AL') {
            mysqli_query($conn, "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE emp_id = '$emp_id'");
        } elseif ($abbr === 'SL' || $abbr === 'UL') {
            mysqli_query($conn, "UPDATE employees SET sick_leave_balance = GREATEST(0, sick_leave_balance - $days) WHERE emp_id = '$emp_id'");
        } elseif ($abbr === 'CL') {
            
        }

        $msg = "Leave request approved successfully.";
    } elseif ($action == 'reject') {
        $rejection_reason = mysqli_real_escape_string($conn, $_POST['rejection_reason'] ?? '');
        // Check if table has rejection_reason column first, we might need to alter it if missing, but manage_requests.php uses it so it exists.
        mysqli_query($conn, "UPDATE leave_requests SET status = 'rejected', rejection_reason = '$rejection_reason' WHERE id = $leave_id");
        $msg = "Leave request rejected.";
    }
}

// Filtering for Admins
$start_date = isset($_GET['start_date']) ? mysqli_real_escape_string($conn, $_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? mysqli_real_escape_string($conn, $_GET['end_date']) : '';
$selected_employees = isset($_GET['employees']) ? (array)$_GET['employees'] : [];

// Fetch past leave requests
if ($role_slug === 'employee') {
    $history_query = mysqli_query($conn, "SELECT * FROM leave_requests WHERE emp_id='$current_employee_id' ORDER BY id DESC LIMIT 20");
} else {
    $where_clauses = ["r.status = 'pending'"];
    if (!empty($start_date)) {
        $where_clauses[] = "DATE(r.start_date) >= '$start_date'";
    }
    if (!empty($end_date)) {
        $where_clauses[] = "DATE(r.start_date) <= '$end_date'";
    }
    if (!empty($selected_employees)) {
        $safe_emps = array_map(function($emp) use ($conn) {
            return "'" . mysqli_real_escape_string($conn, $emp) . "'";
        }, $selected_employees);
        $where_clauses[] = "r.emp_id IN (" . implode(',', $safe_emps) . ")";
    }
    $where_sql = implode(' AND ', $where_clauses);
    $history_query = mysqli_query($conn, "SELECT r.*, e.name as emp_name FROM leave_requests r JOIN employees e ON r.emp_id=e.emp_id WHERE $where_sql ORDER BY r.id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Application - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dark_mode.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .toast { padding: 15px 20px; border-radius: 8px; font-weight: 600; margin-bottom: 25px; }
        .toast-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .toast-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .leave-form-container {
            max-width: 650px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .leave-form-title {
            text-align: center;
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 20px;
            color: #0f172a;
        }

        .leave-instructions {
            font-size: 13px;
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 30px;
        }
        .leave-instructions ol {
            padding-left: 20px;
            margin: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        .form-label span.req { color: #ef4444; }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #0f172a;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            border-color: #3b82f6;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }

        /* File dropzone */
        .file-dropzone {
            border: 1px dashed #cbd5e1;
            padding: 40px 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            background-color: #f8fafc;
            transition: 0.2s;
            position: relative;
        }
        .file-dropzone:hover {
            background-color: #f1f5f9;
        }
        .file-dropzone input[type="file"] {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .file-dropzone-text {
            color: #64748b;
            font-size: 14px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background-color: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .btn-submit:hover {
            background-color: #2563eb;
        }

        /* Admin / History Section */
        .history-container {
            width: 100%;
            padding: 0;
        }

        .calendar-wrapper {
            background: var(--bg-card, #ffffff);
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color, rgba(226, 232, 240, 0.5));
            margin-bottom: 24px;
            max-width: 100%;
        }
        .btn-export {
            padding: 8px 16px;
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-export:hover { background-color: #059669; }

        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; opacity: 0.8; }
        .empty-state-text { font-size: 16px; font-weight: 600; }

        .content { padding: 0 40px 40px 40px; }

        .table-container { background: transparent; padding: 10px 0; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 14px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 18px 20px; background: #ffffff; border-top: 1px solid rgba(226, 232, 240, 0.6); border-bottom: 1px solid rgba(226, 232, 240, 0.6); transition: all 0.3s ease; vertical-align: middle; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226, 232, 240, 0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226, 232, 240, 0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); }
        
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #dcfce7; color: #166534; }
        .badge-rejected { background: #fee2e2; color: #991b1b; }
        
        .mc-link {
            color: #3b82f6;
            text-decoration: none;
        }
        .mc-link:hover { text-decoration: underline; }

    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php if($msg): ?><div class="toast toast-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if($error): ?><div class="toast toast-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>




        <!-- History & Admin View -->
        <header class="topbar">
            <div class="page-title"><?= $role_slug === 'employee' ? 'My Leave History' : 'All Leave Applications' ?></div>
            <?php if ($role_slug !== 'employee'): ?>
            <form method="POST" action="">
                <button type="submit" name="export_leaves" class="btn-export bg-transparent text-slate-600" style="  border: 1px solid #cbd5e1; padding: 10px 16px; font-size: 14px; text-decoration: none; border-radius: 8px; font-weight: 600; box-shadow: none;">Export to CSV</button>
            </form>
            <?php endif; ?>
        </header>

        <div class="content">
            <div class="history-container">
            <?php if ($role_slug !== 'employee'): ?>
            <div class="calendar-wrapper no-print">
                <form method="GET" action="">
                    <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                        <div>
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                        </div>
                        <div>
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                        </div>
                        <div style="flex: 1; min-width: 250px;">
                            <label style="display:block; font-weight: 600; margin-bottom: 8px;">Filter by Employee</label>
                            <select name="employees[]" id="employee_select" multiple>
                                <?php foreach ($all_employees as $emp): ?>
                                    <?php $selected = in_array($emp['emp_id'], $selected_employees) ? 'selected' : ''; ?>
                                    <option value="<?php echo htmlspecialchars($emp['emp_id']); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($emp['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="padding-top: 29px;">
                            <button type="submit" class="bg-blue-600 text-white" style="  border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 14px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">Apply Filter</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="bg-transparent" style=" padding: 10px 0;">
            <table>
                <thead>
                    <tr>
                        <?php if ($role_slug !== 'employee'): ?>
                        <th>Employee</th>
                        <?php endif; ?>
                        <th>Type</th>
                        <th>Duration</th>
                        <th>Start DateTime</th>
                        <th>End DateTime</th>
                        <th>App Date</th>
                        <th>MC</th>
                        <th>Status</th>
                        <?php if ($role_slug !== 'employee'): ?>
                        <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($history_query) > 0): ?>
                    <?php while($row = mysqli_fetch_assoc($history_query)): ?>
                    <tr>
                        <?php if ($role_slug !== 'employee'): ?>
                        <td><strong><?= htmlspecialchars($row['emp_name']) ?></strong><br><span style="font-size:12px; color:#9ca3af;"><?= $row['emp_id'] ?></span></td>
                        <?php endif; ?>
                        <td><?= htmlspecialchars($row['leave_type']) ?></td>
                        <td><?= htmlspecialchars($row['duration']) ?></td>
                        <td>
                            <?= date('d M Y', strtotime($row['start_date'])) ?><br>
                            <span style="font-size:12px; color:#9ca3af;"><?= date('h:i A', strtotime($row['start_time'] ?? '00:00:00')) ?></span>
                        </td>
                        <td>
                            <?= date('d M Y', strtotime($row['end_date'])) ?><br>
                            <span style="font-size:12px; color:#9ca3af;"><?= date('h:i A', strtotime($row['end_time'] ?? '00:00:00')) ?></span>
                        </td>
                        <td><?= date('d M Y', strtotime($row['application_date'] ?? $row['created_at'])) ?></td>
                        <td>
                            <?php if (!empty($row['mc_attachment'])): ?>
                                <a href="<?= htmlspecialchars($row['mc_attachment']) ?>" target="_blank" class="mc-link">View File</a>
                            <?php else: ?>
                                <span style="color:#6b7280;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= strtolower($row['status']) ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <?php if ($role_slug !== 'employee'): ?>
                        <td>
                            <?php if ($row['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;" class="action-form">
                                <input type="hidden" name="leave_action" value="1">
                                <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
                                <input type="hidden" name="emp_id" value="<?= htmlspecialchars($row['emp_id']) ?>">
                                <input type="hidden" name="leave_type" value="<?= htmlspecialchars($row['leave_type']) ?>">
                                <input type="hidden" name="start_date" value="<?= $row['start_date'] ?>">
                                <input type="hidden" name="end_date" value="<?= $row['end_date'] ?>">
                                
                                <button type="submit" name="action" value="approve" class="btn" style="padding: 4px 8px; font-size: 12px; min-width: auto; background-color: #10b981;">Accept</button>
                                <button type="button" class="btn btn-outline text-red-500" style="padding: 4px 8px; font-size: 12px; min-width: auto;  border-" onclick="rejectLeave(this)">Reject</button>
                            </form>
                            <?php else: ?>
                            <span style="color:#9ca3af; font-size: 12px;">Processed</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="<?= $role_slug !== 'employee' ? '9' : '7' ?>">
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <div class="empty-state-text">No pending leave requests found.</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const element = document.getElementById('employee_select');
            if (element) {
                const choices = new Choices(element, {
                    removeItemButton: true,
                    placeholderValue: 'Select employees...',
                    searchPlaceholderValue: 'Search name...',
                    itemSelectText: '',
                });
            }
        });
        
        function rejectLeave(btn) {
            let reason = prompt("Please enter the reason for rejection:");
            if (reason !== null) {
                let form = btn.closest('form');
                let reasonInput = document.createElement('input');
                reasonInput.type = 'hidden';
                reasonInput.name = 'rejection_reason';
                reasonInput.value = reason;
                form.appendChild(reasonInput);
                
                let actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reject';
                form.appendChild(actionInput);
                
                form.submit();
            }
        }
    </script>
    <script src="assets/js/theme_toggle.js"></script>
</body>
</html>
