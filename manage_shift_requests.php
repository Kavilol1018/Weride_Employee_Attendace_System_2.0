<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$status_message = "";

// Handle Shift Change Requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['shift_change_action'])) {
    $request_id = (int)$_POST['request_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];
    $requested_shift = mysqli_real_escape_string($conn, $_POST['requested_shift']);

    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE shift_change_requests SET status = 'approved' WHERE id = $request_id");
        mysqli_query($conn, "UPDATE employees SET shift_hour = '$requested_shift' WHERE emp_id = '$emp_id'");
        $status_message = "Shift change request approved successfully.";
        
        $msg = "Your request to change your shift hour to $requested_shift was approved.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    } elseif ($action == 'reject') {
        mysqli_query($conn, "UPDATE shift_change_requests SET status = 'rejected' WHERE id = $request_id");
        $status_message = "Shift change request rejected.";
        
        $msg = "Your request to change your shift hour to $requested_shift was rejected.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    }
}

$gl_filter = $role === 'gl' ? "WHERE e.group_leader_id = '{$_SESSION['emp_id']}'" : "";

// FETCH SHIFT REQUESTS
$shift_requests_sql = "
    SELECT r.*, e.name, e.employment_type 
    FROM shift_change_requests r 
    JOIN employees e ON r.emp_id = e.emp_id 
    $gl_filter
    ORDER BY CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, r.created_at DESC
";
$shift_requests_result = mysqli_query($conn, $shift_requests_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Manage Shift Changes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #334155; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; background-color: #f8fafc; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .section-header { font-size: 20px; color: #0f172a; font-weight: 700; margin-bottom: 10px; margin-top: 10px; }
        
        .table-container { background: transparent; padding: 10px 0; margin-bottom: 30px; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; font-size: 14px; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; text-transform: uppercase; }
        .bg-pending { background-color: #fef3c7; color: #d97706; }
        .bg-approved { background-color: #ecfdf5; color: #059669; }
        .bg-rejected { background-color: #fef2f2; color: #ef4444; }

        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; }
        .btn-approve { background: #10b981; color: white; margin-right: 8px; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        
        .toast-success { background: #ecfdf5; color: #059669; padding: 16px 20px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #a7f3d0; }
        
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .main-content, body.dark-mode .topbar { background-color: #0f172a; }
        body.dark-mode .page-title, body.dark-mode .section-header { color: #f8fafc; }
        body.dark-mode td { background-color: #1e293b; border-color: #334155; }
        body.dark-mode td:first-child { border-left-color: #334155; }
        body.dark-mode td:last-child { border-right-color: #334155; }
        body.dark-mode .badge.bg-pending { background-color: #78350f; color: #fcd34d; }
        body.dark-mode .badge.bg-approved { background-color: #064e3b; color: #6ee7b7; }
        body.dark-mode .badge.bg-rejected { background-color: #7f1d1d; color: #fca5a5; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Shift Hour Change Requests</div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="toast-success">
                    ✅ <?php echo htmlspecialchars($status_message); ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Current Shift</th>
                            <th>Requested Shift</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($shift_requests_result && mysqli_num_rows($shift_requests_result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($shift_requests_result)): ?>
                                <tr>
                                    <td><b class="text-slate-900" style=" font-size: 15px;"><?php echo htmlspecialchars($row['name']); ?></b><br><span class="text-slate-500" style=" font-weight: 600; font-size: 12px;">#<?php echo htmlspecialchars($row['emp_id']); ?></span></td>
                                    <td class="text-slate-500" style=" font-weight: 600;"><?php echo htmlspecialchars($row['current_shift']); ?></td>
                                    <td class="text-slate-900" style=" font-weight: 700;"><?php echo htmlspecialchars($row['requested_shift']); ?></td>
                                    <td><span class="badge bg-<?php echo strtolower($row['status']); ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="shift_change_action" value="1">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="requested_shift" value="<?php echo $row['requested_shift']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-approve">Approve</button>
                                            </form>
                                            <form method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="shift_change_action" value="1">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="emp_id" value="<?php echo $row['emp_id']; ?>">
                                                <input type="hidden" name="requested_shift" value="<?php echo $row['requested_shift']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-reject">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 13px; font-weight: 600;">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-slate-500" style="text-align:center;  padding:20px; font-weight: 600;">No shift change requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</body>
</html>
