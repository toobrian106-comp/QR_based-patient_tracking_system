<?php 
include('../includes/auth_check.php');
include('../config/db.php');

$where = "WHERE 1=1";

$from_date = isset($_GET['from_date']) ? mysqli_real_escape_string($conn, $_GET['from_date']) : "";
$to_date = isset($_GET['to_date']) ? mysqli_real_escape_string($conn, $_GET['to_date']) : "";
$service_filter = isset($_GET['service_given']) ? mysqli_real_escape_string($conn, $_GET['service_given']) : "";
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : "";

if (!empty($from_date) && !empty($to_date)) {
    $where .= " AND attendance.visit_date BETWEEN '$from_date' AND '$to_date'";
}

if (!empty($service_filter)) {
    $where .= " AND attendance.service_given='$service_filter'";
}

if (!empty($search)) {
    $where .= " AND (
        patients.fullname LIKE '%$search%' 
        OR patients.patient_id LIKE '%$search%'
        OR patients.location LIKE '%$search%'
    )";
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_report.csv');

$output = fopen("php://output", "w");

fputcsv($output, array(
    'Patient ID',
    'Full Name',
    'Gender',
    'Location',
    'Visit Date',
    'Service Given',
    'Notes'
));

$query = "
SELECT attendance.*, patients.fullname, patients.gender, patients.location
FROM attendance
INNER JOIN patients ON attendance.patient_id = patients.patient_id
$where
ORDER BY attendance.id DESC
";

$result = mysqli_query($conn, $query);

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
?>