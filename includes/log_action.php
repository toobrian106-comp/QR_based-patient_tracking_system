<?php

if (!function_exists('logAction')) {

    function logAction(mysqli $conn, string $action, string $description = ""): void
    {
        if (!$conn) {
            return;
        }

        $user = "System";

        if (isset($_SESSION['fullname']) && !empty($_SESSION['fullname'])) {
            $user = $_SESSION['fullname'];
        } elseif (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
            $user = $_SESSION['username'];
        } elseif (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
            $user = $_SESSION['email'];
        }

        if (empty($description)) {
            $description = "No detailed description provided.";
        }

        $user = mysqli_real_escape_string($conn, $user);
        $action = mysqli_real_escape_string($conn, $action);
        $description = mysqli_real_escape_string($conn, $description);

        mysqli_query($conn, "
            INSERT INTO audit_logs (user_name, action, description)
            VALUES ('$user', '$action', '$description')
        ");
    }

}
?>