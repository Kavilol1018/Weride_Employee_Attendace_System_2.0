<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

// Pagination and Filtering
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';

$where_clauses = [];
if (!empty($search)) {
    $where_clauses[] = "(al.emp_id LIKE '%$search%' OR e.name LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where_clauses[] = "al.status = '$status_filter'";
}
if (!empty($date_filter)) {
    $where_clauses[] = "DATE(al.login_time) = '$date_filter'";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "SELECT al.*, e.name 
          FROM access_logs al 
          LEFT JOIN employees e ON al.emp_id = e.emp_id 
          $where_sql 
          ORDER BY al.login_time DESC LIMIT 500";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Access Logs</title>
    <link rel="stylesheet" href="assets/css/dark_mode.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #334155; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }
        .controls-container { background: white; padding: 20px; border-radius: 16px; margin-bottom: 24px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .filter-input { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
        .btn-primary { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary:hover { background: #2563eb; }
        
        .table-container { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 16px 24px; background: #f8fafc; color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: 14px; }
        tr:hover td { background-color: #f1f5f9; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge.success { background: #dcfce7; color: #166534; }
        .badge.failed { background: #fee2e2; color: #991b1b; }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="topbar">
            <h1 class="page-title">Access Logs</h1>
        </div>

        <div class="content">
            <div class="controls-container">
                <form method="GET" action="" style="display: flex; gap: 15px; width: 100%; flex-wrap: wrap;">
                    <input type="text" name="search" class="filter-input" placeholder="Search Emp ID or Name" value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status" class="filter-input">
                        <option value="">All Statuses</option>
                        <option value="Success" <?php if($status_filter == 'Success') echo 'selected'; ?>>Success</option>
                        <option value="Failed" <?php if($status_filter == 'Failed') echo 'selected'; ?>>Failed</option>
                    </select>
                    <input type="date" name="date" class="filter-input" value="<?php echo htmlspecialchars($date_filter); ?>">
                    <button type="submit" class="btn-primary">Filter</button>
                    <a href="admin_access_logs.php" class="btn-primary" style="background: #94a3b8;">Clear</a>
                </form>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Emp ID</th>
                            <th>Name</th>
                            <th>Login Time</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['emp_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($row['login_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                    <td>
                                        <span class="badge <?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-slate-500" style="text-align: center; padding: 30px; ">No access logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="assets/js/theme_toggle.js"></script>
</body>
</html>
