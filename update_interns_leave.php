<?php
require 'db_connect.php';

// Update interns' leave balances to 0 for Annual and Sick leave
mysqli_query($conn, "UPDATE employees SET annual_leave_quota = 0, annual_leave_balance = 0, sick_leave_quota = 0, sick_leave_balance = 0 WHERE employment_type LIKE 'Intern%'");

echo "Interns leave balances updated to 0";
?>
