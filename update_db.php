<?php
include 'db_connect.php';

$queries = [
    "ALTER TABLE employees MODIFY annual_leave_balance DECIMAL(5,1) DEFAULT 14.0",
    "ALTER TABLE employees MODIFY sick_leave_balance DECIMAL(5,1) DEFAULT 14.0",
    "ALTER TABLE employees MODIFY annual_leave_quota DECIMAL(5,1) DEFAULT 8.0",
    "ALTER TABLE employees MODIFY sick_leave_quota DECIMAL(5,1) DEFAULT 14.0",
    "ALTER TABLE employees MODIFY unpaid_leave_quota DECIMAL(5,1) DEFAULT 30.0",
];

echo "<h2>Database Update Script</h2>";
echo "<ul>";

foreach ($queries as $query) {
    if (mysqli_query($conn, $query)) {
        echo "<li style='color:green;'>SUCCESS: $query</li>";
    } else {
        echo "<li style='color:red;'>FAILED: $query<br>Error: " . mysqli_error($conn) . "</li>";
    }
}

echo "</ul>";
echo "<p>Update complete. You can now delete this file.</p>";
?>
