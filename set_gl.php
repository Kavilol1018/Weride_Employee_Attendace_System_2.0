<?php
include 'db_connect.php';
mysqli_query($conn, "UPDATE employees SET group_leader_id = 'GL001' WHERE emp_id = 'IN-2606-002'");
echo "Done";
?>
