<?php
include('../includes/admin_check.php');
include('../config/db.php');

/*
|--------------------------------------------------------------------------
| Helper function for summary counts
|--------------------------------------------------------------------------
*/

function getFraudCount($conn, $sql)
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int) ($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Summary statistics
|--------------------------------------------------------------------------
*/

$total_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total FROM qr_scan_attempts"
);

$successful_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE attempt_status='Successful'"
);

$failed_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE attempt_status='Failed'"
);

$today_failed_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE attempt_status='Failed'
     AND DATE(created_at)=CURDATE()"
);

$token_mismatch_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE failure_reason LIKE '%token mismatch%'"
);

$expired_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE failure_reason LIKE '%Expired QR%'"
);

$suspicious_status_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE failure_reason LIKE '%Suspended%'
        OR failure_reason LIKE '%Lost QR%'
        OR failure_reason LIKE '%Stolen QR%'
        OR failure_reason LIKE '%Deactivated QR%'"
);

$duplicate_attempts = getFraudCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE failure_reason LIKE '%Duplicate attendance%'"
);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$reason_filter = trim($_GET['reason'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_conditions = [];

if ($search !== '') {

    $safe_search = mysqli_real_escape_string($conn, $search);

    $where_conditions[] = "(
        qr_scan_attempts.patient_id LIKE '%$safe_search%'
        OR qr_scan_attempts.attempted_by LIKE '%$safe_search%'
        OR qr_scan_attempts.ip_address LIKE '%$safe_search%'
        OR qr_scan_attempts.failure_reason LIKE '%$safe_search%'
    )";
}

if ($status_filter !== '') {

    $safe_status = mysqli_real_escape_string($conn, $status_filter);

    $where_conditions[] = "
        qr_scan_attempts.attempt_status='$safe_status'
    ";
}

if ($reason_filter !== '') {

    $safe_reason = mysqli_real_escape_string($conn, $reason_filter);

    switch ($safe_reason) {

        case "Token Mismatch":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%token mismatch%'
            ";
            break;

        case "Expired QR":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Expired QR%'
            ";
            break;

        case "Suspended QR":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Suspended QR%'
            ";
            break;

        case "Lost QR":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Lost QR%'
            ";
            break;

        case "Stolen QR":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Stolen QR%'
            ";
            break;

        case "Deactivated QR":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Deactivated QR%'
            ";
            break;

        case "Duplicate Attendance":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Duplicate attendance%'
            ";
            break;

        case "Invalid Format":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Invalid QR format%'
            ";
            break;

        case "Unknown Patient":
            $where_conditions[] = "
                qr_scan_attempts.failure_reason LIKE '%Unknown patient%'
            ";
            break;
    }
}

if ($from_date !== '') {

    $safe_from_date = mysqli_real_escape_string($conn, $from_date);

    $where_conditions[] = "
        DATE(qr_scan_attempts.created_at) >= '$safe_from_date'
    ";
}

if ($to_date !== '') {

    $safe_to_date = mysqli_real_escape_string($conn, $to_date);

    $where_conditions[] = "
        DATE(qr_scan_attempts.created_at) <= '$safe_to_date'
    ";
}

$where_sql = "";

if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

/*
|--------------------------------------------------------------------------
| Main scan attempt query
|--------------------------------------------------------------------------
*/

$attempts_query = mysqli_query(
    $conn,
    "
    SELECT
        qr_scan_attempts.*,
        patients.id AS patient_db_id,
        patients.fullname,
        patients.photo,
        patients.qr_status
    FROM qr_scan_attempts
    LEFT JOIN patients
        ON qr_scan_attempts.patient_id = patients.patient_id
    $where_sql
    ORDER BY qr_scan_attempts.id DESC
    "
);

if (!$attempts_query) {
    die("Fraud attempt query failed: " . mysqli_error($conn));
}

/*
|--------------------------------------------------------------------------
| Most targeted patients
|--------------------------------------------------------------------------
*/

$targeted_patients_query = mysqli_query(
    $conn,
    "
    SELECT
        qr_scan_attempts.patient_id,
        patients.id AS patient_db_id,
        patients.fullname,
        COUNT(*) AS attempt_count
    FROM qr_scan_attempts
    LEFT JOIN patients
        ON qr_scan_attempts.patient_id = patients.patient_id
    WHERE qr_scan_attempts.attempt_status='Failed'
    AND qr_scan_attempts.patient_id IS NOT NULL
    AND qr_scan_attempts.patient_id != ''
    GROUP BY
        qr_scan_attempts.patient_id,
        patients.id,
        patients.fullname
    ORDER BY attempt_count DESC
    LIMIT 10
    "
);

/*
|--------------------------------------------------------------------------
| Most active scanning staff
|--------------------------------------------------------------------------
*/

$staff_activity_query = mysqli_query(
    $conn,
    "
    SELECT
        attempted_by,
        COUNT(*) AS total_scans,
        SUM(
            CASE
                WHEN attempt_status='Failed' THEN 1
                ELSE 0
            END
        ) AS failed_scans
    FROM qr_scan_attempts
    GROUP BY attempted_by
    ORDER BY total_scans DESC
    LIMIT 10
    "
);
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>QR Fraud Detection Dashboard</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <style>
        body {
            background: #eef3f8;
            font-family: Arial, sans-serif;
        }

        .page-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .summary-card {
            color: white;
            border-radius: 18px;
            padding: 22px;
            min-height: 130px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.13);
            transition: 0.25s ease;
        }

        .summary-card:hover {
            transform: translateY(-4px);
        }

        .summary-card h2 {
            font-size: 38px;
            font-weight: bold;
            margin: 10px 0;
        }

        .table th {
            background: #1f2937 !important;
            color: white;
            vertical-align: middle;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .status-badge {
            padding: 7px 12px;
            border-radius: 30px;
            font-size: 12px;
        }

        .patient-photo {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0d6efd;
        }

        .photo-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #adb5bd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #6c757d;
            background: #f8f9fa;
        }

        .fraud-alert {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 16px;
        }

        .critical-alert {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
            border-radius: 10px;
            padding: 16px;
        }

        .fingerprint-text {
            max-width: 180px;
            font-family: monospace;
            font-size: 11px;
            word-break: break-all;
        }

        @media print {

            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .page-card,
            .summary-card {
                box-shadow: none;
            }
        }
    </style>

