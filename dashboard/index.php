<?php
include(__DIR__ . '/../includes/auth_check.php');
require_once(__DIR__ . '/../config/db.php');
include(__DIR__ . '/../includes/generate_notifications.php');

function getDashboardCount(mysqli $conn, string $sql): int
{
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

$today = date('Y-m-d');
$next_7_days = date('Y-m-d', strtotime('+7 days'));
$default_days = 30;

$total_patients = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total FROM patients"
);

$today_visits = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM attendance
     WHERE visit_date='$today'"
);

$total_services = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total FROM attendance"
);

$today_appointments = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE appointment_date='$today'
     AND status='Scheduled'"
);

$upcoming_appointments = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE appointment_date > '$today'
     AND appointment_date <= '$next_7_days'
     AND status='Scheduled'"
);

$missed_appointments = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE appointment_date < '$today'
     AND status='Scheduled'"
);

$defaulting_query = mysqli_query(
    $conn,
    "SELECT patients.patient_id
     FROM patients
     LEFT JOIN attendance
        ON patients.patient_id = attendance.patient_id
     GROUP BY patients.patient_id
     HAVING MAX(attendance.visit_date) IS NULL
        OR DATEDIFF(CURDATE(), MAX(attendance.visit_date)) > $default_days"
);

$defaulting_patients = $defaulting_query
    ? mysqli_num_rows($defaulting_query)
    : 0;

$active_qr_cards = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status='Active'
     AND (
        qr_expiry_date IS NULL
        OR qr_expiry_date >= CURDATE()
     )"
);

$expired_qr_cards = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_expiry_date IS NOT NULL
     AND qr_expiry_date < CURDATE()"
);

$suspended_qr_cards = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM patients
     WHERE qr_status IN ('Suspended', 'Lost', 'Stolen', 'Deactivated', 'Inactive')"
);

$failed_qr_attempts = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM qr_scan_attempts
     WHERE attempt_status='Failed'"
);

$recent_result = mysqli_query(
    $conn,
    "SELECT
        attendance.*,
        patients.fullname
     FROM attendance
     INNER JOIN patients
        ON attendance.patient_id = patients.patient_id
     ORDER BY attendance.id DESC
     LIMIT 6"
);

$today_appointment_list = mysqli_query(
    $conn,
    "SELECT
        appointments.*,
        patients.fullname,
        patients.phone
     FROM appointments
     INNER JOIN patients
        ON appointments.patient_id = patients.patient_id
     WHERE appointments.appointment_date='$today'
     AND appointments.status='Scheduled'
     ORDER BY appointments.appointment_time ASC
     LIMIT 5"
);

$high_risk_patients = mysqli_query(
    $conn,
    "SELECT
        patients.id,
        patients.patient_id,
        patients.fullname,
        patients.phone,
        patients.risk_level,
        MAX(attendance.visit_date) AS last_visit,
        DATEDIFF(CURDATE(), MAX(attendance.visit_date)) AS days_since_last_visit
     FROM patients
     LEFT JOIN attendance
        ON patients.patient_id = attendance.patient_id
     GROUP BY
        patients.id,
        patients.patient_id,
        patients.fullname,
        patients.phone,
        patients.risk_level
     HAVING
        MAX(attendance.visit_date) IS NULL
        OR DATEDIFF(CURDATE(), MAX(attendance.visit_date)) > $default_days
        OR patients.risk_level='High'
     ORDER BY days_since_last_visit DESC
     LIMIT 5"
);

$recent_audit_logs = mysqli_query(
    $conn,
    "SELECT *
     FROM audit_logs
     ORDER BY id DESC
     LIMIT 6"
);

$monthly_query = mysqli_query(
    $conn,
    "SELECT
        DATE_FORMAT(visit_date, '%b') AS month_name,
        MONTH(visit_date) AS month_number,
        COUNT(*) AS total
     FROM attendance
     WHERE YEAR(visit_date)=YEAR(CURDATE())
     GROUP BY
        MONTH(visit_date),
        DATE_FORMAT(visit_date, '%b')
     ORDER BY month_number ASC"
);

$months = [];
$monthly_totals = [];

if ($monthly_query) {
    while ($row = mysqli_fetch_assoc($monthly_query)) {
        $months[] = $row['month_name'];
        $monthly_totals[] = (int) $row['total'];
    }
}

$service_query = mysqli_query(
    $conn,
    "SELECT
        service_given,
        COUNT(*) AS total
     FROM attendance
     GROUP BY service_given
     ORDER BY total DESC"
);

$services = [];
$service_totals = [];

if ($service_query) {
    while ($row = mysqli_fetch_assoc($service_query)) {
        $services[] = $row['service_given'];
        $service_totals[] = (int) $row['total'];
    }
}

