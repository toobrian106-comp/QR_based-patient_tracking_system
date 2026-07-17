<?php 
include('../includes/admin_check.php');
include('../config/db.php');

$message = "";

if (!isset($conn)) {
    die("Database connection error.");
}

$settings_query = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
$settings = mysqli_fetch_assoc($settings_query);

if (isset($_POST['save'])) {

    $system_name = mysqli_real_escape_string($conn, $_POST['system_name']);
    $ngo_name = mysqli_real_escape_string($conn, $_POST['ngo_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $footer_text = mysqli_real_escape_string($conn, $_POST['footer_text']);

    $update = "UPDATE settings SET
               system_name='$system_name',
               ngo_name='$ngo_name',
               email='$email',
               phone='$phone',
               location='$location',
               footer_text='$footer_text'
               WHERE id='" . $settings['id'] . "'";

    if (mysqli_query($conn, $update)) {
        $message = "Settings updated successfully.";
        $settings_query = mysqli_query($conn, "SELECT * FROM settings LIMIT 1");
        $settings = mysqli_fetch_assoc($settings_query);
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>System Settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background:#eef3f8;
            font-family: Arial, sans-serif;
        }

        .settings-card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
    </style>
</head>

<body>

<div class="container mt-5">

    <div class="card settings-card">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">System Settings</h4>
            <a href="../dashboard/index.php" class="btn btn-light btn-sm">Dashboard</a>
        </div>

        <div class="card-body">

            <?php if ($message != "") { ?>
                <div class="alert alert-info">
                    <?php echo $message; ?>
                </div>
            <?php } ?>

            <form method="POST">

                <div class="mb-3">
                    <label>System Name</label>
                    <input type="text" name="system_name" class="form-control"
                           value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label>NGO / Organization Name</label>
                    <input type="text" name="ngo_name" class="form-control"
                           value="<?php echo htmlspecialchars($settings['ngo_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control"
                           value="<?php echo htmlspecialchars($settings['email']); ?>">
                </div>

                <div class="mb-3">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control"
                           value="<?php echo htmlspecialchars($settings['phone']); ?>">
                </div>

                <div class="mb-3">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control"
                           value="<?php echo htmlspecialchars($settings['location']); ?>">
                </div>

                <div class="mb-3">
                    <label>Footer Text</label>
                    <input type="text" name="footer_text" class="form-control"
                           value="<?php echo htmlspecialchars($settings['footer_text']); ?>">
                </div>

                <button type="submit" name="save" class="btn btn-primary">
                    Save Settings
                </button>

            </form>

        </div>

    </div>

</div>

</body>
</html>