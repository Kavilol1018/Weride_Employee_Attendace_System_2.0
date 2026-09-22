<?php
include 'db_connect.php';

echo "Recalculating Unpaid and Hospitalization Leave balances...<br>";

// Reset everyone's balances to the defaults first
mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = 0.0, hospitalization_leave_balance = CASE WHEN LOWER(employment_type) = 'intern' THEN 0.0 ELSE 60.0 END");

// Fetch all approved leave requests for Unpaid and Hospitalization
$query = mysqli_query($conn, "SELECT emp_id, leave_type, duration FROM leave_requests WHERE status IN ('approved') AND (leave_type LIKE '%Unpaid%' OR leave_type LIKE '%Hospitalization%')");

while ($row = mysqli_fetch_assoc($query)) {
    $emp_id = $row['emp_id'];
    $duration = (float)$row['duration'];
    $type = strtoupper($row['leave_type']);
    
    if (strpos($type, 'UNPAID') !== false) {
        mysqli_query($conn, "UPDATE employees SET unpaid_leave_balance = unpaid_leave_balance + $duration WHERE emp_id = '$emp_id'");
    } elseif (strpos($type, 'HOSPITALIZATION') !== false) {
        mysqli_query($conn, "UPDATE employees SET hospitalization_leave_balance = GREATEST(0, hospitalization_leave_balance - $duration) WHERE emp_id = '$emp_id' AND LOWER(employment_type) != 'intern'");
    }
}

echo "Done! The leave balances have been retroactively updated.";
?>
