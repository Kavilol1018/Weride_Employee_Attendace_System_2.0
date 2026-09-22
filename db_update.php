<?php
include 'db_connect.php';
mysqli_query($conn, "UPDATE leave_requests SET leave_type = 'Emergency Leave' WHERE leave_type = 'Early Leave'");
// checkout_status is not a real column, it's computed dynamically in PHP.
// mysqli_query($conn, "UPDATE attendance_logs SET checkout_status = 'Emergency Leave' WHERE checkout_status = 'Early Leave'");

// Update leave balances to support decimals
mysqli_query($conn, "ALTER TABLE employees MODIFY COLUMN annual_leave_balance DECIMAL(5,1) DEFAULT 8.0");
mysqli_query($conn, "ALTER TABLE employees MODIFY COLUMN sick_leave_balance DECIMAL(5,1) DEFAULT 14.0");

// Add new leave types
@mysqli_query($conn, "ALTER TABLE employees ADD COLUMN unpaid_leave_balance DECIMAL(5,1) DEFAULT 0.0");
@mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = 0.0");
@mysqli_query($conn, "ALTER TABLE employees ADD COLUMN hospitalization_leave_balance DECIMAL(5,1) DEFAULT 60.0");

echo 'DB updated.';
?>
