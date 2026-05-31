<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($env['app_name']); ?> — Receptionist Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hms/public/assets/css/styles.css" rel="stylesheet">
    <style>
        :root { --recp-primary:#0d6efd; --recp-sidebar:#0b5ed7; --recp-accent:#20c997; }
        body { background-color:#f6f7fb; }
        .top-navbar { background:var(--recp-primary); color:#fff; padding:.75rem 1.5rem; box-shadow:0 2px 4px rgba(0,0,0,.1); position:fixed; top:0; left:250px; right:0; z-index:1000; }
        .sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:var(--recp-sidebar); color:#fff; display:flex; flex-direction:column; padding-top:1.25rem; box-shadow:2px 0 12px rgba(0,0,0,.15); z-index:1100; overflow-y:auto; overflow-x:hidden; }
        .sidebar .logo { text-align:center; margin-bottom:1.25rem; }
        .sidebar .logo i { font-size:2rem; color:var(--recp-accent); }
        .sidebar .logo div { font-weight:600; margin-top:.25rem; }
        .sidebar .nav-link { color:rgba(255,255,255,.9); padding:.85rem 1.25rem; display:flex; align-items:center; gap:.65rem; font-size:.95rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background:rgba(255,255,255,.08); color:#fff; }
        .sidebar .nav-link i { font-size:1.05rem; width:1.25rem; }
        .main-content { margin-left:250px; padding:100px 24px 32px; }
        @media (max-width:992px){ .sidebar{width:210px;} .top-navbar{left:210px;} .main-content{margin-left:210px; padding:90px 16px 24px;} }
        @media (max-width:768px){ .sidebar{position:static; width:100%; height:auto;} .top-navbar{position:static; left:0; right:0;} .main-content{margin-left:0; padding:24px 16px;} }
    </style>
    </head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="bi bi-person-badge"></i>
            <div>Reception Panel</div>
        </div>
        <nav class="nav flex-column">
            <?php $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH); ?>
            <a class="nav-link <?php echo $current_path==='/hms/public/dashboard-receptionist.php'?'active':''; ?>" href="/hms/public/dashboard-receptionist.php"><i class="bi bi-speedometer2"></i><?php echo t('dashboard'); ?></a>
            <a class="nav-link" href="/hms/public/patients/index.php"><i class="bi bi-people"></i>Patients</a>
            <a class="nav-link" href="/hms/public/appointments/index.php"><i class="bi bi-calendar-check"></i>Appointments</a>
            <a class="nav-link" href="/hms/public/appointments/verify.php"><i class="bi bi-shield-check"></i>Verify Appointment</a>
            <a class="nav-link" href="/hms/public/appointments/direct.php"><i class="bi bi-person-walking"></i>Direct Patient</a>
            <a class="nav-link" href="#section-queue"><i class="bi bi-list-ol"></i>Queue</a>
            <a class="nav-link" href="#section-reports"><i class="bi bi-graph-up"></i>Reports</a>
            <a class="nav-link mt-auto" href="/hms/public/logout.php"><i class="bi bi-box-arrow-right"></i><?php echo t('logout'); ?></a>
        </nav>
    </div>
    <nav class="top-navbar d-flex justify-content-between align-items-center">
        <div>
            <strong><?php echo sanitize($env['app_name']); ?></strong>
            <span class="ms-2 text-warning text-uppercase small">Receptionist</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-semibold"><?php echo sanitize(current_user_name()); ?></span>
            <a class="btn btn-outline-light btn-sm" href="/hms/public/account/password.php"><?php echo t('change_password'); ?></a>
        </div>
    </nav>
    <div class="main-content">