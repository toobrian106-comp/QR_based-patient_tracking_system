<?php

if (!isset($conn)) {
    return;
}

$default_days = 30;

/* DEFAULTING PATIENTS NOTIFICATION */
$defaulting_sql = "
SELECT patients.patient_id
FROM patients
LEFT JOIN attendance ON patients.patient_id = attendance.patient_id
GROUP BY patients.patient_id
HAVING MAX(attendance.visit_date) IS NULL
OR DATEDIFF(CURDATE(), MAX(attendance.visit_date)) > $default_days
";

$defaulting_result = mysqli_query($conn, $defaulting_sql);

if ($defaulting_result) {

    $defaulting_count = mysqli_num_rows($defaulting_result);

    if ($defaulting_count > 0) {

        $title = mysqli_real_escape_string($conn, "Defaulting Patients Alert");
        $message = mysqli_real_escape_string($conn, $defaulting_count . " patient(s) may be defaulting.");
        $type = mysqli_real_escape_string($conn, "danger");
        $link = mysqli_real_escape_string($conn, "../reports/defaulting_detection.php");

        $check_sql = "
        SELECT id FROM notifications
        WHERE title='$title'
        AND DATE(created_at)=CURDATE()
        LIMIT 1
        ";

        $check_result = mysqli_query($conn, $check_sql);

        if ($check_result && mysqli_num_rows($check_result) == 0) {
            mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
            ");
        }
    }
}

/* TODAY ATTENDANCE NOTIFICATION */
$today_visits_sql = "
SELECT COUNT(*) AS total 
FROM attendance
WHERE visit_date = CURDATE()
";

$today_visits_result = mysqli_query($conn, $today_visits_sql);

if ($today_visits_result) {

    $today_data = mysqli_fetch_assoc($today_visits_result);
    $today_visits = $today_data['total'];

    if ($today_visits > 0) {

        $title = mysqli_real_escape_string($conn, "Today Attendance Update");
        $message = mysqli_real_escape_string($conn, $today_visits . " visit(s) recorded today.");
        $type = mysqli_real_escape_string($conn, "success");
        $link = mysqli_real_escape_string($conn, "../attendance/attendance_list.php");

        $check_sql = "
        SELECT id FROM notifications
        WHERE title='$title'
        AND DATE(created_at)=CURDATE()
        LIMIT 1
        ";

        $check_result = mysqli_query($conn, $check_sql);

        if ($check_result && mysqli_num_rows($check_result) == 0) {
            mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
            ");
        }
    }
}

/* NEW PATIENTS TODAY NOTIFICATION */
$new_patients_sql = "
SELECT COUNT(*) AS total
FROM patients
WHERE DATE(created_at) = CURDATE()
";

$new_patients_result = mysqli_query($conn, $new_patients_sql);

if ($new_patients_result) {

    $new_patient_data = mysqli_fetch_assoc($new_patients_result);
    $new_patients = $new_patient_data['total'];

    if ($new_patients > 0) {

        $title = mysqli_real_escape_string($conn, "New Patient Registration");
        $message = mysqli_real_escape_string($conn, $new_patients . " new patient(s) registered today.");
        $type = mysqli_real_escape_string($conn, "info");
        $link = mysqli_real_escape_string($conn, "../patients/manage_patients.php");

        $check_sql = "
        SELECT id FROM notifications
        WHERE title='$title'
        AND DATE(created_at)=CURDATE()
        LIMIT 1
        ";

        $check_result = mysqli_query($conn, $check_sql);

        if ($check_result && mysqli_num_rows($check_result) == 0) {
            mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
            ");
        }
    }
}
