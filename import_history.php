<?php
session_start();
require 'db_connect.php';

// Get parameters from URL, default to March
$import_year = 2026;
$import_month = isset($_GET['month']) ? (int)$_GET['month'] : 3;
$csv_file = isset($_GET['file']) ? $_GET['file'] : 'march_2026.csv';

echo "<h2>Starting Import for Month $import_month / $import_year using $csv_file...</h2>";

if (!file_exists($csv_file)) {
    die("CSV file not found: $csv_file");
}

$handle = fopen($csv_file, "r");
if ($handle !== FALSE) {
    $header_found = false;
    $name_col_idx = -1;
    $day_indices = [];
    
    // 1. Find Header
    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        foreach ($data as $idx => $val) {
            $val = trim(preg_replace('/[\xEF\xBB\xBF]/', '', $val));
            if ($val === 'Employee Name') {
                $name_col_idx = $idx;
                $header_found = true;
            }
            if ($header_found && preg_match('/^(0[1-9]|[12]\d|3[01])$/', $val)) {
                $day_indices[(int)$val] = $idx;
            }
        }
        if ($header_found) break;
    }

    if (!$header_found || empty($day_indices)) {
        die("Could not find header row or day columns (01-31).");
    }

    echo "Found " . count($day_indices) . " day columns in the CSV.<br><br>";

    // 2. Process Rows
    $employees_processed = 0;
    $employees_not_found = [];
    
    while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
        if (!isset($data[$name_col_idx])) continue;
        
        $emp_name = trim(preg_replace('/[\xEF\xBB\xBF]/', '', $data[$name_col_idx]));
        if (empty($emp_name) || strpos($emp_name, 'TOTAL') !== false || $emp_name === 'Employee Name') {
            continue;
        }

        // Try exact match, or match without trailing spaces
        $name_safe = mysqli_real_escape_string($conn, $emp_name);
        $emp_query = mysqli_query($conn, "SELECT emp_id FROM employees WHERE TRIM(name) = TRIM('$name_safe') LIMIT 1");
        
        if (mysqli_num_rows($emp_query) > 0) {
            $emp_id = mysqli_fetch_assoc($emp_query)['emp_id'];
            
            // Loop through days 1-31
            foreach ($day_indices as $day => $col_idx) {
                $status_raw = isset($data[$col_idx]) ? trim($data[$col_idx]) : '';
                
                // Map status
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
                
                $date_str = sprintf("%04d-%02d-%02d", $import_year, $import_month, $day);
                
                if (checkdate($import_month, $day, $import_year)) {
                    if ($status === '') {
                        // Delete if blank or BO
                        mysqli_query($conn, "DELETE FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$date_str'");
                    } else {
                        // Check if exists
                        $check_log = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$date_str'");
                        if (mysqli_num_rows($check_log) > 0) {
                            mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' WHERE emp_id = '$emp_id' AND date = '$date_str'");
                        } else {
                            mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date_str', '$status')");
                        }
                    }
                }
            }
            $employees_processed++;
        } else {
            $employees_not_found[] = $emp_name;
        }
    }
    
    fclose($handle);
    
    echo "<h3 style='color:green;'>Import complete! Processed $employees_processed employees for Month $import_month / 2026.</h3>";
    
    if (!empty($employees_not_found)) {
        echo "<h4 style='color:red;'>Could not find the following employees in the database (Check for typos):</h4><ul>";
        foreach ($employees_not_found as $not_found) {
            echo "<li>" . htmlspecialchars($not_found) . "</li>";
        }
        echo "</ul>";
    }
    
} else {
    echo "Failed to open CSV.";
}
?>
