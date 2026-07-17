<?php
include('../includes/auth_check.php');

if ($_SESSION['role'] !== 'Admin') {
    echo "<script>
            alert('Access denied. Admin only.');
            window.location.href='../dashboard/index.php';
          </script>";
    exit();
}
?>