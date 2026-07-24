<?php
include('../includes/admin_check.php');
include('../config/db.php');

// Ensure database connection is available
if (!isset($conn) || $conn === null) {
    die('Database connection failed.');
}

/*
|--------------------------------------------------------------------------
| QR SECURITY SUMMARY
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");

function getCount(mysqli $conn, string $sql): int
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    return (int) ($row['total'] ?? 0);
}

$total_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_token IS NOT NULL
     AND qr_token != ''"
);

$active_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Active'
     AND (
        qr_expiry_date IS NULL
        OR qr_expiry_date >= CURDATE()
     )"
);

$suspended_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Suspended'"
);

$lost_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Lost'"
);

$stolen_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Stolen'"
);

$deactivated_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Deactivated'
     OR qr_status='Inactive'"
);

$expired_qr_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_expiry_date IS NOT NULL
     AND qr_expiry_date < CURDATE()"
);

$expiring_soon_cards = getCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Active'
     AND qr_expiry_date IS NOT NULL
     AND qr_expiry_date BETWEEN CURDATE()
     AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
);

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_conditions = [];

if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);

    $where_conditions[] = "(
        patients.fullname LIKE '%$safe_search%'
        OR patients.patient_id LIKE '%$safe_search%'
        OR patients.phone LIKE '%$safe_search%'
        OR patients.location LIKE '%$safe_search%'
    )";
}

if ($status_filter !== '') {
    $safe_status = mysqli_real_escape_string($conn, $status_filter);

    if ($safe_status === 'Expired') {
        $where_conditions[] = "
            patients.qr_expiry_date IS NOT NULL
            AND patients.qr_expiry_date < CURDATE()
        ";
    } elseif ($safe_status === 'Expiring Soon') {
        $where_conditions[] = "
            patients.qr_status='Active'
            AND patients.qr_expiry_date IS NOT NULL
            AND patients.qr_expiry_date BETWEEN CURDATE()
            AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ";
    } elseif ($safe_status === 'Deactivated') {
        $where_conditions[] = "
            patients.qr_status IN ('Deactivated', 'Inactive')
        ";
    } else {
        $where_conditions[] = "patients.qr_status='$safe_status'";
    }
}

$where_sql = '';

if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

/*
|--------------------------------------------------------------------------
| QR CARD RECORDS
|--------------------------------------------------------------------------
*/

$qr_cards_query = mysqli_query(
    $conn,
    "
    SELECT
        patients.id,
        patients.patient_id,
        patients.fullname,
        patients.gender,
        patients.phone,
        patients.location,
        patients.photo,
        patients.qr_code,
        patients.qr_token,
        patients.qr_status,
        patients.qr_expiry_date,
        patients.qr_last_regenerated,
        patients.status AS patient_status
    FROM patients
    $where_sql
    ORDER BY patients.fullname ASC
    "
);

if (!$qr_cards_query) {
    die("QR card query failed: " . mysqli_error($conn));
}

/*
|--------------------------------------------------------------------------
| RECENT QR SECURITY ACTIONS
|--------------------------------------------------------------------------
*/

$security_logs_query = mysqli_query(
    $conn,
    "
    SELECT
        qr_security_logs.*,
        patients.id AS patient_db_id,
        patients.fullname
    FROM qr_security_logs
    LEFT JOIN patients
        ON qr_security_logs.patient_id = patients.patient_id
    ORDER BY qr_security_logs.id DESC
    LIMIT 15
    "
);

