<?php 
include(__DIR__ . '/../includes/admin_check.php');
include(__DIR__ . '/../config/db.php');

$message = "";

if (isset($_POST['save'])) {

    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $password = md5($_POST['password']);

    $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");

    if (mysqli_num_rows($check) > 0) {
        $message = "Email already exists.";
    } else {
        $query = "INSERT INTO users (fullname, email, password, role)
                  VALUES ('$fullname', '$email', '$password', '$role')";

        if (mysqli_query($conn, $query)) {
            $message = "User added successfully.";
        } else {
            $message = "Error: " . mysqli_error($conn);
        }
    }
}

$result = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");

if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container-fluid mt-4">

    <div class="card shadow">

        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Manage System Users</h4>

            <a href="/patient_tracking_system/dashboard/index.php" class="btn btn-light btn-sm">
                Back to Dashboard
            </a>
        </div>

        <div class="card-body">

            <?php if ($message != "") { ?>
                <div class="alert alert-info">
                    <?php echo $message; ?>
                </div>
            <?php } ?>

            <form method="POST" class="mb-4">

                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label>Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>Role</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="Admin">Admin</option>
                            <option value="Healthcare Worker">Healthcare Worker</option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="col-md-1 mb-3 d-grid">
                        <label>&nbsp;</label>
                        <button type="submit" name="save" class="btn btn-primary">
                            Save
                        </button>
                    </div>

                </div>

            </form>

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>
                            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['role']); ?></td>

                            <td>
                                <a href="/patient_tracking_system/users/edit_user.php?id=<?php echo $row['id']; ?>" 
                                   class="btn btn-warning btn-sm">
                                    Edit
                                </a>

                                <?php if ($row['id'] != $_SESSION['user_id']) { ?>
                                    <a href="/patient_tracking_system/users/delete_user.php?id=<?php echo $row['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this user?');">
                                        Delete
                                    </a>
                                <?php } else { ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        Current User
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>

                    <?php 
                        }
                    } else {
                    ?>

                        <tr>
                            <td colspan="5" class="text-center">
                                No users found
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