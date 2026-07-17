<?php
include('../includes/auth_check.php');
require_once __DIR__ . '/../config/db.php';
include('../includes/log_action.php');

if (!isset($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

// Ensure database connection is available before escaping input
if (!isset($conn) || !$conn) {
    die('Database connection not available.');
}

// Validate id parameter
if (!isset($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id_raw = $_GET['id'];
if (!ctype_digit((string)$id_raw)) {
    die('Invalid patient id.');
}
$id = (int)$id_raw;

// Use prepared statements to avoid escaping issues
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
if (!$stmt) die('Prepare failed: ' . $conn->error);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    die('Patient not found.');
}
$patient = $result->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("UPDATE patients SET qr_status = 'Active' WHERE id = ?");
if (!$stmt) die('Prepare failed: ' . $conn->error);
$stmt->bind_param('i', $id);
$update = $stmt->execute();
$stmt->close();

if ($update) {

    logAction(
        $conn,
        "QR Activated",
        "Activated QR card for " . $patient['fullname'] . " (" . $patient['patient_id'] . ")."
    );

    $title = "QR Card Activated";
    $safe_fullname = mysqli_real_escape_string($conn, $patient['fullname']);
    $message = "QR card for " . $safe_fullname . " has been activated.";
    $type = "success";
    $link = "../patients/view_patient.php?id=" . $id;

    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, link)
        VALUES ('$title', '$message', '$type', '$link')
    ");

    header("Location: view_patient.php?id=$id");
    exit();

} else {
    die("Failed to activate QR: " . mysqli_error($conn));
}