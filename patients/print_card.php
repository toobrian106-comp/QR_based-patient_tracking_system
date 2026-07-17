<?php
include('../includes/auth_check.php');
include('../config/db.php');
include('../phpqrcode/qrlib.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id = (int) $_GET['id'];

/*
|--------------------------------------------------------------------------
| Fetch patient
|--------------------------------------------------------------------------
*/

$query = mysqli_query($conn, "
    SELECT *
    FROM patients
    WHERE id = $id
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) === 0) {
    die("Patient record not found.");
}

$patient = mysqli_fetch_assoc($query);

/*
|--------------------------------------------------------------------------
| Patient details
|--------------------------------------------------------------------------
*/

$patient_id = $patient['patient_id'];
$fullname = $patient['fullname'];
$gender = $patient['gender'];
$age = $patient['age'];
$location = $patient['location'];

$qr_token = $patient['qr_token'] ?? '';
$qr_status = $patient['qr_status'] ?? 'Inactive';
$qr_expiry_date = $patient['qr_expiry_date'] ?? null;
$qr_last_regenerated = $patient['qr_last_regenerated'] ?? null;

$registration_date = $patient['created_at'] ?? date("Y-m-d H:i:s");

/*
|--------------------------------------------------------------------------
| Patient photograph
|--------------------------------------------------------------------------
*/

$photo_filename = $patient['photo'] ?? '';

$photo_server_path = __DIR__ . "/../assets/patient_photos/" . $photo_filename;
$photo_web_path = "../assets/patient_photos/" . rawurlencode($photo_filename);

$has_photo = (
    !empty($photo_filename) &&
    file_exists($photo_server_path)
);

/*
|--------------------------------------------------------------------------
| Optional system logo
|--------------------------------------------------------------------------
|
| Put your logo here:
|
| assets/images/system_logo.png
|
| If the file is missing, the card shows a simple medical icon instead.
|--------------------------------------------------------------------------
*/

$logo_server_path = __DIR__ . "/../assets/images/system_logo.png";
$logo_web_path = "../assets/images/system_logo.png";

$has_logo = file_exists($logo_server_path);

/*
|--------------------------------------------------------------------------
| Secure QR image
|--------------------------------------------------------------------------
*/

$qr_filename = !empty($patient['qr_code'])
    ? $patient['qr_code']
    : $patient_id . ".png";

$qr_directory = __DIR__ . "/../assets/qr_codes/";
$qr_server_path = $qr_directory . $qr_filename;
$qr_web_path = "../assets/qr_codes/" . rawurlencode($qr_filename);

if (!is_dir($qr_directory)) {
    mkdir($qr_directory, 0777, true);
}

/*
|--------------------------------------------------------------------------
| Generate secure QR if the image is missing
|--------------------------------------------------------------------------
*/

