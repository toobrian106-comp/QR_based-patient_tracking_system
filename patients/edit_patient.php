<?php 
include_once(__DIR__ . '/../includes/auth_check.php');
require_once __DIR__ . '/../config/db.php';
include_once(__DIR__ . '/../includes/log_action.php');

// Ensure logAction is available (fallback if include failed or defines a different name)
if(!function_exists('logAction')){
    function logAction($conn, $title, $details){
        // Try to insert into activity_log if table exists; suppress errors
        $t = mysqli_real_escape_string($conn, $title);
        $d = mysqli_real_escape_string($conn, $details);
        @mysqli_query($conn, "INSERT INTO activity_log (title, details, created_at) VALUES ('$t', '$d', NOW())");
    }
}

if (!isset($conn) || !$conn) {
    die('Database connection not established.');
}

if(!isset($_GET['id'])){
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$result = mysqli_query($conn, "SELECT * FROM patients WHERE id='$id'");

if(!$result || mysqli_num_rows($result) == 0){
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($result);

if(isset($_POST['update'])){

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $next_visit_date = mysqli_real_escape_string($conn, $_POST['next_visit_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $risk_level = mysqli_real_escape_string($conn, $_POST['risk_level']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $photo_sql = "";

    if(!empty($_FILES['photo']['name'])){

        $photo_folder = "../assets/patient_photos/";

        if(!is_dir($photo_folder)){
            mkdir($photo_folder, 0777, true);
        }

        $photo_name = time() . "_" . basename($_FILES['photo']['name']);
        $photo_path = $photo_folder . $photo_name;

        if(move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)){
            $photo_sql = ", photo='$photo_name'";
        }
    }

    $update = "UPDATE patients SET
               fullname='$fullname',
               gender='$gender',
               age='$age',
               phone='$phone',
               location='$location',
               next_visit_date='$next_visit_date',
               status='$status',
               risk_level='$risk_level',
               notes='$notes'
               $photo_sql
               WHERE id='$id'";

    if(mysqli_query($conn, $update)){

        logAction(
            $conn,
            "Patient Updated",
            "Updated patient record: $fullname (" . $patient['patient_id'] . "), Gender: $gender, Age: $age, Phone: $phone, Location: $location, Status: $status, Risk Level: $risk_level."
        );

        $title = mysqli_real_escape_string($conn, "Patient Record Updated");
        $message = mysqli_real_escape_string($conn, $fullname . " record has been updated.");
        $type = mysqli_real_escape_string($conn, "info");
        $link = mysqli_real_escape_string($conn, "../patients/view_patient.php?id=" . $id);

        mysqli_query($conn, "
            INSERT INTO notifications (title, message, type, link)
            VALUES ('$title', '$message', '$type', '$link')
        ");

        echo "<script>
                alert('Patient updated successfully.');
                window.location.href='view_patient.php?id=$id';
              </script>";
        exit();

    }else{
        die("Update failed: " . mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Patient</title>
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

        .current-photo{
            width:90px;
            height:90px;
            object-fit:cover;
            border-radius:50%;
            border:3px solid #0d6efd;
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <div class="card page-card">

        <div class="card-header bg-warning d-flex justify-content-between align-items-center">

            <h4 class="mb-0">Edit Patient</h4>

            <div>
                <a href="view_patient.php?id=<?php echo $id; ?>" class="btn btn-dark btn-sm">
                    Back to Profile
                </a>

                <a href="manage_patients.php" class="btn btn-dark btn-sm">
                    Manage Patients
                </a>
            </div>

        </div>

        <div class="card-body">

            <form method="POST" enctype="multipart/form-data">

                <div class="row">

                    <div class="col-md-12 mb-4 text-center">
                        <?php if(!empty($patient['photo'])){ ?>
                            <img src="../assets/patient_photos/<?php echo htmlspecialchars($patient['photo']); ?>" class="current-photo">
                            <p class="text-muted mt-2">Current Patient Photo</p>
                        <?php } else { ?>
                            <p class="text-muted">No patient photo uploaded.</p>
                        <?php } ?>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Full Name</label>
                        <input type="text"
                               name="fullname"
                               class="form-control"
                               value="<?php echo htmlspecialchars($patient['fullname']); ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php if($patient['gender']=="Male") echo "selected"; ?>>
                                Male
                            </option>

                            <option value="Female" <?php if($patient['gender']=="Female") echo "selected"; ?>>
                                Female
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Age</label>
                        <input type="number"
                               name="age"
                               class="form-control"
                               value="<?php echo htmlspecialchars($patient['age']); ?>"
                               required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Phone</label>
                        <input type="text"
                               name="phone"
                               class="form-control"
                               value="<?php echo htmlspecialchars($patient['phone']); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Location</label>
                        <input type="text"
                               name="location"
                               class="form-control"
                               value="<?php echo htmlspecialchars($patient['location']); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Next Visit Date</label>
                        <input type="date"
                               name="next_visit_date"
                               class="form-control"
                               value="<?php echo htmlspecialchars($patient['next_visit_date']); ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php if($patient['status']=="Active") echo "selected"; ?>>
                                Active
                            </option>

                            <option value="Inactive" <?php if($patient['status']=="Inactive") echo "selected"; ?>>
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label>Risk Level</label>
                        <select name="risk_level" class="form-control">
                            <option value="Low" <?php if($patient['risk_level']=="Low") echo "selected"; ?>>
                                Low
                            </option>

                            <option value="Moderate" <?php if($patient['risk_level']=="Moderate") echo "selected"; ?>>
                                Moderate
                            </option>

                            <option value="High" <?php if($patient['risk_level']=="High") echo "selected"; ?>>
                                High
                            </option>
                        </select>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label>Change Photo</label>
                        <input type="file"
                               name="photo"
                               class="form-control"
                               accept="image/*">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label>Notes</label>
                        <textarea name="notes"
                                  class="form-control"
                                  rows="4"><?php echo htmlspecialchars($patient['notes']); ?></textarea>
                    </div>

                </div>

                <button type="submit"
                        name="update"
                        class="btn btn-warning">
                    Update Patient
                </button>

                <a href="view_patient.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>