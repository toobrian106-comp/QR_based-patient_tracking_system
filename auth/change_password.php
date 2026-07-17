<?php 
include('../includes/auth_check.php');
require_once('../config/db.php');

if (!isset($conn) || $conn === false) {
    die("Database connection not available.");
}

$message = "";
$message_type = "";

if (isset($_POST['change_password'])) {

    $user_id = $_SESSION['user_id'];

    $current_password = md5($_POST['current_password']);
    $new_password = md5($_POST['new_password']);
    $confirm_password = md5($_POST['confirm_password']);

    $check_query = "SELECT * FROM users WHERE id='$user_id' AND password='$current_password'";
    $check_result = mysqli_query($conn, $check_query);

    if (!$check_result) {
        die("Query failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($check_result) == 0) {

        $message = "Current password is incorrect.";
        $message_type = "danger";

    } elseif ($new_password != $confirm_password) {

        $message = "New password and confirm password do not match.";
        $message_type = "danger";

    } else {

        $update_query = "UPDATE users SET password='$new_password' WHERE id='$user_id'";

        if (mysqli_query($conn, $update_query)) {

            $message = "Password changed successfully.";
            $message_type = "success";

        } else {

            $message = "Password update failed: " . mysqli_error($conn);
            $message_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Password</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background: #eef3f8;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .password-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 14px 30px rgba(0,0,0,0.13);
        }

        .card-header {
            border-radius: 20px 20px 0 0 !important;
        }

        .form-control {
            height: 48px;
            border-radius: 12px;
        }

        .btn {
            border-radius: 12px;
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-6">

            <div class="card password-card">

                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

                    <h4 class="mb-0">
                        <i class="bi bi-lock-fill"></i>
                        Change Password
                    </h4>

                    <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-light btn-sm">
                        Dashboard
                    </a>

                </div>

                <div class="card-body p-4">

                    <?php if ($message != "") { ?>
                        <div class="alert alert-<?php echo $message_type; ?>">
                            <?php echo $message; ?>
                        </div>
                    <?php } ?>

                    <form method="POST" action="/patient_tracking_system/auth/change_password.php">

                        <div class="mb-3">
                            <label>Current Password</label>
                            <input type="password" 
                                   name="current_password" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="mb-3">
                            <label>New Password</label>
                            <input type="password" 
                                   name="new_password" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="mb-3">
                            <label>Confirm New Password</label>
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   required>
                        </div>

                        <button type="submit" name="change_password" class="btn btn-primary">
                            Update Password
                        </button>

                        <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-secondary">
                            Cancel
                        </a>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>