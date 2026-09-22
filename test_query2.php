<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_kavinesganesen');
if (!$conn) die('Failed');
$sql = "SELECT count(*) FROM lunch_breaks WHERE status = 'returned' AND employee_id = 'IN-2606-011'";
$res = mysqli_query($conn, $sql);
print_r(mysqli_fetch_assoc($res));
?>
