<?php
session_start();
include 'db_connect.php';

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
    // For now, this is a placeholder. You would typically send an email here.
    $msg = "If an account with that ID exists, an email has been sent to the registered address with instructions to reset your password.";
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Forgot Password</title>
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
            color: #f8fafc;
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
            letter-spacing: -1px;
        }

        .subtitle {
            color: #94a3b8;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .msg-box {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(16, 185, 129, 0.2);
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
        <div class="subtitle">Reset Password</div>

        <?php if ($msg): ?>
            <div class="msg-box">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label">Employee ID</label>
                <input type="text" name="emp_id" class="form-input" required placeholder="e.g., WR-001" autofocus>
            </div>

            <button type="submit" class="btn-login">Send Reset Link</button>
        </form>

        <div class="footer-text">
            Remembered your password? <a href="index.php" style="color: #3b82f6; text-decoration: none; font-weight: 600;">Sign in</a>
        </div>
    </div>

</body>

</html>
