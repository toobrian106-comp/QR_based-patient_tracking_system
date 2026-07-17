<?php 
include('../includes/admin_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: /patient_tracking_system/users/manage_users.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

if ($id == $_SESSION['user_id']) {
    echo "<script>
            alert('You cannot delete your own account while logged in.');
            window.location.href='/patient_tracking_system/users/manage_users.php';
          </script>";
    exit();
}

$delete = "DELETE FROM users WHERE id='$id'";

if (mysqli_query($conn, $delete)) {
    echo "<script>
            alert('User deleted successfully.');
            window.location.href='/patient_tracking_system/users/manage_users.php';
          </script>";
    exit();
} else {
    die("Delete failed: " . mysqli_error($conn));
}
?>