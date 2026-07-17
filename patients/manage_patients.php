<?php 
include('../includes/auth_check.php');
include('../config/db.php');

// Normalize possible connection variable names from config
if(!isset($conn)){
    if(isset($link)){
        $conn = $link;
    }elseif(isset($mysqli)){
        $conn = $mysqli;
    }
}

$search = "";

if(isset($_GET['search'])){
    if(isset($conn) && $conn){
        $search = mysqli_real_escape_string($conn, $_GET['search']);
    }else{
        // fallback if connection variable isn't available to avoid undefined variable
        $search = addslashes($_GET['search']);
    }

    $query = "SELECT * FROM patients
              WHERE fullname LIKE '%$search%'
              OR patient_id LIKE '%$search%'
              OR location LIKE '%$search%'
              OR phone LIKE '%$search%'
              ORDER BY id DESC";
}else{
    $query = "SELECT * FROM patients ORDER BY id DESC";
}

$result = false;

// Ensure $conn is available before attempting query
if(!isset($conn) || !$conn){
    die("Database connection not available.");
}

$result = mysqli_query($conn, $query);

if(!$result){
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Patients</title>
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

        .patient-photo{
            width:55px;
            height:55px;
            object-fit:cover;
            border-radius:50%;
            border:2px solid #0d6efd;
        }

        .qr-img{
            width:60px;
            border:1px solid #ddd;
            padding:4px;
            border-radius:8px;
            background:white;
        }

        .table th{
            background:#1f2937 !important;
            color:white;
            font-size:13px;
            vertical-align:middle;
        }

        .table td{
            font-size:13px;
            vertical-align:middle;
        }

        .badge-status{
            padding:7px 10px;
            border-radius:30px;
            font-size:11px;
        }

        .action-btn{
            width:120px;
            margin-bottom:4px;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="card page-card">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">

            <div>
                <h4 class="mb-0">Manage Patients</h4>
                <small>Patient records, QR security status, and identification cards</small>
            </div>

            <div>
                <a href="../dashboard/index.php" class="btn btn-light btn-sm">
                    Dashboard
                </a>

                <a href="add_patient.php" class="btn btn-light btn-sm">
                    Add Patient
                </a>
            </div>

        </div>

        <div class="card-body">

            <form method="GET" class="mb-3">

                <div class="input-group">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Search by name, patient ID, phone or location"
                           value="<?php echo htmlspecialchars($search); ?>">

                    <button class="btn btn-primary">
                        Search
                    </button>

                    <a href="manage_patients.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Patient ID</th>
                            <th>Photo</th>
                            <th>QR</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>Patient Status</th>
                            <th>QR Status</th>
                            <th>QR Expiry</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($result) > 0){

                        while($row = mysqli_fetch_assoc($result)){

                            $qr_status = $row['qr_status'] ?? 'Inactive';
                            $qr_expiry_date = $row['qr_expiry_date'] ?? null;

                            $is_expired = false;

                            if(!empty($qr_expiry_date) && strtotime($qr_expiry_date) < strtotime(date("Y-m-d"))){
                                $is_expired = true;
                            }

                            $qr_badge = "success";
                            $qr_text = "Active";

                            if($qr_status != "Active"){
                                $qr_badge = "danger";
                                $qr_text = "Inactive";
                            }

                            if($is_expired){
                                $qr_badge = "warning";
                                $qr_text = "Expired";
                            }

                            $patient_badge = "success";

                            if(($row['status'] ?? '') != "Active"){
                                $patient_badge = "secondary";
                            }
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td>
                                <strong><?php echo htmlspecialchars($row['patient_id']); ?></strong>
                            </td>

                            <td>
                                <?php if(!empty($row['photo'])){ ?>
                                    <img src="../assets/patient_photos/<?php echo htmlspecialchars($row['photo']); ?>" class="patient-photo">
                                <?php }else{ ?>
                                    <span class="text-muted">No Photo</span>
                                <?php } ?>
                            </td>

                            <td>
                                <?php if(!empty($row['qr_code']) && file_exists("../assets/qr_codes/" . $row['qr_code'])){ ?>
                                    <img src="../assets/qr_codes/<?php echo htmlspecialchars($row['qr_code']); ?>?v=<?php echo time(); ?>" class="qr-img">
                                <?php }else{ ?>
                                    <span class="text-danger">Missing</span>
                                <?php } ?>
                            </td>

                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>

                            <td><?php echo htmlspecialchars($row['gender']); ?></td>

                            <td><?php echo htmlspecialchars($row['age']); ?></td>

                            <td><?php echo htmlspecialchars($row['phone']); ?></td>

                            <td><?php echo htmlspecialchars($row['location']); ?></td>

                            <td>
                                <span class="badge bg-<?php echo $patient_badge; ?> badge-status">
                                    <?php echo htmlspecialchars($row['status'] ?? 'Active'); ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-<?php echo $qr_badge; ?> badge-status">
                                    <?php echo $qr_text; ?>
                                </span>
                            </td>

                            <td>
                                <?php 
                                echo !empty($qr_expiry_date) 
                                    ? htmlspecialchars($qr_expiry_date) 
                                    : "Not Set"; 
                                ?>
                            </td>

                            <td>
                                <a href="view_patient.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-primary btn-sm action-btn">
                                    View Profile
                                </a>

                                <br>

                                <a href="print_card.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-info btn-sm action-btn" 
                                   target="_blank">
                                    Print Card
                                </a>

                                <br>

                                <a href="regenerate_qr.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-warning btn-sm action-btn"
                                   onclick="return confirm('Regenerate QR? Old QR cards and photocopies will stop working.');">
                                    Regenerate QR
                                </a>

                                <br>

                                <a href="edit_patient.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-secondary btn-sm action-btn">
                                    Edit
                                </a>

                                <br>

                                <a href="delete_patient.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-danger btn-sm action-btn"
                                   onclick="return confirm('Delete this patient record?');">
                                    Delete
                                </a>
                            </td>
                        </tr>

                    <?php 
                        }

                    }else{
                    ?>

                        <tr>
                            <td colspan="13" class="text-center">
                                No patients found.
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