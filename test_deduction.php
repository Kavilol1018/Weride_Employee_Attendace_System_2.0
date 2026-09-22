<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_lunchbreak');
$leave_id = 22; // The Annual Leave 4 days request for GL003
$emp_id = 'GL003';
$leave_type = 'Annual Leave';

$lr_data_q = mysqli_query($conn, "SELECT duration FROM leave_requests WHERE id = $leave_id");
$lr_data = mysqli_fetch_assoc($lr_data_q);
$days = (float)($lr_data['duration'] ?? 1);

echo "Days to deduct: $days\n";

$abbr = 'L';
$lt = strtoupper($leave_type);
if (strpos($lt, 'HALF') !== false) { $abbr = 'HD'; $days = 0.5; }
elseif (strpos($lt, 'EMERGENCY') !== false) { $abbr = 'EMERGENCY'; $days = 0; }
elseif (strpos($lt, 'ANNUAL') !== false) { $abbr = 'AL'; }
elseif (strpos($lt, 'SICK') !== false) { $abbr = 'SL'; }
elseif (strpos($lt, 'UNPLANNED') !== false) { $abbr = 'UL'; }

echo "Abbr: $abbr\n";

if ($abbr === 'AL') {
    $q = "UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE emp_id = '$emp_id'";
    echo "Query: $q\n";
    mysqli_query($conn, $q);
    echo "Rows affected: " . mysqli_affected_rows($conn) . "\n";
}

$emp_q = mysqli_query($conn, "SELECT annual_leave_balance FROM employees WHERE emp_id = '$emp_id'");
$emp_d = mysqli_fetch_assoc($emp_q);
echo "New AL Balance: " . $emp_d['annual_leave_balance'] . "\n";

?>
