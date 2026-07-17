<?php 
include('../includes/admin_check.php');
require_once('../config/db.php');

if (!isset($conn) || !$conn) {
    die('Database connection not established.');
}

$search = "";
$action_filter = "";
$from_date = "";
$to_date = "";

$where = "WHERE 1=1";

if (isset($_GET['filter'])) {

    // Safely retrieve raw inputs first
    $raw_search = isset($_GET['search']) ? $_GET['search'] : '';
    $raw_action = isset($_GET['action']) ? $_GET['action'] : '';
    $raw_from = isset($_GET['from_date']) ? $_GET['from_date'] : '';
    $raw_to = isset($_GET['to_date']) ? $_GET['to_date'] : '';

    // Use mysqli_real_escape_string only if $conn is available, otherwise fall back to addslashes
    if (isset($conn)) {
        $search = mysqli_real_escape_string($conn, $raw_search);
        $action_filter = mysqli_real_escape_string($conn, $raw_action);
        $from_date = mysqli_real_escape_string($conn, $raw_from);
        $to_date = mysqli_real_escape_string($conn, $raw_to);
    } else {
        $search = addslashes($raw_search);
        $action_filter = addslashes($raw_action);
        $from_date = addslashes($raw_from);
        $to_date = addslashes($raw_to);
    }

    if (!empty($search)) {
        $where .= " AND (
            user_name LIKE '%$search%' 
            OR action LIKE '%$search%' 
            OR description LIKE '%$search%'
        )";
    }

    if (!empty($action_filter)) {
        $where .= " AND action='$action_filter'";
    }

    if (!empty($from_date) && !empty($to_date)) {
        $where .= " AND DATE(created_at) BETWEEN '$from_date' AND '$to_date'";
    }
}

/* SAFE SUMMARY QUERIES */

$total_logs_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM audit_logs");
$total_logs = $total_logs_query ? mysqli_fetch_assoc($total_logs_query)['total'] : 0;

