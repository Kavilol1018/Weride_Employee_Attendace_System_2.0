<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_kavinesganesen');
if (!$conn) die('Failed');
$sql = "SELECT COUNT(*) FROM short_breaks WHERE emp_id = 'FT-2606-001' AND DATE(break_start) >= '2026-07-01'";
$res = mysqli_query($conn, $sql);
print_r(mysqli_fetch_assoc($res));
?>
