<?php 
include_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($conn) || !$conn) {
    die('Database connection not established.');
}

$total_patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM patients"))['total'];
$total_attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM attendance"))['total'];
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background:#eef3f8;
            font-family: Arial, sans-serif;
        }

        .report-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: 0.3s;
            height: 100%;
        }

        .report-card:hover {
            transform: translateY(-5px);
        }

        .icon-box {
            font-size: 38px;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold">Reports Center</h2>
            <p class="text-muted mb-0">Central access point for system reports and analytics.</p>
        </div>

        <a href="../dashboard/index.php" class="btn btn-primary">
            Back to Dashboard
        </a>
    </div>

    <div class="row mb-4">

        <div class="col-md-4 mb-3">
            <div class="card report-card">
                <div class="card-body">
                    <h6>Total Patients</h6>
                    <h2><?php echo $total_patients; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card report-card">
                <div class="card-body">
                    <h6>Total Attendance Records</h6>
                    <h2><?php echo $total_attendance; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card report-card">
                <div class="card-body">
                    <h6>System Users</h6>
                    <h2><?php echo $total_users; ?></h2>
                </div>
            </div>
        </div>

    </div>

    <div class="row">

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">📅</div>
                    <h5>Attendance Report</h5>
                    <p class="text-muted">View all patient visits, services received, and attendance history.</p>
                    <a href="reports.php" class="btn btn-primary">Open Report</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">📈</div>
                    <h5>Adherence Report</h5>
                    <p class="text-muted">Analyze expected visits, actual visits, missed visits, and adherence percentage.</p>
                    <a href="adherence_report.php" class="btn btn-success">Open Report</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">⚠</div>
                    <h5>Defaulting Detection</h5>
                    <p class="text-muted">Identify patients who have never attended or whose last visit is overdue.</p>
                    <a href="defaulting_detection.php" class="btn btn-danger">Open Report</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">👥</div>
                    <h5>Patient Records</h5>
                    <p class="text-muted">Open the patient management module and access patient profiles.</p>
                    <a href="../patients/manage_patients.php" class="btn btn-info">Open Patients</a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">📥</div>
                    <h5>Export Attendance CSV</h5>
                    <p class="text-muted">Download attendance records for offline analysis and documentation.</p>
                    <a href="export_csv.php" class="btn btn-dark">Export CSV</a>
                </div>
            </div>
        </div>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'Admin'){ ?>

        <div class="col-md-4 mb-4">
            <div class="card report-card">
                <div class="card-body">
                    <div class="icon-box">💾</div>
                    <h5>Backup Data</h5>
                    <p class="text-muted">Access system backup options for safeguarding records.</p>
                    <a href="backup_data.php" class="btn btn-secondary">Open Backup</a>
                </div>
            </div>
        </div>

        <?php } ?>

    </div>

</div>

</body>
</html>