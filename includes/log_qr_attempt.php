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

        $patientId = mysqli_real_escape_string($conn, $patientId);
        $scannedValue = mysqli_real_escape_string($conn, $scannedValue);

        // Convert status to match ENUM values in the database
        switch (strtoupper($attemptStatus)) {
            case 'SUCCESSFUL':
            case 'SUCCESS':
                $dbStatus = 'SUCCESS';
                break;

            case 'FAILED':
            case 'FAIL':
                $dbStatus = 'FAILED';
                break;

            case 'INVALID':
                $dbStatus = 'INVALID';
                break;

            default:
                $dbStatus = 'RESTRICTED';
        }

        return mysqli_query($conn, "
            INSERT INTO qr_scan_attempts
            (
                patient_id,
                qr_code,
                scan_status
            )
            VALUES
            (
                '$patientId',
                '$scannedValue',
                '$dbStatus'
            )
        ");
    }
}
?>