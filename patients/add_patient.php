<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

include('../includes/auth_check.php');
include('../config/db.php');
include('../includes/log_action.php');
include('../phpqrcode/qrlib.php');

if(!isset($conn)){
    die("Database connection failed. Please contact administrator.");
}

if(isset($_POST['save_patient'])){

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $next_visit_date = mysqli_real_escape_string($conn, $_POST['next_visit_date']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $patient_id = "PT-" . date("Y") . "-" . rand(1000,9999);

    // SECURE QR DETAILS
    $secure_token = bin2hex(random_bytes(32));
    $qr_status = "Active";
    $qr_expiry_date = date("Y-m-d", strtotime("+1 year"));
    $qr_last_regenerated = date("Y-m-d H:i:s");

    // QR now stores Patient ID + secret token
    $qr_data = $patient_id . "|" . $secure_token;

    $qr_filename = $patient_id . ".png";
    $qr_folder = "../assets/qr_codes/";

    if(!is_dir($qr_folder)){
        mkdir($qr_folder, 0777, true);
    }

    QRcode::png($qr_data, $qr_folder . $qr_filename, QR_ECLEVEL_H, 8, 2);

    $photo_name = "";

    if(!empty($_FILES['photo']['name'])){

        $photo_folder = "../assets/patient_photos/";

        if(!is_dir($photo_folder)){
            mkdir($photo_folder, 0777, true);
        }

        $photo_name = time() . "_" . basename($_FILES['photo']['name']);
        $photo_path = $photo_folder . $photo_name;

        move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path);
    }

    $query = "INSERT INTO patients
    (patient_id, fullname, gender, age, phone, location, qr_code, qr_token, qr_status, qr_expiry_date, qr_last_regenerated, photo, next_visit_date, status, risk_level, notes)
    VALUES
    ('$patient_id', '$fullname', '$gender', '$age', '$phone', '$location', '$qr_filename', '$secure_token', '$qr_status', '$qr_expiry_date', '$qr_last_regenerated', '$photo_name', '$next_visit_date', 'Active', 'Low', '$notes')";

    if(mysqli_query($conn, $query)){

        $new_patient_db_id = mysqli_insert_id($conn);

        logAction(
            $conn,
            "Patient Registration",
            "Registered patient: $fullname ($patient_id), Gender: $gender, Age: $age, Phone: $phone, Location: $location. Secure QR generated, status: Active, expiry: $qr_expiry_date."
        );

        $title = mysqli_real_escape_string($conn, "New Patient Registration");
        $message = mysqli_real_escape_string($conn, $fullname . " has been registered with secure QR identification. Card expires on " . $qr_expiry_date);
        $type = mysqli_real_escape_string($conn, "info");
        $link = mysqli_real_escape_string($conn, "../patients/view_patient.php?id=" . $new_patient_db_id);

        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
        ");

        header("Location: ../dashboard/index.php?toast=patient_registered");
        exit();

    }else{
        die("Error: " . mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <title>Add Patient</title>

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

        .form-control,
        .form-select{
            height:45px;
        }

        textarea.form-control{
            height:auto;
        }

        .security-note{
            background:#e7f1ff;
            border-left:5px solid #0d6efd;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
        }
    </style>

</head>

<body>

<div class="container mt-5">

    <div class="card page-card">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <h4 class="mb-0">
                Add New Patient
            </h4>

            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                    Dashboard
                </a>

                <a href="manage_patients.php" class="btn btn-light btn-sm">
                    Manage Patients
                </a>
            </div>

        </div>

        <div class="card-body">

            <form method="POST" enctype="multipart/form-data">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Full Name</label>
                        <input type="text"
                               name="fullname"
                               class="form-control"
                               placeholder="Enter patient full name"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Age</label>
                        <input type="number"
                               name="age"
                               class="form-control"
                               placeholder="Enter age"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Phone</label>
                        <input type="tel"
                               name="phone"
                               class="form-control"
                               placeholder="07XXXXXXXX"
                               maxlength="10"
                               pattern="[0-9]{10}"
                               inputmode="numeric"
                               required oninput="this.value=this.value.replace(/[^0-9]/g,'');">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Location</label>
                        <input type="text"
                               name="location"
                               class="form-control"
                               placeholder="Enter village/location"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Next Visit Date</label>
                        <input type="date"
                               name="next_visit_date"
                               class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Patient Photo</label>
                        <input type="file"
                               name="photo"
                               class="form-control"
                               accept="image/*">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label>Notes</label>
                        <textarea name="notes"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Enter patient notes"></textarea>
                    </div>

                </div>

                <button type="submit"
                        name="save_patient"
                        class="btn btn-primary">
                    Save Patient
                </button>

                <a href="manage_patients.php" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>