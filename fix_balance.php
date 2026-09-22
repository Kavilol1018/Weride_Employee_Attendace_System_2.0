<?php
include 'db_connect.php';
mysqli_query($conn, "UPDATE employees SET annual_leave_balance = 5.5 WHERE emp_id = 'IN-2606-002'");
echo "Serene's Annual Leave balance has been corrected to 5.5!";
?>
