<?php
include('../includes/admin_check.php');
include('../config/db.php');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_logs_' . date("Y-m-d") . '.csv');

$output = fopen("php://output", "w");

fputcsv($output, array(
    'ID',
    'User',
    'Action',
    'Description',
    'Date & Time'
));

$conn = $conn ?? $connection ?? $db ?? null;
if (!($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection not available';
    exit;
}

$query = mysqli_query($conn, "
SELECT * FROM audit_logs
ORDER BY id DESC
");

while ($row = mysqli_fetch_assoc($query)) {
    fputcsv($output, array(
        $row['id'],
        $row['user_name'],
        $row['action'],
        $row['description'],
        $row['created_at']
    ));
}

fclose($output);
exit();