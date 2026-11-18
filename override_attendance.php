<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
require 'db_connect.php';

$role_slug = $_SESSION['role'];
$current_page = 'override_attendance.php';
$msg = isset($_GET['msg']) ? $_GET['msg'] : (isset($_GET['success']) ? $_GET['success'] : '');
$error = isset($_GET['error']) ? $_GET['error'] : '';

// AJAX: Fetch existing attendance for selected employee and date
if (isset($_GET['action']) && $_GET['action'] === 'get_attendance') {
    header('Content-Type: application/json');
    $e_id = mysqli_real_escape_string($conn, $_GET['emp_id'] ?? '');
    $d = mysqli_real_escape_string($conn, $_GET['date'] ?? '');
    
    if (empty($e_id) || empty($d)) {
        echo json_encode(['found' => false]);
        exit();
    }
    
    $q = mysqli_query($conn, "SELECT clock_in, clock_out, work_hours, status, flag FROM attendance_logs WHERE emp_id = '$e_id' AND `date` = '$d' LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        echo json_encode([
            'found' => true,
            'clock_in' => $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '',
            'clock_out' => $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '',
            'work_hours' => $row['work_hours'] !== null ? (float)$row['work_hours'] : null,
            'status' => $row['status'],
            'flag' => $row['flag']
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit();
}

// Handle Override Time Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'override_time') {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $clock_in = mysqli_real_escape_string($conn, $_POST['clock_in']);
    $clock_out = mysqli_real_escape_string($conn, $_POST['clock_out']);
    $manual_hours = isset($_POST['work_hours']) && trim($_POST['work_hours']) !== '' ? floatval($_POST['work_hours']) : null;
    
    $check = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND `date` = '$date'");
    
    $clock_in_val = empty($clock_in) ? "NULL" : "'$date $clock_in'";
    $clock_out_val = empty($clock_out) ? "NULL" : "'$date $clock_out'";

    // Compute work_hours
    $work_hours = 0.00;
    if ($manual_hours !== null && $manual_hours >= 0) {
        $work_hours = round($manual_hours, 2);
    } elseif (!empty($clock_in) && !empty($clock_out)) {
        $ci_ts = strtotime("$date $clock_in");
        $co_ts = strtotime("$date $clock_out");
        if ($co_ts < $ci_ts) {
            $co_ts = strtotime("$date $clock_out +1 day");
            $clock_out_val = "'" . date('Y-m-d H:i:s', $co_ts) . "'";
        }
        $diff_sec = $co_ts - $ci_ts;
        $work_hours = round(max(0, $diff_sec / 3600.0), 2);
    }

    // Fetch employee shift to recalculate Late/Early flag
    $emp_shift_res = mysqli_query($conn, "SELECT shift_hour FROM employees WHERE emp_id = '$emp_id'");
    $emp_shift_row = mysqli_fetch_assoc($emp_shift_res);
    $shift = !empty($emp_shift_row['shift_hour']) ? $emp_shift_row['shift_hour'] : '09:00-18:00';
    $shift_parts = explode('-', $shift);
    $shift_start = trim($shift_parts[0] ?? '09:00');
    $shift_end = trim($shift_parts[1] ?? '18:00');

    $is_late = false;
    if (!empty($clock_in)) {
        $ci_time = date('H:i', strtotime($clock_in));
        if ($ci_time > $shift_start) {
            $is_late = true;
        }
    }

    $is_early = false;
    if (!empty($clock_out)) {
        $co_time = date('H:i', strtotime($clock_out));
        if ($co_time < $shift_end) {
            $is_early = true;
        }
    }

    $new_flag = 'None';
    if ($is_late && $is_early) $new_flag = 'Both';
    elseif ($is_late) $new_flag = 'Late';
    elseif ($is_early) $new_flag = 'Early';

    $new_status = (!empty($clock_in)) ? 'P' : 'A';

    if ($check && mysqli_num_rows($check) > 0) {
        $res = mysqli_query($conn, "UPDATE attendance_logs SET clock_in = $clock_in_val, clock_out = $clock_out_val, status = '$new_status', flag = '$new_flag', work_hours = $work_hours WHERE emp_id = '$emp_id' AND `date` = '$date'");
    } else {
        $res = mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, `date`, clock_in, clock_out, status, flag, work_hours) VALUES ('$emp_id', '$date', $clock_in_val, $clock_out_val, '$new_status', '$new_flag', $work_hours)");
    }
    
    if (isset($res) && $res) {
        $msg = "Times overridden successfully. Work hours recorded: " . number_format($work_hours, 2) . " hrs.";
    } else {
        $error = mysqli_error($conn);
    }
}

