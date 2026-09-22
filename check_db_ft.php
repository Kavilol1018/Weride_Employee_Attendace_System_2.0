<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_lunchbreak');
$emp_id = 'FT-2606-003';
$q = mysqli_query($conn, "SELECT * FROM leave_requests WHERE emp_id = '$emp_id' ORDER BY id DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($q)) {
    print_r($row);
}
echo "\n--- EMPLOYEE INFO ---\n";
$q2 = mysqli_query($conn, "SELECT annual_leave_balance, sick_leave_balance, employment_type FROM employees WHERE emp_id = '$emp_id'");
print_r(mysqli_fetch_assoc($q2));
?>
