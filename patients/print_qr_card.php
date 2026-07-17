<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = "SELECT * FROM patients WHERE id='$id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
    echo "Patient not found.";
    exit();
}

$patient = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Patient QR Card</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
        }

        .card-container {
            width: 360px;
            margin: 50px auto;
            background: white;
            border: 2px solid #0d6efd;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .card-header {
            background: #0d6efd;
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 20px;
        }

        .card-body p {
            margin: 6px 0;
            font-size: 14px;
        }

        .qr-code {
            margin: 15px 0;
        }

        .qr-code img {
            width: 160px;
            height: 160px;
        }

        .btn-print {
            margin-top: 20px;
            padding: 10px 25px;
            background: #0d6efd;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-back {
            margin-top: 10px;
            display: inline-block;
            text-decoration: none;
            color: white;
            background: #6c757d;
            padding: 10px 25px;
            border-radius: 8px;
        }

        @media print {
            body {
                background: white;
            }

            .btn-print,
            .btn-back {
                display: none;
            }

            .card-container {
                margin-top: 20px;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<div class="card-container">

    <div class="card-header">
        <h3>Patient Identification Card</h3>
    </div>

    <div class="card-body">

        <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($patient['patient_id']); ?></p>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['fullname']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?></p>
        <p><strong>Location:</strong> <?php echo htmlspecialchars($patient['location']); ?></p>

        <div class="qr-code">
            <?php if (!empty($patient['qr_code'])) { ?>
                <img src="../assets/qr_codes/<?php echo htmlspecialchars($patient['qr_code']); ?>" alt="Patient QR Code">
            <?php } else { ?>
                <p>No QR Code Available</p>
            <?php } ?>
        </div>

        <p><strong>Use this card during every clinic visit.</strong></p>

    </div>

    <button onclick="window.print()" class="btn-print">
        Print Card
    </button>

    <br>

    <a href="manage_patients.php" class="btn-back">
        Back
    </a>

</div>

</body>
</html>