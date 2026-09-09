<?php
include 'db_connect.php';
mysqli_query($conn, "UPDATE leave_requests SET leave_type = 'Emergency Leave' WHERE leave_type = 'Early Leave'");
// checkout_status is not a real column, it's computed dynamically in PHP.
// mysqli_query($conn, "UPDATE attendance_logs SET checkout_status = 'Emergency Leave' WHERE checkout_status = 'Early Leave'");
echo 'DB updated.';
?>
