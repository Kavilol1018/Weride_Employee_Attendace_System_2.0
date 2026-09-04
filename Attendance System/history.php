<?php
use Shuchkin\SimpleXLSX;
session_start();

// Ensure user is management
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: ../index.php");
    exit();
}

require_once 'SimpleXLSX.php';

// Display view
$view = isset($_GET['view']) ? $_GET['view'] : 'check-in-out';

$error = '';
$success = '';
$report_data = [];
$current_loaded_history_file = '';

// Handle file loading from dropdown
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_file']) && !empty($_POST['load_file'])) {
    $action = $_POST['action'] ?? 'load';
    $file_to_action = $_POST['load_file'];
    
    if ($action === 'delete') {
        if (file_exists($file_to_action) && strpos($file_to_action, 'uploads/') === 0) {
            unlink($file_to_action);
            if (isset($_SESSION['history_loaded_file']) && $_SESSION['history_loaded_file'] === $file_to_action) {
                unset($_SESSION['history_loaded_file']);
                $current_loaded_history_file = '';
            }
            $success = "File deleted successfully.";
        } else {
            $error = "Failed to delete file.";
        }
    } else {
        if (file_exists($file_to_action)) {
            $current_loaded_history_file = $file_to_action;
            $_SESSION['history_loaded_file'] = $file_to_action;
        }
    }
} elseif (isset($_SESSION['history_loaded_file']) && file_exists($_SESSION['history_loaded_file'])) {
    $current_loaded_history_file = $_SESSION['history_loaded_file'];
}

// Parse the loaded file
if (!empty($current_loaded_history_file)) {
    if ($xlsx = SimpleXLSX::parse($current_loaded_history_file)) {
        $isFirst = true;
        $colMap = [];
        $emptyCount = 0;
        foreach ($xlsx->readRows() as $row) {
            $row = (array)$row;
            if (empty(array_filter($row))) {
                $emptyCount++;
                if ($emptyCount > 20) break;
                continue;
            }
            $emptyCount = 0;

            if ($isFirst) {
                foreach ($row as $idx => $colName) {
                    $colMap[strtolower(trim($colName))] = $idx;
                }
                $isFirst = false;
                continue;
            }

            $person_id = isset($colMap['person id']) ? $row[$colMap['person id']] : ($row[1] ?? '');
            $name = isset($colMap['name']) ? $row[$colMap['name']] : ($row[2] ?? '');
            $date = isset($colMap['attendance date']) ? $row[$colMap['attendance date']] : ($row[3] ?? '');
            $raw_period = isset($colMap['period']) ? $row[$colMap['period']] : ($row[5] ?? '');
            $raw_check_in = isset($colMap['check in time']) ? $row[$colMap['check in time']] : ($row[6] ?? '');

            $preferred_hours = '';
            if (preg_match('/\((\d{2}:\d{2}-\d{2}:\d{2})\)/', $raw_period, $matches)) {
                $preferred_hours = $matches[1];
            } else {
                $preferred_hours = $raw_period;
            }

            $check_in = '';
            if (strtolower(trim($raw_check_in)) === 'not checked in') {
                $check_in = 'Not Checked In';
            } else if (preg_match('/\d{2}:\d{2}/', $raw_check_in, $matches)) {
                $check_in = substr($matches[0], 0, 5); 
            } else {
                $check_in = trim($raw_check_in);
            }
            
            $raw_check_out = isset($colMap['check out time']) ? $row[$colMap['check out time']] : ($row[7] ?? '');
            $check_out = '';
            if (strtolower(trim($raw_check_out)) === 'not checked out' || empty(trim($raw_check_out))) {
                $check_out = 'Not Checked Out';
            } else if (preg_match('/\d{2}:\d{2}/', $raw_check_out, $matches)) {
                $check_out = substr($matches[0], 0, 5); 
            } else {
                $check_out = trim($raw_check_out);
            }

            $shift_start = '';
            $shift_end = '';
            if (strpos($preferred_hours, '-') !== false) {
                list($start, $end) = explode('-', $preferred_hours);
                $shift_start = trim($start);
                $shift_end = trim($end);
                
                if (strlen($shift_start) < 5 && strpos($shift_start, ':') !== false) $shift_start = '0' . $shift_start;
                if (strlen($shift_end) < 5 && strpos($shift_end, ':') !== false) $shift_end = '0' . $shift_end;
            }

            $status = '';
            if (empty($check_in) || strtolower($check_in) === 'not checked in') {
                $status = 'Absent';
                $check_in = 'Not Checked In';
            } else {
                if (!empty($shift_start)) {
                    if (strtotime($check_in) > strtotime($shift_start)) {
                        $status = 'Late';
                    } else {
                        $status = 'On Time';
                    }
                }
            }
            
            $checkout_status = '';
            if (empty($check_out) || strtolower($check_out) === 'not checked out' || $check_out === 'Not Checked Out') {
                $checkout_status = 'Missed';
                $check_out = 'Not Checked Out';
            } else {
                if (!empty($shift_end)) {
                    if (strtotime($check_out) < strtotime($shift_end)) {
                        $checkout_status = 'Early Leave';
                    } else {
                        $checkout_status = 'On Time';
                    }
                }
            }

            $overall_status = '';
            if ($status === 'Absent' && $checkout_status === 'Missed') {
                $overall_status = 'Absent';
            } elseif ($status === 'Late' || $checkout_status === 'Early Leave' || $checkout_status === 'Missed') {
                $overall_status = 'Incomplete';
            } else {
                $overall_status = 'Complete';
            }
            
            $agenda_status = ($status === 'Absent') ? 'A' : 'P';

            $report_data[] = [
                'agenda' => $agenda_status,
                'person_id' => $person_id,
                'name' => $name,
                'date' => $date,
                'preferred_hours' => $preferred_hours,
                'shift_start' => $shift_start,
                'shift_end' => $shift_end,
                'check_in' => $check_in,
                'status' => $status,
                'check_out' => $check_out,
                'checkout_status' => $checkout_status,
                'overall_status' => $overall_status
            ];
        }
    } else {
        $error = SimpleXLSX::parseError();
    }
}

