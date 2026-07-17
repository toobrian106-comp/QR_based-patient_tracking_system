<?php 
include('../includes/auth_check.php');
require_once dirname(__DIR__) . '/config/db.php';

/*
    Expected visits:
    For this project, we assume each patient should visit once per month.
    For 12-month adherence tracking, expected visits = 12.
*/

$expected_visits = 12;

$query = "
SELECT 
    patients.id,
    patients.patient_id,
    patients.fullname,
    patients.gender,
    patients.location,
    COUNT(attendance.id) AS actual_visits
FROM patients
LEFT JOIN attendance 
ON patients.patient_id = attendance.patient_id
GROUP BY patients.id, patients.patient_id, patients.fullname, patients.gender, patients.location
ORDER BY patients.fullname ASC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die('Query failed: ' . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Adherence Report</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-5">

    <div class="card shadow">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">Patient Adherence Report</h4>

            <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                Dashboard
            </a>

        </div>

        <div class="card-body">

            <p>
                This report shows each patient's attendance consistency based on an expected 
                <strong>12 visits per year</strong>.
            </p>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender</th>
                            <th>Location</th>
                            <th>Expected Visits</th>
                            <th>Actual Visits</th>
                            <th>Missed Visits</th>
                            <th>Adherence %</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {

                        while ($row = mysqli_fetch_assoc($result)) {

                            $actual_visits = $row['actual_visits'];
                            $missed_visits = $expected_visits - $actual_visits;

                            if ($missed_visits < 0) {
                                $missed_visits = 0;
                            }

                            $adherence_percentage = ($actual_visits / $expected_visits) * 100;
                            $adherence_percentage = round($adherence_percentage, 1);

                            if ($adherence_percentage >= 80) {
                                $status = "Good";
                                $badge = "success";
                            } elseif ($adherence_percentage >= 50) {
                                $status = "Moderate";
                                $badge = "warning";
                            } else {
                                $status = "Defaulting";
                                $badge = "danger";
                            }
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['gender']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo $expected_visits; ?></td>
                            <td><?php echo $actual_visits; ?></td>
                            <td><?php echo $missed_visits; ?></td>
                            <td><?php echo $adherence_percentage; ?>%</td>
                            <td>
                                <span class="badge bg-<?php echo $badge; ?>">
                                    <?php echo $status; ?>
                                </span>
                            </td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="10" class="text-center">
                                No patients found
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