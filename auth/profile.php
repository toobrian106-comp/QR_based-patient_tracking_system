<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!isset($conn)) {
    die("Database connection failed.");
}

$user_id = $_SESSION['user_id'];

$query = "SELECT id, fullname, email, role FROM users WHERE id='$user_id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
    echo "User not found.";
    exit();
}

$user = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Profile</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background: #eef3f8;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .profile-card {
            border: none;
            border-radius: 22px;
            box-shadow: 0 16px 35px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #0d6efd, #064fb4);
            color: white;
            padding: 35px;
            text-align: center;
        }

        .profile-icon {
            width: 95px;
            height: 95px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: auto;
            margin-bottom: 15px;
        }

        .profile-body {
            padding: 30px;
        }

        .info-row {
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }

        .info-label {
            font-weight: bold;
            color: #333;
        }

        .btn {
            border-radius: 12px;
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <div class="row justify-content-center">

        <div class="col-md-7">

            <div class="card profile-card">

                <div class="profile-header">

                    <div class="profile-icon">
                        <i class="bi bi-person-circle"></i>
                    </div>

                    <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>

                    <span class="badge bg-light text-primary p-2">
                        <?php echo htmlspecialchars($user['role']); ?>
                    </span>

                </div>

                <div class="profile-body">

                    <div class="row info-row">
                        <div class="col-md-4 info-label">User ID</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($user['id']); ?></div>
                    </div>

                    <div class="row info-row">
                        <div class="col-md-4 info-label">Full Name</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($user['fullname']); ?></div>
                    </div>

                    <div class="row info-row">
                        <div class="col-md-4 info-label">Email Address</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>

                    <div class="row info-row">
                        <div class="col-md-4 info-label">Role</div>
                        <div class="col-md-8"><?php echo htmlspecialchars($user['role']); ?></div>
                    </div>

                    <div class="mt-4 d-flex justify-content-between">

                        <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>

                        <a href="/patient_tracking_system/auth/change_password.php" class="btn btn-warning">
                            <i class="bi bi-lock"></i> Change Password
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>