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
        
        $headers = [];
        $name_col_idx = -1;
        $day_start_idx = -1;
        $header_found = false;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (!$header_found) {
                // Search for 'Employee Name' or '01' to identify the header row
                foreach ($data as $idx => $val) {
                    // Remove BOM and trim
                    $val = trim(preg_replace('/[\xEF\xBB\xBF]/', '', $val));
                    if ($val === 'Employee Name') $name_col_idx = $idx;
                    elseif ($val === '01') $day_start_idx = $idx;
                }
                
                if ($name_col_idx !== -1 && $day_start_idx !== -1) {
                    $header_found = true;
                }
                continue; // Skip the header row itself and any rows before it
            }
            
            // Now we are past the header row
            $emp_name = trim($data[$name_col_idx] ?? '');
            if (empty($emp_name) || $emp_name === 'TOTAL LEAVES PER MONTH') continue;
            
            // Find emp_id by name
            $name_safe = mysqli_real_escape_string($conn, $emp_name);
            $emp_query = mysqli_query($conn, "SELECT emp_id FROM employees WHERE name = '$name_safe' LIMIT 1");
            if (mysqli_num_rows($emp_query) > 0) {
                $emp_id = mysqli_fetch_assoc($emp_query)['emp_id'];
                
                // Process days 1 to 31
                for ($day = 1; $day <= 31; $day++) {
                    $col_idx = $day_start_idx - 1 + $day; 
                    if (isset($data[$col_idx])) {
                        $status_raw = trim($data[$col_idx]);
                        
                        // Map CSV status to DB status
                        $status = '';
                        if ($status_raw === 'P') $status = 'P';
                        elseif ($status_raw === 'A') $status = 'A';
                        elseif ($status_raw === 'PH') $status = 'PH';
                        elseif ($status_raw === 'WO') $status = 'WO';
                        elseif ($status_raw === '0.5 AL' || $status_raw === '0.5AL') $status = '0.5 AL';
                        elseif ($status_raw === 'AL') $status = 'AL';
                        elseif ($status_raw === '0.5 SL' || $status_raw === '0.5SL') $status = '0.5 SL';
                        elseif ($status_raw === 'SL') $status = 'SL';
                        elseif ($status_raw === '0.5 UPL' || $status_raw === '0.5UPL') $status = '0.5UPL';
                        elseif ($status_raw === 'UPL') $status = 'UPL';
                        elseif ($status_raw === 'HL') $status = 'HL';
                        elseif ($status_raw === 'HD' || $status_raw === '0.5') $status = 'HD';
                        
                        if ($status !== '') {
                            $date_str = sprintf("%04d-%02d-%02d", $target_year, $target_month, $day);
                            
                            // Check if valid date (e.g. Feb 30th is invalid)
                            if (checkdate($target_month, $day, $target_year)) {
                                $check_log = mysqli_query($conn, "SELECT id, status FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$date_str'");
                                $old_status = '';
                                if (mysqli_num_rows($check_log) > 0) {
                                    $row = mysqli_fetch_assoc($check_log);
                                    $old_status = $row['status'];
                                    // Update existing
                                    $clear_clocks = ($status !== 'P') ? ", clock_in = NULL, clock_out = NULL" : "";
                                    mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' $clear_clocks WHERE emp_id = '$emp_id' AND date = '$date_str'");
                                } else {
                                    // Insert new
                                    mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$status')");
                                }
                                
                                // Incremental leave balance update
                                if ($old_status !== $status) {
                                    $al_diff = 0; $sl_diff = 0; $upl_diff = 0;
                                    
                                    if ($old_status === 'AL') $al_diff += 1;
                                    elseif ($old_status === '0.5 AL') $al_diff += 0.5;
                                    elseif ($old_status === 'SL') $sl_diff += 1;
                                    elseif ($old_status === '0.5 SL') $sl_diff += 0.5;
                                    elseif ($old_status === 'UPL') $upl_diff -= 1;
                                    elseif ($old_status === '0.5UPL') $upl_diff -= 0.5;
                            
                                    if ($status === 'AL') $al_diff -= 1;
                                    elseif ($status === '0.5 AL') $al_diff -= 0.5;
                                    elseif ($status === 'SL') $sl_diff -= 1;
                                    elseif ($status === '0.5 SL') $sl_diff -= 0.5;
                                    elseif ($status === 'UPL') $upl_diff += 1;
                                    elseif ($status === '0.5UPL') $upl_diff += 0.5;
                                    
                                    if ($al_diff != 0 || $sl_diff != 0 || $upl_diff != 0) {
                                        mysqli_query($conn, "
                                            UPDATE employees 
                                            SET 
                                                annual_leave_balance = annual_leave_balance + ($al_diff),
                                                sick_leave_balance = sick_leave_balance + ($sl_diff),
                                                unpaid_leave_balance = unpaid_leave_balance + ($upl_diff)
                                            WHERE emp_id = '$emp_id'
                                        ");
                                    }
                                }
                                
                                $updated_count++;
                            }
                        }
                    }
                }
            }
        }
        fclose($handle);
        
        // Redirect back with success message
        header("Location: override_attendance.php?success=" . urlencode("Attendance overrides imported successfully."));
        exit();
    } else {
        die("Error opening file.");
    }
} else {
    header("Location: override_attendance.php");
    exit();
}

