<?php
include 'db_connect.php';

$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $new_password = $_POST['new_password'];
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $check = mysqli_query($conn, "SELECT id FROM employees WHERE emp_id = '$emp_id'");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE employees SET password = '$hashed', login_attempts = 0, lockout_time = NULL WHERE emp_id = '$emp_id'");
        $msg = "Password for $emp_id has been successfully reset!";
    } else {
        $msg = "Employee ID not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Password Reset</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f0f2f5; }
        .box { background: white; padding: 30px; border-radius: 10px; max-width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        input, button { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ccc; }
        button { background: #3b82f6; color: white; border: none; cursor: pointer; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>
    <div class="box">
        <h2>Local Password Reset</h2>
        <?php if($msg) echo "<p style='color:green; font-weight:bold;'>$msg</p>"; ?>
        <form method="POST">
            <label>Employee ID:</label>
            <input type="text" name="emp_id" placeholder="e.g. WR-001" required>
            
            <label>New Password:</label>
            <input type="password" name="new_password" placeholder="Enter new password" required>
            
            <button type="submit">Reset Password</button>
        </form>
        <p><a href="index.php">Back to Login</a></p>
    </div>
</body>
</html>
