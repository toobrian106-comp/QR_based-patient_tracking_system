<?php 
include('../includes/auth_check.php');
include('../config/db.php');

if (!($conn instanceof mysqli)) {
    die('Database connection failed.');
}

if (!isset($_GET['id'])) {
    header("Location: attendance_list.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = "SELECT * FROM attendance WHERE id='$id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
    echo "<script>
            alert('Attendance record not found.');
            window.location.href='attendance_list.php';
          </script>";
    exit();
}

$attendance = mysqli_fetch_assoc($result);

if (isset($_POST['update'])) {

    $visit_date = mysqli_real_escape_string($conn, $_POST['visit_date']);
    $service_given = mysqli_real_escape_string($conn, $_POST['service_given']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $update = "UPDATE attendance 
               SET visit_date='$visit_date',
                   service_given='$service_given',
                   notes='$notes'
               WHERE id='$id'";

    if (mysqli_query($conn, $update)) {
        echo "<script>
                alert('Attendance record updated successfully.');
                window.location.href='attendance_list.php';
              </script>";
        exit();
    } else {
        die('Update failed: ' . mysqli_error($conn));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Attendance</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit Attendance Record</h4>
            <a href="attendance_list.php" class="btn btn-dark btn-sm">Back</a>
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="mb-3">
                    <label>Patient ID</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($attendance['patient_id']); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label>Visit Date</label>
                    <input type="date" name="visit_date" class="form-control"
                           value="<?php echo htmlspecialchars($attendance['visit_date']); ?>" required>
                </div>

                <div class="mb-3">
                    <label>Service Given</label>
                    <select name="service_given" class="form-control" required>

                        <option value="">-- Select Service --</option>

                        <option value="Food Basket"
                            <?php if ($attendance['service_given'] == "Food Basket") echo "selected"; ?>>
                            Food Basket
                        </option>

                        <option value="Vitamin Supplements"
                            <?php if ($attendance['service_given'] == "Vitamin Supplements") echo "selected"; ?>>
                            Vitamin Supplements
                        </option>

                        <option value="Food Basket and Vitamin Supplements"
                            <?php if ($attendance['service_given'] == "Food Basket and Vitamin Supplements") echo "selected"; ?>>
                            Food Basket and Vitamin Supplements
                        </option>

                    </select>
                </div>

                <div class="mb-3">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($attendance['notes']); ?></textarea>
                </div>

                <button type="submit" name="update" class="btn btn-warning">
                    Update Attendance
                </button>

                <a href="attendance_list.php" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>