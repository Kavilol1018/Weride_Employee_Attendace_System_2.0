<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$current_emp_id = $_SESSION['emp_id'];

// BUILD QUERY
$history_sql = "
    SELECT 
        id, leave_type, start_date, start_time, end_date, end_time, duration, reason, application_date, status, rejection_reason
    FROM leave_requests
    WHERE emp_id = '$current_emp_id'
    ORDER BY application_date DESC, id DESC
";
$history_result = mysqli_query($conn, $history_sql);

// STATS
$total_leaves_query = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM leave_requests WHERE emp_id = '$current_emp_id'");
$total_leaves = mysqli_fetch_assoc($total_leaves_query)['cnt'];

$approved_leaves_query = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM leave_requests WHERE emp_id = '$current_emp_id' AND status='approved'");
$approved_leaves = mysqli_fetch_assoc($approved_leaves_query)['cnt'];

$pending_leaves_query = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM leave_requests WHERE emp_id = '$current_emp_id' AND status='pending'");
$pending_leaves = mysqli_fetch_assoc($pending_leaves_query)['cnt'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - My Leave History</title>
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

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px; }
        
        .table-container { background: transparent; padding: 10px 0; }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .table-title { font-weight: 700; color: #0f172a; font-size: 18px; }
        .result-count { background: #f1f5f9; padding: 6px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; color: #64748b; }

        table { width: 100%; border-collapse: separate; border-spacing: 0 14px; text-align: left; }
        th { padding: 0 20px 10px 20px; color: #94a3b8; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 18px 20px; background: #ffffff; border-top: 1px solid rgba(226,232,240,0.6); border-bottom: 1px solid rgba(226,232,240,0.6); transition: all 0.3s ease; }
        td:first-child { border-radius: 20px 0 0 20px; border-left: 1px solid rgba(226,232,240,0.6); box-shadow: -10px 10px 20px -10px rgba(0,0,0,0.04); }
        td:last-child { border-radius: 0 20px 20px 0; border-right: 1px solid rgba(226,232,240,0.6); box-shadow: 10px 10px 20px -10px rgba(0,0,0,0.04); }
        tr:hover td { transform: translateY(-3px); box-shadow: 0 15px 30px -10px rgba(0,0,0,0.08); }

        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; display: inline-block; text-transform: capitalize; }
        .badge-approved { background-color: #ecfdf5; color: #059669; }
        .badge-pending { background-color: #fffbeb; color: #d97706; }
        .badge-rejected { background-color: #fef2f2; color: #ef4444; }
        
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
        .empty-state-text { font-size: 16px; font-weight: 600; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">My Leave History</div>
        </header>

        <div class="content">
            <div class="stats-grid">
                <div class="premium-stat-card">
                    <div class="icon-box" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Total Applications</div>
                        <div class="info-value"><?php echo $total_leaves; ?></div>
                    </div>
                </div>

                <div class="premium-stat-card">
                    <div class="icon-box" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Approved</div>
                        <div class="info-value"><?php echo $approved_leaves; ?></div>
                    </div>
                </div>
                
                <div class="premium-stat-card">
                    <div class="icon-box" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div class="info-area">
                        <div class="info-title">Pending</div>
                        <div class="info-value"><?php echo $pending_leaves; ?></div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">Leave History Logs</div>
                    <div class="result-count">Showing <?php echo mysqli_num_rows($history_result); ?> records</div>
                </div>

                <?php if (mysqli_num_rows($history_result) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date Applied</th>
                                <th>Type</th>
                                <th>Period</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($history_result)): ?>
                            <?php 
                                $badge_class = 'badge-pending';
                                if ($row['status'] == 'approved') $badge_class = 'badge-approved';
                                if ($row['status'] == 'rejected') $badge_class = 'badge-rejected';
                            ?>
                            <tr>
                                <td class="text-slate-900" style="font-weight: 600; "><?php echo date('M d, Y', strtotime($row['application_date'])); ?></td>
                                <td style="font-weight: 500; color: #3b82f6;"><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                <td>
                                    <?php echo date('M d, Y h:i A', strtotime($row['start_date'] . ' ' . $row['start_time'])); ?>
                                    <br>
                                    <span style="color: #94a3b8; font-size: 12px;">to</span>
                                    <br>
                                    <?php echo date('M d, Y h:i A', strtotime($row['end_date'] . ' ' . $row['end_time'])); ?>
                                </td>
                                <td class="text-slate-600" style="font-weight: 600; "><?php echo htmlspecialchars($row['duration']); ?> mins</td>
                                <td>
                                    <div class="text-slate-500" style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; " title="<?php echo htmlspecialchars($row['reason']); ?>">
                                        <?php echo htmlspecialchars($row['reason']); ?>
                                    </div>
                                    <?php if ($row['status'] == 'rejected' && !empty($row['rejection_reason'])): ?>
                                        <div class="bg-red-50 text-red-500" style="margin-top: 6px; font-size: 11px;  font-weight: 600;  padding: 4px 8px; border-radius: 6px; max-width: 250px;">
                                            <strong style="color: #991b1b;">Manager Note:</strong> <?php echo htmlspecialchars($row['rejection_reason']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <div class="empty-state-text">No leave requests found.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

</body>
</html>
