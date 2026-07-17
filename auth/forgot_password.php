<?php
session_start();

include('../config/db.php');
include('../includes/log_action.php');

// Ensure $conn is available (fallback if config/db.php does not set it)
if (!isset($conn) || !$conn) {
    $conn = mysqli_connect('localhost', 'root', '', 'patient_tracking_system');
    if (!$conn) {
        die('Database connection failed: ' . mysqli_connect_error());
    }
}

$message = "";
$message_type = "";

if (isset($_POST['reset_password'])) {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password != $confirm_password) {

        $message = "New password and confirm password do not match.";
        $message_type = "danger";

    } else {

        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

        if (!$check) {
            die("Query failed: " . mysqli_error($conn));
        }

        if (mysqli_num_rows($check) == 0) {

            $message = "No account found with that email address.";
            $message_type = "danger";

        } else {

            $user = mysqli_fetch_assoc($check);

            $hashed_password = md5($new_password);

            $update = "UPDATE users SET password='$hashed_password' WHERE email='$email'";

            if (mysqli_query($conn, $update)) {

                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = $user['role'];

                logAction($conn, "Password reset using forgot password for email: " . $email);

                unset($_SESSION['fullname']);
                unset($_SESSION['role']);

                $message = "Password reset successfully. You can now login.";
                $message_type = "success";

            } else {

                $message = "Password reset failed: " . mysqli_error($conn);
                $message_type = "danger";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Patient Tracking System</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd, #198754);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .reset-card {
            width: 430px;
            max-width: 95%;
            background: white;
            border-radius: 22px;
            box-shadow: 0 18px 42px rgba(0,0,0,0.25);
            padding: 35px;
        }

        .icon-circle {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            background: #e9f2ff;
            color: #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: auto;
            margin-bottom: 18px;
        }

        .form-control {
            height: 48px;
            border-radius: 12px;
        }

        .btn {
            border-radius: 12px;
            height: 46px;
        }
    </style>
</head>

<body>

<div class="reset-card">

    <div class="icon-circle">
        <i class="bi bi-key-fill"></i>
    </div>

    <h3 class="text-center mb-2">Reset Password</h3>

    <p class="text-center text-muted mb-4">
        Enter your registered email and new password
    </p>

    <?php if ($message != "") { ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
    <?php } ?>

    <form method="POST" action="/patient_tracking_system/auth/forgot_password.php">

        <div class="mb-3">
            <label>Email Address</label>
            <input type="email" 
                   name="email" 
                   class="form-control" 
                   placeholder="Enter registered email"
                   required>
        </div>

        <div class="mb-3">
            <label>New Password</label>
            <input type="password" 
                   name="new_password" 
                   class="form-control" 
                   placeholder="Enter new password"
                   required>
        </div>

        <div class="mb-3">
            <label>Confirm Password</label>
            <input type="password" 
                   name="confirm_password" 
                   class="form-control" 
                   placeholder="Confirm new password"
                   required>
        </div>

        <button type="submit" name="reset_password" class="btn btn-primary w-100">
            Reset Password
        </button>

    </form>

    <div class="text-center mt-3">
        <a href="/patient_tracking_system/auth/login.php">
            Back to Login
        </a>
    </div>

</div>

</body>
</html>