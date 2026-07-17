<?php 
include('../includes/auth_check.php');
include('../config/db.php');

$query = "
SELECT patients.*
FROM patients
LEFT JOIN attendance 
ON patients.patient_id = attendance.patient_id
WHERE attendance.patient_id IS NULL
ORDER BY patients.id DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Defaulting Patients Report</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow">

        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">Defaulting Patients Report</h4>

            <div>
                <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-light btn-sm">
                    Dashboard
                </a>

                <a href="/patient_tracking_system/reports/reports.php" class="btn btn-light btn-sm">
                    Reports
                </a>
            </div>

        </div>

        <div class="card-body">

            <p class="text-muted">
                This report shows patients who have been registered but have no attendance record.
            </p>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>QR Code</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Date Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>

                            <td>
                                <?php if (!empty($row['qr_code'])) { ?>
                                    <img src="/patient_tracking_system/assets/qr_codes/<?php echo htmlspecialchars($row['qr_code']); ?>" 
                                         width="65">
                                <?php } else { ?>
                                    No QR
                                <?php } ?>
                            </td>

                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><?php echo htmlspecialchars($row['age']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>

                            <td>
                                <a href="/patient_tracking_system/patients/patient_profile.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    View
                                </a>

                                <a href="/patient_tracking_system/attendance/scan_qr.php" 
                                   class="btn btn-success btn-sm">
                                    Record Visit
                                </a>
                            </td>
                        </tr>

                    <?php 
                        }
                    } else {
                    ?>

                        <tr>
                            <td colspan="10" class="text-center">
                                No defaulting patients found
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>