<?php
// This script adds a 'source' column to attendance_logs
// Run once via browser: https://yourdomain.com/add_source_column.php
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
require 'db_connect.php';

// Add the source column if it doesn't exist
$res = mysqli_query($conn, "ALTER TABLE attendance_logs ADD COLUMN source ENUM('manual','leave_form') NOT NULL DEFAULT 'manual'");
if ($res) {
    echo "Column 'source' added successfully.<br>";
} else {
    $err = mysqli_error($conn);
    if (strpos($err, 'Duplicate column name') !== false) {
        echo "Column 'source' already exists — no change needed.<br>";
    } else {
        echo "Error: $err<br>";
    }
}

// Mark all existing records that match an approved leave request as 'leave_form'
$res2 = mysqli_query($conn, "
    UPDATE attendance_logs al
    INNER JOIN leave_requests lr 
        ON al.emp_id = lr.emp_id 
        AND al.date BETWEEN lr.start_date AND lr.end_date
        AND lr.status = 'approved'
    SET al.source = 'leave_form'
    WHERE al.status IN ('AL', '0.5 AL', 'SL', '0.5 SL', 'UPL', '0.5UPL', 'HOSP')
");
if ($res2) {
    echo "Existing leave form records tagged successfully (" . mysqli_affected_rows($conn) . " rows updated).<br>";
} else {
    echo "Error tagging records: " . mysqli_error($conn) . "<br>";
}

echo "<br>Done! You can delete this file now.";
?>
