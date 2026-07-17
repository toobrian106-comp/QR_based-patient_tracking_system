<?php 
include('../includes/admin_check.php');
include('../config/db.php');

if (!isset($_GET['id'])) {
    header("Location: /patient_tracking_system/users/manage_users.php");
    exit();
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

$query = "SELECT * FROM users WHERE id='$id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

if (mysqli_num_rows($result) == 0) {
    echo "User not found.";
    exit();
}

$user = mysqli_fetch_assoc($result);

$message = "";

if (isset($_POST['update'])) {

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $new_password = $_POST['password'];

    $email_check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND id != '$id'");

    if (mysqli_num_rows($email_check) > 0) {

        $message = "Email already belongs to another user.";

    } else {

        if (!empty($new_password)) {
            $password = md5($new_password);

            $update = "UPDATE users 
                       SET fullname='$fullname',
                           email='$email',
                           role='$role',
                           password='$password'
                       WHERE id='$id'";
        } else {
            $update = "UPDATE users 
                       SET fullname='$fullname',
                           email='$email',
                           role='$role'
                       WHERE id='$id'";
        }

        if (mysqli_query($conn, $update)) {
            echo "<script>
                    alert('User updated successfully.');
                    window.location.href='/patient_tracking_system/users/manage_users.php';
                  </script>";
            exit();
        } else {
            $message = "Update failed: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow">

        <div class="card-header bg-warning d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Edit User</h4>

            <a href="/patient_tracking_system/users/manage_users.php" class="btn btn-dark btn-sm">
                Back
            </a>
        </div>

        <div class="card-body">

            <?php if ($message != "") { ?>
                <div class="alert alert-danger">
                    <?php echo $message; ?>
                </div>
            <?php } ?>

            <form method="POST">

                <div class="mb-3">
                    <label>Full Name</label>
                    <input type="text" 
                           name="fullname" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($user['fullname']); ?>" 
                           required>
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" 
                           name="email" 
                           class="form-control" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           required>
                </div>

                <div class="mb-3">
                    <label>Role</label>
                    <select name="role" class="form-control" required>
                        <option value="Admin" <?php if ($user['role'] == "Admin") echo "selected"; ?>>
                            Admin
                        </option>

                        <option value="Healthcare Worker" <?php if ($user['role'] == "Healthcare Worker") echo "selected"; ?>>
                            Healthcare Worker
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label>New Password</label>
                    <input type="password" name="password" class="form-control">

                    <small class="text-muted">
                        Leave blank if you do not want to change password.
                    </small>
                </div>

                <button type="submit" name="update" class="btn btn-warning">
                    Update User
                </button>

                <a href="/patient_tracking_system/users/manage_users.php" class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

</body>
</html>