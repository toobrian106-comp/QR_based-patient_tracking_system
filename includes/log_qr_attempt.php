<?php

if (!function_exists('logQrAttempt')) {

    function logQrAttempt(
        mysqli $conn,
        string $patientId,
        string $scannedValue,
        string $attemptStatus,
        string $failureReason = ''
    ): bool {
        if (!$conn) {
            return false;
        }

        $attemptedBy = $_SESSION['fullname']
            ?? $_SESSION['username']
            ?? $_SESSION['email']
            ?? 'System User';

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        $patientId = mysqli_real_escape_string($conn, (string) $patientId);
        $scannedValue = mysqli_real_escape_string($conn, (string) $scannedValue);
        $attemptStatus = mysqli_real_escape_string($conn, (string) $attemptStatus);
        $failureReason = mysqli_real_escape_string($conn, (string) $failureReason);
        $attemptedBy = mysqli_real_escape_string($conn, (string) $attemptedBy);
        $ipAddress = mysqli_real_escape_string($conn, (string) $ipAddress);

        return mysqli_query($conn, "
            INSERT INTO qr_scan_attempts
            (
                patient_id,
                scanned_value,
                attempt_status,
                failure_reason,
                attempted_by,
                ip_address
            )
            VALUES
            (
                NULLIF('$patientId', ''),
                '$scannedValue',
                '$attemptStatus',
                NULLIF('$failureReason', ''),
                '$attemptedBy',
                '$ipAddress'
            )
        ");
    }
}
?>