<?php
// cron_auto_checkout.php
// This script should be run by a cron job every night at midnight (or late night).

// Security: Only allow CLI execution or requests with a valid secret key
$cron_secret = 'weride_cron_checkout_secure_key_8f3a92';
$is_cli = (php_sapi_name() === 'cli');
$has_secret = (isset($_GET['secret']) && $_GET['secret'] === $cron_secret);

if (!$is_cli && !$has_secret) {
    http_response_code(403);
    die("Access denied. Direct HTTP access is forbidden.");
}

include 'db_connect.php';

// Only auto-checkout past dates (yesterday and earlier), or late at night (after 23:00)
// This strictly protects employees working on their active daytime shifts from being clocked out
$current_hour = (int)date('H');
if ($current_hour >= 23) {
    // If running late at night (11 PM or later), can include today
    $date_condition = "DATE(date) <= CURDATE()";
} else {
    // During daytime hours, ONLY auto-checkout unclosed records from yesterday or earlier
    $date_condition = "DATE(date) < CURDATE()";
}

// Find employees who clocked in but have not clocked out, and who actually had a clock_in
$sql = "SELECT id, emp_id, clock_in, date FROM attendance_logs WHERE clock_out IS NULL AND clock_in IS NOT NULL AND clock_in != '' AND $date_condition";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $count = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $id = (int)$row['id'];
        $emp_id = mysqli_real_escape_string($conn, $row['emp_id']);
        $log_date = $row['date'];
        
        // Auto clock out at 23:59:59 of that shift day
        $auto_checkout_time = $log_date . ' 23:59:59';
        
        mysqli_query($conn, "UPDATE attendance_logs SET clock_out = '$auto_checkout_time', work_hours = GREATEST(0, TIMESTAMPDIFF(MINUTE, clock_in, '$auto_checkout_time')/60.0) WHERE id = $id");
        
        $alert_msg = mysqli_real_escape_string($conn, "System Auto-Checkout: Employee $emp_id was automatically clocked out for $log_date because they forgot to clock out.");
        mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
        
        $notif_msg = mysqli_real_escape_string($conn, "You forgot to clock out on $log_date. The system has automatically recorded your checkout.");
        mysqli_query($conn, "INSERT INTO employee_notifications (emp_id, message) VALUES ('$emp_id', '$notif_msg')");
        $count++;
    }
    echo "Auto-checkout completed for " . $count . " employees.";
} else {
    echo "No pending checkouts found.";
}
?>
