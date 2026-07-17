<?php 
include('../includes/auth_check.php');
include('../config/db.php');

$search = "";
$service_filter = "";
$date_filter = "";

$query = "
SELECT attendance.*, patients.fullname, patients.gender, patients.location
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
    die("Export query failed: " . mysqli_error($conn));
}

$filename = "filtered_attendance_report_" . date("Y-m-d_H-i-s") . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen("php://output", "w");

fputcsv($output, array(
    "Patient ID",
    "Full Name",
    "Gender",
    "Location",
    "Visit Date",
    "Service Given",
    "Notes"
));

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, array(
        $row['patient_id'],
        $row['fullname'],
        $row['gender'],
        $row['location'],
        $row['visit_date'],
        $row['service_given'],
        $row['notes']
    ));
}

fclose($output);
exit();
?>