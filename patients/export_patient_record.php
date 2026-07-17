<?php
include('../includes/auth_check.php');
include('../config/db.php');

if(!isset($_GET['id'])){
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$patient_query = mysqli_query($conn, "SELECT * FROM patients WHERE id='$id'");

if(!$patient_query || mysqli_num_rows($patient_query) == 0){
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($patient_query);
$patient_id = $patient['patient_id'];

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=patient_record_" . $patient_id . ".xls");

echo "<h2>Patient Record</h2>";
echo "<table border='1'>";
echo "<tr><th>Patient ID</th><td>".$patient['patient_id']."</td></tr>";
echo "<tr><th>Full Name</th><td>".$patient['fullname']."</td></tr>";
echo "<tr><th>Gender</th><td>".$patient['gender']."</td></tr>";
echo "<tr><th>Age</th><td>".$patient['age']."</td></tr>";
echo "<tr><th>Phone</th><td>".$patient['phone']."</td></tr>";
echo "<tr><th>Location</th><td>".$patient['location']."</td></tr>";
echo "<tr><th>Status</th><td>".$patient['status']."</td></tr>";
echo "<tr><th>Date Registered</th><td>".$patient['created_at']."</td></tr>";
echo "</table>";

echo "<br><h3>Attendance History</h3>";

$history = mysqli_query($conn, "SELECT * FROM attendance WHERE patient_id='$patient_id' ORDER BY visit_date DESC");

echo "<table border='1'>";
echo "<tr><th>#</th><th>Visit Date</th><th>Service Given</th><th>Notes</th></tr>";

$count = 1;

while($row = mysqli_fetch_assoc($history)){
    echo "<tr>";
    echo "<td>".$count++."</td>";
    echo "<td>".$row['visit_date']."</td>";
    echo "<td>".$row['service_given']."</td>";
    echo "<td>".$row['notes']."</td>";
    echo "</tr>";
}

echo "</table>";
?>