<?php require 'db_connect.php'; $res = mysqli_query($conn, 'SHOW COLUMNS FROM employees'); while($row = mysqli_fetch_assoc($res)) { echo $row['Field'] . ' '; } ?>
