<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$status_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $request_id = (int)$_POST['request_id'];
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $action = $_POST['action'];

    if ($action == 'approve') {
        mysqli_query($conn, "UPDATE break_override_requests SET status = 'approved' WHERE id = $request_id");
        $status_message = "Override request approved successfully.";
        
        $msg = "Your lunchtime override request was approved by your manager.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    } elseif ($action == 'reject') {
        mysqli_query($conn, "UPDATE break_override_requests SET status = 'rejected' WHERE id = $request_id");
        $status_message = "Override request rejected.";
        
        $msg = "Your lunchtime override request was rejected by your manager.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$msg')");
    }
}

$gl_filter = $role === 'gl' ? "WHERE e.group_leader_id = '{$_SESSION['emp_id']}'" : "";
$requests_sql = "
    SELECT r.*, e.name, e.employment_type 
    FROM break_override_requests r 
    JOIN employees e ON r.emp_id = e.emp_id 
    $gl_filter
    ORDER BY CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, r.created_at DESC
";
$requests_result = mysqli_query($conn, $requests_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Manage Overrides</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; border-right: none; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; text-transform: capitalize; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .table-container { background: transparent; padding: 10px 0; }
        table { width: 100%; border-collapse: separate; border-spacing: 0 16px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; }
        .bg-pending { background-color: #fef3c7; color: #d97706; }
        .bg-approved { background-color: #ecfdf5; color: #059669; }
        .bg-rejected { background-color: #fef2f2; color: #ef4444; }

        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; }
        .btn-approve { background: #10b981; color: white; margin-right: 8px; }
        .btn-approve:hover { background: #059669; }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; }
        
        .toast-success { background: #ecfdf5; color: #059669; padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #a7f3d0; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Override Requests</div>
        </header>

        <div class="content">
            <?php if ($status_message): ?>
                <div class="toast-success"><?php echo $status_message; ?></div>
            <?php endif; ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Reason</th>
                            <th>Duration (Mins)</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($requests_result) > 0) {
                            while ($row = mysqli_fetch_assoc($requests_result)) {
                                $status_class = 'bg-' . $row['status'];
                                $status_text = ucfirst($row['status']);
                                $date_display = date('M d, Y h:i A', strtotime($row['created_at']));
                                
                                echo "<tr>";
                                echo "<td style='color: #64748b; font-size: 13px; font-weight: 600;'>" . $date_display . "</td>";
                                echo "<td style='font-weight: 700; color: #0f172a;'>" . htmlspecialchars($row['name']) . "<br><span style='font-size: 12px; color: #64748b; font-weight: 600;'>#" . htmlspecialchars($row['emp_id']) . "</span></td>";
                                echo "<td><span style='background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 600;'>" . htmlspecialchars($row['reason']) . "</span></td>";
                                echo "<td><span style='background: #eff6ff; color: #1d4ed8; padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 700;'>" . htmlspecialchars($row['duration']) . " mins</span></td>";
                                echo "<td><span class='badge $status_class'>$status_text</span></td>";
                                
                                echo "<td>";
                                if ($row['status'] == 'pending') {
                                    echo "<form method='POST' action='' style='display:inline;'>
                                            <input type='hidden' name='request_id' value='{$row['id']}'>
                                            <input type='hidden' name='emp_id' value='{$row['emp_id']}'>
                                            <button type='submit' name='action' value='approve' class='btn btn-approve'>Approve</button>
                                            <button type='submit' name='action' value='reject' class='btn btn-reject'>Reject</button>
                                          </form>";
                                } else {
                                    echo "<span style='color: #94a3b8; font-size: 13px; font-weight: 600;'>Processed</span>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' style='text-align: center; padding: 60px; color: #94a3b8; font-weight: 600;'>No override requests found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
