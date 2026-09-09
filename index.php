<?php
session_start();
include 'db_connect.php';

$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : "";
$msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM employees WHERE emp_id = '$emp_id' OR welabel_id = '$emp_id'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Check Lockout
        if ($row['lockout_time'] && strtotime($row['lockout_time']) > time()) {
            $remaining = ceil((strtotime($row['lockout_time']) - time()) / 60);
            $error = "Account locked due to too many failed attempts. Try again in $remaining minutes.";
        } else {
            if (password_verify($password, $row['password'])) {
                // Reset lockout counters on success
                mysqli_query($conn, "UPDATE employees SET login_attempts = 0, lockout_time = NULL WHERE id = {$row['id']}");
                
                // Log access
                $ip_address = $_SERVER['REMOTE_ADDR'];
                mysqli_query($conn, "INSERT INTO access_logs (emp_id, login_time, ip_address, status) VALUES ('{$row['emp_id']}', NOW(), '$ip_address', 'Success')");
                
                // Set session variables
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['emp_id'] = $row['emp_id'];
                $_SESSION['name'] = $row['name'];
                $_SESSION['role'] = $row['role'];

                // Redirect based on role
                switch ($row['role']) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'hr':
                        header("Location: hr_dashboard.php");
                        break;
                    case 'tl':
                        header("Location: tl_dashboard.php");
                        break;
                    case 'gl':
                        header("Location: gl_dashboard.php");
                        break;
                    default:
                        header("Location: employee_dashboard.php");
                        break;
                }
                exit();
            } else {
                $attempts = $row['login_attempts'] + 1;
                if ($attempts >= 5) {
                    mysqli_query($conn, "UPDATE employees SET login_attempts = $attempts, lockout_time = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = {$row['id']}");
                    $error = "Account locked for 15 minutes due to 5 failed login attempts.";
                } else {
                    mysqli_query($conn, "UPDATE employees SET login_attempts = $attempts WHERE id = {$row['id']}");
                    $error = "Invalid password. Attempt $attempts of 5.";
                }
                
                // Log failed access
                $ip_address = $_SERVER['REMOTE_ADDR'];
                mysqli_query($conn, "INSERT INTO access_logs (emp_id, login_time, ip_address, status) VALUES ('{$row['emp_id']}', NOW(), '$ip_address', 'Failed')");
            }
        }
    } else {
        $error = "No user found with that Employee ID or WeLabel ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            background-color: #0b1120;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: #0f172a;
            padding: 50px 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            border: 1px solid #1f2937;
        }

        .logo {
            font-size: 32px;
            font-weight: 800;
            color: #f8fafc;
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 15px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            display: block;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 14px;
            border: 1px solid #1e293b;
            border-radius: 12px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background: #1e293b;
            color: #f8fafc;
        }

        .form-input:focus {
            border-color: #3b82f6;
            background: #0f172a;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
        }

        .btn-login {
            background-color: #4f46e5;
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            width: 100%;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .footer-text {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
    </style>


        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>

<body>

    <div class="login-container">
        <div class="logo">
            <img src="pic/WeRide_logo.png" alt="WeRide" style="max-height: 48px; width: auto; object-fit: contain;">
        </div>
        <div class="subtitle">Lunch & Breaktime Management</div>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($msg): ?>
            <div class="error-message bg-emerald-50 text-emerald-600" style="  border: 1px solid #a7f3d0;">
                ✅ <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label class="form-label">Employee ID or WeLabel ID</label>
                <input type="text" name="emp_id" class="form-input" required placeholder="e.g., FT-2606-001 or KL_Name@welabel.ai" autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="footer-text">
            <a href="forgot_password.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Forgot Password?</a>
            <br><br>
            For access issues, please Lark Kavi :).
        </div>
    </div>

</body>

</html>
