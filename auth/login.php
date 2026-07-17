<?php
session_start();

include('../config/db.php');
include('../includes/log_action.php');

$error = "";

if (isset($_POST['login'])) {

    if (!isset($conn) || !$conn) {
        $error = "Database connection error. Please try again later.";
    } else if (!isset($_POST['email']) || !isset($_POST['password'])) {
        $error = "Email and password are required.";
    } else {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = "SELECT * FROM users WHERE email='$email' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Login query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) == 1) {

        $user = mysqli_fetch_assoc($result);

       if (
    $password == $user['password'] || 
    md5($password) == $user['password'] || 
    password_verify($password, $user['password'])
) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'] ?? $user['username'] ?? 'System Admin';
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'] ?? 'Admin';

            logAction(
                $conn,
                "User Login",
                ($_SESSION['fullname'] ?? 'User') . " logged into the system using email " . $_SESSION['email'] . "."
            );

            header("Location: ../dashboard/index.php");
            exit();

        } else {
            $error = "Invalid email or password.";
        }

    } else {
        $error = "Invalid email or password.";
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Patient Tracking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd, #198754);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }

        .login-wrapper {
            width: 850px;
            max-width: 95%;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }

        .left-panel {
            background: linear-gradient(180deg, #0d6efd, #084298);
            color: white;
            padding: 60px 35px;
            text-align: center;
        }

        .left-panel .icon-circle {
            width: 100px;
            height: 100px;
            margin: 0 auto 25px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
        }

        .right-panel {
            padding: 50px 40px;
        }

        .form-control {
            height: 48px;
        }

        .input-group-text {
            width: 50px;
            justify-content: center;
        }

        .btn-login {
            height: 48px;
            border-radius: 12px;
            font-weight: bold;
        }

        .login-title {
            font-weight: bold;
            color: #0d6efd;
        }

        @media(max-width: 768px) {
            .left-panel {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="login-wrapper">

    <div class="row g-0">

        <div class="col-md-6 left-panel">

            <div class="icon-circle">
                <i class="bi bi-qr-code-scan"></i>
            </div>

            <h2 class="fw-bold">Patient Tracking System</h2>

            <p class="mt-3">
                QR-Based Patient Identification and Attendance Tracking Portal
            </p>

        </div>

        <div class="col-md-6 right-panel">

            <h2 class="login-title text-center mb-2">Welcome Back</h2>

            <p class="text-muted text-center mb-4">
                Login to continue to the dashboard
            </p>

            <?php if (!empty($error)) { ?>

                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php } ?>

            <form method="POST">

                <div class="mb-3">
                    <label>Email Address</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-envelope"></i>
                        </span>

                        <input type="email"
                               name="email"
                               class="form-control"
                               placeholder="Enter email address"
                               required>
                    </div>
                </div>

                <div class="mb-4">
                    <label>Password</label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>

                        <input type="password"
                               name="password"
                               class="form-control"
                               placeholder="Enter password"
                               required>
                    </div>
                </div>

                <button type="submit"
                        name="login"
                        class="btn btn-primary w-100 btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>