// Fetch all employees for the dropdown
$emp_query = mysqli_query($conn, "SELECT emp_id, name FROM employees ORDER BY name ASC");
$emp_names = [];
if ($emp_query) {
    while ($row = mysqli_fetch_assoc($emp_query)) {
        $emp_names[$row['emp_id']] = $row['name'];
    }
}

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Override Attendance - WeRide</title>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--bg-color, #f8fafc); color: var(--text-color, #1e293b); display: flex; height: 100vh; overflow: hidden; transition: background 0.2s, color 0.2s; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .header h1 { margin: 0; font-size: 24px; color: var(--text-color, #1e293b); }
        .card { background: var(--card-bg, white); border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; transition: background 0.2s; }
        .card h2 { margin-top: 0; color: var(--heading-color, #0f172a); font-size: 18px; margin-bottom: 15px; }
        .card p { color: var(--p-color, #64748b); }
        .form-group { margin-bottom: 15px; }
        .form-group label, .form-label { display: block; font-weight: 600; font-size: 13px; color: var(--label-color, #475569); margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color, #cbd5e1); background: var(--input-bg, white); color: var(--text-color, #1e293b); outline: none; box-sizing: border-box; font-family: inherit; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 14px; border: none; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-outline { background: transparent; color: #3b82f6; border: 1px solid #3b82f6; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
        .alert-success { background: var(--success-bg, #dcfce7); color: var(--success-text, #166534); }
        .alert-error { background: var(--error-bg, #fee2e2); color: var(--error-text, #991b1b); }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 300px; }

        /* Dark mode variables */
        body.dark-mode {
            --bg-color: #0f172a;
            --text-color: #f8fafc;
            --card-bg: #1e293b;
            --heading-color: #f1f5f9;
            --p-color: #94a3b8;
            --label-color: #cbd5e1;
            --border-color: #334155;
            --input-bg: #0f172a;
            --success-bg: #064e3b;
            --success-text: #34d399;
            --error-bg: #7f1d1d;
            --error-text: #fca5a5;
        }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Override Attendance</h1>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Manual Time Override -->
            <div class="col card">
                <h2>Manual Time Override</h2>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">Override clock in/out times for a specific employee and date.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="override_time">
                    
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="emp_id" required class="form-control">
                            <option value="" disabled selected>-- Select an employee --</option>
                            <?php foreach($emp_names as $e_id => $e_name): ?>
                                <option value="<?= htmlspecialchars($e_id) ?>"><?= htmlspecialchars($e_name) ?> (<?= htmlspecialchars($e_id) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="date" required class="form-control">
                    </div>

                    <div class="row" style="gap: 15px; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Clock In Time</label>
                            <input type="time" name="clock_in" id="clock_in" class="form-control">
                        </div>
                        <div style="flex: 1;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Clock Out Time</label>
                            <input type="time" name="clock_out" id="clock_out" class="form-control">
                        </div>
                        <div style="flex: 1;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Work Hours (hrs)</label>
                            <input type="number" step="0.01" min="0" max="24" name="work_hours" id="work_hours" class="form-control" placeholder="Auto-calculated">
                        </div>
                    </div>
                    <div id="record_status_hint" style="font-size: 13px; margin-bottom: 20px; display: none;"></div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Save Override</button>
                </form>
            </div>

            <!-- Import CSV Override -->
            <div class="col card">
                <h2>Import CSV Override</h2>
                <p style="color: #64748b; font-size: 14px; margin-bottom: 20px;">Upload a monthly calendar CSV export to override attendance records in bulk.</p>
                <form action="import_attendance.php" method="POST" enctype="multipart/form-data">
                    <div class="row" style="gap: 15px; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Month</label>
                            <select name="month" required class="form-control">
                                <?php
                                for ($m=1; $m<=12; $m++) {
                                    $monthName = date('F', mktime(0, 0, 0, $m, 10));
                                    $selected = ($m == $selected_month) ? 'selected' : '';
                                    echo "<option value='$m' $selected>$monthName</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label style="display:block; font-weight:600; font-size:13px; margin-bottom:5px;">Year</label>
                            <select name="year" required class="form-control">
                                <?php
                                for ($y=$selected_year-1; $y<=$selected_year+2; $y++) {
                                    $selected = ($y == $selected_year) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px; margin-bottom: 20px;">
                        <input type="file" name="csv_file" accept=".csv" required style="width: 100%;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Upload & Import</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const empSelect = document.querySelector('select[name="emp_id"]');
            const dateInput = document.querySelector('input[name="date"]');
            const ciInput = document.getElementById('clock_in');
            const coInput = document.getElementById('clock_out');
            const whInput = document.getElementById('work_hours');
            const hint = document.getElementById('record_status_hint');

            let userManuallySetHours = false;

            whInput.addEventListener('input', function() {
                userManuallySetHours = (this.value.trim() !== '');
            });

            function autoCalculateHours() {
                const ci = ciInput.value;
                const co = coInput.value;
                if (ci && co) {
                    const [ciH, ciM] = ci.split(':').map(Number);
                    const [coH, coM] = co.split(':').map(Number);
                    let diffMinutes = (coH * 60 + coM) - (ciH * 60 + ciM);
                    if (diffMinutes < 0) {
                        diffMinutes += 24 * 60; // Overnight shift
                    }
                    const hours = (diffMinutes / 60).toFixed(2);
                    whInput.value = hours;
                    userManuallySetHours = false;
                } else if (!ci || !co) {
                    if (!userManuallySetHours) {
                        whInput.value = '0.00';
                    }
                }
            }

            ciInput.addEventListener('change', autoCalculateHours);
            coInput.addEventListener('change', autoCalculateHours);
            ciInput.addEventListener('input', autoCalculateHours);
            coInput.addEventListener('input', autoCalculateHours);

            function loadExistingRecord() {
                const empId = empSelect.value;
                const dateVal = dateInput.value;
                if (!empId || !dateVal) return;

                hint.style.display = 'block';
                hint.style.color = '#64748b';
                hint.innerHTML = '⏳ <em>Checking existing records...</em>';

                fetch(`override_attendance.php?action=get_attendance&emp_id=${encodeURIComponent(empId)}&date=${encodeURIComponent(dateVal)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.found) {
                            ciInput.value = data.clock_in || '';
                            coInput.value = data.clock_out || '';
                            if (data.work_hours !== null && data.work_hours !== undefined) {
                                whInput.value = parseFloat(data.work_hours).toFixed(2);
                            } else {
                                autoCalculateHours();
                            }
                            hint.style.color = '#0284c7';
                            hint.innerHTML = `ℹ️ <strong>Existing record loaded:</strong> Clock In: <b>${data.clock_in || '--'}</b> | Clock Out: <b>${data.clock_out || '--'}</b> | Work Hours: <b>${data.work_hours !== null ? data.work_hours : '0.00'} hrs</b>`;
                        } else {
                            hint.style.color = '#10b981';
                            hint.innerHTML = `✨ <em>No existing record found for this date. A new record will be created.</em>`;
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching attendance record:', err);
                        hint.style.display = 'none';
                    });
            }

            empSelect.addEventListener('change', loadExistingRecord);
            dateInput.addEventListener('change', loadExistingRecord);
        });
    </script>
</body>
</html>
