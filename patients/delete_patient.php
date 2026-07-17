<?php 
require_once(__DIR__ . '/../includes/auth_check.php');
require_once(__DIR__ . '/../config/db.php');
require_once(__DIR__ . '/../includes/log_action.php');

if (!isset($conn) || !$conn) {
    die('Database connection failed.');
}

if(!isset($_GET['id'])){
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$patient_query = mysqli_query($conn, "SELECT * FROM patients WHERE id='$id'");

if(!$patient_query || mysqli_num_rows($patient_query) == 0){
    echo "<script>
            alert('Patient not found.');
            window.location.href='manage_patients.php';
          </script>";
    exit();
}

$patient = mysqli_fetch_assoc($patient_query);

$patient_id = $patient['patient_id'];
$fullname = $patient['fullname'];
$photo = $patient['photo'];
$qr_code = $patient['qr_code'];

$delete_patient = mysqli_query($conn, "DELETE FROM patients WHERE id='$id'");

if($delete_patient){

    mysqli_query($conn, "DELETE FROM attendance WHERE patient_id='$patient_id'");

    if(!empty($photo)){
        $photo_path = "../assets/patient_photos/" . $photo;

        if(file_exists($photo_path)){
            unlink($photo_path);
        }
    }

    if(!empty($qr_code)){
        $qr_path = "../assets/qr_codes/" . $qr_code;

        if(file_exists($qr_path)){
            unlink($qr_path);
        }
    }

    logAction(
        $conn,
        "Patient Deleted",
        "Deleted patient record: $fullname ($patient_id). All related attendance records were also removed."
    );

    $title = mysqli_real_escape_string($conn, "Patient Record Deleted");
    $message = mysqli_real_escape_string($conn, "$fullname ($patient_id) was deleted from the system.");
    $type = mysqli_real_escape_string($conn, "danger");
    $link = mysqli_real_escape_string($conn, "../patients/manage_patients.php");

    mysqli_query($conn, "
        INSERT INTO notifications (title, message, type, link)
        VALUES ('$title', '$message', '$type', '$link')
    ");

    echo "<script>
            alert('Patient deleted successfully.');
            window.location.href='manage_patients.php';
          </script>";
    exit();

}else{
    die("Delete failed: " . mysqli_error($conn));
}
