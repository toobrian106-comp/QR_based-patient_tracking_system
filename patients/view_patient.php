<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../phpqrcode/qrlib.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id = (int) $_GET['id'];

$patient_query = mysqli_query($conn, "
    SELECT *
    FROM patients
    WHERE id = $id
    LIMIT 1
");

if (!$patient_query || mysqli_num_rows($patient_query) === 0) {
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($patient_query);
$patient_id = $patient['patient_id'];

/*
|--------------------------------------------------------------------------
| Current logged-in user role
|--------------------------------------------------------------------------
*/

$current_role = $_SESSION['role'] ?? 'Healthcare Worker';
$is_admin = strtolower($current_role) === 'admin';

/*
|--------------------------------------------------------------------------
| Secure QR generation if image is missing
|--------------------------------------------------------------------------
*/

$qr_folder = __DIR__ . "/../assets/qr_codes/";
$qr_filename = !empty($patient['qr_code'])
    ? $patient['qr_code']
    : $patient_id . ".png";

$qr_server_path = $qr_folder . $qr_filename;
$qr_web_path = "../assets/qr_codes/" . $qr_filename;

if (!is_dir($qr_folder)) {
    mkdir($qr_folder, 0777, true);
}

if (
    !file_exists($qr_server_path) &&
    !empty($patient['qr_token'])
) {
    $qr_data = $patient_id . "|" . $patient['qr_token'];

    QRcode::png(
        $qr_data,
        $qr_server_path,
        QR_ECLEVEL_H,
        8,
        2
    );

    $safe_qr_filename = mysqli_real_escape_string($conn, $qr_filename);

    mysqli_query($conn, "
        UPDATE patients
        SET qr_code = '$safe_qr_filename'
        WHERE id = $id
    ");
}

/*
|--------------------------------------------------------------------------
| Attendance statistics
|--------------------------------------------------------------------------
*/

$total_visits_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM attendance
    WHERE patient_id = '" . mysqli_real_escape_string($conn, $patient_id) . "'
");

$total_visits = 0;

if ($total_visits_query) {
    $total_visits_data = mysqli_fetch_assoc($total_visits_query);
    $total_visits = (int) $total_visits_data['total'];
}

$expected_visits = 12;

$missed_visits = $expected_visits - $total_visits;

if ($missed_visits < 0) {
    $missed_visits = 0;
}

$adherence_percentage = round(
    ($total_visits / $expected_visits) * 100,
    1
);

if ($adherence_percentage >= 80) {
    $adherence_status = "Good";
    $adherence_badge = "success";
} elseif ($adherence_percentage >= 50) {
    $adherence_status = "Moderate";
    $adherence_badge = "warning";
} else {
    $adherence_status = "Defaulting";
    $adherence_badge = "danger";
}

/*
|--------------------------------------------------------------------------
| Last visit and defaulting status
|--------------------------------------------------------------------------
*/

$safe_patient_id = mysqli_real_escape_string($conn, $patient_id);

$last_visit_query = mysqli_query($conn, "
    SELECT MAX(visit_date) AS last_visit
    FROM attendance
    WHERE patient_id = '$safe_patient_id'
");

$last_visit = null;

if ($last_visit_query) {
    $last_visit_data = mysqli_fetch_assoc($last_visit_query);
    $last_visit = $last_visit_data['last_visit'];
}

$default_days = 30;
$is_defaulting = false;
$days_since_last_visit = null;

if (empty($last_visit)) {
    $is_defaulting = true;
} else {
    $last_visit_date = new DateTime($last_visit);
    $today_date = new DateTime(date("Y-m-d"));

    $days_since_last_visit = $last_visit_date
        ->diff($today_date)
        ->days;

    if ($days_since_last_visit > $default_days) {
        $is_defaulting = true;
    }
}

/*
|--------------------------------------------------------------------------
| QR security status
|--------------------------------------------------------------------------
*/

$qr_status = $patient['qr_status'] ?? 'Inactive';
$qr_expiry_date = $patient['qr_expiry_date'] ?? null;

$is_qr_expired = false;

if (
    !empty($qr_expiry_date) &&
    strtotime($qr_expiry_date) < strtotime(date("Y-m-d"))
) {
    $is_qr_expired = true;
}

$qr_status_text = $qr_status;
$qr_status_badge = "secondary";

switch ($qr_status) {
    case "Active":
        $qr_status_badge = "success";
        break;

    case "Suspended":
        $qr_status_badge = "warning";
        break;

    case "Lost":
        $qr_status_badge = "dark";
        break;

    case "Stolen":
        $qr_status_badge = "danger";
        break;

    case "Deactivated":
        $qr_status_badge = "secondary";
        break;

    default:
        $qr_status_badge = "secondary";
}

if ($is_qr_expired) {
    $qr_status_text = "Expired";
    $qr_status_badge = "warning";
}

/*
|--------------------------------------------------------------------------
| Attendance history
|--------------------------------------------------------------------------
*/

$attendance_history = mysqli_query($conn, "
    SELECT *
    FROM attendance
    WHERE patient_id = '$safe_patient_id'
    ORDER BY visit_date DESC, id DESC
");

/*
|--------------------------------------------------------------------------
| QR security history
|--------------------------------------------------------------------------
*/

$qr_security_history = mysqli_query($conn, "
    SELECT *
    FROM qr_security_logs
    WHERE patient_id = '$safe_patient_id'
    ORDER BY id DESC
    LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Patient Profile - <?php echo htmlspecialchars($patient['fullname']); ?>
    </title>

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

        .profile-header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .profile-photo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #0d6efd;
        }

        .photo-placeholder {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 4px solid #0d6efd;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: auto;
            color: #6c757d;
        }

        .stat-card {
            border: none;
            border-radius: 18px;
            color: white;
            padding: 20px;
            min-height: 125px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-card h2 {
            font-weight: bold;
            margin: 10px 0;
        }

        .card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .qr-image {
            width: 190px;
            height: 190px;
            object-fit: contain;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 8px;
        }

        .security-information {
            background: #eef5ff;
            border-left: 5px solid #0d6efd;
            border-radius: 10px;
            padding: 15px;
        }

        .security-warning {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
        }

        .table th {
            background: #1f2937 !important;
            color: white;
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }

        .security-action-card {
            border: 1px solid #dee2e6;
            border-radius: 14px;
            padding: 18px;
            background: #f8f9fa;
        }

        .badge-security {
            padding: 8px 14px;
            border-radius: 30px;
        }

        .action-buttons .btn {
            min-width: 150px;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Patient Management Dashboard
            </h2>

            <p class="text-muted mb-0">
                Patient identification, adherence, attendance and QR security management
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
                href="manage_patients.php"
                class="btn btn-secondary"
            >
                Back to Patients
            </a>
        </div>

    </div>

    <?php if (isset($_GET['qr']) && $_GET['qr'] === 'regenerated') { ?>

        <div class="alert alert-success">
            <strong>Secure QR regenerated successfully.</strong>
            All previous copies of the old QR code are now invalid.
        </div>

    <?php } ?>

    <div class="profile-header mb-4">

        <div class="row align-items-center">

            <div class="col-md-2 text-center">

                <?php if (!empty($patient['photo'])) { ?>

                    <img
                        src="../assets/patient_photos/<?php echo htmlspecialchars($patient['photo']); ?>"
                        class="profile-photo"
                        alt="Patient Photo"
                    >

                <?php } else { ?>

                    <div class="photo-placeholder">
                        No Photo
                    </div>

                <?php } ?>

            </div>

            <div class="col-md-4">

                <h3 class="fw-bold">
                    <?php echo htmlspecialchars($patient['fullname']); ?>
                </h3>

                <p class="text-muted mb-2">
                    <?php echo htmlspecialchars($patient['patient_id']); ?>
                </p>

                <span class="badge bg-<?php echo ($patient['status'] ?? 'Active') === 'Active' ? 'success' : 'secondary'; ?>">
                    Patient <?php echo htmlspecialchars($patient['status'] ?? 'Active'); ?>
                </span>

                <?php if ($is_defaulting) { ?>

                    <span class="badge bg-danger">
                        Defaulting Risk
                    </span>

                <?php } else { ?>

                    <span class="badge bg-primary">
                        Stable
                    </span>

                <?php } ?>

                <span class="badge bg-<?php echo $qr_status_badge; ?>">
                    QR <?php echo htmlspecialchars($qr_status_text); ?>
                </span>

            </div>

            <div class="col-md-3 text-center">

                <?php if (file_exists($qr_server_path)) { ?>

                    <img
                        src="<?php echo htmlspecialchars($qr_web_path); ?>?v=<?php echo time(); ?>"
                        class="qr-image"
                        alt="Secure Patient QR Code"
                    >

                <?php } else { ?>

                    <div class="alert alert-danger">
                        QR image is missing.
                    </div>

                <?php } ?>

            </div>

            <div class="col-md-3 text-end action-buttons">

                <a
                    href="print_card.php?id=<?php echo $patient['id']; ?>"
                    target="_blank"
                    class="btn btn-info mb-2"
                >
                    Print QR Card
                </a>

                <br>

                <a
                    href="export_patient_record.php?id=<?php echo $patient['id']; ?>"
                    class="btn btn-success mb-2"
                >
                    Export Record
                </a>

                <br>

                <a
                    href="edit_patient.php?id=<?php echo $patient['id']; ?>"
                    class="btn btn-warning mb-2"
                >
                    Edit Patient
                </a>

            </div>

        </div>

    </div>

    <div class="row mb-4">

        <div class="col-md-3 mb-3">

            <div class="stat-card bg-primary">

                <h5>Total Visits</h5>

                <h2>
                    <?php echo $total_visits; ?>
                </h2>

                <small>Recorded attendance entries</small>

            </div>

        </div>

        <div class="col-md-3 mb-3">

            <div class="stat-card bg-success">

                <h5>Adherence</h5>

                <h2>
                    <?php echo $adherence_percentage; ?>%
                </h2>

                <small>
                    <?php echo $adherence_status; ?>
                </small>

            </div>

        </div>

        <div class="col-md-3 mb-3">

            <div class="stat-card bg-warning">

                <h5>Missed Visits</h5>

                <h2>
                    <?php echo $missed_visits; ?>
                </h2>

                <small>
                    Based on <?php echo $expected_visits; ?> expected yearly visits
                </small>

            </div>

        </div>

        <div class="col-md-3 mb-3">

            <div class="stat-card bg-danger">

                <h5>Defaulting Status</h5>

                <h2>
                    <?php echo $is_defaulting ? "Risk" : "Safe"; ?>
                </h2>

                <small>
                    Last visit:
                    <?php echo empty($last_visit) ? "None" : htmlspecialchars($last_visit); ?>
                </small>

            </div>

        </div>

    </div>

    <!-- QR SECURITY MANAGEMENT -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">
                QR Security Management
            </h5>

        </div>

        <div class="card-body">

            <div class="row mb-4">

                <div class="col-md-3">

                    <strong>Current QR Status</strong>

                    <br>

                    <span class="badge bg-<?php echo $qr_status_badge; ?> badge-security mt-2">
                        <?php echo htmlspecialchars(strtoupper($qr_status_text)); ?>
                    </span>

                </div>

                <div class="col-md-3">

                    <strong>Expiry Date</strong>

                    <br>

                    <span class="mt-2 d-inline-block">

                        <?php
                        echo !empty($qr_expiry_date)
                            ? date("d M Y", strtotime($qr_expiry_date))
                            : "Not set";
                        ?>

                    </span>

                </div>

                <div class="col-md-3">

                    <strong>Last Regenerated</strong>

                    <br>

                    <span class="mt-2 d-inline-block">

                        <?php
                        echo !empty($patient['qr_last_regenerated'])
                            ? date("d M Y H:i", strtotime($patient['qr_last_regenerated']))
                            : "Never";
                        ?>

                    </span>

                </div>

                <div class="col-md-3">

                    <strong>Logged-in Role</strong>

                    <br>

                    <span class="badge bg-primary badge-security mt-2">
                        <?php echo htmlspecialchars($current_role); ?>
                    </span>

                </div>

            </div>

            <div class="security-action-card mb-4">

                <h5>
                    Change QR Security Status
                </h5>

                <form
                    method="POST"
                    action="update_qr_status.php"
                >

                    <input
                        type="hidden"
                        name="patient_db_id"
                        value="<?php echo $patient['id']; ?>"
                    >

                    <div class="row">

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                New QR Status
                            </label>

                            <select
                                name="qr_status"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select QR status
                                </option>

                                <?php if (!$is_admin) { ?>

                                    <option value="Suspended">
                                        Suspend — Suspected fraud
                                    </option>

                                    <option value="Lost">
                                        Mark as Lost
                                    </option>

                                    <option value="Stolen">
                                        Mark as Stolen
                                    </option>

                                <?php } ?>

                                <?php if ($is_admin) { ?>

                                    <option value="Active">
                                        Activate
                                    </option>

                                    <option value="Suspended">
                                        Suspend
                                    </option>

                                    <option value="Lost">
                                        Mark as Lost
                                    </option>

                                    <option value="Stolen">
                                        Mark as Stolen
                                    </option>

                                    <option value="Deactivated">
                                        Permanently Deactivate
                                    </option>

                                <?php } ?>

                            </select>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Reason
                            </label>

                            <select
                                name="reason"
                                class="form-select"
                                required
                            >

                                <option value="">
                                    Select reason
                                </option>

                                <option value="Suspected photocopy or duplicated QR card">
                                    Suspected photocopy or duplicated QR
                                </option>

                                <option value="Identity mismatch during verification">
                                    Identity mismatch
                                </option>

                                <option value="Patient reported card lost">
                                    Card reported lost
                                </option>

                                <option value="Patient reported card stolen">
                                    Card reported stolen
                                </option>

                                <option value="QR card damaged">
                                    QR card damaged
                                </option>

                                <option value="Patient requested replacement">
                                    Patient requested replacement
                                </option>

                                <option value="Administrative security control">
                                    Administrative security control
                                </option>

                            </select>

                        </div>

                        <div class="col-md-2 mb-3 d-grid">

                            <label class="form-label">
                                &nbsp;
                            </label>

                            <button
                                type="submit"
                                name="update_qr_status"
                                class="btn btn-danger"
                                onclick="return confirm('Apply this QR security status change?');"
                            >
                                Update Status
                            </button>

                        </div>

                    </div>

                </form>

            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">

                <?php if ($is_admin) { ?>

                    <a
                        href="regenerate_qr.php?id=<?php echo $patient['id']; ?>"
                        class="btn btn-warning"
                        onclick="return confirm('Generate a completely new secure QR? Every previous QR copy will become invalid immediately.');"
                    >
                        Regenerate Secure QR
                    </a>

                    <a
                        href="print_card.php?id=<?php echo $patient['id']; ?>"
                        target="_blank"
                        class="btn btn-primary"
                    >
                        Print Current QR Card
                    </a>

                <?php } else { ?>

                    <span class="text-muted">
                        QR regeneration and permanent activation are restricted to administrators.
                    </span>

                <?php } ?>

           <div class="card mt-4">

    <div class="card-header bg-dark text-white">

        <h5 class="mb-0">

            QR Security Intelligence

        </h5>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-4">

                <strong>QR Issued</strong><br>

                <?php

                echo date(
                    "d M Y",
                    strtotime($patient['created_at'])
                );

                ?>

            </div>

            <div class="col-md-4">

                <strong>Expiry Date</strong><br>

                <?php

                echo date(
                    "d M Y",
                    strtotime($patient['qr_expiry_date'])
                );

                ?>

            </div>

            <div class="col-md-4">

                <strong>Current Status</strong><br>

                <span class="badge bg-<?php echo $qr_status_badge; ?>">

                    <?php echo $qr_status_text; ?>

                </span>

            </div>

        </div>

        <hr>

        <div class="row">

            <div class="col-md-4">

                <strong>Last Regenerated</strong><br>

                <?php

                if(empty($patient['qr_last_regenerated'])){

                    echo "Never";

                }else{

                    echo date(
                        "d M Y H:i",
                        strtotime($patient['qr_last_regenerated'])
                    );

                }

                ?>

            </div>

            <div class="col-md-4">

                <strong>Security Token</strong><br>

                <?php

                if(!empty($patient['qr_token'])){

                    echo "<span class='text-success'>Verified</span>";

                }else{

                    echo "<span class='text-danger'>Missing</span>";

                }

                ?>

            </div>

            <div class="col-md-4">

                <strong>Risk Level</strong><br>

                <?php

                if($qr_status=="Active"){

                    echo "<span class='badge bg-success'>LOW</span>";

                }

                elseif($qr_status=="Suspended"){

                    echo "<span class='badge bg-warning'>MEDIUM</span>";

                }

                elseif($qr_status=="Lost"){

                    echo "<span class='badge bg-dark'>HIGH</span>";

                }

                elseif($qr_status=="Stolen"){

                    echo "<span class='badge bg-danger'>CRITICAL</span>";

                }

                else{

                    echo "<span class='badge bg-secondary'>UNKNOWN</span>";

                }

                ?>

            </div>

        </div>

        <hr>

        <?php

        $scan_query = mysqli_query($conn,"
        SELECT COUNT(*) total
        FROM attendance
        WHERE patient_id='$patient_id'
        ");

        $scan = mysqli_fetch_assoc($scan_query);

        ?>

        <div class="row">

            <div class="col-md-6">

                <strong>Total Successful QR Scans</strong>

                <h3 class="text-primary">

                    <?php echo $scan['total']; ?>

                </h3>

            </div>

            <div class="col-md-6">

                <strong>Failed QR Attempts</strong>

                <h3 class="text-danger">

                    0

                </h3>

            </div>

        </div>

    </div>

</div>

    </div>

    <!-- PERSONAL INFORMATION AND ATTENDANCE -->

    <div class="row">

        <div class="col-md-5">

            <div class="card mb-4">

                <div class="card-header bg-dark text-white">

                    <h5 class="mb-0">
                        Personal Information
                    </h5>

                </div>

                <div class="card-body">

                    <table class="table table-bordered">

                        <tr>
                            <th>Full Name</th>

                            <td>
                                <?php echo htmlspecialchars($patient['fullname']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Patient ID</th>

                            <td>
                                <?php echo htmlspecialchars($patient['patient_id']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Gender</th>

                            <td>
                                <?php echo htmlspecialchars($patient['gender']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Age</th>

                            <td>
                                <?php echo htmlspecialchars($patient['age']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Phone</th>

                            <td>
                                <?php echo htmlspecialchars($patient['phone']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Location</th>

                            <td>
                                <?php echo htmlspecialchars($patient['location']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Next Visit Date</th>

                            <td>
                                <?php echo htmlspecialchars($patient['next_visit_date'] ?? ''); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Registered On</th>

                            <td>
                                <?php echo htmlspecialchars($patient['created_at']); ?>
                            </td>
                        </tr>

                        <tr>
                            <th>Notes</th>

                            <td>
                                <?php echo htmlspecialchars($patient['notes'] ?? ''); ?>
                            </td>
                        </tr>

                    </table>

                </div>

            </div>

        </div>

        <div class="col-md-7">

            <div class="card mb-4">

                <div class="card-header bg-dark text-white">

                    <h5 class="mb-0">
                        Attendance and Service History
                    </h5>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-striped">

                            <thead>

                                <tr>
                                    <th>#</th>
                                    <th>Visit Date</th>
                                    <th>Service Given</th>
                                    <th>Notes</th>
                                </tr>

                            </thead>

                            <tbody>

                            <?php
                            $count = 1;

                            if (
                                $attendance_history &&
                                mysqli_num_rows($attendance_history) > 0
                            ) {
                                while ($history = mysqli_fetch_assoc($attendance_history)) {
                            ?>

                                <tr>

                                    <td>
                                        <?php echo $count++; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($history['visit_date']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($history['service_given']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($history['notes'] ?? ''); ?>
                                    </td>

                                </tr>

                            <?php
                                }
                            } else {
                            ?>

                                <tr>

                                    <td colspan="4" class="text-center">
                                        No attendance history found.
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

    <!-- QR SECURITY HISTORY -->

    <div class="card mb-4">

        <div class="card-header bg-dark text-white">

            <h5 class="mb-0">
                QR Security History
            </h5>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Security Action</th>
                            <th>Reason</th>
                            <th>Changed By</th>
                            <th>Date and Time</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php
                    $security_count = 1;

                    if (
                        $qr_security_history &&
                        mysqli_num_rows($qr_security_history) > 0
                    ) {
                        while ($security_log = mysqli_fetch_assoc($qr_security_history)) {
                    ?>

                        <tr>

                            <td>
                                <?php echo $security_count++; ?>
                            </td>

                            <td>

                                <?php
                                $security_badge = "secondary";

                                switch ($security_log['action']) {
                                    case "Active":
                                        $security_badge = "success";
                                        break;

                                    case "Suspended":
                                        $security_badge = "warning";
                                        break;

                                    case "Lost":
                                        $security_badge = "dark";
                                        break;

                                    case "Stolen":
                                        $security_badge = "danger";
                                        break;

                                    case "Deactivated":
                                        $security_badge = "secondary";
                                        break;
                                }
                                ?>

                                <span class="badge bg-<?php echo $security_badge; ?>">
                                    <?php echo htmlspecialchars($security_log['action']); ?>
                                </span>

                            </td>

                            <td>
                                <?php echo htmlspecialchars($security_log['reason']); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($security_log['changed_by']); ?>
                            </td>

                            <td>
                                <?php echo date(
                                    "d M Y H:i",
                                    strtotime($security_log['created_at'])
                                ); ?>
                            </td>

                        </tr>

                    <?php
                        }
                    } else {
                    ?>

                        <tr>

                            <td colspan="5" class="text-center">
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