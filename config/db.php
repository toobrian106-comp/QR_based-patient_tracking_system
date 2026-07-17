<?php

$conn = mysqli_connect("localhost", "root", "", "patient_tracking_db");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

?>