<?php
// cron_auto_checkout.php
// This script should be run by a cron job every night at midnight (or late night).

include 'db_connect.php';

// Find all employees who clocked in today but have not clocked out
$sql = "SELECT id, emp_id, clock_in FROM attendance_logs WHERE clock_out IS NULL AND DATE(date) <= CURDATE()";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $id = $row['id'];
        $emp_id = $row['emp_id'];
        
        // Update the log with current time as clock_out and set a flag (e.g. adding a note in system alerts or modifying the log)
        // Since we don't have an "exception" column yet in attendance_logs, we'll just log an alert.
        mysqli_query($conn, "UPDATE attendance_logs SET clock_out = CURRENT_TIMESTAMP WHERE id = $id");
        
        $alert_msg = mysqli_real_escape_string($conn, "System Auto-Checkout: Employee $emp_id was automatically clocked out because they forgot to clock out.");
        mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
        
        // Also notify the employee
        $notif_msg = "You forgot to clock out today. The system has automatically clocked you out.";
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$notif_msg')");
    }
    echo "Auto-checkout completed for " . mysqli_num_rows($result) . " employees.";
} else {
    echo "No pending checkouts found.";
}
?>
