<?php 
include('../includes/auth_check.php');
require_once __DIR__ . '/../config/db.php';
if (!isset($conn)) {
    die('Database connection failed.');
}

if (isset($_GET['mark_read'])) {
    $id = mysqli_real_escape_string($conn, $_GET['mark_read']);

    mysqli_query($conn, "UPDATE notifications SET is_read=1 WHERE id='$id'");

    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read=1");

    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);

    mysqli_query($conn, "DELETE FROM notifications WHERE id='$id'");

    header("Location: notifications.php");
    exit();
}

$notifications = mysqli_query($conn, "
SELECT *
FROM notifications
ORDER BY created_at DESC
");

$unread_count = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) AS total
FROM notifications
WHERE is_read=0
"))['total'];

$total_count = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT COUNT(*) AS total
FROM notifications
"))['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Notifications Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background:#eef3f8;
            font-family: Arial, sans-serif;
        }

        .page-card {
            border:none;
            border-radius:18px;
            box-shadow:0 10px 25px rgba(0,0,0,0.08);
        }

        .notification-item {
            border-left:5px solid #0d6efd;
            border-radius:12px;
            background:white;
            padding:18px;
            margin-bottom:15px;
            box-shadow:0 5px 15px rgba(0,0,0,0.06);
        }

        .notification-item.unread {
            border-left-color:#dc3545;
            background:#fff5f5;
        }

        .badge-soft {
            padding:8px 12px;
            border-radius:30px;
        }
    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold">Notifications Center</h2>
            <p class="text-muted mb-0">
                View system alerts, defaulting warnings, attendance updates, and registration notices.
            </p>
        </div>

        <div>
            <a href="../dashboard/index.php" class="btn btn-primary">
                Dashboard
            </a>

            <a href="notifications.php?mark_all_read=1" class="btn btn-success"
               onclick="return confirm('Mark all notifications as read?');">
                Mark All Read
            </a>
        </div>

    </div>

    <div class="row mb-4">

        <div class="col-md-6 mb-3">
            <div class="card page-card">
                <div class="card-body">
                    <h6>Total Notifications</h6>
                    <h2><?php echo $total_count; ?></h2>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card page-card">
                <div class="card-body">
                    <h6>Unread Notifications</h6>
                    <h2><?php echo $unread_count; ?></h2>
                </div>
            </div>
        </div>

    </div>

    <div class="card page-card">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">System Notifications</h5>
        </div>

        <div class="card-body">

            <?php if (mysqli_num_rows($notifications) > 0) { ?>

                <?php while ($note = mysqli_fetch_assoc($notifications)) { ?>

                    <div class="notification-item <?php echo $note['is_read'] == 0 ? 'unread' : ''; ?>">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($note['title']); ?>

                                    <?php if ($note['is_read'] == 0) { ?>
                                        <span class="badge bg-danger">Unread</span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary">Read</span>
                                    <?php } ?>
                                </h5>

                                <p class="mb-1">
                                    <?php echo htmlspecialchars($note['message']); ?>
                                </p>

                                <small class="text-muted">
                                    <?php echo date("d M Y H:i", strtotime($note['created_at'])); ?>
                                </small>

                            </div>

                            <div class="text-end">

                                <?php if (!empty($note['link'])) { ?>
                                    <a href="<?php echo htmlspecialchars($note['link']); ?>" class="btn btn-primary btn-sm mb-1">
                                        Open
                                    </a>
                                    <br>
                                <?php } ?>

                                <?php if ($note['is_read'] == 0) { ?>
                                    <a href="notifications.php?mark_read=<?php echo $note['id']; ?>" 
                                       class="btn btn-success btn-sm mb-1">
                                        Mark Read
                                    </a>
                                    <br>
                                <?php } ?>

                                <a href="notifications.php?delete=<?php echo $note['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Delete this notification?');">
                                    Delete
                                </a>

                            </div>

                        </div>

                    </div>

                <?php } ?>

            <?php } else { ?>

                <div class="alert alert-info">
                    No notifications available.
                </div>

            <?php } ?>

        </div>

    </div>

</div>

</body>
</html>