// Calculate Stats
$total_records = count($report_data);
$on_time_count = 0;
$late_count = 0;
$absent_count = 0;

foreach ($report_data as $row) {
    if ($row['status'] === 'On Time') $on_time_count++;
    if ($row['status'] === 'Late') $late_count++;
    if ($row['status'] === 'Absent') $absent_count++;
}

// Fetch saved files
$saved_files = glob("uploads/*.xlsx");
if (is_array($saved_files)) {
    $saved_files = array_filter($saved_files, function($file) {
        return basename($file) !== 'temp_upload.xlsx';
    });
    rsort($saved_files); // newest first
} else {
    $saved_files = [];
}

// Filter data based on view
$filtered_data = [];
foreach ($report_data as $row) {
    if ($view === 'late' && $row['status'] !== 'Late') continue;
    $filtered_data[] = $row;
}
$show_checkout = ($view === 'check-in-out');

$user_role = $_SESSION['role'] ?? 'employee';
$role_slug = $user_role;
if ($role_slug === 'qa') $role_slug = 'employee';
$current_page = 'history.php'; // for sidebar logic

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="../pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Attendance History</title>
    
    <style>
        /* Base Reset & Typography */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; overflow-x: hidden; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        /* File Loader area */
        .controls-wrapper {
            background: var(--bg-card, #ffffff);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border-color, rgba(226, 232, 240, 0.5));
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .history-dropdown { flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 8px; }
        .history-dropdown select { padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 14px; background: white; }
        
        .btn-action { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-action:hover { background: #2563eb; }

        .error-msg { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid #fecaca; }

        /* Summary Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: #ffffff; padding: 20px 24px; border-radius: 12px; border: 1px solid #e2e8f0; display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto auto; column-gap: 16px; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card:hover { transform: none; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .stat-title { grid-column: 2; grid-row: 1; color: #94a3b8; font-size: 11px; font-weight: 700; margin-bottom: 2px; text-transform: uppercase; letter-spacing: 0.5px; align-self: end; }
        .stat-value { grid-column: 2; grid-row: 2; font-size: 24px; font-weight: 800; color: #0f172a; align-self: center; }
        .stat-subtitle { grid-column: 2; grid-row: 3; font-size: 11px; color: #94a3b8; margin-top: 2px; font-weight: 600; align-self: start; }
        .stat-icon { grid-column: 1; grid-row: 1 / span 3; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 0; }

        /* Report Table */
        .report-section { background: #ffffff; padding: 28px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.5); }
        .report-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .report-title { font-weight: 700; color: #0f172a; font-size: 18px; }
        
        .filter-buttons { display: flex; gap: 10px; }
        .btn-filter { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 13px; transition: 0.2s; }
        .btn-filter.active { background: #334155; color: white; border-color: #1e293b; }
        .btn-filter:hover:not(.active) { background: #e2e8f0; }

        .table-container { overflow-x: auto; width: 100%; }
        .report-table { width: 100%; border-collapse: collapse; text-align: left; }
        .report-table thead { background: #f8fafc; position: sticky; top: 0; z-index: 5;}
        .report-table th { padding: 14px 18px; color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; }
        .report-table td { padding: 14px 18px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .report-table tbody tr:hover { background: #f8fafc; }

        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
        .badge-ok { background-color: #ecfdf5; color: #059669; }
        .badge-late { background-color: #fef2f2; color: #ef4444; }
        .badge-absent { background-color: #fffbeb; color: #b45309; }
    </style>
    <link rel="stylesheet" href="../assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="../assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include '../sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Attendance History</div>
        </header>

        <div class="content">
            <?php if ($error): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid #a7f3d0;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- CONTROLS -->
            <div class="controls-wrapper">
                <!-- Load History -->
                <form action="" method="POST" class="history-dropdown" style="margin: 0;">
                    <label style="font-weight: 600; color: #334155;">Select Saved Past File</label>
                    <div style="display: flex; gap: 10px;">
                        <select name="load_file" style="flex: 1;">
                            <option value="">-- Select a saved file --</option>
                            <?php foreach ($saved_files as $sf): ?>
                                <option value="<?php echo htmlspecialchars($sf); ?>" <?php if($current_loaded_history_file == $sf) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars(str_replace('_', ' ', pathinfo($sf, PATHINFO_FILENAME))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="action" value="load" class="btn-action">Load</button>
                        <button type="submit" name="action" value="delete" class="btn-action" style="background: #ef4444;" onclick="return confirm('Are you sure you want to delete this file?');">Delete</button>
                    </div>
                    <?php if (!empty($current_loaded_history_file)): ?>
                        <div style="font-size: 12px; color: #059669; font-weight: 600; margin-top: 4px;">Currently loaded: <?php echo htmlspecialchars(str_replace('_', ' ', pathinfo($current_loaded_history_file, PATHINFO_FILENAME))); ?></div>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($report_data)): ?>
            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;">📄</div>
                    <div class="stat-title">Total Records</div>
                    <div class="stat-value"><?php echo $total_records; ?></div>
                    <div class="stat-subtitle">Rows processed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #ecfdf5; color: #059669;">✅</div>
                    <div class="stat-title">On Time</div>
                    <div class="stat-value" style="color: #059669;"><?php echo $on_time_count; ?></div>
                    <div class="stat-subtitle">Check-in on time</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">⚠️</div>
                    <div class="stat-title">Late</div>
                    <div class="stat-value" style="color: <?php echo $late_count > 0 ? '#ef4444' : '#0f172a'; ?>;"><?php echo $late_count; ?></div>
                    <div class="stat-subtitle">Arrived late</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: #fffbeb; color: #b45309;">🚫</div>
                    <div class="stat-title">Absent</div>
                    <div class="stat-value" style="color: #b45309;"><?php echo $absent_count; ?></div>
                    <div class="stat-subtitle">Did not check in</div>
                </div>
            </div>

            <!-- TABLE SECTION -->
            <div class="report-section">
                <div class="report-header">
                    <h3 class="report-title">Attendance Records</h3>
                    <div class="filter-buttons" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <a href="?view=check-in-out" class="btn-filter <?php echo $view === 'check-in-out' ? 'active' : ''; ?>">Check In/Out</a>
                        <a href="?view=check-in" class="btn-filter <?php echo $view === 'check-in' ? 'active' : ''; ?>">Check In</a>
                        <a href="?view=late" class="btn-filter <?php echo $view === 'late' ? 'active' : ''; ?>">Late</a>
                        
                        <!-- Pagination Dropdown -->
                        <div class="pagination-size" style="margin-left: 10px; color: #64748b; font-weight: 600; font-size: 13px;">
                            Show 
                            <select id="pageSize" style="padding: 6px; border-radius: 6px; border: 1px solid #e2e8f0; font-family: inherit; font-size: 13px; color: #334155; margin: 0 5px; outline: none; cursor: pointer; background: white;">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">All</option>
                            </select> 
                            entries
                        </div>

                        <!-- Export Dropdown -->
                        <div class="export-dropdown" style="position: relative; margin-left: auto;">
                            <button type="button" class="btn-action" style="background: #10b981; padding: 8px 16px; display: flex; align-items: center; gap: 8px;" onclick="document.getElementById('exportMenu').classList.toggle('show')">
                                📥 Export ▼
                            </button>
                            <div id="exportMenu" class="dropdown-content" style="display: none; position: absolute; right: 0; top: 100%; background: #ffffff; min-width: 150px; box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.1); z-index: 10; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 5px; overflow: hidden;">
                                <a href="export_attendance.php?source=history&view=<?php echo $view; ?>" style="color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; font-weight: 600; border-bottom: 1px solid #e2e8f0;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">CSV Document</a>
                                <a href="#" onclick="exportAsImage(); return false;" style="color: #334155; padding: 12px 16px; text-decoration: none; display: block; font-size: 13px; font-weight: 600;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">PNG Image</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <style>
                    .dropdown-content.show { display: block !important; }
                    body.dark-mode .pagination-size { color: var(--text-muted-dark) !important; }
                    body.dark-mode .pagination-size select { background-color: #0f172a !important; color: var(--text-main-dark) !important; border-color: var(--border-dark) !important; }
                    body.dark-mode .dropdown-content { background-color: var(--card-dark) !important; border-color: var(--border-dark) !important; }
                    body.dark-mode .dropdown-content a { color: var(--text-main-dark) !important; border-color: var(--border-dark) !important; }
                    body.dark-mode .dropdown-content a:hover { background-color: #334155 !important; }
                </style>

                <div class="table-container">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Employee ID</th>
                                <th>Employee Name</th>
                                <th>Date</th>
                                <th>Check-in</th>
                                <th>Status</th>
                                <?php if ($show_checkout): ?>
                                    <th>Check-out</th>
                                    <th>Out Status</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $row_num = 1;
                            foreach ($filtered_data as $row): 
                                $statusBadge = 'badge-ok';
                                if ($row['status'] === 'Late') $statusBadge = 'badge-late';
                                elseif ($row['status'] === 'Absent') $statusBadge = 'badge-absent';

                                $outBadge = 'badge-ok';
                                if ($row['checkout_status'] === 'Early Leave') $outBadge = 'badge-late';
                                elseif ($row['checkout_status'] === 'Missed') $outBadge = 'badge-absent';
                            ?>
                            <tr>
                                <td style="color: #94a3b8; font-weight: 600; font-size: 13px;"><?php echo $row_num++; ?></td>
                                <td style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($row['person_id']); ?></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo htmlspecialchars($row['check_in']); ?></td>
                                <td><span class="badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <?php if ($show_checkout): ?>
                                    <td><?php echo htmlspecialchars($row['check_out']); ?></td>
                                    <td><span class="badge <?php echo $outBadge; ?>"><?php echo htmlspecialchars($row['checkout_status']); ?></span></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($filtered_data)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">No records found for this view.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state" style="background: #ffffff; padding: 60px 20px; border-radius: 20px; text-align: center; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.03); border: 1px solid rgba(226, 232, 240, 0.5);">
                <div style="font-size: 48px; margin-bottom: 16px;">📂</div>
                <h3 style="margin-bottom: 8px;">No File Loaded</h3>
                <p class="suggestion-text">Select a saved attendance file from the dropdown above to view records.</p>
            </div>
            <?php endif; ?>

        </div>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    // Export Image function
    function exportAsImage() {
        const tableContainer = document.querySelector('.report-section');
        if(!tableContainer) return;
        
        // Hide export menu before screenshot
        document.getElementById('exportMenu').classList.remove('show');
        
        // Temporarily change styling for a clean screenshot
        const originalStyle = tableContainer.style.cssText;
        tableContainer.style.background = document.body.classList.contains('dark-mode') ? '#1e293b' : '#ffffff';
        tableContainer.style.padding = '20px';
        
        html2canvas(tableContainer, {
            scale: 2, // Higher resolution
            backgroundColor: document.body.classList.contains('dark-mode') ? '#0f172a' : '#f8fafc'
        }).then(canvas => {
            // Restore styles
            tableContainer.style.cssText = originalStyle;
            
            // Download image
            const link = document.createElement('a');
            link.download = 'attendance_report.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Dropdown close logic
        window.addEventListener('click', function(e) {
            if (!e.target.closest('.export-dropdown')) {
                const menu = document.getElementById('exportMenu');
                if (menu) menu.classList.remove('show');
            }
        });

        const table = document.querySelector('.report-table');
        if (!table) return;
        
        const headers = table.querySelectorAll('th');
        const tbody = table.querySelector('tbody');
        const originalRows = Array.from(tbody.querySelectorAll('tr'));
        
        if (originalRows.length === 0 || (originalRows.length === 1 && originalRows[0].cells.length === 1)) return; 
        
        // Pagination logic
        let currentPage = 1;
        let rowsPerPage = 10;
        let currentRows = [...originalRows];
        
        const pageSizeSelect = document.getElementById('pageSize');
        
        // Create pagination controls
        const paginationContainer = document.createElement('div');
        paginationContainer.className = 'pagination-controls';
        paginationContainer.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 1px solid #e2e8f0;';
        
        const infoText = document.createElement('div');
        infoText.style.cssText = 'color: #64748b; font-size: 13px; font-weight: 500;';
        
        const buttonsContainer = document.createElement('div');
        buttonsContainer.style.cssText = 'display: flex; gap: 8px;';
        
        const prevBtn = document.createElement('button');
        prevBtn.innerText = 'Previous';
        prevBtn.className = 'btn-page';
        
        const nextBtn = document.createElement('button');
        nextBtn.innerText = 'Next';
        nextBtn.className = 'btn-page';
        
        buttonsContainer.appendChild(prevBtn);
        buttonsContainer.appendChild(nextBtn);
        paginationContainer.appendChild(infoText);
        paginationContainer.appendChild(buttonsContainer);
        table.parentElement.appendChild(paginationContainer);

        function renderTable() {
            tbody.innerHTML = '';
            
            if (rowsPerPage === 'all') {
                currentRows.forEach(row => tbody.appendChild(row));
                infoText.innerText = `Showing 1 to ${currentRows.length} of ${currentRows.length} entries`;
                prevBtn.disabled = true;
                nextBtn.disabled = true;
                return;
            }
            
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + parseInt(rowsPerPage);
            const paginatedRows = currentRows.slice(start, end);
            
            paginatedRows.forEach(row => tbody.appendChild(row));
            
            infoText.innerText = `Showing ${start + 1} to ${Math.min(end, currentRows.length)} of ${currentRows.length} entries`;
            
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = end >= currentRows.length;
        }

        if(pageSizeSelect) {
            pageSizeSelect.addEventListener('change', (e) => {
                rowsPerPage = e.target.value === 'all' ? 'all' : parseInt(e.target.value);
                currentPage = 1;
                renderTable();
            });
        }

        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderTable();
            }
        });

        nextBtn.addEventListener('click', () => {
            const maxPage = Math.ceil(currentRows.length / rowsPerPage);
            if (currentPage < maxPage) {
                currentPage++;
                renderTable();
            }
        });

        // Add styling for buttons
        const style = document.createElement('style');
        style.innerHTML = `
            .btn-page { padding: 6px 12px; border: 1px solid #e2e8f0; background: #ffffff; border-radius: 6px; cursor: pointer; color: #334155; font-size: 13px; font-weight: 500; transition: 0.2s; }
            .btn-page:hover:not(:disabled) { background: #f1f5f9; }
            .btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
            body.dark-mode .btn-page { background: #1e293b; border-color: #334155; color: #94a3b8; }
            body.dark-mode .btn-page:hover:not(:disabled) { background: #334155; color: #f8fafc; }
            body.dark-mode .pagination-controls { border-top-color: #334155 !important; }
        `;
        document.head.appendChild(style);

        // Sorting logic
        let currentSort = { index: -1, asc: true };
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.title = 'Click to sort';
            
            header.addEventListener('click', () => {
                const isAsc = currentSort.index === index ? !currentSort.asc : true;
                currentSort = { index, asc: isAsc };
                
                headers.forEach(h => h.innerHTML = h.innerHTML.replace(' ▴', '').replace(' ▾', ''));
                header.innerHTML += isAsc ? ' ▴' : ' ▾';
                
                currentRows.sort((a, b) => {
                    const aCol = a.cells[index];
                    const bCol = b.cells[index];
                    if (!aCol || !bCol) return 0;
                    
                    let aVal = aCol.innerText.trim();
                    let bVal = bCol.innerText.trim();
                    
                    if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
                        return isAsc ? aVal - bVal : bVal - aVal;
                    }
                    return isAsc ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                });
                
                currentPage = 1; // Reset to first page after sort
                renderTable();
            });
        });
        
        // Initial render
        renderTable();
    });
    </script>
</body>
</html>

