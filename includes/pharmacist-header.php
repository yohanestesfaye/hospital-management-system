// c:\xampp\htdocs\hms\includes\pharmacist-header.php
<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($env['app_name']); ?> — Pharmacist Workspace</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hms/public/assets/css/styles.css" rel="stylesheet">
    <style>
        :root {
            --pharm-primary: #0d6efd;
            --pharm-sidebar-bg: #0b5ed7;
            --pharm-accent: #dc3545;
        }
        body { background-color: #f6f7fb; }
        .top-navbar {
            background: var(--pharm-primary);
            color: #fff;
            padding: 0.75rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: fixed; top: 0; left: 250px; right: 0; z-index: 1000;
        }
        .sidebar {
            position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
            background: var(--pharm-sidebar-bg); color: #fff;
            display: flex; flex-direction: column; padding-top: 1.25rem;
            box-shadow: 2px 0 12px rgba(0,0,0,0.15); z-index: 1100;
            overflow-y: auto; overflow-x: hidden;
        }
        .sidebar .logo { text-align: center; margin-bottom: 1.25rem; }
        .sidebar .logo i { font-size: 2rem; color: var(--pharm-accent); }
        .sidebar .logo div { font-weight: 600; margin-top: .25rem; }
        .sidebar .nav-link { color: rgba(255,255,255,0.9); padding: .85rem 1.25rem; display:flex; align-items:center; gap:.65rem; font-size:.95rem; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: rgba(255,255,255,0.08); color:#fff; }
        .sidebar .nav-link { border-left: 4px solid transparent; }
        .sidebar .nav-link.active { box-shadow: 0 4px 12px rgba(0,0,0,0.25); border-left-color: #fff; }
        .sidebar .nav-link i { font-size: 1.05rem; width: 1.25rem; }
        .sidebar .submenu { background: rgba(255,255,255,0.06); padding-left: 0; }
        .sidebar .submenu .nav-link { padding-left: 2rem; font-size: .9rem; }
        .main-content { margin-left: 250px; padding: 100px 24px 32px; }
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="bi bi-capsule-pill"></i>
            <div>Pharmacist Panel</div>
        </div>
        <nav class="nav flex-column">
            <?php
            $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $is_manage_pharmacy = in_array($current_path, [
                '/hms/public/pharmacy/index.php',
                '/hms/public/pharmacy/products/index.php',
                '/hms/public/pharmacy/categories/index.php',
                '/hms/public/pharmacy/vendors/index.php'
            ], true);
            $collapse_class = $is_manage_pharmacy ? 'show' : '';
            $is_low_stock = ($current_path === '/hms/public/pharmacy/products/index.php') && (get('low','')==='1');

            echo '<a class="nav-link ' . ($current_path === '/hms/public/dashboard-pharmacist.php' ? 'active' : '') . '" href="/hms/public/dashboard-pharmacist.php"><i class="bi bi-speedometer2"></i>Dashboard</a>';
            echo '<a class="nav-link ' . ($is_manage_pharmacy ? 'active' : '') . '" data-bs-toggle="collapse" href="#pharmMenu" role="button" aria-expanded="' . ($is_manage_pharmacy ? 'true' : 'false') . '" aria-controls="pharmMenu"><i class="bi bi-capsule"></i>Manage Pharmacy<span class="ms-auto"><i class="bi bi-chevron-down"></i></span></a>';
            echo '<div class="collapse submenu ' . $collapse_class . '" id="pharmMenu">';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/pharmacy/products/index.php' ? 'active' : '') . '" href="/hms/public/pharmacy/products/index.php">Pharmaceuticals</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/pharmacy/categories/index.php' ? 'active' : '') . '" href="/hms/public/pharmacy/categories/index.php">Categories</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/pharmacy/vendors/index.php' ? 'active' : '') . '" href="/hms/public/pharmacy/vendors/index.php">Vendors</a>';
            echo '</div>';
            $db = get_db_connection();
            try {
                $thr = (int)($env['low_stock_threshold'] ?? 5);
                $low_count = (int)$db->query("SELECT COUNT(*) AS c FROM pharmaceuticals WHERE stock_qty <= " . $thr)->fetch()['c'];
            } catch (Throwable $e) { $low_count=0; }
            echo '<a class="nav-link ' . ($is_low_stock ? 'active' : '') . '" href="/hms/public/pharmacy/products/index.php?low=1"><i class="bi bi-exclamation-triangle"></i>Low Stock Inventory<span class="badge bg-light text-dark ms-auto">' . (int)$low_count . '</span></a>';

            echo '<a class="nav-link ' . ($current_path === '/hms/public/prescriptions/index.php' ? 'active' : '') . '" href="/hms/public/prescriptions/index.php"><i class="bi bi-clipboard-plus"></i>Prescriptions</a>';
            echo '<a class="nav-link ' . ($current_path === '/hms/public/account/profile.php' ? 'active' : '') . '" href="/hms/public/account/profile.php"><i class="bi bi-person-circle"></i>Profile</a>';
            ?>
            <a class="nav-link mt-auto" href="/hms/public/logout.php"><i class="bi bi-box-arrow-right"></i><?php echo t('logout'); ?></a>
        </nav>
    </div>

    <nav class="top-navbar d-flex justify-content-between align-items-center">
        <div>
            <strong><?php echo sanitize($env['app_name']); ?></strong>
            <span class="ms-2 text-danger text-uppercase small">Pharmacist Workspace</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-semibold"><?php echo sanitize(current_user_name()); ?></span>
            <a class="btn btn-outline-light btn-sm" href="/hms/public/account/password.php"><?php echo t('change_password'); ?></a>
        </div>
    </nav>

    <div class="main-content">