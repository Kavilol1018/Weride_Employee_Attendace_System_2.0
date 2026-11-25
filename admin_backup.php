<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr'])) {
    header("Location: index.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

$role_slug = $_SESSION['role'];
$current_page = 'admin_backup.php';

// Helper function to export full database SQL
function export_database_sql($conn, $output_handle) {
    fwrite($output_handle, "-- ========================================================\n");
    fwrite($output_handle, "-- WeRide Employee Attendance System 2.0 - Database Backup\n");
    fwrite($output_handle, "-- Backup Generated At: " . date('Y-m-d H:i:s') . " (Malaysia Time)\n");
    fwrite($output_handle, "-- Server Host: " . mysqli_get_host_info($conn) . "\n");
    fwrite($output_handle, "-- MySQL Version: " . mysqli_get_server_info($conn) . "\n");
    fwrite($output_handle, "-- Created by Admin / HR via 1-Click Backup Tool\n");
    fwrite($output_handle, "-- ========================================================\n\n");
    fwrite($output_handle, "SET NAMES utf8mb4;\n");
    fwrite($output_handle, "SET FOREIGN_KEY_CHECKS = 0;\n");
    fwrite($output_handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
    fwrite($output_handle, "SET time_zone = '+08:00';\n\n");

    $tables = [];
    $res = mysqli_query($conn, "SHOW TABLES");
    if ($res) {
        while ($row = mysqli_fetch_row($res)) {
            $tables[] = $row[0];
        }
    }

    foreach ($tables as $table) {
        fwrite($output_handle, "-- --------------------------------------------------------\n");
        fwrite($output_handle, "-- Table structure for `$table`\n");
        fwrite($output_handle, "-- --------------------------------------------------------\n");
        fwrite($output_handle, "DROP TABLE IF EXISTS `$table`;\n");

        $create_res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        if ($create_res && $create_row = mysqli_fetch_row($create_res)) {
            fwrite($output_handle, $create_row[1] . ";\n\n");
        }

        // Dump table data
        fwrite($output_handle, "-- Dumping data for `$table`\n");
        $data_res = mysqli_query($conn, "SELECT * FROM `$table`", MYSQLI_USE_RESULT);
        if ($data_res) {
            $num_fields = mysqli_num_fields($data_res);
            $batch_values = [];
            $batch_size = 200;

            while ($row = mysqli_fetch_row($data_res)) {
                $escaped = [];
                for ($i = 0; $i < $num_fields; $i++) {
                    if ($row[$i] === null) {
                        $escaped[] = "NULL";
                    } else {
                        $escaped[] = "'" . mysqli_real_escape_string($conn, $row[$i]) . "'";
                    }
                }
                $batch_values[] = "(" . implode(", ", $escaped) . ")";

                if (count($batch_values) >= $batch_size) {
                    fwrite($output_handle, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch_values) . ";\n");
                    $batch_values = [];
                }
            }
            if (!empty($batch_values)) {
                fwrite($output_handle, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch_values) . ";\n");
            }
            fwrite($output_handle, "\n");
        }
    }

    fwrite($output_handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
    fwrite($output_handle, "-- ========================================================\n");
    fwrite($output_handle, "-- Database Backup Complete at " . date('Y-m-d H:i:s') . "\n");
    fwrite($output_handle, "-- ========================================================\n");
}

// Handle Download Action
if (isset($_GET['action']) && in_array($_GET['action'], ['download_sql', 'download_gz'])) {
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die("Security error: Invalid CSRF token.");
    }

    $is_gz = ($_GET['action'] === 'download_gz' && function_exists('gzencode'));
    $base_name = 'weride_backup_' . date('Y-m-d_His');
    $filename = $base_name . ($is_gz ? '.sql.gz' : '.sql');

    // Clean output buffer before sending headers
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ($is_gz ? 'application/gzip' : 'application/sql; charset=utf-8'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    if ($is_gz) {
        $temp_buffer = fopen('php://temp', 'r+');
        export_database_sql($conn, $temp_buffer);
        rewind($temp_buffer);
        $raw_sql = stream_get_contents($temp_buffer);
        fclose($temp_buffer);
        echo gzencode($raw_sql, 9);
    } else {
        $out = fopen('php://output', 'w');
        export_database_sql($conn, $out);
        fclose($out);
    }
    exit();
}

// Query Table Statistics
$table_stats = [];
$total_rows = 0;
$total_bytes = 0;
$status_q = mysqli_query($conn, "SHOW TABLE STATUS");
if ($status_q) {
    while ($row = mysqli_fetch_assoc($status_q)) {
        $bytes = (float)($row['Data_length'] + $row['Index_length']);
        $total_bytes += $bytes;
        $total_rows += (int)$row['Rows'];
        $table_stats[] = [
            'name' => $row['Name'],
            'rows' => (int)$row['Rows'],
            'bytes' => $bytes,
            'engine' => $row['Engine'] ?? 'InnoDB',
            'collation' => $row['Collation'] ?? 'utf8mb4_general_ci',
            'update_time' => $row['Update_time'] ?? '-'
        ];
    }
}
$total_mb = round($total_bytes / (1024 * 1024), 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Database Backup &amp; Recovery - WeRide</title>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <style>
        body { 
            margin: 0; 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background: var(--bg-color, #f8fafc); 
            color: var(--text-color, #1e293b); 
            min-height: 100vh; 
            transition: background 0.2s, color 0.2s; 
        }
        .main-content { 
            flex: 1; 
            padding: 30px; 
            overflow-y: auto; 
            -webkit-overflow-scrolling: touch;
            display: flex; 
            flex-direction: column; 
            min-height: 0;
            box-sizing: border-box;
        }
        @media (min-width: 1025px) {
            .main-content {
                height: 100vh;
                max-height: 100vh;
            }
        }
        @media (max-width: 1024px) {
            body {
                height: auto !important;
                overflow-y: auto !important;
            }
            .main-content {
                height: auto !important;
                overflow-y: visible !important;
                padding: max(20px, calc(16px + env(safe-area-inset-top, 0px))) 16px calc(100px + env(safe-area-inset-bottom, 0px)) 16px !important;
            }
        }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .header h1 { margin: 0; font-size: 24px; color: var(--text-color, #1e293b); display: flex; align-items: center; gap: 10px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 25px; }
        .stat-card { background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 14px; padding: 20px 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); display: flex; align-items: center; gap: 16px; }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .stat-info { display: flex; flex-direction: column; }
        .stat-label { font-size: 12px; font-weight: 700; color: var(--p-color, #64748b); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-value { font-size: 22px; font-weight: 800; color: var(--heading-color, #0f172a); }

        .card { background: var(--card-bg, white); border-radius: 14px; padding: 25px; border: 1px solid var(--border-color, #e2e8f0); box-shadow: 0 2px 5px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .card-title { margin: 0 0 10px 0; font-size: 18px; font-weight: 700; color: var(--heading-color, #0f172a); display: flex; align-items: center; gap: 8px; }
        .card-desc { color: var(--p-color, #64748b); font-size: 14px; margin-top: 0; margin-bottom: 20px; line-height: 1.5; }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; border: none; transition: 0.15s ease-in-out; }
        .btn-success { background: #059669; color: white; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25); }
        .btn-success:hover { background: #047857; transform: translateY(-1px); }
        .btn-primary { background: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-outline { background: transparent; color: var(--text-color, #1e293b); border: 1px solid var(--border-color, #cbd5e1); }
        .btn-outline:hover { background: var(--border-color, #f1f5f9); }

        .button-group { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 15px; }

        .info-callout { background: rgba(59, 130, 246, 0.08); border-left: 4px solid #3b82f6; padding: 14px 18px; border-radius: 0 10px 10px 0; margin-top: 20px; font-size: 13.5px; color: var(--text-color, #1e293b); line-height: 1.6; }
        .info-callout ul { margin: 6px 0 0 0; padding-left: 20px; }

        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
        th { padding: 12px 16px; background: rgba(0,0,0,0.02); color: var(--p-color, #64748b); font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color, #e2e8f0); }
        td { padding: 12px 16px; border-bottom: 1px solid var(--border-color, #e2e8f0); color: var(--text-color, #1e293b); }
        tr:hover td { background: rgba(0,0,0,0.015); }
        .table-responsive { overflow-x: auto; max-height: 480px; overflow-y: auto; border-radius: 8px; border: 1px solid var(--border-color, #e2e8f0); }

        .badge-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: #e0f2fe; color: #0369a1; }

        /* Dark mode */
        body.dark-mode {
            --bg-color: #0f172a;
            --text-color: #f8fafc;
            --card-bg: #1e293b;
            --heading-color: #f1f5f9;
            --p-color: #94a3b8;
            --border-color: #334155;
            color-scheme: dark;
        }
        body.dark-mode .info-callout {
            background: rgba(59, 130, 246, 0.15);
            color: #cbd5e1;
        }
        body.dark-mode th {
            background: rgba(255,255,255,0.03);
            border-bottom-color: #334155;
        }
        body.dark-mode td {
            border-bottom-color: #334155;
        }
        body.dark-mode .badge-pill {
            background: rgba(3, 105, 161, 0.3);
            color: #38bdf8;
        }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="header">
            <div>
                <h1>
                    <span>💾</span> Database Backup &amp; Recovery
                </h1>
                <div style="font-size: 13px; color: var(--p-color, #64748b); margin-top: 4px;">
                    Instant 1-Click MySQL dump generator for emergency snapshots and payroll archives.
                </div>
            </div>
            <div>
                <a href="<?php echo htmlspecialchars($role_slug); ?>_dashboard.php" class="btn btn-outline" style="padding: 8px 16px; font-size: 13px;">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #059669;">📦</div>
                <div class="stat-info">
                    <span class="stat-label">Total Tables</span>
                    <span class="stat-value"><?php echo count($table_stats); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">📑</div>
                <div class="stat-info">
                    <span class="stat-label">Total Records</span>
                    <span class="stat-value"><?php echo number_format($total_rows); ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #d97706;">💾</div>
                <div class="stat-info">
                    <span class="stat-label">Database Size</span>
                    <span class="stat-value"><?php echo $total_mb; ?> MB</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #f3e8ff; color: #7c3aed;">⚡</div>
                <div class="stat-info">
                    <span class="stat-label">Storage Engine</span>
                    <span class="stat-value" style="font-size: 18px;">InnoDB / UTF8MB4</span>
                </div>
            </div>
        </div>

        <!-- 1-CLICK BACKUP CARD -->
        <div class="card">
            <h2 class="card-title">
                <span>⚡</span> 1-Click Instant Backup
            </h2>
            <p class="card-desc">
                Clicking the buttons below generates a complete SQL dump of all database structures (`CREATE TABLE`) and all records (`INSERT INTO`). The file is streamed directly to your browser download folder in under 1 second.
            </p>

            <div class="button-group">
                <a href="admin_backup.php?action=download_sql&token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="btn btn-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download SQL Dump (.sql)
                </a>

                <?php if (function_exists('gzencode')): ?>
                <a href="admin_backup.php?action=download_gz&token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download Compressed (.sql.gz)
                </a>
                <?php endif; ?>
            </div>

            <div class="info-callout">
                <strong>💡 Backup Specifications &amp; Compatibility:</strong>
                <ul>
                    <li><strong>Full Scope:</strong> Backs up employee profiles, leave balances, attendance logs, alerts, break records, and system settings.</li>
                    <li><strong>Compatibility:</strong> Fully compatible with phpMyAdmin (Hostinger Import), MySQL Workbench, and command line restores.</li>
                    <li><strong>Safety:</strong> Uses `DROP TABLE IF EXISTS` and disables foreign key checks during import to ensure zero import conflicts.</li>
                </ul>
            </div>
        </div>

        <!-- TABLE INVENTORY CARD -->
        <div class="card">
            <h2 class="card-title">
                <span>📋</span> Table Inventory &amp; Row Counts
            </h2>
            <p class="card-desc">
                Current tables included in every generated database backup:
            </p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Table Name</th>
                            <th>Records</th>
                            <th>Size</th>
                            <th>Engine</th>
                            <th>Collation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $idx = 1;
                        foreach ($table_stats as $ts): 
                            $kb = round($ts['bytes'] / 1024, 1);
                        ?>
                        <tr>
                            <td><?php echo $idx++; ?></td>
                            <td><strong><code><?php echo htmlspecialchars($ts['name']); ?></code></strong></td>
                            <td><?php echo number_format($ts['rows']); ?></td>
                            <td><?php echo $kb >= 1024 ? round($kb / 1024, 2) . ' MB' : $kb . ' KB'; ?></td>
                            <td><span class="badge-pill"><?php echo htmlspecialchars($ts['engine']); ?></span></td>
                            <td style="color: var(--p-color, #64748b); font-size: 12px;"><?php echo htmlspecialchars($ts['collation']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>