if (!$security_logs_query) {
    die("QR security history query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>QR Security Dashboard</title>

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

        .patient-photo {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #0d6efd;
        }

        .photo-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: 2px solid #adb5bd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
        }

        .qr-thumbnail {
            width: 58px;
            height: 58px;
            object-fit: contain;
            background: white;
            padding: 3px;
            border: 1px solid #dee2e6;
            border-radius: 7px;
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
            padding: 7px 11px;
            border-radius: 30px;
        }

        .security-note {
            background: #e7f1ff;
            border-left: 5px solid #0d6efd;
            padding: 16px;
            border-radius: 10px;
        }

        .action-column .btn {
            min-width: 105px;
            margin-bottom: 4px;
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
                QR Security Dashboard
            </h2>

            <p class="text-muted mb-0">
                Monitor secure QR cards, fraud alerts, expiry dates and status changes.
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
                href="../patients/manage_patients.php"
                class="btn btn-dark"
            >
                Manage Patients
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


    <!-- SUMMARY CARDS -->

    <div class="row mb-4">

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-primary">
                <h6>Total Secure QR Cards</h6>
                <h2><?php echo $total_qr_cards; ?></h2>
                <small>Patients with generated tokens</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-success">
                <h6>Active QR Cards</h6>
                <h2><?php echo $active_qr_cards; ?></h2>
                <small>Valid and currently usable</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-warning">
                <h6>Suspended</h6>
                <h2><?php echo $suspended_qr_cards; ?></h2>
                <small>Under security investigation</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-danger">
                <h6>Expired</h6>
                <h2><?php echo $expired_qr_cards; ?></h2>
                <small>Past their validity period</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-dark">
                <h6>Lost Cards</h6>
                <h2><?php echo $lost_qr_cards; ?></h2>
                <small>Reported lost by patients</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-danger">
                <h6>Stolen Cards</h6>
                <h2><?php echo $stolen_qr_cards; ?></h2>
                <small>Reported stolen</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-secondary">
                <h6>Deactivated</h6>
                <h2><?php echo $deactivated_qr_cards; ?></h2>
                <small>Permanently disabled cards</small>
            </div>
        </div>

        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="summary-card bg-info">
                <h6>Expiring Soon</h6>
                <h2><?php echo $expiring_soon_cards; ?></h2>
                <small>Expiry within 30 days</small>
            </div>
        </div>

    </div>

    <!-- FILTERS -->

    <div class="card page-card mb-4 no-print">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">
                Search and Filter QR Cards
            </h5>
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-7 mb-3">

                        <label class="form-label">
                            Search Patient
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Name, patient ID, phone or location"
                            value="<?php echo htmlspecialchars($search); ?>"
                        >

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            QR Status
                        </label>

                        <select
                            name="status"
                            class="form-select"
                        >

                            <option value="">
                                All QR Statuses
                            </option>

                            <?php
                            $status_options = [
                                "Active",
                                "Suspended",
                                "Lost",
                                "Stolen",
                                "Deactivated",
                                "Expired",
                                "Expiring Soon"
                            ];

                            foreach ($status_options as $option) {
                                $selected = $status_filter === $option
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

                    <div class="col-md-2 mb-3 d-grid">

                        <label class="form-label">
                            &nbsp;
                        </label>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Apply Filter
                        </button>

                    </div>

                </div>

                <a
                    href="qr_security_dashboard.php"
                    class="btn btn-secondary"
                >
                    Reset Filters
                </a>

            </form>

        </div>

    </div>

    <!-- QR CARD RECORDS -->

    <div class="card page-card mb-4">

        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                Patient QR Security Records
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
                            <th>QR</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Patient Status</th>
                            <th>QR Status</th>
                            <th>Expiry Date</th>
                            <th>Last Regenerated</th>
                            <th class="no-print">Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php
                    $count = 1;

                    if (mysqli_num_rows($qr_cards_query) > 0) {

                        while ($patient = mysqli_fetch_assoc($qr_cards_query)) {

                            $display_status = $patient['qr_status'] ?? 'Inactive';
                            $status_badge = 'secondary';

                            $patient_is_expired =
                                !empty($patient['qr_expiry_date']) &&
                                strtotime($patient['qr_expiry_date']) <
                                strtotime($today);

                            if ($patient_is_expired) {
                                $display_status = 'Expired';
                                $status_badge = 'warning';
                            } else {
                                switch ($display_status) {
                                    case 'Active':
                                        $status_badge = 'success';
                                        break;

                                    case 'Suspended':
                                        $status_badge = 'warning';
                                        break;

                                    case 'Lost':
                                        $status_badge = 'dark';
                                        break;

                                    case 'Stolen':
                                        $status_badge = 'danger';
                                        break;

                                    case 'Deactivated':
                                    case 'Inactive':
                                        $status_badge = 'secondary';
                                        break;
                                }
                            }

                            $patient_status_badge =
                                ($patient['patient_status'] ?? '') === 'Active'
                                ? 'success'
                                : 'secondary';

                            $qr_image_path =
                                "../assets/qr_codes/" .
                                ($patient['qr_code'] ?? '');
                    ?>

                        <tr>

                            <td>
                                <?php echo $count++; ?>
                            </td>

                            <td>
                                <strong>
                                    <?php echo htmlspecialchars($patient['fullname']); ?>
                                </strong>

                                <br>

                                <small class="text-muted">
                                    <?php echo htmlspecialchars($patient['patient_id']); ?>
                                </small>
                            </td>

                            <td>

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

                            </td>

                            <td>

                                <?php
                                if (
                                    !empty($patient['qr_code']) &&
                                    file_exists($qr_image_path)
                                ) {
                                ?>

                                    <img
                                        src="<?php echo htmlspecialchars($qr_image_path); ?>?v=<?php echo time(); ?>"
                                        class="qr-thumbnail"
                                        alt="Patient QR"
                                    >

                                <?php } else { ?>

                                    <span class="text-danger">
                                        Missing
                                    </span>

                                <?php } ?>

                            </td>

                            <td>
                                <?php echo htmlspecialchars($patient['phone']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($patient['location']); ?>
                            </td>

                            <td>

                                <span class="badge bg-<?php echo $patient_status_badge; ?> status-badge">

                                    <?php
                                    echo htmlspecialchars(
                                        $patient['patient_status'] ?? 'Inactive'
                                    );
                                    ?>

                                </span>

                            </td>

                            <td>

                                <span class="badge bg-<?php echo $status_badge; ?> status-badge">

                                    <?php echo htmlspecialchars($display_status); ?>

                                </span>

                            </td>

                            <td>

                                <?php
                                echo !empty($patient['qr_expiry_date'])
                                    ? date(
                                        "d M Y",
                                        strtotime($patient['qr_expiry_date'])
                                    )
                                    : 'Not set';
                                ?>

                            </td>

                            <td>

                                <?php
                                echo !empty($patient['qr_last_regenerated'])
                                    ? date(
                                        "d M Y H:i",
                                        strtotime($patient['qr_last_regenerated'])
                                    )
                                    : 'Never';
                                ?>

                            </td>

                            <td class="action-column no-print">

                                <a
                                    href="../patients/view_patient.php?id=<?php echo $patient['id']; ?>"
                                    class="btn btn-primary btn-sm"
                                >
                                    View
                                </a>

                                <br>

                                <a
                                    href="../patients/print_card.php?id=<?php echo $patient['id']; ?>"
                                    target="_blank"
                                    class="btn btn-info btn-sm"
                                >
                                    Print
                                </a>

                                <br>

                                <a
                                    href="../patients/regenerate_qr.php?id=<?php echo $patient['id']; ?>"
                                    class="btn btn-warning btn-sm"
                                    onclick="return confirm('Regenerate this QR? All old copies will become invalid immediately.');"
                                >
                                    Regenerate
                                </a>

                            </td>

                        </tr>

                    <?php
                        }
                    } else {
                    ?>

                        <tr>

                            <td
                                colspan="11"
                                class="text-center"
                            >
                                No QR security records match the selected filter.
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- RECENT SECURITY ACTIONS -->

    <div class="card page-card">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">
                Recent QR Security Actions
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Patient</th>
                            <th>Action</th>
                            <th>Reason</th>
                            <th>Changed By</th>
                            <th>Date and Time</th>
                            <th class="no-print">Profile</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php
                    $log_count = 1;

                    if (
                        $security_logs_query &&
                        mysqli_num_rows($security_logs_query) > 0
                    ) {

                        while (
                            $log = mysqli_fetch_assoc($security_logs_query)
                        ) {

                            $log_badge = 'secondary';

                            switch ($log['action']) {
                                case 'Active':
                                    $log_badge = 'success';
                                    break;

                                case 'Suspended':
                                    $log_badge = 'warning';
                                    break;

                                case 'Lost':
                                    $log_badge = 'dark';
                                    break;

                                case 'Stolen':
                                    $log_badge = 'danger';
                                    break;

                                case 'Deactivated':
                                    $log_badge = 'secondary';
                                    break;
                            }
                    ?>

                        <tr>

                            <td>
                                <?php echo $log_count++; ?>
                            </td>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $log['fullname'] ?? 'Deleted Patient'
                                );
                                ?>

                                <br>

                                <small class="text-muted">
                                    <?php echo htmlspecialchars($log['patient_id']); ?>
                                </small>

                            </td>

                            <td>

                                <span class="badge bg-<?php echo $log_badge; ?> status-badge">

                                    <?php echo htmlspecialchars($log['action']); ?>

                                </span>

                            </td>

                            <td>
                                <?php echo htmlspecialchars($log['reason']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($log['changed_by']); ?>
                            </td>

                            <td>

                                <?php
                                echo date(
                                    "d M Y H:i",
                                    strtotime($log['created_at'])
                                );
                                ?>

                            </td>

                            <td class="no-print">

                                <?php if (!empty($log['patient_db_id'])) { ?>

                                    <a
                                        href="../patients/view_patient.php?id=<?php echo $log['patient_db_id']; ?>"
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

                            <td
                                colspan="7"
                                class="text-center"
                            >
                                No QR security actions have been recorded.
                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>
</html>