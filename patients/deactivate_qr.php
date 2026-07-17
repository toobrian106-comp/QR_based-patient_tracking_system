<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/log_action.php';

if (!function_exists('logAction')) {
    function logAction(mysqli $conn, string $action, string $details) {
        if (function_exists('log_action')) {
            return call_user_func('log_action', $conn, $action, $details);
        }
        return false;
    }
}

if (!isset($conn)) {
    if (isset($connection)) {
        $conn = $connection;
    } elseif (isset($db)) {
        $conn = $db;
    } else {
        die("Database connection not found.");
    }
}

if (!isset($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$patient_query = mysqli_query($conn, "SELECT * FROM patients WHERE id='$id'");

if (!$patient_query || mysqli_num_rows($patient_query) == 0) {
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($patient_query);

$update = mysqli_query($conn, "
    UPDATE patients 
    SET qr_status='Inactive'
    WHERE id='$id'
");

if ($update) {

    logAction(
        $conn,
        "QR Deactivated",
        "Deactivated QR card for " . $patient['fullname'] . " (" . $patient['patient_id'] . ") due to security control."
    );

    $title = mysqli_real_escape_string($conn, "QR Card Deactivated");
    $message = mysqli_real_escape_string($conn, "QR card for " . $patient['fullname'] . " has been deactivated.");
    $type = mysqli_real_escape_string($conn, "warning");
    $link = mysqli_real_escape_string($conn, "../patients/view_patient.php?id=" . $id);

    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, link)
        VALUES ('$title', '$message', '$type', '$link')
    ");

    header("Location: view_patient.php?id=$id");
    exit();

} else {
    die("Failed to deactivate QR: " . mysqli_error($conn));
}
