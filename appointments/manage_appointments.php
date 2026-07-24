<?php 
include('../includes/auth_check.php');
include('../config/db.php');

/** @var mysqli $conn */

$search = "";
$status_filter = "";

$where = "WHERE 1=1";

if (isset($_GET['filter'])) {

    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $status_filter = mysqli_real_escape_string($conn, $_GET['status']);

    if (!empty($search)) {
        $where .= " AND (
            patients.fullname LIKE '%$search%' 
            OR appointments.patient_id LIKE '%$search%'
            OR patients.phone LIKE '%$search%'
            OR appointments.purpose LIKE '%$search%'
        )";
    }

    if (!empty($status_filter)) {
        $where .= " AND appointments.status='$status_filter'";
    }
}

$query = "
SELECT appointments.*, patients.fullname, patients.phone, patients.location
FROM appointments
INNER JOIN patients ON appointments.patient_id = patients.patient_id
$where
ORDER BY appointments.appointment_date ASC, appointments.appointment_time ASC
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
    <title>Manage Appointments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background:#eef3f8;
            font-family: Arial, sans-serif;
        }

        .page-card {
            border:none;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,0.08);
        }

        .badge-status {
            padding:8px 12px;
            border-radius:30px;
            font-size:12px;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">Follow-Up Visit Management</h2>
            <p class="text-muted mb-0">
                Schedule, monitor, and manage patient follow-up visits.
            </p>
        </div>

        <div>
            <a href="../dashboard/index.php" class="btn btn-primary">Dashboard</a>
            <a href="add_appointment.php" class="btn btn-success">Add Appointment</a>
        </div>

    </div>

    <div class="card page-card mb-4">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Filter Appointments</h5>
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Search</label>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="Patient name, ID, phone, purpose"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Scheduled" <?php if($status_filter=="Scheduled") echo "selected"; ?>>Scheduled</option>
                            <option value="Attended" <?php if($status_filter=="Attended") echo "selected"; ?>>Attended</option>
                            <option value="Missed" <?php if($status_filter=="Missed") echo "selected"; ?>>Missed</option>
                            <option value="Cancelled" <?php if($status_filter=="Cancelled") echo "selected"; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-3 d-grid">
                        <label>.</label>
                        <button type="submit" name="filter" class="btn btn-primary">
                            Filter
                        </button>
                    </div>

                </div>

                <a href="manage_appointments.php" class="btn btn-secondary">
                    Reset
                </a>

            </form>

        </div>

    </div>

    <div class="card page-card">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Appointment Records</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Notify</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {

                        while ($row = mysqli_fetch_assoc($result)) {

                            $badge = "secondary";

                            if ($row['status'] == "Scheduled") {
                                $badge = "primary";
                            } elseif ($row['status'] == "Attended") {
                                $badge = "success";
                            } elseif ($row['status'] == "Missed") {
                                $badge = "warning";
                            } elseif ($row['status'] == "Cancelled") {
                                $badge = "danger";
                            }

                            $phone = preg_replace('/[^0-9]/', '', $row['phone']);

                            if (substr($phone, 0, 1) == "0") {
                                $phone = "254" . substr($phone, 1);
                            }

                            $wa_message = urlencode(
                                "Hello " . $row['fullname'] . ", this is a reminder about your appointment on " .
                                $row['appointment_date'] . " at " . $row['appointment_time'] .
                                ". Purpose: " . $row['purpose'] . "."
                            );
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td>
                                <?php echo htmlspecialchars($row['fullname']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($row['patient_id']); ?></small>
                            </td>

                            <td><?php echo htmlspecialchars($row['phone']); ?></td>

                            <td><?php echo htmlspecialchars($row['location']); ?></td>

                            <td><?php echo htmlspecialchars($row['appointment_date']); ?></td>

                            <td><?php echo htmlspecialchars($row['appointment_time']); ?></td>

                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>

                            <td>
                                <span class="badge bg-<?php echo $badge; ?> badge-status">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>

                            <td>
                                <?php if (!empty($phone)) { ?>
                                    <a href="https://wa.me/<?php echo $phone; ?>?text=<?php echo $wa_message; ?>"
                                       target="_blank"
                                       class="btn btn-success btn-sm">
                                        WhatsApp
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">No phone</span>
                                <?php } ?>
                            </td>

                            <td>
                                <a href="edit_appointment.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-warning btn-sm mb-1">
                                    Edit
                                </a>

                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Attended" 
                                   class="btn btn-success btn-sm mb-1">
                                    Attended
                                </a>

                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Missed" 
                                   class="btn btn-secondary btn-sm mb-1">
                                    Missed
                                </a>

                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Cancelled" 
                                   class="btn btn-danger btn-sm mb-1"
                                   onclick="return confirm('Cancel this appointment?');">
                                    Cancel
                                </a>
                            </td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="10" class="text-center">
                                No appointments found.
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