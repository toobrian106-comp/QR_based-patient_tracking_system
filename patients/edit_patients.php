<?php 
include('../includes/auth_check.php');
include('../config/db.php');

$id = $_GET['id'];

$query = "SELECT * FROM patients WHERE id='$id'";
$result = mysqli_query($conn, $query);
$patient = mysqli_fetch_assoc($result);

if (isset($_POST['update'])) {

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $age = mysqli_real_escape_string($conn, $_POST['age']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);

    $update = "UPDATE patients 
               SET fullname='$fullname',
                   gender='$gender',
                   age='$age',
                   phone='$phone',
                   location='$location'
               WHERE id='$id'";

    if (mysqli_query($conn, $update)) {
        echo "<script>
                alert('Patient updated successfully.');
                window.location.href='manage_patients.php';
              </script>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Patient</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header bg-warning">
            <h4>Edit Patient</h4>
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Full Name</label>
                        <input type="text" name="fullname" class="form-control" 
                               value="<?php echo $patient['fullname']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required>
                            <option <?php if($patient['gender']=="Male") echo "selected"; ?>>Male</option>
                            <option <?php if($patient['gender']=="Female") echo "selected"; ?>>Female</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Age</label>
                        <input type="number" name="age" class="form-control" 
                               value="<?php echo $patient['age']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?php echo $patient['phone']; ?>">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" 
                               value="<?php echo $patient['location']; ?>" required>
                    </div>

                </div>

                <button type="submit" name="update" class="btn btn-warning">
                    Update Patient
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