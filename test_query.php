<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_phase2');
if (!$conn) die('Failed');
$sql = "SELECT * FROM lunch_breaks WHERE status = 'returned' AND employee_id = 'FT-2606-001' AND DATE(break_start) BETWEEN '2026-07-01' AND '2026-09-17'";
$res = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
?>
