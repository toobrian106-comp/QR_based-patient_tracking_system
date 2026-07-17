<?php 
include('../includes/auth_check.php');
include('../config/db.php');

$query = "
SELECT attendance.*, patients.fullname
FROM attendance
INNER JOIN patients 
ON attendance.patient_id = patients.patient_id
ORDER BY attendance.id DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Attendance Records</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <!-- HEADER -->
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">Attendance Records</h4>

            <div>

                <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-light btn-sm">
                    Dashboard
                </a>

                <a href="/patient_tracking_system/attendance/scan_qr.php" class="btn btn-success btn-sm">
                    Scan QR
                </a>

            </div>

        </div>

        <!-- BODY -->
        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">

                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Visit Date</th>
                            <th>Service Given</th>
                            <th>Notes</th>
                            <th>Actions</th>
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

                            <td>
                                <?php echo htmlspecialchars($row['patient_id']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['fullname']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['visit_date']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['service_given']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['notes']); ?>
                            </td>

                            <td>

                                <a href="/patient_tracking_system/attendance/edit_attendance.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-warning btn-sm">
                                    Edit
                                </a>

                                <a href="/patient_tracking_system/attendance/delete_attendance.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this attendance record?');">
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="7" class="text-center">
                                No attendance records found
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