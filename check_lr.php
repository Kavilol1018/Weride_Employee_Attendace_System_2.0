<?php
$conn = mysqli_connect('localhost', 'root', '', 'u251904595_lunchbreak');
$q = mysqli_query($conn, "SELECT id, emp_id, leave_type, duration, status FROM leave_requests ORDER BY id DESC LIMIT 10");
while($r = mysqli_fetch_assoc($q)) {
    print_r($r);
}
?>
