<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($env['app_name']); ?> — Accountant Workspace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hms/public/assets/css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --acc-primary: #1e40af;
            --acc-sidebar-bg: #1e3a8a;
            --acc-accent: #3b82f6;
            --acc-success: #10b981;
            --acc-warning: #f59e0b;
            --acc-danger: #ef4444;
        }
        body { background-color: #f6f7fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .top-navbar {
            background: linear-gradient(135deg, var(--acc-primary), var(--acc-sidebar-bg));
            color: #fff;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: fixed; top: 0; left: 250px; right: 0; z-index: 1000;
        }
        .sidebar {
            position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
            background: linear-gradient(180deg, var(--acc-sidebar-bg), #1e3a8a);
            color: #fff;
            display: flex; flex-direction: column; padding-top: 1.25rem;
            box-shadow: 2px 0 12px rgba(0,0,0,0.2); z-index: 1100;
            overflow-y: auto; overflow-x: hidden;
        }
        .sidebar .logo { text-align: center; margin-bottom: 1.5rem; padding: 0 1rem; }
        .sidebar .logo i { font-size: 2.5rem; color: var(--acc-accent); }
        .sidebar .logo div { font-weight: 700; margin-top: .5rem; font-size: 1.1rem; }
        .sidebar .nav-link { 
            color: rgba(255,255,255,0.9); 
            padding: .9rem 1.25rem; 
            display:flex; 
            align-items:center; 
            gap:.75rem; 
            font-size:.95rem;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { 
            background: rgba(255,255,255,0.12); 
            color:#fff; 
            transform: translateX(4px);
        }
        .sidebar .nav-link { border-left: 4px solid transparent; }
        .sidebar .nav-link.active { 
            box-shadow: 0 4px 12px rgba(0,0,0,0.25); 
            border-left-color: var(--acc-accent);
            background: rgba(255,255,255,0.15);
        }
        .sidebar .nav-link i { font-size: 1.1rem; width: 1.5rem; }
        .sidebar .submenu { background: rgba(255,255,255,0.08); padding-left: 0; }
        .sidebar .submenu .nav-link { padding-left: 2.5rem; font-size: .9rem; }
        .main-content { margin-left: 250px; padding: 100px 24px 32px; min-height: 100vh; }
        .kpi-card {
            border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .kpi-icon {
            width: 56px; height: 56px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.75rem;
        }
        .notification-badge {
            position: absolute; top: -4px; right: -4px;
            background: var(--acc-danger); color: white;
            border-radius: 50%; width: 20px; height: 20px;
            font-size: 0.7rem; display: flex; align-items: center; justify-content: center;
        }
        @media (max-width: 992px) {
            .sidebar { width: 210px; }
            .top-navbar { left: 210px; }
            .main-content { margin-left: 210px; padding: 90px 16px 24px; }
        }
        @media (max-width: 768px) {
            .sidebar { position: static; width: 100%; height: auto; }
            .top-navbar { position: static; left: 0; right: 0; }
            .main-content { margin-left: 0; padding: 24px 16px; }
        }
        .table-modern { border-radius: 8px; overflow: hidden; }
        .table-modern thead { background: linear-gradient(135deg, var(--acc-primary), var(--acc-sidebar-bg)); color: white; }
        .btn-modern { border-radius: 8px; font-weight: 500; }
        .card-modern { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="bi bi-calculator"></i>
            <div>Accountant Panel</div>
        </div>
        <nav class="nav flex-column">
            <?php
            $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $is_billing = strpos($current_path, '/billing/') !== false;
            $is_payments = strpos($current_path, '/payments/') !== false;
            $is_insurance = strpos($current_path, '/insurance/') !== false;
            $is_reports = strpos($current_path, '/reports/') !== false;
            $is_audit = strpos($current_path, '/audit/') !== false;

            echo '<a class="nav-link ' . ($current_path === '/hms/public/dashboard-accountant.php' ? 'active' : '') . '" href="/hms/public/dashboard-accountant.php"><i class="bi bi-speedometer2"></i>Dashboard</a>';
            echo '<a class="nav-link ' . ($is_billing ? 'active' : '') . '" href="/hms/public/billing/index.php"><i class="bi bi-receipt-cutoff"></i>Billing</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/billing/prescriptions.php' ? 'active' : '') . '" href="/hms/public/billing/prescriptions.php"><i class="bi bi-prescription2"></i>Prescription Billing</a>';
            echo '<a class="nav-link ' . ($is_payments ? 'active' : '') . '" href="/hms/public/payments/collect.php"><i class="bi bi-cash-coin"></i>Payment Collection</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/payments/history.php' ? 'active' : '') . '" href="/hms/public/payments/history.php"><i class="bi bi-clock-history"></i>Transaction History</a>';
            echo '<a class="nav-link ' . ($is_reports ? 'active' : '') . '" href="/hms/public/reports/financial.php"><i class="bi bi-graph-up-arrow"></i>Financial Reports</a>';
            echo '<a class="nav-link ' . ($is_audit ? 'active' : '') . '" href="/hms/public/audit/logs.php"><i class="bi bi-file-earmark-text"></i>Audit Logs</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/account/profile.php' ? 'active' : '') . '" href="/hms/public/account/profile.php"><i class="bi bi-person-circle"></i>Profile</a>';
            ?>
            <a class="nav-link mt-auto" href="/hms/public/logout.php"><i class="bi bi-box-arrow-right"></i><?php echo t('logout'); ?></a>
        </nav>
    </div>

    <nav class="top-navbar d-flex justify-content-between align-items-center">
        <div>
            <strong><?php echo sanitize($env['app_name']); ?></strong>
            <span class="ms-2 text-uppercase small" style="opacity: 0.9;">Accountant Workspace</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php
            // Get notification count
            $db = get_db_connection();
            $notif_count = 0;
            try {
                $notif_stmt = $db->prepare('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0');
                $notif_stmt->execute([current_user_id()]);
                $notif_count = (int)$notif_stmt->fetch()['c'];
            } catch (Throwable $e) {}
            
            // Get pending payments count
            $pending_payments = 0;
            try {
                $pending_stmt = $db->query("SELECT COUNT(*) AS c FROM invoices WHERE status = 'Unpaid'");
                $pending_payments = (int)$pending_stmt->fetch()['c'];
            } catch (Throwable $e) {}
            
            // Get pending insurance claims
            $pending_insurance = 0;
            try {
                $ins_stmt = $db->query("SELECT COUNT(*) AS c FROM invoices WHERE status = 'Unpaid' AND source = 'Insurance'");
                $pending_insurance = (int)$ins_stmt->fetch()['c'];
            } catch (Throwable $e) {}
            
            $total_notifications = $notif_count + $pending_payments + $pending_insurance;
            ?>
            <div class="position-relative">
                <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-bell"></i>
                    <?php if ($total_notifications > 0) { ?>
                        <span class="notification-badge"><?php echo $total_notifications > 9 ? '9+' : $total_notifications; ?></span>
                    <?php } ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                    <li><h6 class="dropdown-header">Notifications</h6></li>
                    <?php if ($pending_payments > 0) { ?>
                        <li><a class="dropdown-item" href="/hms/public/payments/collect.php">
                            <i class="bi bi-cash-coin text-warning"></i> <?php echo $pending_payments; ?> Pending Payment<?php echo $pending_payments > 1 ? 's' : ''; ?>
                        </a></li>
                    <?php } ?>
                    <?php if ($pending_insurance > 0) { ?>
                        <li><a class="dropdown-item" href="/hms/public/insurance/claims.php">
                            <i class="bi bi-shield-check text-info"></i> <?php echo $pending_insurance; ?> Pending Insurance Claim<?php echo $pending_insurance > 1 ? 's' : ''; ?>
                        </a></li>
                    <?php } ?>
                    <?php if ($notif_count > 0) { ?>
                        <li><a class="dropdown-item" href="/hms/public/account/notifications.php">
                            <i class="bi bi-bell text-primary"></i> <?php echo $notif_count; ?> New Notification<?php echo $notif_count > 1 ? 's' : ''; ?>
                        </a></li>
                    <?php } ?>
                    <?php if ($total_notifications === 0) { ?>
                        <li><span class="dropdown-item text-muted">No new notifications</span></li>
                    <?php } ?>
                </ul>
            </div>
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-person-circle" style="font-size: 1.5rem;"></i>
                <span class="fw-semibold"><?php echo sanitize(current_user_name()); ?></span>
            </div>
            <a class="btn btn-outline-light btn-sm" href="/hms/public/account/password.php">Change Password</a>
        </div>
    </nav>

    <div class="main-content">

