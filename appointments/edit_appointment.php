<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_action.php');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

if (!isset($_GET['id'])) {
    header("Location: manage_appointments.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = mysqli_query($conn, "
SELECT appointments.*, patients.fullname
FROM appointments
INNER JOIN patients
ON appointments.patient_id = patients.patient_id
WHERE appointments.id='$id'
");

if (!$query || mysqli_num_rows($query) == 0) {
    die("Appointment not found.");
}

$appointment = mysqli_fetch_assoc($query);

if (isset($_POST['update'])) {

    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $update = mysqli_query($conn,"
        UPDATE appointments
        SET
            appointment_date='$appointment_date',
            appointment_time='$appointment_time',
            purpose='$purpose',
            status='$status',
            notes='$notes'
        WHERE id='$id'
    ");

    if (!$update) {
        die("Update failed: " . mysqli_error($conn));
    }

    logAction(
        $conn,
        "Appointment Updated",
        "Updated appointment for " .
        $appointment['fullname'] .
        " (" .
        $appointment['patient_id'] .
        "). New date: $appointment_date, Time: $appointment_time, Status: $status."
    );

    $title = "Appointment Updated";
    $message = $appointment['fullname'] . "'s appointment has been updated.";
    $type = "info";
    $link = "../appointments/manage_appointments.php";

    $title = mysqli_real_escape_string($conn,$title);
    $message = mysqli_real_escape_string($conn,$message);
    $type = mysqli_real_escape_string($conn,$type);
    $link = mysqli_real_escape_string($conn,$link);

    mysqli_query($conn,"
        INSERT INTO notifications
        (title,message,type,link)
        VALUES
        ('$title','$message','$type','$link')
    ");

    header("Location: manage_appointments.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>Edit Appointment</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#eef3f8;
    font-family:Arial,sans-serif;
}

.page-card{
    border:none;
    border-radius:18px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

</style>

</head>

<body>

<div class="container mt-5">

<div class="card page-card">

<div class="card-header bg-warning d-flex justify-content-between">

<h4>Edit Appointment</h4>

<div>

<a href="manage_appointments.php" class="btn btn-dark btn-sm">
Back
</a>

</div>

</div>

<div class="card-body">

<form method="POST">

<div class="mb-3">

<label>Patient</label>

<input type="text"
class="form-control"
value="<?php echo htmlspecialchars($appointment['fullname']); ?>"
readonly>

</div>

<div class="row">

<div class="col-md-6 mb-3">

<label>Appointment Date</label>

<input
type="date"
name="appointment_date"
class="form-control"
value="<?php echo $appointment['appointment_date']; ?>"
required>

</div>

<div class="col-md-6 mb-3">

<label>Appointment Time</label>

<input
type="time"
name="appointment_time"
class="form-control"
value="<?php echo $appointment['appointment_time']; ?>">

</div>

</div>

<div class="mb-3">

<label>Purpose</label>

<select
name="purpose"
class="form-control">

<?php

$purposes=[
"Food Basket Collection",
"Vitamin Supplement Collection",
"Adherence Follow-Up",
"Counselling Session",
"General Follow-Up"
];

foreach($purposes as $p){

$selected=($appointment['purpose']==$p)?"selected":"";

echo "<option value=\"$p\" $selected>$p</option>";

}

?>

</select>

</div>

<div class="mb-3">

<label>Status</label>

<select
name="status"
class="form-control">

<?php

$statuses=[
"Scheduled",
"Attended",
"Missed",
"Cancelled"
];

foreach($statuses as $s){

$selected=($appointment['status']==$s)?"selected":"";

echo "<option value=\"$s\" $selected>$s</option>";

}

?>

</select>

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
rows="4"
class="form-control"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>

</div>

<button
type="submit"
name="update"
class="btn btn-warning">

Update Appointment

</button>

<a href="manage_appointments.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

</div>

</body>

</html>