<?php 
include('../includes/auth_check.php');
include('../config/db.php');

/*
    Defaulting logic:
    A patient is marked as defaulting if:
    - They have never attended, OR
    - Their last visit was more than 30 days ago.
*/

$default_days = 30;

$query = "
SELECT 
    patients.id,
    patients.patient_id,
    patients.fullname,
    patients.gender,
    patients.phone,
    patients.location,
    patients.created_at,
    MAX(attendance.visit_date) AS last_visit,
    COUNT(attendance.id) AS total_visits
FROM patients
LEFT JOIN attendance 
ON patients.patient_id = attendance.patient_id
GROUP BY 
    patients.id,
    patients.patient_id,
    patients.fullname,
    patients.gender,
    patients.phone,
    patients.location,
    patients.created_at
ORDER BY patients.fullname ASC
";

if (!isset($conn) || !$conn) {
    die("Database connection failed.");
}

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$today = date("Y-m-d");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Defaulting Patients Detection</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-5">

    <div class="card shadow">

        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Defaulting Patients Detection</h4>

            <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                Dashboard
            </a>
        </div>

        <div class="card-body">

            <div class="alert alert-warning">
                Patients are considered <strong>defaulting</strong> if they have never attended or if their last visit was more than 
                <strong><?php echo $default_days; ?> days</strong> ago.
            </div>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Total Visits</th>
                            <th>Last Visit</th>
                            <th>Days Since Last Visit</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php
                    $count = 1;
                    $found_defaulting = false;

                    while ($row = mysqli_fetch_assoc($result)) {

                        $last_visit = $row['last_visit'];
                        $days_since_last_visit = "Never Visited";
                        $is_defaulting = false;

                        if (empty($last_visit)) {
                            $is_defaulting = true;
                        } else {
                            $date1 = new DateTime($last_visit);
                            $date2 = new DateTime($today);
                            $diff = $date1->diff($date2);
                            $days_since_last_visit = $diff->days;

                            if ($days_since_last_visit > $default_days) {
                                $is_defaulting = true;
                            }
                        }

                        if ($is_defaulting) {
                            $found_defaulting = true;
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo htmlspecialchars($row['total_visits']); ?></td>
                            <td>
                                <?php 
                                echo empty($last_visit) ? "No Visit Recorded" : htmlspecialchars($last_visit); 
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($days_since_last_visit); ?></td>
                            <td>
                                <span class="badge bg-danger">
                                    Defaulting
                                </span>
                            </td>
                        </tr>

                    <?php 
                        }
                    }

                    if (!$found_defaulting) {
                    ?>

                        <tr>
                            <td colspan="10" class="text-center">
                                No defaulting patients found.
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