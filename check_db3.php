<?php
$c = mysqli_connect('localhost', 'root', '', 'u251904595_lunchbreak');
$r = mysqli_query($c, "SELECT * FROM leave_requests ORDER BY id DESC LIMIT 5");
while($d = mysqli_fetch_assoc($r)) {
    print_r($d);
}
?>
