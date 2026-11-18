<?php
$conn = mysqli_connect('localhost','root','','weride_employee_system');
if(!$conn) die('DB connection failed: ' . mysqli_connect_error());

$emp_id = 'E001';
$target_year = 2026;

$query = "
    UPDATE employees e 
    SET 
        annual_leave_balance = annual_leave_quota - (
            IFNULL((SELECT COUNT(*) FROM attendance_logs WHERE emp_id = e.emp_id AND status = 'AL' AND YEAR(date) = '$target_year'), 0)
            + IFNULL((SELECT COUNT(*) FROM attendance_logs WHERE emp_id = e.emp_id AND status = '0.5 AL' AND YEAR(date) = '$target_year'), 0) * 0.5
        )
    WHERE emp_id = '$emp_id'
";
$res = mysqli_query($conn, $query);
if(!$res) {
    echo 'Error: ' . mysqli_error($conn);
} else {
    echo 'Query success';
}
