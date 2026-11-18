<?php
$conn = @mysqli_connect('srv2101.hstgr.io', 'u251904595_phase2_1', 'Weridekl1234', 'u251904595_phase2_1', 3306);
if (!$conn) die("Failed\n");

// Check if we can write a tiny PHP file to test on the server or inspect extensions
// Wait, we don't have direct write access to Hostinger web root via MySQL, but wait!
// Can MySQL tell us anything? No.
echo "Connected to DB\n";

