<?php 
include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_action.php');

if (isset($_POST['record'])) {

    // Ensure $conn is a valid mysqli connection before escaping input
    if (!($conn instanceof mysqli)) {
        die("Database connection not available.");
    }

    $patient_id = mysqli_real_escape_string($conn, $_POST['patient_id']);
    $service_given = mysqli_real_escape_string($conn, $_POST['service_given']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    // NEW security check: ensure that the healthcare worker confirms the patient's identity before recording attendance      
    $identity_confirmed = $_POST['identity_confirmed'] ?? '';

    // NEW SECURITY CHECK
    if ($identity_confirmed !== 'Yes') {
        die("Security Error: The healthcare worker must confirm the patient's identity before attendance can be recorded.");
    }

    $visit_date = date("Y-m-d");

    /*
        SECURITY CHECK:
        Attendance can only be recorded if the patient was verified
        through secure QR scanning first.
    */

    if (
        !isset($_SESSION['verified_patient_id']) ||
        $_SESSION['verified_patient_id'] != $patient_id
    ) {
        die("Security Error: Attendance cannot be recorded without secure QR verification.");
    }

    // Ensure $conn is a valid mysqli connection before querying
    if (!($conn instanceof mysqli)) {
        die("Database connection not available.");
    }

    $check = mysqli_query($conn, "
        SELECT * FROM patients 
        WHERE patient_id='" . mysqli_real_escape_string($conn, $patient_id) . "'
        AND qr_status='Active'
        AND status='Active'
        LIMIT 1
    ");

    if (!$check) {
        die("Patient check failed: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($check) > 0) {

        $patient = mysqli_fetch_assoc($check);

        if (!empty($patient['qr_expiry_date']) && strtotime($patient['qr_expiry_date']) < strtotime(date("Y-m-d"))) {
            die("Security Error: This QR card has expired.");
        }

        $duplicate = mysqli_query($conn, "
            SELECT * FROM attendance
            WHERE patient_id='$patient_id'
            AND visit_date='$visit_date'
        ");

        if ($duplicate && mysqli_num_rows($duplicate) > 0) {
            die("Attendance for this patient has already been recorded today.");
        }

        $fullname = $patient['fullname'];
        $patient_db_id = $patient['id'];

        $insert = "INSERT INTO attendance 
                  (patient_id, visit_date, service_given, notes)
                  VALUES 
                  ('$patient_id', '$visit_date', '$service_given', '$notes')";

        // Ensure $conn is a valid mysqli connection before attempting query
        if ($conn instanceof mysqli && mysqli_query($conn, $insert)) {

            unset($_SESSION['verified_patient_id']);

            logAction(
                $conn,
                "Attendance Recorded",
                "Recorded secure QR-verified attendance for $fullname ($patient_id). Service given: $service_given. Notes: $notes."
            );

            $title = mysqli_real_escape_string($conn, "Attendance Recorded");
            $message = mysqli_real_escape_string($conn, $fullname . " received " . $service_given . " on " . $visit_date);
            $type = mysqli_real_escape_string($conn, "success");
            $link = mysqli_real_escape_string($conn, "../patients/view_patient.php?id=" . $patient_db_id);

            mysqli_query($conn, "
                INSERT INTO notifications (title, message, type, link)
                VALUES ('$title', '$message', '$type', '$link')
            ");

            header("Location: ../dashboard/index.php?toast=attendance_recorded");
            exit();

        } else {
            die("Attendance insert failed: " . mysqli_error($conn));
        }

    } else {

        die("Security Error: Patient not found, inactive, or QR inactive.");

    }

} else {

    header("Location: scan_qr.php");
    exit();

}