$notification_query = mysqli_query(
    $conn,
    "SELECT *
     FROM notifications
     ORDER BY created_at DESC
     LIMIT 10"
);

$notification_count = getDashboardCount(
    $conn,
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE is_read=0"
);

$dashboard_alerts = mysqli_query(
    $conn,
    "SELECT *
     FROM notifications
     ORDER BY created_at DESC
     LIMIT 4"
);

$current_user_name = $_SESSION['fullname'] ?? 'System User';
$current_user_role = $_SESSION['role'] ?? 'User';
$current_hour = (int) date('H');

if ($current_hour < 12) {
    $greeting = 'Good morning';
} elseif ($current_hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$qr_coverage = $total_patients > 0
    ? round(($active_qr_cards / $total_patients) * 100)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Patient Tracking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #0f6cbd;
            --primary-dark: #0a4f8d;
            --primary-soft: #e8f2fc;
            --sidebar: #17233c;
            --sidebar-dark: #111a2d;
            --success: #198754;
            --warning: #f39c12;
            --danger: #dc3545;
            --surface: #ffffff;
            --background: #f3f6fa;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e4e9f0;
            --shadow: 0 12px 32px rgba(31, 45, 61, 0.08);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            overflow-x: hidden;
            background: var(--background);
            color: var(--text);
            font-family: Arial, Helvetica, sans-serif;
        }

        a { text-decoration: none; }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 278px;
            min-height: 100vh;
            overflow-y: auto;
            background: linear-gradient(180deg, var(--sidebar) 0%, var(--sidebar-dark) 100%);
            color: white;
            box-shadow: 8px 0 30px rgba(17, 26, 45, 0.18);
            z-index: 1000;
        }

        .sidebar-brand {
            padding: 24px 20px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.10);
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .sidebar-brand-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, #1f8ff5, #0f6cbd);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 18px rgba(15, 108, 189, 0.34);
        }

        .sidebar-brand h3 { margin: 0; font-size: 18px; font-weight: 800; }
        .sidebar-brand p { margin: 3px 0 0; color: rgba(255,255,255,0.62); font-size: 11px; }
        .sidebar-menu { padding: 15px 12px 24px; }

        .sidebar-label {
            margin: 15px 12px 7px;
            color: rgba(255,255,255,0.42);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .sidebar-link,
        .sidebar-toggle {
            width: 100%;
            min-height: 46px;
            margin-bottom: 4px;
            padding: 11px 13px;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: rgba(255,255,255,0.82);
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .sidebar-link:hover,
        .sidebar-toggle:hover {
            background: rgba(255,255,255,0.10);
            color: white;
            transform: translateX(3px);
        }

        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(31,143,245,0.34), rgba(31,143,245,0.12));
            color: white;
            border-left: 3px solid #35a6ff;
        }

        .sidebar-link i,
        .sidebar-toggle i { width: 20px; font-size: 17px; text-align: center; }
        .sidebar-toggle .chevron { margin-left: auto; transition: transform 0.2s ease; }
        .sidebar-toggle[aria-expanded="true"] .chevron { transform: rotate(180deg); }
        .submenu { margin: 3px 0 8px; padding-left: 14px; }
        .submenu .sidebar-link { min-height: 40px; padding-left: 38px; font-size: 13px; color: rgba(255,255,255,0.69); }
        .sidebar-logout { color: #ffd7dc; }

        .main-content { min-height: 100vh; margin-left: 278px; padding: 24px; }

        .topbar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 20px 24px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .greeting-label { margin-bottom: 4px; color: var(--primary); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px; }
        .topbar h1 { margin: 0; color: #172033; font-size: 27px; font-weight: 800; }
        .topbar-subtitle { margin: 5px 0 0; color: var(--muted); font-size: 13px; }
        .topbar-actions { display: flex; align-items: center; gap: 12px; }

        .notification-button {
            width: 46px;
            height: 46px;
            padding: 0;
            border: 1px solid var(--border);
            border-radius: 50%;
            background: white;
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            position: relative;
            box-shadow: 0 7px 16px rgba(31,45,61,0.08);
        }

        .notification-button:hover { color: var(--primary); background: var(--primary-soft); }

        .notification-count {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 20px;
            height: 20px;
            padding: 0 5px;
            border-radius: 20px;
            background: var(--danger);
            color: white;
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 800;
        }

        .user-chip {
            min-height: 46px;
            padding: 6px 13px 6px 7px;
            border-radius: 25px;
            background: #f5f8fc;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #28a7e8);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }

        .user-chip strong { display: block; color: #172033; font-size: 13px; line-height: 1.2; }
        .user-chip small { color: var(--muted); font-size: 10px; }

        .quick-actions {
            margin-bottom: 22px;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .quick-action {
            min-height: 78px;
            padding: 15px;
            border-radius: 15px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 8px 22px rgba(31,45,61,0.06);
            transition: 0.22s ease;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            border-color: rgba(15,108,189,0.28);
            color: var(--primary);
            box-shadow: 0 12px 26px rgba(31,45,61,0.10);
        }

        .quick-action-icon {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 13px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .quick-action strong { display: block; font-size: 13px; }
        .quick-action small { color: var(--muted); font-size: 10px; }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .kpi-card {
            min-height: 145px;
            padding: 20px;
            border-radius: 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: 0.22s ease;
        }

        .kpi-card:hover { transform: translateY(-4px); }
        .kpi-card::after { content: ""; position: absolute; width: 86px; height: 86px; right: -26px; top: -26px; border-radius: 50%; background: currentColor; opacity: 0.07; }

        .kpi-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 21px;
            margin-bottom: 16px;
        }

        .kpi-card h6 { margin: 0; color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.7px; }
        .kpi-card h2 { margin: 7px 0 3px; color: #172033; font-size: 34px; font-weight: 900; }
        .kpi-card small { color: var(--muted); font-size: 11px; }

        .kpi-blue { color: var(--primary); }
        .kpi-blue .kpi-icon { background: #e7f2fc; color: var(--primary); }
        .kpi-green { color: var(--success); }
        .kpi-green .kpi-icon { background: #e9f7f0; color: var(--success); }
        .kpi-orange { color: var(--warning); }
        .kpi-orange .kpi-icon { background: #fff5e5; color: var(--warning); }
        .kpi-red { color: var(--danger); }
        .kpi-red .kpi-icon { background: #fdecef; color: var(--danger); }
        .kpi-purple { color: #7257c8; }
        .kpi-purple .kpi-icon { background: #f0edfc; color: #7257c8; }
        .kpi-cyan { color: #0a91b9; }
        .kpi-cyan .kpi-icon { background: #e8f8fc; color: #0a91b9; }

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(300px, 0.85fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .panel-card {
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--surface);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .panel-header {
            min-height: 63px;
            padding: 16px 19px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .panel-title { margin: 0; color: #172033; font-size: 15px; font-weight: 800; }
        .panel-subtitle { margin: 3px 0 0; color: var(--muted); font-size: 10px; }
        .panel-link { color: var(--primary); font-size: 11px; font-weight: 800; }
        .panel-body { padding: 18px; }
        .chart-wrapper { height: 310px; }

        .mini-stat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 11px; }
        .mini-stat { min-height: 90px; padding: 14px; border: 1px solid var(--border); border-radius: 14px; background: #f8fafc; }
        .mini-stat strong { display: block; margin-bottom: 6px; color: var(--muted); font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; }
        .mini-stat span { color: #172033; font-size: 26px; font-weight: 900; }

        .list-item { padding: 13px 0; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; gap: 12px; }
        .list-item:last-child { border-bottom: none; }
        .list-icon { width: 36px; height: 36px; flex-shrink: 0; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .list-item-content { min-width: 0; flex: 1; }
        .list-item-content strong { display: block; overflow: hidden; color: #172033; font-size: 12px; white-space: nowrap; text-overflow: ellipsis; }
        .list-item-content small { display: block; margin-top: 3px; color: var(--muted); font-size: 10px; }
        .list-action { flex-shrink: 0; }

        .table { margin-bottom: 0; }
        .table thead th { padding: 12px; border: none; background: #f4f7fb !important; color: #546174; font-size: 10px; font-weight: 800; letter-spacing: 0.6px; text-transform: uppercase; white-space: nowrap; }
        .table tbody td { padding: 13px 12px; border-color: var(--border); color: #344054; font-size: 12px; vertical-align: middle; }

        .notification-menu {
            width: 420px;
            max-height: 480px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(31,45,61,0.16);
        }

        .notification-menu-header { padding: 14px 16px; border-bottom: 1px solid var(--border); font-weight: 800; }
        .notification-item { padding: 13px 15px; border-bottom: 1px solid var(--border); }
        .notification-item:last-child { border-bottom: none; }
        .notification-unread { background: #f7fbff; }
        .notification-read { background: white; opacity: 0.72; }
        .notification-item strong { color: #172033; font-size: 12px; }
        .notification-item p { margin: 5px 0; color: var(--muted); font-size: 11px; }

        .toast { border: none; border-radius: 14px; overflow: hidden; box-shadow: 0 18px 42px rgba(31,45,61,0.20); }

        @media (max-width: 1200px) {
            .quick-actions { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .content-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 850px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .topbar-actions { width: 100%; justify-content: flex-end; }
            .quick-actions { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 560px) {
            .main-content { padding: 14px; }
            .quick-actions,
            .kpi-grid,
            .mini-stat-grid { grid-template-columns: 1fr; }
            .notification-menu { width: 92vw; }
            .user-chip-text { display: none; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div>
            <h3>Patient System</h3>
            <p>Tracking & Adherence Portal</p>
        </div>
    </div>

    <div class="sidebar-menu">
        <div class="sidebar-label">Main</div>

        <a href="index.php" class="sidebar-link active">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <div class="sidebar-label">Patient Services</div>

        <button type="button" class="sidebar-toggle" data-bs-toggle="collapse" data-bs-target="#patientMenu" aria-expanded="false">
            <i class="bi bi-people-fill"></i>
            <span>Patient Management</span>
            <i class="bi bi-chevron-down chevron"></i>
        </button>

        <div class="collapse submenu" id="patientMenu">
            <a href="../patients/add_patient.php" class="sidebar-link">
                <i class="bi bi-person-plus-fill"></i>
                <span>Add Patient</span>
            </a>

            <a href="../patients/manage_patients.php" class="sidebar-link">
                <i class="bi bi-person-lines-fill"></i>
                <span>Manage Patients</span>
            </a>
        </div>

        <button type="button" class="sidebar-toggle" data-bs-toggle="collapse" data-bs-target="#attendanceMenu" aria-expanded="false">
            <i class="bi bi-qr-code-scan"></i>
            <span>Attendance</span>
            <i class="bi bi-chevron-down chevron"></i>
        </button>

        <div class="collapse submenu" id="attendanceMenu">
            <a href="../attendance/scan_qr.php" class="sidebar-link">
                <i class="bi bi-camera-fill"></i>
                <span>Scan Secure QR</span>
            </a>

            <a href="../attendance/attendance_list.php" class="sidebar-link">
                <i class="bi bi-calendar-check-fill"></i>
                <span>Attendance Records</span>
            </a>
        </div>

        <button type="button" class="sidebar-toggle" data-bs-toggle="collapse" data-bs-target="#appointmentMenu" aria-expanded="false">
            <i class="bi bi-calendar2-week-fill"></i>
            <span>Appointments</span>
            <i class="bi bi-chevron-down chevron"></i>
        </button>

        <div class="collapse submenu" id="appointmentMenu">
            <a href="../appointments/appointment_dashboard.php" class="sidebar-link">
                <i class="bi bi-calendar2-range-fill"></i>
                <span>Appointment Dashboard</span>
            </a>

            <a href="../appointments/add_appointment.php" class="sidebar-link">
                <i class="bi bi-calendar-plus-fill"></i>
                <span>Schedule Visit</span>
            </a>

            <a href="../appointments/manage_appointments.php" class="sidebar-link">
                <i class="bi bi-calendar-event-fill"></i>
                <span>Manage Appointments</span>
            </a>
        </div>

        <div class="sidebar-label">Monitoring</div>

        <button type="button" class="sidebar-toggle" data-bs-toggle="collapse" data-bs-target="#reportsMenu" aria-expanded="false">
            <i class="bi bi-bar-chart-fill"></i>
            <span>Reports</span>
            <i class="bi bi-chevron-down chevron"></i>
        </button>

        <div class="collapse submenu" id="reportsMenu">
            <a href="../reports/reports_center.php" class="sidebar-link">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>Reports Centre</span>
            </a>

            <a href="../reports/adherence_report.php" class="sidebar-link">
                <i class="bi bi-graph-up-arrow"></i>
                <span>Adherence Report</span>
            </a>

            <a href="../reports/defaulting_detection.php" class="sidebar-link">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Defaulting Detection</span>
            </a>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') { ?>
            <div class="sidebar-label">Administration</div>

            <button type="button" class="sidebar-toggle" data-bs-toggle="collapse" data-bs-target="#adminMenu" aria-expanded="false">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Administration</span>
                <i class="bi bi-chevron-down chevron"></i>
            </button>

            <div class="collapse submenu" id="adminMenu">
                <a href="../users/manage_users.php" class="sidebar-link">
                    <i class="bi bi-person-gear"></i>
                    <span>Manage Users</span>
                </a>

                <a href="../notifications/notifications.php" class="sidebar-link">
                    <i class="bi bi-bell-fill"></i>
                    <span>Notifications</span>
                </a>

                <a href="../settings/settings.php" class="sidebar-link">
                    <i class="bi bi-sliders"></i>
                    <span>System Settings</span>
                </a>

                <a href="../reports/audit_logs.php" class="sidebar-link">
                    <i class="bi bi-journal-text"></i>
                    <span>Audit Logs</span>
                </a>

                <a href="../reports/qr_security_dashboard.php" class="sidebar-link">
                    <i class="bi bi-qr-code"></i>
                    <span>QR Security</span>
                </a>

                <a href="../reports/qr_fraud_dashboard.php" class="sidebar-link">
                    <i class="bi bi-shield-exclamation"></i>
                    <span>QR Fraud Detection</span>
                </a>

                <a href="../reports/backup_data.php" class="sidebar-link">
                    <i class="bi bi-database-fill-down"></i>
                    <span>Database Backup</span>
                </a>
            </div>
        <?php } ?>

        <div class="sidebar-label">Session</div>

                <a href="../auth/logout.php" class="sidebar-link sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
    </div>
</aside>

<main class="main-content">
    <section class="topbar">
        <div>
            <div class="greeting-label"><?php echo htmlspecialchars($greeting); ?></div>
            <h1><?php echo htmlspecialchars($current_user_name); ?></h1>
            <p class="topbar-subtitle">
                Patient Tracking and Adherence Management System
                &nbsp;•&nbsp;
                <?php echo date('l, d F Y'); ?>
            </p>
        </div>

        <div class="topbar-actions">
            <div class="dropdown">
                <button type="button" class="notification-button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell-fill"></i>
                    <?php if ($notification_count > 0) { ?>
                        <span id="notification-count" class="notification-count"><?php echo $notification_count; ?></span>
                    <?php } ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end notification-menu">
                    <div class="notification-menu-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <a href="../notifications/notifications.php" class="panel-link">View all</a>
                        </div>
                    </div>

                    <?php if ($notification_query && mysqli_num_rows($notification_query) > 0) { ?>
                        <?php while ($note = mysqli_fetch_assoc($notification_query)) { ?>
                            <div id="notification-<?php echo $note['id']; ?>" class="notification-item <?php echo (int) $note['is_read'] === 0 ? 'notification-unread' : 'notification-read'; ?>">
                                <strong><?php echo htmlspecialchars($note['title']); ?></strong>

                                <?php if ((int) $note['is_read'] === 0) { ?>
                                    <span class="badge bg-danger ms-1 status-badge">New</span>
                                <?php } ?>

                                <p><?php echo htmlspecialchars($note['message']); ?></p>
                                <small class="text-muted"><?php echo date('d M Y H:i', strtotime($note['created_at'])); ?></small>

                                <div class="mt-2 d-flex gap-1">
                                    <?php if (!empty($note['link'])) { ?>
                                        <a href="<?php echo htmlspecialchars($note['link']); ?>" class="btn btn-primary btn-sm">Open</a>
                                    <?php } ?>

                                    <?php if ((int) $note['is_read'] === 0) { ?>
                                        <button type="button" class="btn btn-outline-success btn-sm mark-read-btn" data-id="<?php echo $note['id']; ?>">Mark Read</button>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="p-4 text-center text-muted">No notifications available.</div>
                    <?php } ?>
                </div>
            </div>

            <div class="user-chip">
                <div class="user-avatar"><?php echo strtoupper(substr($current_user_name, 0, 1)); ?></div>
                <div class="user-chip-text">
                    <strong><?php echo htmlspecialchars($current_user_name); ?></strong>
                    <small><?php echo htmlspecialchars($current_user_role); ?></small>
                </div>
            </div>
        </div>
    </section>

    <section class="quick-actions">
        <a href="../patients/add_patient.php" class="quick-action">
            <div class="quick-action-icon"><i class="bi bi-person-plus-fill"></i></div>
            <div><strong>Add Patient</strong><small>Register a new patient</small></div>
        </a>

        <a href="../attendance/scan_qr.php" class="quick-action">
            <div class="quick-action-icon"><i class="bi bi-qr-code-scan"></i></div>
            <div><strong>Scan QR</strong><small>Verify and record attendance</small></div>
        </a>

        <a href="../appointments/add_appointment.php" class="quick-action">
            <div class="quick-action-icon"><i class="bi bi-calendar-plus-fill"></i></div>
            <div><strong>Schedule Visit</strong><small>Create a follow-up appointment</small></div>
        </a>

        <a href="../patients/manage_patients.php" class="quick-action">
            <div class="quick-action-icon"><i class="bi bi-person-lines-fill"></i></div>
            <div><strong>Patient Profiles</strong><small>Search and open records</small></div>
        </a>

        <a href="../reports/reports_center.php" class="quick-action">
            <div class="quick-action-icon"><i class="bi bi-file-earmark-bar-graph-fill"></i></div>
            <div><strong>Reports</strong><small>Review program performance</small></div>
        </a>
    </section>

    <section class="kpi-grid">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
            <h6>Total Patients</h6>
            <h2><?php echo $total_patients; ?></h2>
            <small>Registered patient records</small>
        </div>

        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="bi bi-person-check-fill"></i></div>
            <h6>Today's Visits</h6>
            <h2><?php echo $today_visits; ?></h2>
            <small>Attendance entries recorded today</small>
        </div>

        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h6>Defaulting Patients</h6>
            <h2><?php echo $defaulting_patients; ?></h2>
            <small>No attendance for more than <?php echo $default_days; ?> days</small>
        </div>

        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-heart-pulse-fill"></i></div>
            <h6>Services Given</h6>
            <h2><?php echo $total_services; ?></h2>
            <small>Total recorded service transactions</small>
        </div>
    </section>

    <section class="kpi-grid">
        <div class="kpi-card kpi-cyan">
            <div class="kpi-icon"><i class="bi bi-calendar2-check-fill"></i></div>
            <h6>Today's Appointments</h6>
            <h2><?php echo $today_appointments; ?></h2>
            <small>Scheduled follow-up visits today</small>
        </div>

        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="bi bi-calendar2-week-fill"></i></div>
            <h6>Upcoming Appointments</h6>
            <h2><?php echo $upcoming_appointments; ?></h2>
            <small>Scheduled within the next seven days</small>
        </div>

        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="bi bi-calendar-x-fill"></i></div>
            <h6>Missed Follow-Ups</h6>
            <h2><?php echo $missed_appointments; ?></h2>
            <small>Past appointments still marked scheduled</small>
        </div>

        <div class="kpi-card kpi-red">
            <div class="kpi-icon"><i class="bi bi-shield-exclamation"></i></div>
            <h6>Failed QR Attempts</h6>
            <h2><?php echo $failed_qr_attempts; ?></h2>
            <small>Rejected or suspicious QR scans</small>
        </div>
    </section>

    <section class="content-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Monthly Attendance Trend</h3>
                    <p class="panel-subtitle">Patient visits recorded during the current year</p>
                </div>
                <a href="../attendance/attendance_list.php" class="panel-link">View records</a>
            </div>
            <div class="panel-body">
                <div class="chart-wrapper"><canvas id="monthlyChart"></canvas></div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">QR Security Summary</h3>
                    <p class="panel-subtitle">Current card validity and fraud-monitoring status</p>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') { ?>
                    <a href="../reports/qr_security_dashboard.php" class="panel-link">Open security</a>
                <?php } ?>
            </div>

            <div class="panel-body">
                <div class="mini-stat-grid">
                    <div class="mini-stat">
                        <strong>Active QR Cards</strong>
                        <span class="text-success"><?php echo $active_qr_cards; ?></span>
                    </div>
                    <div class="mini-stat">
                        <strong>Expired Cards</strong>
                        <span class="text-danger"><?php echo $expired_qr_cards; ?></span>
                    </div>
                    <div class="mini-stat">
                        <strong>Restricted Cards</strong>
                        <span class="text-warning"><?php echo $suspended_qr_cards; ?></span>
                    </div>
                    <div class="mini-stat">
                        <strong>Failed Scans</strong>
                        <span class="text-danger"><?php echo $failed_qr_attempts; ?></span>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Active-card coverage</span>
                        <strong class="small"><?php echo $qr_coverage; ?>%</strong>
                    </div>
                    <div class="progress" style="height: 9px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $qr_coverage; ?>%;"></div>
                    </div>
                </div>

                <div class="mt-4 d-grid gap-2">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') { ?>
                        <a href="../reports/qr_fraud_dashboard.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-shield-exclamation me-1"></i>Review Fraud Attempts
                        </a>
                    <?php } ?>

                    <a href="../attendance/scan_qr.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-qr-code-scan me-1"></i>Scan Secure QR
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Service Distribution</h3>
                    <p class="panel-subtitle">Breakdown of services issued to patients</p>
                </div>
                <a href="../reports/reports_center.php" class="panel-link">Open reports</a>
            </div>
            <div class="panel-body">
                <div class="chart-wrapper"><canvas id="serviceChart"></canvas></div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Latest Alerts</h3>
                    <p class="panel-subtitle">Recent system and patient notifications</p>
                </div>
                <a href="../notifications/notifications.php" class="panel-link">View all</a>
            </div>

            <div class="panel-body">
                <?php if ($dashboard_alerts && mysqli_num_rows($dashboard_alerts) > 0) { ?>
                    <?php while ($alert = mysqli_fetch_assoc($dashboard_alerts)) { ?>
                        <?php
                        $alert_icon = 'bi-bell-fill';
                        $alert_background = '#e8f2fc';
                        $alert_color = '#0f6cbd';

                        if (
                            stripos($alert['type'] ?? '', 'danger') !== false ||
                            stripos($alert['title'], 'fraud') !== false ||
                            stripos($alert['title'], 'default') !== false
                        ) {
                            $alert_icon = 'bi-exclamation-triangle-fill';
                            $alert_background = '#fdecef';
                            $alert_color = '#dc3545';
                        } elseif (stripos($alert['type'] ?? '', 'success') !== false) {
                            $alert_icon = 'bi-check-circle-fill';
                            $alert_background = '#e9f7f0';
                            $alert_color = '#198754';
                        } elseif (stripos($alert['type'] ?? '', 'warning') !== false) {
                            $alert_icon = 'bi-exclamation-circle-fill';
                            $alert_background = '#fff5e5';
                            $alert_color = '#f39c12';
                        }
                        ?>

                        <div class="list-item">
                            <div class="list-icon" style="background: <?php echo $alert_background; ?>; color: <?php echo $alert_color; ?>;">
                                <i class="bi <?php echo $alert_icon; ?>"></i>
                            </div>

                            <div class="list-item-content">
                                <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                <small><?php echo htmlspecialchars($alert['message']); ?></small>
                                <small><?php echo date('d M Y H:i', strtotime($alert['created_at'])); ?></small>
                            </div>

                            <?php if (!empty($alert['link'])) { ?>
                                <div class="list-action">
                                    <a href="<?php echo htmlspecialchars($alert['link']); ?>" class="btn btn-outline-primary btn-sm">Open</a>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-bell-slash fs-2"></i>
                        <p class="mt-2 mb-0">No dashboard alerts available.</p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="content-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Today's Appointments</h3>
                    <p class="panel-subtitle">Scheduled follow-up visits requiring attention</p>
                </div>
                <a href="../appointments/appointment_dashboard.php" class="panel-link">View all</a>
            </div>

            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Purpose</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($today_appointment_list && mysqli_num_rows($today_appointment_list) > 0) { ?>
                            <?php while ($appt = mysqli_fetch_assoc($today_appointment_list)) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('H:i', strtotime($appt['appointment_time']))); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appt['fullname']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($appt['patient_id']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($appt['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['purpose']); ?></td>
                                    <td>
                                        <a href="../appointments/update_appointment_status.php?id=<?php echo $appt['id']; ?>&status=Attended" class="btn btn-success btn-sm">Attended</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No appointments scheduled for today.</td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">High-Risk Patients</h3>
                    <p class="panel-subtitle">Patients requiring follow-up intervention</p>
                </div>
                <a href="../reports/defaulting_detection.php" class="panel-link">Open report</a>
            </div>

            <div class="panel-body">
                <?php if ($high_risk_patients && mysqli_num_rows($high_risk_patients) > 0) { ?>
                    <?php while ($risk = mysqli_fetch_assoc($high_risk_patients)) { ?>
                        <div class="list-item">
                            <div class="list-icon" style="background:#fdecef;color:#dc3545;">
                                <i class="bi bi-person-exclamation"></i>
                            </div>

                            <div class="list-item-content">
                                <strong><?php echo htmlspecialchars($risk['fullname']); ?></strong>
                                <small><?php echo htmlspecialchars($risk['patient_id']); ?></small>
                                <small>
                                    <?php if (empty($risk['last_visit'])) { ?>
                                        No attendance recorded
                                    <?php } else { ?>
                                        Last visit <?php echo (int) $risk['days_since_last_visit']; ?> day(s) ago
                                    <?php } ?>
                                </small>
                            </div>

                            <div class="list-action">
                                <a href="../patients/view_patient.php?id=<?php echo $risk['id']; ?>" class="btn btn-outline-danger btn-sm">Profile</a>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="text-center text-muted py-4">No high-risk patients detected.</div>
                <?php } ?>
            </div>
        </div>
    </section>

    <section class="content-grid">
        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Recent Patient Activity</h3>
                    <p class="panel-subtitle">Latest attendance and services recorded</p>
                </div>
                <a href="../attendance/attendance_list.php" class="panel-link">View all</a>
            </div>

            <div class="panel-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Name</th>
                                <th>Visit Date</th>
                                <th>Service</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($recent_result && mysqli_num_rows($recent_result) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($recent_result)) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['patient_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($row['visit_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['service_given']); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No recent patient activity found.</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-header">
                <div>
                    <h3 class="panel-title">Recent System Activity</h3>
                    <p class="panel-subtitle">Latest actions recorded in the audit trail</p>
                </div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') { ?>
                    <a href="../reports/audit_logs.php" class="panel-link">Audit logs</a>
                <?php } ?>
            </div>

            <div class="panel-body">
                <?php if ($recent_audit_logs && mysqli_num_rows($recent_audit_logs) > 0) { ?>
                    <?php while ($log = mysqli_fetch_assoc($recent_audit_logs)) { ?>
                        <div class="list-item">
                            <div class="list-icon" style="background:#f0edfc;color:#7257c8;">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div class="list-item-content">
                                <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                <small><?php echo htmlspecialchars($log['description'] ?? 'No description'); ?></small>
                                <small><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?> • <?php echo date('d M Y H:i', strtotime($log['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="text-center text-muted py-4">No recent system activity found.</div>
                <?php } ?>
            </div>
        </div>
    </section>
</main>

<?php if (isset($_GET['toast'])) { ?>
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;">
    <div id="liveToast" class="toast show" role="alert">
        <?php if ($_GET['toast'] === 'patient_registered') { ?>
            <div class="toast-header bg-success text-white">
                <strong class="me-auto"><i class="bi bi-check-circle-fill me-1"></i>Patient Registered</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">New patient has been registered successfully.</div>
        <?php } elseif ($_GET['toast'] === 'attendance_recorded') { ?>
            <div class="toast-header bg-primary text-white">
                <strong class="me-auto"><i class="bi bi-calendar-check-fill me-1"></i>Attendance Recorded</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">Patient attendance has been recorded successfully.</div>
        <?php } elseif ($_GET['toast'] === 'appointment_scheduled') { ?>
            <div class="toast-header bg-success text-white">
                <strong class="me-auto"><i class="bi bi-calendar-plus-fill me-1"></i>Appointment Scheduled</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">The follow-up appointment was scheduled successfully.</div>
        <?php } elseif ($_GET['toast'] === 'appointment_attended') { ?>
            <div class="toast-header bg-success text-white">
                <strong class="me-auto">Appointment Attended</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">The appointment has been marked as attended.</div>
        <?php } elseif ($_GET['toast'] === 'appointment_missed') { ?>
            <div class="toast-header bg-warning">
                <strong class="me-auto">Appointment Missed</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">The appointment has been marked as missed.</div>
        <?php } elseif ($_GET['toast'] === 'appointment_cancelled') { ?>
            <div class="toast-header bg-danger text-white">
                <strong class="me-auto">Appointment Cancelled</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">The appointment has been cancelled.</div>
        <?php } ?>
    </div>
</div>
<?php } ?>

<script>
const monthlyLabels = <?php echo json_encode($months); ?>;
const monthlyData = <?php echo json_encode($monthly_totals); ?>;
const serviceLabels = <?php echo json_encode($services); ?>;
const serviceData = <?php echo json_encode($service_totals); ?>;

new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'Number of Visits',
            data: monthlyData,
            borderWidth: 1,
            borderRadius: 7,
            maxBarThickness: 42,
            backgroundColor: 'rgba(15,108,189,0.78)',
            borderColor: '#0f6cbd'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: 'rgba(148,163,184,0.16)' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});

new Chart(document.getElementById('serviceChart'), {
    type: 'doughnut',
    data: {
        labels: serviceLabels,
        datasets: [{
            data: serviceData,
            borderWidth: 3,
            borderColor: '#ffffff',
            backgroundColor: [
                '#0f6cbd',
                '#198754',
                '#f39c12',
                '#dc3545',
                '#7257c8',
                '#0a91b9',
                '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '64%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 14,
                    font: { size: 11 }
                }
            }
        }
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.mark-read-btn').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const notificationId = this.getAttribute('data-id');
            const buttonElement = this;

            fetch('../notifications/mark_read_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + encodeURIComponent(notificationId)
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed.');
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Failed to mark notification as read.');
                }

                const notificationBox = document.getElementById('notification-' + notificationId);

                buttonElement.innerText = 'Read';
                buttonElement.classList.remove('btn-outline-success');
                buttonElement.classList.add('btn-secondary');
                buttonElement.disabled = true;

                if (notificationBox) {
                    notificationBox.classList.remove('notification-unread');
                    notificationBox.classList.add('notification-read');

                    const statusBadge = notificationBox.querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.remove();
                    }
                }

                const badge = document.getElementById('notification-count');

                if (badge) {
                    if (Number(data.unread_count) > 0) {
                        badge.innerText = data.unread_count;
                    } else {
                        badge.remove();
                    }
                }
            })
            .catch(function (error) {
                alert(error.message);
            });
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>