</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">

        <div>

            <h2 class="fw-bold mb-1">
                QR Fraud Detection Dashboard
            </h2>

            <p class="text-muted mb-0">
                Monitor successful scans, failed attempts and suspicious QR activity.
            </p>

        </div>

        <div>

            <a
                href="../dashboard/index.php"
                class="btn btn-primary"
            >
                Dashboard
            </a>

            <a
                href="qr_security_dashboard.php"
                class="btn btn-dark"
            >
                QR Security
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="btn btn-secondary"
            >
                Print Report
            </button>

        </div>

    </div>

    <?php if ($token_mismatch_attempts > 0 || $suspicious_status_attempts > 0) { ?>

        <div class="critical-alert mb-4">

            <strong>Security warning:</strong>

            The system has recorded possible fraudulent QR activity.

            Review token mismatches and attempts involving suspended, lost,
            stolen or deactivated QR cards.

        </div>

    <?php } else { ?>

    <?php } ?>

    <!-- SUMMARY CARDS -->

    <div class="row mb-4">

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-primary">

                <h6>Total Scan Attempts</h6>

                <h2>
                    <?php echo $total_attempts; ?>
                </h2>

                <small>All recorded QR verifications</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-success">

                <h6>Successful Scans</h6>

                <h2>
                    <?php echo $successful_attempts; ?>
                </h2>

                <small>Secure verification passed</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-danger">

                <h6>Failed Scans</h6>

                <h2>
                    <?php echo $failed_attempts; ?>
                </h2>

                <small>Rejected verification attempts</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-warning">

                <h6>Failed Today</h6>

                <h2>
                    <?php echo $today_failed_attempts; ?>
                </h2>

                <small>Rejected attempts today</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-dark">

                <h6>Token Mismatches</h6>

                <h2>
                    <?php echo $token_mismatch_attempts; ?>
                </h2>

                <small>Possible copied or forged QR codes</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-secondary">

                <h6>Expired QR Attempts</h6>

                <h2>
                    <?php echo $expired_attempts; ?>
                </h2>

                <small>Expired cards presented</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-danger">

                <h6>Restricted Cards</h6>

                <h2>
                    <?php echo $suspicious_status_attempts; ?>
                </h2>

                <small>Suspended, lost, stolen or disabled</small>

            </div>

        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">

            <div class="summary-card bg-info">

                <h6>Duplicate Attempts</h6>

                <h2>
                    <?php echo $duplicate_attempts; ?>
                </h2>

                <small>Repeat attendance attempts</small>

            </div>

        </div>

    </div>

    <!-- FILTERS -->

    <div class="card page-card mb-4 no-print">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">
                Search and Filter Scan Attempts
            </h5>

        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Patient ID, staff, IP or reason"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                    </div>

                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            Attempt Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="Successful"
                                <?php echo $status_filter === 'Successful' ? 'selected' : ''; ?>
                            >
                                Successful
                            </option>

                            <option
                                value="Failed"
                                <?php echo $status_filter === 'Failed' ? 'selected' : ''; ?>
                            >
                                Failed
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Rejection Reason
                        </label>

                        <select
                            name="reason"
                            class="form-select"
                        >

                            <option value="">
                                All Reasons
                            </option>

                            <?php
                            $reason_options = [
                                "Token Mismatch",
                                "Expired QR",
                                "Suspended QR",
                                "Lost QR",
                                "Stolen QR",
                                "Deactivated QR",
                                "Duplicate Attendance",
                                "Invalid Format",
                                "Unknown Patient"
                            ];

                            foreach ($reason_options as $option) {
                                $selected = $reason_filter === $option
                                    ? 'selected'
                                    : '';
                            ?>

                                <option
                                    value="<?php echo htmlspecialchars($option); ?>"
                                    <?php echo $selected; ?>
                                >
                                    <?php echo htmlspecialchars($option); ?>
                                </option>

                            <?php } ?>

                        </select>

                    </div>

                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            From Date
                        </label>

                        <input
                            type="date"
                            name="from_date"
                            class="form-control"
                            value="<?php echo htmlspecialchars($from_date); ?>"
                        >

                    </div>

                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            To Date
                        </label>

                        <input
                            type="date"
                            name="to_date"
                            class="form-control"
                            value="<?php echo htmlspecialchars($to_date); ?>"
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>

                <a
                    href="qr_fraud_dashboard.php"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            </form>

        </div>

    </div>

    <!-- SCAN ATTEMPTS TABLE -->

    <div class="card page-card mb-4">

        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                QR Scan Attempt Records
            </h5>

            <span>
                Generated:
                <?php echo date("d M Y H:i"); ?>
            </span>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Attempted By</th>
                            <th>IP Address</th>
                            <th>QR Fingerprint</th>
                            <th>Date and Time</th>
                            <th class="no-print">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($attempts_query) > 0) {

                        while ($attempt = mysqli_fetch_assoc($attempts_query)) {

                            $attempt_badge =
                                $attempt['attempt_status'] === 'Successful'
                                ? 'success'
                                : 'danger';
                    ?>

                        <tr>

                            <td>
                                <?php echo $count++; ?>
                            </td>

                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $attempt['fullname']
                                        ?? $attempt['patient_id']
                                        ?? 'Unknown Patient'
                                    );
                                    ?>

                                </strong>

                                <?php if (!empty($attempt['fullname'])) { ?>

                                    <br>

                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($attempt['patient_id']); ?>
                                    </small>

                                <?php } ?>

                            </td>

                            <td>

                                <?php if (!empty($attempt['photo'])) { ?>

                                    <img
                                        src="../assets/patient_photos/<?php echo htmlspecialchars($attempt['photo']); ?>"
                                        class="patient-photo"
                                        alt="Patient Photo"
                                    >

                                <?php } else { ?>

                                    <div class="photo-placeholder">
                                        No Photo
                                    </div>

                                <?php } ?>

                            </td>

                            <td>

                                <span class="badge bg-<?php echo $attempt_badge; ?> status-badge">

                                    <?php echo htmlspecialchars($attempt['attempt_status']); ?>

                                </span>

                            </td>

                            <td>

                                <?php
                                echo !empty($attempt['failure_reason'])
                                    ? htmlspecialchars($attempt['failure_reason'])
                                    : 'Verification passed';
                                ?>

                            </td>

                            <td>
                                <?php echo htmlspecialchars($attempt['attempted_by']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($attempt['ip_address']); ?>
                            </td>

                            <td>

                                <div class="fingerprint-text">

                                    <?php
                                    echo htmlspecialchars(
                                        $attempt['scanned_value'] ?? ''
                                    );
                                    ?>

                                </div>

                            </td>

                            <td>

                                <?php
                                echo date(
                                    "d M Y H:i:s",
                                    strtotime($attempt['created_at'])
                                );
                                ?>

                            </td>

                            <td class="no-print">

                                <?php if (!empty($attempt['patient_db_id'])) { ?>

                                    <a
                                        href="../patients/view_patient.php?id=<?php echo $attempt['patient_db_id']; ?>"
                                        class="btn btn-primary btn-sm"
                                    >
                                        View Patient
                                    </a>

                                <?php } else { ?>

                                    <span class="text-muted">
                                        No profile
                                    </span>

                                <?php } ?>

                            </td>

                        </tr>

                    <?php
                        }
                    } else {
                    ?>

                        <tr>

                            <td
                                colspan="10"
                                class="text-center"
                            >
                                No QR scan attempts match the selected filters.
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="row">

        <!-- MOST TARGETED PATIENTS -->

        <div class="col-md-6 mb-4">

            <div class="card page-card h-100">

                <div class="card-header bg-danger text-white">

                    <h5 class="mb-0">
                        Patients with Most Failed Attempts
                    </h5>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead>

                                <tr>
                                    <th>#</th>
                                    <th>Patient</th>
                                    <th>Failed Attempts</th>
                                    <th class="no-print">Profile</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            $target_count = 1;

                            if (
                                $targeted_patients_query &&
                                mysqli_num_rows($targeted_patients_query) > 0
                            ) {

                                while (
                                    $target = mysqli_fetch_assoc(
                                        $targeted_patients_query
                                    )
                                ) {
                            ?>

                                <tr>

                                    <td>
                                        <?php echo $target_count++; ?>
                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $target['fullname']
                                            ?? $target['patient_id']
                                        );
                                        ?>

                                        <br>

                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($target['patient_id']); ?>
                                        </small>

                                    </td>

                                    <td>

                                        <span class="badge bg-danger status-badge">

                                            <?php echo (int) $target['attempt_count']; ?>

                                        </span>

                                    </td>

                                    <td class="no-print">

                                        <?php if (!empty($target['patient_db_id'])) { ?>

                                            <a
                                                href="../patients/view_patient.php?id=<?php echo $target['patient_db_id']; ?>"
                                                class="btn btn-primary btn-sm"
                                            >
                                                Open
                                            </a>

                                        <?php } else { ?>

                                            <span class="text-muted">
                                                Unavailable
                                            </span>

                                        <?php } ?>

                                    </td>

                                </tr>

                            <?php
                                }
                            } else {
                            ?>

                                <tr>

                                    <td colspan="4" class="text-center">
                                        No failed patient-linked attempts found.
                                    </td>

                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

        <!-- STAFF SCANNING ACTIVITY -->

        <div class="col-md-6 mb-4">

            <div class="card page-card h-100">

                <div class="card-header bg-primary text-white">

                    <h5 class="mb-0">
                        Staff QR Scanning Activity
                    </h5>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead>

                                <tr>
                                    <th>#</th>
                                    <th>Staff Member</th>
                                    <th>Total Scans</th>
                                    <th>Failed Scans</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            $staff_count = 1;

                            if (
                                $staff_activity_query &&
                                mysqli_num_rows($staff_activity_query) > 0
                            ) {

                                while (
                                    $staff = mysqli_fetch_assoc(
                                        $staff_activity_query
                                    )
                                ) {
                            ?>

                                <tr>

                                    <td>
                                        <?php echo $staff_count++; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($staff['attempted_by']); ?>
                                    </td>

                                    <td>

                                        <span class="badge bg-primary status-badge">

                                            <?php echo (int) $staff['total_scans']; ?>

                                        </span>

                                    </td>

                                    <td>

                                        <span class="badge bg-danger status-badge">

                                            <?php echo (int) $staff['failed_scans']; ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php
                                }
                            } else {
                            ?>

                                <tr>

                                    <td colspan="4" class="text-center">
                                        No staff scanning activity recorded.
                                    </td>

                                </tr>

                            <?php } ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

</body>

</html>