<?php 
$conn = null;
include(__DIR__ . '/../includes/auth_check.php');
include(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/log_action.php');

if (!isset($conn) && isset($con)) {
    $conn = $con;
}

// Ensure $conn is available. If the include above failed to set $conn, try requiring the config
if (!isset($conn) || !$conn) {
    $db_path = __DIR__ . '/../config/db.php';
    if (file_exists($db_path)) {
        require_once $db_path;
        if (!isset($conn) && isset($con)) {
            $conn = $con;
        }
    }
    if (!isset($conn) || !$conn) {
        die('Database configuration file not found or connection failed.');
    }
}

$patients = mysqli_query($conn, "SELECT patient_id, fullname, phone FROM patients ORDER BY fullname ASC");

if (isset($_POST['save_appointment'])) {

    $patient_id = mysqli_real_escape_string($conn, $_POST['patient_id']);
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date']);
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $created_by = mysqli_real_escape_string($conn, $_SESSION['fullname'] ?? 'System User');

    $patient_result = mysqli_query($conn, "SELECT * FROM patients WHERE patient_id='$patient_id'");
    $patient = mysqli_fetch_assoc($patient_result);
    $fullname = $patient['fullname'];

    $insert = "INSERT INTO appointments 
               (patient_id, appointment_date, appointment_time, purpose, status, notes, created_by)
               VALUES
               ('$patient_id', '$appointment_date', '$appointment_time', '$purpose', 'Scheduled', '$notes', '$created_by')";

    if (mysqli_query($conn, $insert)) {

        logAction(
            $conn,
            "Appointment Scheduled",
            "Scheduled appointment for $fullname ($patient_id) on $appointment_date at $appointment_time. Purpose: $purpose."
        );

        $title = mysqli_real_escape_string($conn, "Appointment Scheduled");
        $message = mysqli_real_escape_string($conn, "$fullname has an appointment scheduled on $appointment_date at $appointment_time.");
        $type = mysqli_real_escape_string($conn, "info");
        $link = mysqli_real_escape_string($conn, "../appointments/manage_appointments.php");

        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
        ");

        header("Location: ../dashboard/index.php?toast=appointment_scheduled");
        exit();

    } else {
        die("Appointment insert failed: " . mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Appointment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body{
            background:#eef3f8;
            font-family:Arial, sans-serif;
        }

        .page-card{
            border:none;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,0.08);
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <div class="card page-card">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Schedule Follow-Up Visit</h4>

            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
                <a href="manage_appointments.php" class="btn btn-light btn-sm">Manage Appointments</a>
            </div>
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="mb-3">
                    <label>Patient</label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>

                        <?php while($row = mysqli_fetch_assoc($patients)){ ?>
                            <option value="<?php echo htmlspecialchars($row['patient_id']); ?>">
                                <?php echo htmlspecialchars($row['fullname']); ?> 
                                (<?php echo htmlspecialchars($row['patient_id']); ?>)
                                - <?php echo htmlspecialchars($row['phone']); ?>
                            </option>
                        <?php } ?>

                    </select>
                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Appointment Date</label>
                        <input type="date" name="appointment_date" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Appointment Time</label>
                        <input type="time" name="appointment_time" class="form-control">
                    </div>

                </div>

                <div class="mb-3">
                    <label>Purpose</label>
                    <select name="purpose" class="form-control" required>
                        <option value="">Select Purpose</option>
                        <option value="Food Basket Collection">Food Basket Collection</option>
                        <option value="Vitamin Supplement Collection">Vitamin Supplement Collection</option>
                        <option value="Adherence Follow-Up">Adherence Follow-Up</option>
                        <option value="Counselling Session">Counselling Session</option>
                        <option value="General Follow-Up">General Follow-Up</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Optional notes"></textarea>
                </div>

                <button type="submit" name="save_appointment" class="btn btn-primary">
                    Save Appointment
                </button>

                <a href="../dashboard/index.php" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>