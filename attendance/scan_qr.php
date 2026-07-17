<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_qr_attempt.php');

$patient = null;
$error = "";
$success = "";
$scanned_qr_value = "";

/*
|--------------------------------------------------------------------------
| Clear previous verification session
|--------------------------------------------------------------------------
*/

unset($_SESSION['verified_patient_id']);
unset($_SESSION['verified_qr_token']);
unset($_SESSION['qr_verified_at']);

/*
|--------------------------------------------------------------------------
| Process scanned QR value
|--------------------------------------------------------------------------
*/

if (isset($_GET['qr']) && trim($_GET['qr']) !== '') {

    $scanned_qr_value = trim($_GET['qr']);
    $scanned_fingerprint = hash('sha256', $scanned_qr_value);

    /*
    |--------------------------------------------------------------------------
    | Expected QR format: PATIENT_ID|SECURE_TOKEN
    |--------------------------------------------------------------------------
    */

    $qr_parts = explode("|", $scanned_qr_value, 2);

    if (
        count($qr_parts) !== 2 ||
        trim($qr_parts[0]) === '' ||
        trim($qr_parts[1]) === ''
    ) {

        $error = "Invalid QR code.";

        logQrAttempt(
            $conn,
            "",
            $scanned_fingerprint,
            "Failed",
            "Invalid QR format"
        );

    } else {

        $patient_id_raw = trim($qr_parts[0]);
        $qr_token_raw = trim($qr_parts[1]);

        if ($conn instanceof mysqli) {
            $patient_id = mysqli_real_escape_string($conn, $patient_id_raw);
        } else {
            $patient_id = $patient_id_raw;
        }

        /*
        |--------------------------------------------------------------------------
        | Find patient
        |--------------------------------------------------------------------------
        */

        if ($conn instanceof mysqli) {
            $patient_query = mysqli_query($conn, "
                SELECT *
                FROM patients
                WHERE patient_id = '$patient_id'
                LIMIT 1
            ");
        } else {
            $patient_query = false;
        }

        if (!$patient_query) {

            $error = "Unable to verify the QR code.";

            logQrAttempt(
                $conn,
                $patient_id_raw,
                $scanned_fingerprint,
                "Failed",
                "Database verification error"
            );

        } elseif (mysqli_num_rows($patient_query) === 0) {

            $error = "Patient record not found.";

            logQrAttempt(
                $conn,
                $patient_id_raw,
                $scanned_fingerprint,
                "Failed",
                "Unknown patient ID"
            );

        } else {

            $patient = mysqli_fetch_assoc($patient_query);

            $stored_token = (string) ($patient['qr_token'] ?? '');
            $qr_status = (string) ($patient['qr_status'] ?? 'Inactive');
            $patient_status = (string) ($patient['status'] ?? 'Inactive');
            $qr_expiry_date = $patient['qr_expiry_date'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | Verify token
            |--------------------------------------------------------------------------
            */

            if (
                $stored_token === '' ||
                !hash_equals($stored_token, $qr_token_raw)
            ) {

                $error = "QR verification failed.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Secure QR token mismatch"
                );

                $patient = null;

            } elseif ($qr_status === "Suspended") {

                $error = "This QR card is suspended.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Suspended QR card scan attempt"
                );

                $patient = null;

            } elseif ($qr_status === "Lost") {

                $error = "This QR card was reported lost.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Lost QR card scan attempt"
                );

                $patient = null;

            } elseif ($qr_status === "Stolen") {

                $error = "This QR card was reported stolen.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Stolen QR card scan attempt"
                );

                $patient = null;

            } elseif (
                $qr_status === "Deactivated" ||
                $qr_status === "Inactive"
            ) {

                $error = "This QR card is inactive.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Deactivated QR card scan attempt"
                );

                $patient = null;

            } elseif ($qr_status !== "Active") {

                $error = "This QR card cannot be used.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "QR card status is " . $qr_status
                );

                $patient = null;

            } elseif (
                !empty($qr_expiry_date) &&
                strtotime($qr_expiry_date) < strtotime(date("Y-m-d"))
            ) {

                $error = "This QR card has expired.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Expired QR card scan attempt"
                );

                $patient = null;

            } elseif ($patient_status !== "Active") {

                $error = "This patient account is inactive.";

                logQrAttempt(
                    $conn,
                    $patient_id_raw,
                    $scanned_fingerprint,
                    "Failed",
                    "Inactive patient account"
                );

                $patient = null;

            } else {

                /*
                |--------------------------------------------------------------------------
                | Prevent duplicate attendance
                |--------------------------------------------------------------------------
                */

                $today = date("Y-m-d");

                $duplicate_query = null;
                if ($conn instanceof mysqli) {
                    $duplicate_query = mysqli_query($conn, "
                        SELECT id
                        FROM attendance
                        WHERE patient_id = '$patient_id'
                        AND visit_date = '$today'
                        LIMIT 1
                    ");
                }

                if (
                    $duplicate_query &&
                    mysqli_num_rows($duplicate_query) > 0
                ) {

                    $error = "Attendance has already been recorded today.";

                    logQrAttempt(
                        $conn,
                        $patient_id_raw,
                        $scanned_fingerprint,
                        "Failed",
                        "Duplicate attendance attempt"
                    );

                    $patient = null;

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Successful verification
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['verified_patient_id'] = $patient['patient_id'];
                    $_SESSION['verified_qr_token'] = hash(
                        'sha256',
                        $qr_token_raw
                    );
                    $_SESSION['qr_verified_at'] = time();

                    $success = "Patient verified successfully.";

                    logQrAttempt(
                        $conn,
                        $patient_id_raw,
                        $scanned_fingerprint,
                        "Successful",
                        "Secure QR verification passed"
                    );
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Secure QR Scanner</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://unpkg.com/html5-qrcode"></script>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #eef3f8;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

        .page-wrapper {
            max-width: 1050px;
            margin: 35px auto;
            padding: 0 16px;
        }

        .scanner-card {
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(31, 45, 61, 0.12);
        }

        .scanner-header {
            padding: 18px 22px;
            color: white;
            background: linear-gradient(135deg, #0f6cbd, #1689ee);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .scanner-header h3 {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
        }

        .scanner-header small {
            display: block;
            margin-top: 4px;
            opacity: 0.9;
        }

        .scanner-body {
            padding: 22px;
        }

        .scanner-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
            gap: 20px;
        }

        .panel {
            background: #f8fafc;
            border: 1px solid #dce4ee;
            border-radius: 16px;
            padding: 18px;
        }

        .panel-title {
            margin: 0 0 15px;
            font-size: 17px;
            font-weight: 800;
            text-align: center;
        }

        #reader {
            width: 100%;
            max-width: 560px;
            margin: auto;
            overflow: hidden;
            border-radius: 14px;
        }

        .scan-status {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 16px;
            padding: 11px 14px;
            border-radius: 12px;
            background: #eaf7ef;
            color: #146c43;
            font-size: 13px;
            font-weight: 700;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #198754;
        }

        .form-control,
        .form-select {
            min-height: 46px;
        }

        textarea.form-control {
            min-height: 115px;
            resize: vertical;
        }

        .patient-card {
            background: #ffffff;
            border: 1px solid #dce4ee;
            border-radius: 16px;
            padding: 20px;
        }

        .patient-photo {
            width: 115px;
            height: 115px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #0d6efd;
        }

        .photo-placeholder {
            width: 115px;
            height: 115px;
            border-radius: 50%;
            border: 4px solid #0d6efd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            background: #eef3f8;
            color: #6c757d;
        }

        .verify-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 13px;
            border-radius: 25px;
            background: #198754;
            color: white;
            font-size: 12px;
            font-weight: 800;
        }

        @media (max-width: 820px) {
            .scanner-layout {
                grid-template-columns: 1fr;
            }

            .scanner-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

</head>

<body>

<div class="page-wrapper">

    <div class="scanner-card">

        <div class="scanner-header">

            <div>
                <h3>
                    <i class="bi bi-qr-code-scan me-2"></i>
                    Secure QR Scanner
                </h3>

                <small>
                    Patient verification and attendance
                </small>
            </div>

            <div>

                <a
                    href="../dashboard/index.php"
                    class="btn btn-light btn-sm"
                >
                    Dashboard
                </a>

                <a
                    href="attendance_list.php"
                    class="btn btn-light btn-sm"
                >
                    Attendance Records
                </a>

            </div>

        </div>

        <div class="scanner-body">

            <?php if ($error !== '') { ?>

                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php } ?>

            <?php if ($success !== '') { ?>

                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>

            <?php } ?>

            <?php if ($patient === null) { ?>

                <div class="scanner-layout">

                    <div class="panel">

                        <div class="scan-status">
                            <span class="status-dot"></span>
                            Ready to scan
                        </div>

                        <h5 class="panel-title">
                            Scan Patient QR Code
                        </h5>

                        <div id="reader"></div>

                    </div>

                    <div class="panel">

                        <h5 class="panel-title">
                            QR Verification
                        </h5>

                        <form method="GET">

                            <textarea
                                name="qr"
                                class="form-control mb-3"
                                placeholder="Waiting for scan..."
                                required
                            ><?php echo htmlspecialchars($scanned_qr_value); ?></textarea>

                            <button
                                type="submit"
                                class="btn btn-primary w-100"
                            >
                                Verify QR Code
                            </button>

                        </form>

                    </div>

                </div>

            <?php } else { ?>

                <div class="patient-card">

                    <div class="row align-items-center">

                        <div class="col-md-2 text-center mb-3">

                            <?php if (!empty($patient['photo'])) { ?>

                                <img
                                    src="../assets/patient_photos/<?php echo htmlspecialchars($patient['photo']); ?>"
                                    class="patient-photo"
                                    alt="Patient Photo"
                                >

                            <?php } else { ?>

                                <div class="photo-placeholder">
                                    No Photo
                                </div>

                            <?php } ?>

                        </div>

                        <div class="col-md-6 mb-3">

                            <span class="verify-badge mb-3">
                                <i class="bi bi-check-circle-fill"></i>
                                Verified
                            </span>

                            <h3 class="fw-bold">
                                <?php echo htmlspecialchars($patient['fullname']); ?>
                            </h3>

                            <p class="mb-1">
                                <strong>Patient ID:</strong>
                                <?php echo htmlspecialchars($patient['patient_id']); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Gender:</strong>
                                <?php echo htmlspecialchars($patient['gender']); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Age:</strong>
                                <?php echo htmlspecialchars($patient['age']); ?>
                            </p>

                            <p class="mb-1">
                                <strong>Location:</strong>
                                <?php echo htmlspecialchars($patient['location']); ?>
                            </p>

                        </div>

                        <div class="col-md-4 mb-3">

                            <div class="alert alert-success mb-2">
                                <strong>QR Status:</strong>
                                Active
                            </div>

                            <div class="alert alert-info mb-0">
                                <strong>Valid Until:</strong>

                                <?php
                                echo !empty($patient['qr_expiry_date'])
                                    ? date(
                                        "d M Y",
                                        strtotime($patient['qr_expiry_date'])
                                    )
                                    : "Not set";
                                ?>
                            </div>

                        </div>

                    </div>

                    <hr>

                    <form
                        method="POST"
                        action="record_attendance.php"
                    >

                        <input
                            type="hidden"
                            name="patient_id"
                            value="<?php echo htmlspecialchars($patient['patient_id']); ?>"
                        >

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Service Given
                                </label>

                                <select
                                    name="service_given"
                                    class="form-select"
                                    required
                                >
                                    <option value="">Select Service</option>
                                    <option value="Food Basket">Food Basket</option>
                                    <option value="Vitamin Supplement">Vitamin Supplement</option>
                                    <option value="Food Basket and Vitamin Supplement">
                                        Food Basket and Vitamin Supplement
                                    </option>
                                    <option value="Counselling">Counselling</option>
                                    <option value="Follow-Up Visit">Follow-Up Visit</option>
                                    <option value="Other">Other</option>
                                </select>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Identity Confirmation
                                </label>

                                <select
                                    name="identity_confirmed"
                                    class="form-select"
                                    required
                                >
                                    <option value="">Select confirmation</option>
                                    <option value="Yes">
                                        Patient identity confirmed
                                    </option>
                                </select>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">
                                Notes
                            </label>

                            <textarea
                                name="notes"
                                class="form-control"
                                rows="3"
                                placeholder="Optional notes"
                            ></textarea>

                        </div>

                        <button
                            type="submit"
                            name="record"
                            class="btn btn-success"
                        >
                            Record Attendance
                        </button>

                        <a
                            href="scan_qr.php"
                            class="btn btn-secondary"
                        >
                            Scan Another QR
                        </a>

                    </form>

                </div>

            <?php } ?>

        </div>

    </div>

</div>

<script>
function onScanSuccess(decodedText) {
    window.location.href =
        "scan_qr.php?qr=" + encodeURIComponent(decodedText);
}

function onScanFailure(errorMessage) {
    // Ignore normal camera scanning failures.
}

<?php if ($patient === null) { ?>

const scanner = new Html5QrcodeScanner(
    "reader",
    {
        fps: 10,
        qrbox: {
            width: 250,
            height: 250
        },
        rememberLastUsedCamera: true,
        supportedScanTypes: [
            Html5QrcodeScanType.SCAN_TYPE_CAMERA,
            Html5QrcodeScanType.SCAN_TYPE_FILE
        ]
    },
    false
);

scanner.render(onScanSuccess, onScanFailure);

<?php } ?>
</script>

</body>
</html>