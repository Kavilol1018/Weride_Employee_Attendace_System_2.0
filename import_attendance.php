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
        // Skip header
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Expected format: emp_id, date (YYYY-MM-DD), status
            if (count($data) >= 3) {
                $emp_id = mysqli_real_escape_string($conn, trim($data[0]));
                $date = mysqli_real_escape_string($conn, trim($data[1]));
                $status = mysqli_real_escape_string($conn, trim($data[2]));
                
                // Validate date format briefly
                if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
                    // Check if record exists
                    $check = mysqli_query($conn, "SELECT id FROM attendance_logs WHERE emp_id = '$emp_id' AND date = '$date'");
                    if (mysqli_num_rows($check) > 0) {
                        // Update
                        mysqli_query($conn, "UPDATE attendance_logs SET status = '$status' WHERE emp_id = '$emp_id' AND date = '$date'");
                    } else {
                        // Insert new override
                        mysqli_query($conn, "INSERT INTO attendance_logs (emp_id, date, status) VALUES ('$emp_id', '$date', '$status')");
                    }
                }
            }
        }
        fclose($handle);
        
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

