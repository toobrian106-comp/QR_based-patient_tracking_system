<?php 
include('../includes/auth_check.php');
include('../config/db.php');

// Safe query wrapper to avoid passing null to mysqli_query
function safe_query($conn, $sql) {
    if ($conn instanceof mysqli) {
        return mysqli_query($conn, $sql);
    }
    return false;
}

$today = date("Y-m-d");
$next_7_days = date("Y-m-d", strtotime("+7 days"));

$result = safe_query($conn, "SELECT COUNT(*) AS total FROM appointments");
$total_appointments = ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$result = safe_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date='$today' AND status='Scheduled'");
$today_count = ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$result = safe_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date > '$today' AND appointment_date <= '$next_7_days' AND status='Scheduled'");
$upcoming_count = ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$result = safe_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE appointment_date < '$today' AND status='Scheduled'");
$missed_count = ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$result = safe_query($conn, "SELECT COUNT(*) AS total FROM appointments WHERE status='Cancelled'");
$cancelled_count = ($result && $row = mysqli_fetch_assoc($result)) ? $row['total'] : 0;

$today_appointments = safe_query($conn, "
SELECT appointments.*, patients.fullname, patients.phone, patients.location
FROM appointments
INNER JOIN patients ON appointments.patient_id = patients.patient_id
WHERE appointments.appointment_date='$today'
AND appointments.status='Scheduled'
ORDER BY appointments.appointment_time ASC
");

$upcoming_appointments = safe_query($conn, "
SELECT appointments.*, patients.fullname, patients.phone, patients.location
FROM appointments
INNER JOIN patients ON appointments.patient_id = patients.patient_id
WHERE appointments.appointment_date > '$today'
AND appointments.appointment_date <= '$next_7_days'
AND appointments.status='Scheduled'
ORDER BY appointments.appointment_date ASC, appointments.appointment_time ASC
");

$missed_appointments = safe_query($conn, "
SELECT appointments.*, patients.fullname, patients.phone, patients.location
FROM appointments
INNER JOIN patients ON appointments.patient_id = patients.patient_id
WHERE appointments.appointment_date < '$today'
AND appointments.status='Scheduled'
ORDER BY appointments.appointment_date DESC
");

function formatPhone($phone){
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if(substr($phone, 0, 1) == "0"){
        $phone = "254" . substr($phone, 1);
    }

    return $phone;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#eef3f8;
            font-family:Arial, sans-serif;
        }

        .page-card{
            border:none;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,0.08);
        }

        .stat-card{
            color:white;
            border-radius:18px;
            padding:22px;
            box-shadow:0 10px 25px rgba(0,0,0,0.12);
            min-height:120px;
        }

        .stat-card h2{
            font-size:38px;
            font-weight:bold;
        }

        .table th{
            background:#1f2937 !important;
            color:white;
        }

        .badge-status{
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
            <h2 class="fw-bold mb-1">Appointment Dashboard</h2>
            <p class="text-muted mb-0">
                Monitor today’s follow-up visits, upcoming appointments, and missed visits.
            </p>
        </div>

        <div>
            <a href="../dashboard/index.php" class="btn btn-primary">Dashboard</a>
            <a href="add_appointment.php" class="btn btn-success">Add Appointment</a>
            <a href="manage_appointments.php" class="btn btn-dark">Manage Appointments</a>
        </div>

    </div>

    <div class="row mb-4">

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-primary">
                <h6>Total Appointments</h6>
                <h2><?php echo $total_appointments; ?></h2>
                <small>All appointment records</small>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-success">
                <h6>Today</h6>
                <h2><?php echo $today_count; ?></h2>
                <small>Scheduled for today</small>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-warning">
                <h6>Upcoming</h6>
                <h2><?php echo $upcoming_count; ?></h2>
                <small>Next 7 days</small>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-danger">
                <h6>Missed</h6>
                <h2><?php echo $missed_count; ?></h2>
                <small>Past scheduled visits</small>
            </div>
        </div>

    </div>

    <div class="card page-card mb-4">

        <div class="card-header bg-success text-white">
            <h5 class="mb-0">Today's Appointments</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Notify</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($today_appointments) > 0){

                        while($row = mysqli_fetch_assoc($today_appointments)){

                            $phone = formatPhone($row['phone']);

                            $wa_message = urlencode(
                                "Hello " . $row['fullname'] . ", this is a reminder that you have a follow-up visit today at " .
                                $row['appointment_time'] . ". Purpose: " . $row['purpose'] . "."
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

                            <td><?php echo htmlspecialchars($row['appointment_time']); ?></td>

                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>

                            <td>
                                <?php if(!empty($phone)){ ?>
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
                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Attended" 
                                   class="btn btn-success btn-sm">
                                    Attended
                                </a>

                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Missed" 
                                   class="btn btn-secondary btn-sm">
                                    Missed
                                </a>
                            </td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="8" class="text-center">
                                No appointments scheduled for today.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="card page-card mb-4">

        <div class="card-header bg-warning">
            <h5 class="mb-0">Upcoming Appointments - Next 7 Days</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Notify</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($upcoming_appointments) > 0){

                        while($row = mysqli_fetch_assoc($upcoming_appointments)){

                            $phone = formatPhone($row['phone']);

                            $wa_message = urlencode(
                                "Hello " . $row['fullname'] . ", this is a reminder that you have a follow-up visit on " .
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
                                <?php if(!empty($phone)){ ?>
                                    <a href="https://wa.me/<?php echo $phone; ?>?text=<?php echo $wa_message; ?>" 
                                       target="_blank" 
                                       class="btn btn-success btn-sm">
                                        WhatsApp
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">No phone</span>
                                <?php } ?>
                            </td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="8" class="text-center">
                                No upcoming appointments in the next 7 days.
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="card page-card">

        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">Missed Appointments</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Missed Date</th>
                            <th>Purpose</th>
                            <th>Notify</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($missed_appointments) > 0){

                        while($row = mysqli_fetch_assoc($missed_appointments)){

                            $phone = formatPhone($row['phone']);

                            $wa_message = urlencode(
                                "Hello " . $row['fullname'] . ", our records show that you missed your follow-up visit on " .
                                $row['appointment_date'] . ". Kindly contact the clinic for rescheduling."
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

                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>

                            <td>
                                <?php if(!empty($phone)){ ?>
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
                                <a href="update_appointment_status.php?id=<?php echo $row['id']; ?>&status=Missed" 
                                   class="btn btn-secondary btn-sm">
                                    Mark Missed
                                </a>

                                <a href="edit_appointment.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-warning btn-sm">
                                    Reschedule
                                </a>
                            </td>
                        </tr>

                    <?php 
                        }

                    } else {
                    ?>

                        <tr>
                            <td colspan="8" class="text-center">
                                No missed appointments detected.
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