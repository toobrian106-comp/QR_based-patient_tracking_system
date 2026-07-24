<?php

$host = "bepc4trzsfaa6oaqjouc-mysql.services.clever-cloud.com";
$user = "uidymb30x4nn7vgm";
$password = "5UbNzPhmnS8RqJq4VKmm";
$database = "bepc4trzsfaa6oaqjouc";
$port = 3306;

$conn = mysqli_connect($host, $user, $password, $database, $port);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

?>