$today_logs_query = mysqli_query($conn, "
SELECT COUNT(*) AS total FROM audit_logs 
WHERE DATE(created_at)=CURDATE()
");
$today_logs = $today_logs_query ? mysqli_fetch_assoc($today_logs_query)['total'] : 0;

$user_login_logs_query = mysqli_query($conn, "
SELECT COUNT(*) AS total FROM audit_logs 
WHERE action LIKE '%Login%'
");
$user_login_logs = $user_login_logs_query ? mysqli_fetch_assoc($user_login_logs_query)['total'] : 0;

$patient_logs_query = mysqli_query($conn, "
SELECT COUNT(*) AS total FROM audit_logs 
WHERE action LIKE '%Patient%'
");
$patient_logs = $patient_logs_query ? mysqli_fetch_assoc($patient_logs_query)['total'] : 0;

/* DISTINCT ACTIONS */

$actions_query = mysqli_query($conn, "
SELECT DISTINCT action FROM audit_logs ORDER BY action ASC
");

/* MAIN LOG QUERY */

$logs_query = "
SELECT * FROM audit_logs
$where
ORDER BY id DESC
";

$logs = mysqli_query($conn, $logs_query);

if (!$logs) {
    die("Audit logs query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap -->

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

        .summary-card{
            color:white;
            border-radius:18px;
            padding:22px;
            box-shadow:0 10px 25px rgba(0,0,0,0.12);
        }

        .summary-card h2{
            font-weight:bold;
            font-size:36px;
        }

        .table th{
            background:#1f2937 !important;
            color:white;
        }

        .badge-action{
            padding:8px 12px;
            border-radius:30px;
            font-size:12px;
        }

        @media print{
            .no-print{
                display:none !important;
            }

            body{
                background:white;
            }

            .page-card{
                box-shadow:none;
            }
        }

    </style>
</head>

<body>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 no-print">

        <div>
            <h2 class="fw-bold mb-1">Audit Logs</h2>
            <p class="text-muted mb-0">
                Track system activities, user actions, and security-related events.
            </p>
        </div>

        <div>
            <a href="../dashboard/index.php" class="btn btn-primary">
                Dashboard
            </a>
            <a href="export_audit_logs.php" class="btn btn-success">
                Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-dark">
                Print Logs
            </button>
        </div>

    </div>

    <!-- SUMMARY CARDS -->

    <div class="row mb-4 no-print">

        <div class="col-md-3 mb-3">
            <div class="summary-card bg-primary">
                <h6>Total Logs</h6>
                <h2><?php echo $total_logs; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="summary-card bg-success">
                <h6>Today's Logs</h6>
                <h2><?php echo $today_logs; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="summary-card bg-warning">
                <h6>Login Events</h6>
                <h2><?php echo $user_login_logs; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="summary-card bg-danger">
                <h6>Patient Actions</h6>
                <h2><?php echo $patient_logs; ?></h2>
            </div>
        </div>

    </div>

    <!-- FILTER SECTION -->

    <div class="card page-card mb-4 no-print">

        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">Filter Audit Logs</h5>
        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row">

                    <div class="col-md-3 mb-3">
                        <label>Search</label>
                        <input type="text"
                               name="search"
                               class="form-control"
                               placeholder="User, action, description"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label>Action</label>
                        <select name="action" class="form-control">

                            <option value="">All Actions</option>

                            <?php if ($actions_query && mysqli_num_rows($actions_query) > 0) { ?>

                                <?php while($action_row = mysqli_fetch_assoc($actions_query)){ ?>

                                    <option value="<?php echo htmlspecialchars($action_row['action']); ?>"
                                        <?php if($action_filter == $action_row['action']) echo "selected"; ?>>

                                        <?php echo htmlspecialchars($action_row['action']); ?>

                                    </option>

                                <?php } ?>

                            <?php } ?>

                        </select>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>From Date</label>
                        <input type="date"
                               name="from_date"
                               class="form-control"
                               value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>

                    <div class="col-md-2 mb-3">
                        <label>To Date</label>
                        <input type="date"
                               name="to_date"
                               class="form-control"
                               value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>

                    <div class="col-md-2 mb-3 d-grid">
                        <label>.</label>
                        <button type="submit" name="filter" class="btn btn-primary">
                            Filter
                        </button>
                    </div>

                </div>

                <a href="audit_logs.php" class="btn btn-secondary">
                    Reset Filters
                </a>

            </form>

        </div>

    </div>

    <!-- AUDIT LOGS TABLE -->

    <div class="card page-card">

        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">

            <h5 class="mb-0">Audit Trail Records</h5>

            <span>
                Generated on <?php echo date("d M Y H:i"); ?>
            </span>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php 
                    $count = 1;

                    if(mysqli_num_rows($logs) > 0){

                        while($row = mysqli_fetch_assoc($logs)){

                            $badge = "secondary";

                            if(stripos($row['action'], "login") !== false){
                                $badge = "success";
                            }elseif(stripos($row['action'], "logout") !== false){
                                $badge = "dark";
                            }elseif(stripos($row['action'], "patient") !== false){
                                $badge = "primary";
                            }elseif(stripos($row['action'], "attendance") !== false){
                                $badge = "info";
                            }elseif(stripos($row['action'], "delete") !== false){
                                $badge = "danger";
                            }elseif(stripos($row['action'], "update") !== false){
                                $badge = "warning";
                            }
                    ?>

                        <tr>
                            <td><?php echo $count++; ?></td>

                            <td>
                                <?php echo htmlspecialchars($row['user_name'] ?? 'System'); ?>
                            </td>

                            <td>
                                <span class="badge bg-<?php echo $badge; ?> badge-action">
                                    <?php echo htmlspecialchars($row['action'] ?? 'System Action'); ?>
                                </span>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['description'] ?? 'No description'); ?>
                            </td>

                            <td>
                                <?php echo date("d M Y H:i", strtotime($row['created_at'])); ?>
                            </td>
                        </tr>

                    <?php 
                        }

                    }else{
                    ?>

                        <tr>
                            <td colspan="5" class="text-center">
                                No audit logs found.
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