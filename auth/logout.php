<?php
session_start();

include('../config/db.php');
include('../includes/log_action.php');

if (isset($conn)) {
    logAction(
        $conn,
    "User Logout",
    ($_SESSION['fullname'] ?? 'User') . " logged out of the system."
);
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

?>