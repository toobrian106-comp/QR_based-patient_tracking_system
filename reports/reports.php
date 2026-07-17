<?php 
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$search = "";
$service_filter = "";
$date_filter = "";

$query = "
SELECT attendance.*, patients.fullname, patients.location
FROM attendance
INNER JOIN patients 
ON attendance.patient_id = patients.patient_id
WHERE 1
";

if (isset($_GET['search']) && $_GET['search'] != "") {

    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $query .= "
    AND (
        patients.fullname LIKE '%$search%'
        OR attendance.patient_id LIKE '%$search%'
        OR patients.location LIKE '%$search%'
    )
    ";
}

if (isset($_GET['service']) && $_GET['service'] != "") {

    $service_filter = mysqli_real_escape_string($conn, $_GET['service']);

    $query .= "
    AND attendance.service_given='$service_filter'
    ";
}

if (isset($_GET['visit_date']) && $_GET['visit_date'] != "") {

    $date_filter = mysqli_real_escape_string($conn, $_GET['visit_date']);

    $query .= "
    AND attendance.visit_date='$date_filter'
    ";
}

$query .= " ORDER BY attendance.id DESC";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Statistics
$total_attendance_query = mysqli_query($conn, "
SELECT COUNT(*) AS total FROM attendance
");
$total_attendance = mysqli_fetch_assoc($total_attendance_query)['total'];

$total_food_query = mysqli_query($conn, "
SELECT COUNT(*) AS total 
FROM attendance
WHERE service_given='Food Basket'
");
$total_food = mysqli_fetch_assoc($total_food_query)['total'];

$total_vitamin_query = mysqli_query($conn, "
SELECT COUNT(*) AS total 
FROM attendance
WHERE service_given='Vitamin Supplements'
");
$total_vitamins = mysqli_fetch_assoc($total_vitamin_query)['total'];

$active_patients_query = mysqli_query($conn, "
SELECT COUNT(DISTINCT patient_id) AS total 
FROM attendance
");
$active_patients = mysqli_fetch_assoc($active_patients_query)['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports Dashboard</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body {
            background:#f4f6f9;
        }

        .stats-card {
            color:white;
            border-radius:15px;
            padding:20px;
            box-shadow:0 10px 20px rgba(0,0,0,0.12);
        }

        .card {
            border:none;
            border-radius:15px;
        }
    </style>
</head>

<body>

<div class="container-fluid mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <h2>
            <i class="bi bi-bar-chart-fill"></i>
            Reports Dashboard
        </h2>

        <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-primary">
            Dashboard
        </a>

    </div>

    <div class="row mb-4">

        <div class="col-md-3 mb-3">
            <div class="stats-card bg-primary">
                <h6>Total Attendance</h6>
                <h2><?php echo $total_attendance; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card bg-success">
                <h6>Food Basket Services</h6>
                <h2><?php echo $total_food; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card bg-warning text-dark">
                <h6>Vitamin Services</h6>
                <h2><?php echo $total_vitamins; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stats-card bg-danger">
                <h6>Active Patients</h6>
                <h2><?php echo $active_patients; ?></h2>
            </div>
        </div>

    </div>

    <div class="card shadow mb-4">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Search & Filter Reports</h5>
        </div>

        <div class="card-body">

            <form method="GET" action="/patient_tracking_system/reports/reports.php">

                <div class="row">

                    <div class="col-md-4 mb-3">
                        <label>Search Patient</label>

                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Patient ID / Name / Location"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Filter by Service</label>

                        <select name="service" class="form-control">

                            <option value="">All Services</option>

                            <option value="Food Basket"
                                <?php if($service_filter=="Food Basket") echo "selected"; ?>>
                                Food Basket
                            </option>

                            <option value="Vitamin Supplements"
                                <?php if($service_filter=="Vitamin Supplements") echo "selected"; ?>>
                                Vitamin Supplements
                            </option>

                            <option value="Food Basket and Vitamin Supplements"
                                <?php if($service_filter=="Food Basket and Vitamin Supplements") echo "selected"; ?>>
                                Food Basket and Vitamin Supplements
                            </option>

                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Filter by Date</label>

                        <input type="date"
                               name="visit_date"
                               class="form-control"
                               value="<?php echo htmlspecialchars($date_filter); ?>">
                    </div>

                    <div class="col-md-2 mb-3 d-grid">
                        <label>&nbsp;</label>

                        <button class="btn btn-primary mb-2">
                            <i class="bi bi-search"></i>
                            Search
                        </button>

                        <a href="/patient_tracking_system/reports/export_filtered_reports.php?search=<?php echo urlencode($search); ?>&service=<?php echo urlencode($service_filter); ?>&visit_date=<?php echo urlencode($date_filter); ?>" 
                           class="btn btn-success">
                            <i class="bi bi-download"></i>
                            Export CSV
                        </a>
                    </div>

                </div>

            </form>

        </div>

    </div>

    <div class="card shadow">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Attendance Reports</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Location</th>
                            <th>Visit Date</th>
                            <th>Service Given</th>
                            <th>Notes</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($result) > 0){

                        while($row = mysqli_fetch_assoc($result)){
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td><?php echo htmlspecialchars($row['patient_id']); ?></td>

                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>

                            <td><?php echo htmlspecialchars($row['location']); ?></td>

                            <td><?php echo htmlspecialchars($row['visit_date']); ?></td>

                            <td><?php echo htmlspecialchars($row['service_given']); ?></td>

                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="7" class="text-center">
                                No records found
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