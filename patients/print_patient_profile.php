<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: /patient_tracking_system/patients/manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$patient_query = "SELECT * FROM patients WHERE id='$id'";
$patient_result = mysqli_query($conn, $patient_query);

if (!$patient_result) {
    die("Patient query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($patient_result) == 0) {
    echo "Patient not found.";
    exit();
}

$patient = mysqli_fetch_assoc($patient_result);
$patient_id = $patient['patient_id'];

// Total visits
$total_visits_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id'
");
$total_visits = mysqli_fetch_assoc($total_visits_query)['total'];

// Food basket count
$food_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id' 
    AND service_given LIKE '%Food Basket%'
");
$food_count = mysqli_fetch_assoc($food_query)['total'];

// Vitamin count
$vitamin_query = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM attendance 
    WHERE patient_id='$patient_id' 
    AND service_given LIKE '%Vitamin Supplements%'
");
$vitamin_count = mysqli_fetch_assoc($vitamin_query)['total'];

// Attendance history
$history_query = mysqli_query($conn, "
    SELECT * FROM attendance 
    WHERE patient_id='$patient_id'
    ORDER BY visit_date DESC, id DESC
");

if (!$history_query) {
    die("History query failed: " . mysqli_error($conn));
}

// Adherence status
if ($total_visits >= 10) {
    $adherence_status = "High Adherence";
} elseif ($total_visits >= 5) {
    $adherence_status = "Moderate Adherence";
} else {
    $adherence_status = "Low Adherence";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Patient Profile</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ffffff;
            padding: 30px;
            color: #000;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .header h2 {
            margin: 0;
            text-transform: uppercase;
        }

        .header p {
            margin: 5px 0 0 0;
        }

        .section-title {
            background: #0d6efd;
            color: white;
            padding: 8px;
            margin-top: 20px;
            font-weight: bold;
        }

        .profile-area {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .details {
            width: 65%;
        }

        .qr {
            width: 30%;
            text-align: center;
        }

        .qr img {
            width: 160px;
            height: 160px;
        }

        p {
            font-size: 15px;
            margin: 8px 0;
        }

        .stats {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 15px;
        }

        .stat-box {
            width: 25%;
            border: 1px solid #000;
            padding: 12px;
            text-align: center;
        }

        .stat-box h3 {
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table, th, td {
            border: 1px solid #000;
        }

        th {
            background: #222;
            color: white;
            padding: 8px;
        }

        td {
            padding: 8px;
            font-size: 14px;
        }

        .buttons {
            text-align: center;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 15px;
            text-decoration: none;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-print {
            background: #198754;
        }

        .btn-back {
            background: #6c757d;
        }

        @media print {
            .buttons {
                display: none;
            }

            body {
                padding: 0;
            }

            .section-title {
                background: #ddd !important;
                color: #000 !important;
                border: 1px solid #000;
            }

            th {
                background: #ddd !important;
                color: #000 !important;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h2>Patient Identification And Attendance Report</h2>
        <p>QR-Based Patient Tracking System</p>
    </div>

    <div class="section-title">
        Patient Details
    </div>

    <div class="profile-area">

        <div class="details">
            <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></p>
            <p><strong>Full Name:</strong> <?php echo htmlspecialchars($patient['fullname']); ?></p>
            <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
            <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($patient['phone']); ?></p>
            <p><strong>Location:</strong> <?php echo htmlspecialchars($patient['location']); ?></p>
            <p><strong>Date Registered:</strong> <?php echo htmlspecialchars($patient['created_at']); ?></p>
            <p><strong>Adherence Status:</strong> <?php echo $adherence_status; ?></p>
        </div>

        <div class="qr">
            <?php if (!empty($patient['qr_code'])) { ?>
                <img src="/patient_tracking_system/assets/qr_codes/<?php echo htmlspecialchars($patient['qr_code']); ?>">
                <p><strong>Patient QR Code</strong></p>
            <?php } else { ?>
                <p>No QR Code Available</p>
            <?php } ?>
        </div>

    </div>

    <div class="section-title">
        Attendance Summary
    </div>

    <div class="stats">

        <div class="stat-box">
            <p>Total Visits</p>
            <h3><?php echo $total_visits; ?></h3>
        </div>

        <div class="stat-box">
            <p>Food Baskets</p>
            <h3><?php echo $food_count; ?></h3>
        </div>

        <div class="stat-box">
            <p>Vitamins</p>
            <h3><?php echo $vitamin_count; ?></h3>
        </div>

        <div class="stat-box">
            <p>Status</p>
            <h3 style="font-size:16px;"><?php echo $adherence_status; ?></h3>
        </div>

    </div>

    <div class="section-title">
        Full Attendance History
    </div>

    <table>
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

        if (mysqli_num_rows($history_query) > 0) {
            while ($row = mysqli_fetch_assoc($history_query)) {
        ?>

            <tr>
                <td><?php echo $count++; ?></td>
                <td><?php echo htmlspecialchars($row['visit_date']); ?></td>
                <td><?php echo htmlspecialchars($row['service_given']); ?></td>
                <td><?php echo htmlspecialchars($row['notes']); ?></td>
            </tr>

        <?php 
            }
        } else {
        ?>

            <tr>
                <td colspan="4" style="text-align:center;">
                    No attendance history found.
                </td>
            </tr>

        <?php } ?>

        </tbody>
    </table>

    <div class="buttons">
        <button onclick="window.print()" class="btn btn-print">Print Report</button>

        <a href="/patient_tracking_system/patients/patient_profile.php?id=<?php echo $patient['id']; ?>" 
           class="btn btn-back">
            Back
        </a>
    </div>

</div>

</body>
</html>