if (
    !file_exists($qr_server_path) &&
    !empty($qr_token)
) {
    $qr_payload = $patient_id . "|" . $qr_token;

    QRcode::png(
        $qr_payload,
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
| QR expiry and display status
|--------------------------------------------------------------------------
*/

$is_expired = false;

if (
    !empty($qr_expiry_date) &&
    strtotime($qr_expiry_date) < strtotime(date("Y-m-d"))
) {
    $is_expired = true;
}

$display_qr_status = strtoupper($qr_status);
$status_class = "status-disabled";

if ($qr_status === "Active" && !$is_expired) {
    $display_qr_status = "ACTIVE";
    $status_class = "status-active";
} elseif ($is_expired) {
    $display_qr_status = "EXPIRED";
    $status_class = "status-expired";
} elseif ($qr_status === "Suspended") {
    $display_qr_status = "SUSPENDED";
    $status_class = "status-suspended";
} elseif ($qr_status === "Lost") {
    $display_qr_status = "LOST";
    $status_class = "status-disabled";
} elseif ($qr_status === "Stolen") {
    $display_qr_status = "STOLEN";
    $status_class = "status-danger";
} elseif (
    $qr_status === "Inactive" ||
    $qr_status === "Deactivated"
) {
    $display_qr_status = "DEACTIVATED";
    $status_class = "status-disabled";
}

/*
|--------------------------------------------------------------------------
| Display dates
|--------------------------------------------------------------------------
*/

$issue_date_source = !empty($qr_last_regenerated)
    ? $qr_last_regenerated
    : $registration_date;

$issue_date = !empty($issue_date_source)
    ? date("d M Y", strtotime($issue_date_source))
    : "Not available";

$expiry_date_display = !empty($qr_expiry_date)
    ? date("d M Y", strtotime($qr_expiry_date))
    : "Not set";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Patient QR Card - <?php echo htmlspecialchars($fullname); ?>
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #eef3f8;
            font-family: Arial, Helvetica, sans-serif;
            color: #172033;
        }

        .page-actions {
            max-width: 760px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .action-button {
            display: inline-block;
            border: none;
            border-radius: 8px;
            padding: 11px 22px;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
        }

        .print-button {
            background: #0d6efd;
        }

        .back-button {
            background: #6c757d;
        }

        .patient-card {
            width: 760px;
            min-height: 480px;
            margin: auto;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 50px rgba(24, 50, 90, 0.18);
            border: 1px solid #d9e3f0;
            position: relative;
        }

        .patient-card::after {
            content: "SECURE";
            position: absolute;
            right: -30px;
            top: 210px;
            font-size: 80px;
            font-weight: 900;
            color: rgba(13, 110, 253, 0.035);
            transform: rotate(-35deg);
            pointer-events: none;
        }

        .card-header {
            min-height: 105px;
            padding: 22px 30px;
            color: white;
            background:
                linear-gradient(
                    135deg,
                    #073b8c 0%,
                    #0969da 55%,
                    #06a3e8 100%
                );
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: "";
            position: absolute;
            width: 470px;
            height: 120px;
            right: -110px;
            top: -65px;
            border-radius: 50%;
            background: rgba(255,255,255,0.10);
            transform: rotate(-8deg);
        }

        .brand-area {
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            z-index: 2;
        }

        .logo-box {
            width: 66px;
            height: 66px;
            background: white;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #0d6efd;
            font-size: 34px;
            font-weight: bold;
        }

        .logo-box img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .brand-title {
            margin: 0;
            font-size: 28px;
            letter-spacing: 0.5px;
        }

        .brand-subtitle {
            margin: 5px 0 0;
            font-size: 13px;
            opacity: 0.9;
            letter-spacing: 1px;
        }

        .card-reference {
            position: relative;
            z-index: 2;
            text-align: right;
            font-size: 12px;
        }

        .card-reference strong {
            display: block;
            font-size: 15px;
            margin-top: 4px;
        }

        .card-body {
            padding: 28px 30px 22px;
        }

        .identity-section {
            display: grid;
            grid-template-columns: 170px 1fr 205px;
            gap: 25px;
            align-items: center;
        }

        .photo-frame {
            width: 160px;
            height: 190px;
            border: 3px solid #1988ef;
            border-radius: 18px;
            padding: 5px;
            background: #f8fbff;
            overflow: hidden;
        }

        .photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            background: #e9f1fa;
            color: #6d7a8d;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-size: 13px;
        }

        .photo-placeholder .person-icon {
            font-size: 55px;
            line-height: 1;
            margin-bottom: 8px;
        }

        .patient-name {
            margin: 0 0 8px;
            font-size: 28px;
            color: #063b85;
            text-transform: uppercase;
        }

        .patient-id {
            margin-bottom: 18px;
            font-size: 15px;
        }

        .patient-id strong {
            color: #0875d1;
        }

        .detail-row {
            display: flex;
            align-items: center;
            min-height: 34px;
            border-bottom: 1px dashed #d0d8e3;
            font-size: 14px;
        }

        .detail-label {
            width: 90px;
            font-weight: bold;
            color: #0c58a7;
        }

        .qr-panel {
            text-align: center;
            padding-left: 20px;
            border-left: 1px solid #d7e1ed;
        }

        .qr-panel img {
            width: 175px;
            height: 175px;
            object-fit: contain;
            background: white;
            padding: 5px;
        }

        .qr-missing {
            width: 175px;
            height: 175px;
            margin: auto;
            background: #f8d7da;
            border: 1px solid #f1aeb5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #842029;
            font-size: 13px;
        }

        .qr-label {
            margin-top: 5px;
            color: #074d9d;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .security-strip {
            margin-top: 24px;
            padding: 16px 20px;
            border-radius: 15px;
            display: grid;
            grid-template-columns: 1fr 1px 1fr;
            align-items: center;
            gap: 22px;
            color: white;
            background:
                linear-gradient(
                    90deg,
                    #119a75,
                    #20ae75,
                    #49ba55
                );
        }

        .security-divider {
            height: 48px;
            background: rgba(255,255,255,0.55);
        }

        .security-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .security-icon {
            width: 45px;
            height: 45px;
            flex-shrink: 0;
            background: rgba(255,255,255,0.95);
            color: #198754;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
        }

        .security-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
            opacity: 0.9;
        }

        .security-value {
            font-size: 18px;
            font-weight: 800;
            margin-top: 3px;
        }

        .status-active {
            color: white;
        }

        .status-expired,
        .status-suspended {
            color: #fff7cc;
        }

        .status-danger {
            color: #ffd0d4;
        }

        .status-disabled {
            color: #e8ebef;
        }

        .card-footer {
            margin-top: 20px;
            border-radius: 13px;
            background: #063b85;
            color: white;
            padding: 13px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
        }

        .footer-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-icon {
            font-size: 18px;
        }

        .verification-message {
            margin-top: 12px;
            text-align: center;
            color: #516175;
            font-size: 11px;
        }

        @media print {
            @page {
                size: landscape;
                margin: 8mm;
            }

            body {
                padding: 0;
                background: white;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .page-actions {
                display: none;
            }

            .patient-card {
                width: 190mm;
                min-height: 118mm;
                box-shadow: none;
                border-radius: 18px;
                break-inside: avoid;
            }
        }

        @media screen and (max-width: 820px) {
            body {
                padding: 15px;
            }

            .patient-card {
                width: 100%;
            }

            .identity-section {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .photo-frame {
                margin: auto;
            }

            .detail-row {
                justify-content: center;
            }

            .qr-panel {
                border-left: none;
                border-top: 1px solid #d7e1ed;
                padding-left: 0;
                padding-top: 20px;
            }

            .security-strip {
                grid-template-columns: 1fr;
            }

            .security-divider {
                width: 100%;
                height: 1px;
            }

            .card-footer {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<div class="page-actions">

    <button
        type="button"
        class="action-button print-button"
        onclick="window.print()"
    >
        Print Patient Card
    </button>

    <a
        href="view_patient.php?id=<?php echo $patient['id']; ?>"
        class="action-button back-button"
    >
        Back to Profile
    </a>

</div>

<div class="patient-card">

    <div class="card-header">

        <div class="brand-area">

            <div class="logo-box">

                <?php if ($has_logo) { ?>

                    <img
                        src="<?php echo htmlspecialchars($logo_web_path); ?>"
                        alt="System Logo"
                    >

                <?php } else { ?>

                    ✚

                <?php } ?>

            </div>

            <div>

                <h1 class="brand-title">
                    PATIENT QR CARD
                </h1>

                <p class="brand-subtitle">
                    UNIQUE • SECURE • CONFIDENTIAL
                </p>

            </div>

        </div>

        <div class="card-reference">

            CARD NUMBER

            <strong>
                <?php echo htmlspecialchars($patient_id); ?>
            </strong>

        </div>

    </div>

    <div class="card-body">

        <div class="identity-section">

            <div class="photo-frame">

                <?php if ($has_photo) { ?>

                    <img
                        src="<?php echo htmlspecialchars($photo_web_path); ?>"
                        alt="Patient Photograph"
                    >

                <?php } else { ?>

                    <div class="photo-placeholder">

                        <div class="person-icon">
                            ◯
                        </div>

                        No photograph available

                    </div>

                <?php } ?>

            </div>

            <div class="patient-details">

                <h2 class="patient-name">
                    <?php echo htmlspecialchars($fullname); ?>
                </h2>

                <div class="patient-id">

                    <strong>PATIENT ID:</strong>

                    <?php echo htmlspecialchars($patient_id); ?>

                </div>

                <div class="detail-row">

                    <span class="detail-label">
                        Gender:
                    </span>

                    <span>
                        <?php echo htmlspecialchars($gender); ?>
                    </span>

                </div>

                <div class="detail-row">

                    <span class="detail-label">
                        Age:
                    </span>

                    <span>
                        <?php echo htmlspecialchars($age); ?> years
                    </span>

                </div>

                <div class="detail-row">

                    <span class="detail-label">
                        Location:
                    </span>

                    <span>
                        <?php echo htmlspecialchars($location); ?>
                    </span>

                </div>

                <div class="detail-row">

                    <span class="detail-label">
                        Issued:
                    </span>

                    <span>
                        <?php echo htmlspecialchars($issue_date); ?>
                    </span>

                </div>

            </div>

            <div class="qr-panel">

                <?php if (file_exists($qr_server_path)) { ?>

                    <img
                        src="<?php echo htmlspecialchars($qr_web_path); ?>?v=<?php echo time(); ?>"
                        alt="Secure Patient QR Code"
                    >

                <?php } else { ?>

                    <div class="qr-missing">
                        Secure QR image unavailable
                    </div>

                <?php } ?>

                <div class="qr-label">
                    SECURE QR CODE
                </div>

            </div>

        </div>

        <div class="security-strip">

            <div class="security-item">

                <div class="security-icon">
                    ✓
                </div>

                <div>

                    <div class="security-label">
                        QR Status
                    </div>

                    <div class="security-value <?php echo $status_class; ?>">
                        <?php echo htmlspecialchars($display_qr_status); ?>
                    </div>

                </div>

            </div>

            <div class="security-divider"></div>

            <div class="security-item">

                <div class="security-icon">
                    ▣
                </div>

                <div>

                    <div class="security-label">
                        Expiry Date
                    </div>

                    <div class="security-value">
                        <?php echo htmlspecialchars($expiry_date_display); ?>
                    </div>

                </div>

            </div>

        </div>

        <div class="card-footer">

            <div class="footer-item">

                <span class="footer-icon">⌁</span>

                <span>
                    Issued by NGO Kisumu
                </span>

            </div>

            <div class="footer-item">

                <span class="footer-icon">▣</span>

                <span>
                    Date issued:
                    <?php echo htmlspecialchars($issue_date); ?>
                </span>

            </div>

            <div class="footer-item">

                <span class="footer-icon">✓</span>

                <span>
                    Valid only after system verification
                </span>

            </div>

        </div>

        <div class="verification-message">
            Present this card during clinic visits.
        </div>

    </div>

</div>

</body>
</html>