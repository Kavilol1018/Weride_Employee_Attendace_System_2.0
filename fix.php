<?php
include 'db_connect.php';
mysqli_query($conn, "ALTER TABLE leave_requests MODIFY COLUMN status ENUM('pending', 'pending_hr', 'approved', 'rejected') DEFAULT 'pending'");
mysqli_query($conn, "ALTER TABLE employees ALTER COLUMN annual_leave_balance SET DEFAULT 8");
echo "DB Updated: " . mysqli_error($conn);
?>
