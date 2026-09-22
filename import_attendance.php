<?php
session_start();
require 'db_connect.php';

// Ensure user is management
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    if (empty($file)) {
        die("Please upload a file.");
    }
    
    $handle = fopen($file, "r");
    if ($handle !== FALSE) {
        $row_count = 0;
        $updated_count = 0;
        
        $target_month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('n');
        $target_year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            if ($row_count <= 2) continue; // Skip headers (first 2 rows)
            
            $emp_name = trim($data[0] ?? '');
            if (empty($emp_name) || $emp_name === 'TOTAL LEAVES PER MONTH') continue;
            
            // Find emp_id by name
            $name_safe = mysqli_real_escape_string($conn, $emp_name);
            $emp_query = mysqli_query($conn, "SELECT emp_id FROM employees WHERE name = '$name_safe' LIMIT 1");
            if (mysqli_num_rows($emp_query) > 0) {
                $emp_id = mysqli_fetch_assoc($emp_query)['emp_id'];
                
                // Process days 1 to 31 (Columns 15 to 45, index 14 to 44)
                for ($day = 1; $day <= 31; $day++) {
                    $col_idx = 13 + $day; // col 14 is '01'
                    if (isset($data[$col_idx])) {
                        $status_raw = trim($data[$col_idx]);
                        
                        // Map CSV status to DB status
                        $status = '';
                        if ($status_raw === 'P') $status = 'P';
                        elseif ($status_raw === 'A') $status = 'A';
                        elseif ($status_raw === 'PH') $status = 'PH';
                        elseif ($status_raw === 'WO') $status = 'WO';
                        elseif ($status_raw === 'AL' || $status_raw === '0.5 AL' || $status_raw === '0.5AL') $status = 'AL';
                        elseif ($status_raw === 'SL' || $status_raw === '0.5 SL' || $status_raw === '0.5SL') $status = 'SL';
                        elseif ($status_raw === 'UPL' || $status_raw === '0.5 UPL' || $status_raw === '0.5UPL') $status = 'UPL';
                        elseif ($status_raw === 'HL') $status = 'HL';
                        
                        if ($status !== '') {
                            $date_str = sprintf("%04d-%02d-%02d", $target_year, $target_month, $day);
                            
                            // Check if valid date (e.g. Feb 30th is invalid)
                            if (checkdate($target_month, $day, $target_year)) {
                                $check_log = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$date_str'");
                                if (mysqli_num_rows($check_log) > 0) {
                                    // Update existing
                                    $clear_clocks = ($status !== 'P') ? ", clock_in = NULL, clock_out = NULL" : "";
                                    mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' $clear_clocks WHERE emp_id = '$emp_id' AND date = '$date_str'");
                                } else {
                                    // Insert new
                                    mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$status')");
                                }
                                $updated_count++;
                            }
                        }
                    }
                }
            }
        }
        fclose($handle);
        
        // Recalculate leave balances for the year based on the updated attendance logs
        mysqli_query($conn, "
            UPDATE employees e 
            SET 
                annual_leave_balance = annual_leave_quota - IFNULL((SELECT COUNT(*) FROM attendance_logs WHERE emp_id = e.emp_id AND status = 'AL' AND YEAR(date) = '$target_year'), 0),
                sick_leave_balance = sick_leave_quota - IFNULL((SELECT COUNT(*) FROM attendance_logs WHERE emp_id = e.emp_id AND status = 'SL' AND YEAR(date) = '$target_year'), 0),
                unpaid_leave_balance = unpaid_leave_quota - IFNULL((SELECT COUNT(*) FROM attendance_logs WHERE emp_id = e.emp_id AND status = 'UPL' AND YEAR(date) = '$target_year'), 0)
        ");
        
        // Redirect back with success message
        header("Location: admin_attendance.php?success=" . urlencode("Attendance overrides imported successfully."));
        exit();
    } else {
        die("Error opening file.");
    }
} else {
    header("Location: admin_attendance.php");
    exit();
}

