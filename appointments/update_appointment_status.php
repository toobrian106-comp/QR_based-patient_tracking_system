<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_action.php');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error.");
}

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    header("Location: manage_appointments.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);
$status = mysqli_real_escape_string($conn, $_GET['status']);

$allowed_status = array(
    "Scheduled",
    "Attended",
    "Missed",
    "Cancelled"
);

if (!in_array($status, $allowed_status)) {
    die("Invalid appointment status.");
}

/* Retrieve appointment details */
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

$update = mysqli_query($conn, "
UPDATE appointments
SET status='$status'
WHERE id='$id'
");

if (!$update) {
    die("Failed to update appointment: " . mysqli_error($conn));
}

/* Audit Log */
logAction(
    $conn,
    "Appointment Status Updated",
    "Changed appointment for " .
    $appointment['fullname'] .
    " (" .
    $appointment['patient_id'] .
    ") to '" .
    $status .
    "'."
);

/* Notification */
$title = "Appointment Updated";
$message = $appointment['fullname'] .
           "'s appointment has been marked as " .
           $status . ".";

$type = "info";

switch ($status) {

    case "Attended":
        $type = "success";
        break;

    case "Missed":
        $type = "warning";
        break;

    case "Cancelled":
        $type = "danger";
        break;

    default:
        $type = "info";
}

$link = "../appointments/manage_appointments.php";

$title = mysqli_real_escape_string($conn, $title);
$message = mysqli_real_escape_string($conn, $message);
$type = mysqli_real_escape_string($conn, $type);
$link = mysqli_real_escape_string($conn, $link);

mysqli_query($conn, "
INSERT INTO notifications
(title, message, type, link)
VALUES
('$title','$message','$type','$link')
");

/* Toast Redirect */

switch ($status) {

    case "Attended":
        header("Location: ../dashboard/index.php?toast=appointment_attended");
        break;

    case "Missed":
        header("Location: ../dashboard/index.php?toast=appointment_missed");
        break;

    case "Cancelled":
        header("Location: ../dashboard/index.php?toast=appointment_cancelled");
        break;

    default:
        header("Location: ../dashboard/index.php?toast=appointment_updated");
        break;
}

exit();