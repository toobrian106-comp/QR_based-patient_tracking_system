<?php
// Ensure required files are loaded and $conn is available
require_once __DIR__ . '/../includes/auth_check.php';
// db.php should define $conn; use require_once with absolute path
require_once __DIR__ . '/../config/db.php';

// Ensure $conn is available. Some setups may use $connection or $mysqli as the DB handle.
if (!isset($conn)) {
    if (isset($connection)) {
        $conn = $connection;
    } elseif (isset($mysqli)) {
        $conn = $mysqli;
    }
}

if (!isset($conn) || !$conn) {
    die('Database connection ($conn) not available.');
}
require_once __DIR__ . '/../includes/log_action.php';
require_once __DIR__ . '/../phpqrcode/qrlib.php';

if (!isset($_GET['id'])) {
    header("Location: manage_patients.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = mysqli_query($conn,"
SELECT * FROM patients
WHERE id='$id'
");

if(!$query || mysqli_num_rows($query)==0){
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($query);

$patient_id = $patient['patient_id'];

/*
|--------------------------------------------------------------------------
| Generate completely new secure token
|--------------------------------------------------------------------------
*/

$new_token = bin2hex(random_bytes(32));

$new_expiry = date("Y-m-d", strtotime("+1 year"));

$generated_time = date("Y-m-d H:i:s");

$qr_status = "Active";

$qr_data = $patient_id . "|" . $new_token;

$qr_folder = "../assets/qr_codes/";

if(!is_dir($qr_folder)){
    mkdir($qr_folder,0777,true);
}

$qr_filename = $patient_id . ".png";

/*
|--------------------------------------------------------------------------
| Delete old QR image
|--------------------------------------------------------------------------
*/

$old_qr = $qr_folder . $qr_filename;

if(file_exists($old_qr)){
    unlink($old_qr);
}

/*
|--------------------------------------------------------------------------
| Generate new secure QR
|--------------------------------------------------------------------------
*/

// Ensure QRcode class is available (phpqrcode library)
if (!class_exists('QRcode')) {
    // Attempt to include library again if not loaded
    $qrLibPath = __DIR__ . '/../phpqrcode/qrlib.php';
    if (file_exists($qrLibPath)) {
        require_once $qrLibPath;
    }
}

if (!class_exists('QRcode')) {
    die('QRcode class not found. Please install or include phpqrcode/qrlib.php');
}

QRcode::png(
    $qr_data,
    $old_qr,
    // Use string 'H' for high error correction level to avoid undefined constant
    'H',
    8,
    2
);

/*
|--------------------------------------------------------------------------
| Update patient record
|--------------------------------------------------------------------------
*/

$update = mysqli_query($conn,"

UPDATE patients

SET

qr_token='$new_token',

qr_status='$qr_status',

qr_expiry_date='$new_expiry',

qr_last_regenerated='$generated_time'

WHERE id='$id'

");

if(!$update){

    die(mysqli_error($conn));

}

/*
|--------------------------------------------------------------------------
| Audit log
|--------------------------------------------------------------------------
*/

logAction(

$conn,

"QR Regenerated",

"Generated a NEW secure QR card for "

. $patient['fullname']

. " ("

. $patient_id

. "). Old QR has been invalidated."

);

/*
|--------------------------------------------------------------------------
| Notification
|--------------------------------------------------------------------------
*/

$title = mysqli_real_escape_string($conn,"QR Card Regenerated");

$message = mysqli_real_escape_string(

$conn,

"A brand new secure QR card has been issued for "

. $patient['fullname']

. ". Previous QR card is now invalid."

);

$type="warning";

$link="../patients/view_patient.php?id=".$id;

mysqli_query($conn,"

INSERT INTO notifications

(title,message,type,link)

VALUES

('$title','$message','$type','$link')

");

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

header("Location:view_patient.php?id=$id&qr=regenerated");

exit();
