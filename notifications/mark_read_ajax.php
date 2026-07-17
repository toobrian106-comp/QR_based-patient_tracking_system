<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_POST['id'])) {

    $id = mysqli_real_escape_string($conn, $_POST['id']);

    $update = mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id='$id'");

    $count_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notifications WHERE is_read=0");
    $count = mysqli_fetch_assoc($count_query)['total'];

    echo json_encode([
        "success" => $update ? true : false,
        "unread_count" => $count
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "No notification ID received"
    ]);

}
?>