<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: attendance_list.php");
    exit();
}

$id = intval($_GET['id']);
if ($id <= 0) {
    header("Location: attendance_list.php");
    exit();
}

$delete = "DELETE FROM attendance WHERE id=$id";

if (!($conn instanceof mysqli)) {
    die('Database connection error.');
}

if (mysqli_query($conn, $delete)) {
    echo "<script>
            alert('Attendance record deleted successfully.');
            window.location.href='attendance_list.php';
          </script>";
    exit();
} else {
    die("Delete failed: " . mysqli_error($conn));
}
