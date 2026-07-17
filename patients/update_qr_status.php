<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_action.php');

if (!isset($_POST['update_qr_status'])) {
    header("Location: manage_patients.php");
    exit();
}

$patient_db_id = mysqli_real_escape_string($conn, $_POST['patient_db_id']);
$new_status = mysqli_real_escape_string($conn, $_POST['qr_status']);
$reason = mysqli_real_escape_string($conn, $_POST['reason']);
$changed_by = mysqli_real_escape_string($conn, $_SESSION['fullname'] ?? 'System User');

$allowed_statuses = [
    "Active",
    "Suspended",
    "Lost",
    "Stolen",
    "Deactivated"
];

if (!in_array($new_status, $allowed_statuses)) {
    die("Invalid QR status selected.");
}

if (empty($reason)) {
    die("Reason is required for QR security status changes.");
}

$patient_query = mysqli_query($conn, "SELECT * FROM patients WHERE id='$patient_db_id'");

if (!$patient_query || mysqli_num_rows($patient_query) == 0) {
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($patient_query);

$patient_id = $patient['patient_id'];
$fullname = $patient['fullname'];

$update = mysqli_query($conn, "
    UPDATE patients 
    SET qr_status='$new_status'
    WHERE id='$patient_db_id'
");

if (!$update) {
    die("Failed to update QR status: " . mysqli_error($conn));
}

mysqli_query($conn, "
    INSERT INTO qr_security_logs
    (patient_id, action, reason, changed_by)
    VALUES
    ('$patient_id', '$new_status', '$reason', '$changed_by')
");

logAction(
    $conn,
    "QR Status Updated",
    "Changed QR status for $fullname ($patient_id) to $new_status. Reason: $reason."
);

$title = mysqli_real_escape_string($conn, "QR Status Updated");
$message = mysqli_real_escape_string($conn, "$fullname QR status changed to $new_status. Reason: $reason");
$type = mysqli_real_escape_string($conn, "warning");
$link = mysqli_real_escape_string($conn, "../patients/view_patient.php?id=" . $patient_db_id);

mysqli_query($conn, "
    INSERT INTO notifications (title, message, type, link)
    VALUES ('$title', '$message', '$type', '$link')
");

header("Location: view_patient.php?id=$patient_db_id");
exit();
?>