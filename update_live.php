<?php
// Simple deployment script to pull latest changes from GitHub
echo "<h2>Pulling latest updates from GitHub...</h2>";
echo "<pre>";
$output = shell_exec('git pull origin master 2>&1');
echo htmlspecialchars($output);
echo "</pre>";
echo "<p>Update complete! <a href='index.php'>Go back to Dashboard</a></p>";
?>
