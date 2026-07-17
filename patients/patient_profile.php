<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: /patient_tracking_system/patients/manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$patient_query = "SELECT * FROM patients WHERE id='$id'";
$patient_result = mysqli_query($conn, $patient_query);

if (!$patient_result) {
    die("Patient query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($patient_result) == 0) {
    echo "Patient not found.";
    exit();
}

$patient = mysqli_fetch_assoc($patient_result);
$patient_id = $patient['patient_id'];

// Total visits
$total_visits_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id'
");
$total_visits = mysqli_fetch_assoc($total_visits_query)['total'];

// Expected visits for one year
$expected_visits = 12;

// Adherence percentage
$adherence_percentage = 0;

if ($expected_visits > 0) {
    $adherence_percentage = round(($total_visits / $expected_visits) * 100, 1);
}

if ($adherence_percentage > 100) {
    $adherence_percentage = 100;
}

// Food basket count
$food_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id' 
    AND service_given LIKE '%Food Basket%'
");
$food_count = mysqli_fetch_assoc($food_query)['total'];

// Vitamin count
$vitamin_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id' 
    AND service_given LIKE '%Vitamin Supplements%'
");
$vitamin_count = mysqli_fetch_assoc($vitamin_query)['total'];

// Attendance history
$history_query = mysqli_query($conn, "
    SELECT * FROM attendance 
    WHERE patient_id='$patient_id'
    ORDER BY visit_date DESC, id DESC
");

if (!$history_query) {
    die("History query failed: " . mysqli_error($conn));
}

// Adherence status based on percentage
if ($adherence_percentage >= 80) {
    $adherence_status = "High Adherence";
    $badge = "success";
} elseif ($adherence_percentage >= 50) {
    $adherence_status = "Moderate Adherence";
    $badge = "warning";
} else {
    $adherence_status = "Low Adherence";
    $badge = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Profile</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>Patient Profile</h2>

        <div>
            <a href="/patient_tracking_system/patients/manage_patients.php" class="btn btn-secondary">
                Back to Patients
            </a>

            <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-primary">
                Dashboard
            </a>
        </div>

    </div>

    <div class="row">

        <!-- PATIENT DETAILS -->
        <div class="col-md-4 mb-4">

            <div class="card shadow">

                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Patient Details</h5>
                </div>

                <div class="card-body text-center">

                    <?php if (!empty($patient['qr_code'])) { ?>
                        <img src="/patient_tracking_system/assets/qr_codes/<?php echo htmlspecialchars($patient['qr_code']); ?>" 
                             width="160" class="mb-3">
                    <?php } else { ?>
                        <p>No QR Code Available</p>
                    <?php } ?>

                    <h4><?php echo htmlspecialchars($patient['fullname']); ?></h4>

                    <p>
                        <strong>Patient ID:</strong> 
                        <?php echo htmlspecialchars($patient['patient_id']); ?>
                    </p>

                    <hr>

                    <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
                    <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($patient['location']); ?></p>
                    <p><strong>Registered:</strong> <?php echo htmlspecialchars($patient['created_at']); ?></p>

                    <span class="badge bg-<?php echo $badge; ?> p-2">
                        <?php echo $adherence_status; ?>
                    </span>

                    <div class="mt-3">

                        <a href="/patient_tracking_system/patients/print_qr_card.php?id=<?php echo $patient['id']; ?>" 
                           class="btn btn-info btn-sm mb-2" target="_blank">
                            Print QR Card
                        </a>

                        <a href="/patient_tracking_system/patients/print_patient_profile.php?id=<?php echo $patient['id']; ?>" 
                           class="btn btn-success btn-sm mb-2" target="_blank">
                            Print Full Profile
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- STATISTICS AND HISTORY -->
        <div class="col-md-8 mb-4">

            <div class="row">

                <div class="col-md-3 mb-3">
                    <div class="card shadow bg-success text-white">
                        <div class="card-body">
                            <h6>Total Visits</h6>
                            <h2><?php echo $total_visits; ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card shadow bg-primary text-white">
                        <div class="card-body">
                            <h6>Expected Visits</h6>
                            <h2><?php echo $expected_visits; ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card shadow bg-warning text-dark">
                        <div class="card-body">
                            <h6>Food Baskets</h6>
                            <h2><?php echo $food_count; ?></h2>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 mb-3">
                    <div class="card shadow bg-info text-white">
                        <div class="card-body">
                            <h6>Vitamins</h6>
                            <h2><?php echo $vitamin_count; ?></h2>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ADHERENCE PERCENTAGE -->
            <div class="card shadow mt-3">

                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Adherence Percentage</h5>
                </div>

                <div class="card-body">

                    <h4><?php echo $adherence_percentage; ?>%</h4>

                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-<?php echo $badge; ?>" 
                             role="progressbar" 
                             style="width: <?php echo $adherence_percentage; ?>%;">
                            <?php echo $adherence_percentage; ?>%
                        </div>
                    </div>

                    <p class="mt-2">
                        Status: 
                        <span class="badge bg-<?php echo $badge; ?>">
                            <?php echo $adherence_status; ?>
                        </span>
                    </p>

                </div>

            </div>

            <!-- ATTENDANCE HISTORY -->
            <div class="card shadow mt-3">

                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Attendance History</h5>
                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-striped">

                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Visit Date</th>
                                    <th>Service Given</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php 
                            $count = 1;

                            if (mysqli_num_rows($history_query) > 0) {
                                while ($row = mysqli_fetch_assoc($history_query)) {
                            ?>

                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td><?php echo htmlspecialchars($row['visit_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['service_given']); ?></td>
                                    <td><?php echo htmlspecialchars($row['notes']); ?></td>
                                </tr>

                            <?php 
                                }
                            } else {
                            ?>

                                <tr>
                                    <td colspan="4" class="text-center">
                                        No attendance history found for this patient
                                    </td>
                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>
</html>