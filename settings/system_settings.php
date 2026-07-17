<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if ($_SESSION['role'] !== 'Admin') {
    echo "<script>
            alert('Access denied. Admin only.');
            window.location.href='/patient_tracking_system/dashboard/index.php';
          </script>";
    exit();
}

$total_users_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$total_users = mysqli_fetch_assoc($total_users_query)['total'];

$total_patients_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM patients");
$total_patients = mysqli_fetch_assoc($total_patients_query)['total'];

$total_attendance_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM attendance");
$total_attendance = mysqli_fetch_assoc($total_attendance_query)['total'];

$total_logs_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM audit_logs");
$total_logs = mysqli_fetch_assoc($total_logs_query)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background: #eef3f8;
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .settings-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 25px rgba(0,0,0,0.12);
        }

        .settings-header {
            background: linear-gradient(135deg, #0d6efd, #064fb4);
            color: white;
            padding: 25px;
            border-radius: 18px 18px 0 0;
        }

        .info-box {
            border-radius: 16px;
            padding: 20px;
            color: white;
            box-shadow: 0 10px 22px rgba(0,0,0,0.12);
        }

        .info-box h3 {
            font-weight: 800;
        }

        .setting-row {
            padding: 14px;
            border-bottom: 1px solid #eee;
        }

        .setting-label {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>

<body>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>
            <i class="bi bi-gear-fill"></i>
            System Settings
        </h2>

        <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-primary">
            Dashboard
        </a>

    </div>

    <div class="row mb-4">

        <div class="col-md-3 mb-3">
            <div class="info-box bg-primary">
                <h6>Total Users</h6>
                <h3><?php echo $total_users; ?></h3>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="info-box bg-success">
                <h6>Total Patients</h6>
                <h3><?php echo $total_patients; ?></h3>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="info-box bg-warning text-dark">
                <h6>Attendance Records</h6>
                <h3><?php echo $total_attendance; ?></h3>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="info-box bg-danger">
                <h6>Audit Logs</h6>
                <h3><?php echo $total_logs; ?></h3>
            </div>
        </div>

    </div>

    <div class="card settings-card">

        <div class="settings-header">
            <h4 class="mb-0">Application Information</h4>
        </div>

        <div class="card-body">

            <div class="setting-row row">
                <div class="col-md-4 setting-label">System Name</div>
                <div class="col-md-8">QR-Based Patient Tracking System</div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">Project Type</div>
                <div class="col-md-8">Final Year System Project</div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">Database</div>
                <div class="col-md-8">patient_tracking_db</div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">Logged-in User</div>
                <div class="col-md-8"><?php echo htmlspecialchars($_SESSION['fullname']); ?></div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">User Role</div>
                <div class="col-md-8"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">Core Modules</div>
                <div class="col-md-8">
                    Patient Registration, QR Identification, Attendance Tracking, Reports, Audit Logs, Backup
                </div>
            </div>

            <div class="setting-row row">
                <div class="col-md-4 setting-label">System Status</div>
                <div class="col-md-8">
                    <span class="badge bg-success">Active</span>
                </div>
            </div>

        </div>

    </div>

</div>

</body>
</html>