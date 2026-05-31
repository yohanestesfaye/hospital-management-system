<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lang.php';
?><!doctype html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo sanitize($env['app_name']); ?> - Admin</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
	<link href="/hms/public/assets/css/styles.css" rel="stylesheet">
	<style>
		body {
			background-color: #f5f5f5;
		}
		.top-navbar {
			background-color: #2c3e50;
			color: white;
			padding: 10px 20px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.sidebar {
			background-color: #28a745;
			color: white;
			height: calc(100vh - 60px);
			width: 250px;
			position: fixed;
			left: 0;
			top: 60px;
			padding: 20px 0;
			box-shadow: 2px 0 4px rgba(0,0,0,0.1);
			overflow-y: auto;
			overflow-x: hidden;
		}
		.sidebar::-webkit-scrollbar {
			width: 6px;
		}
		.sidebar::-webkit-scrollbar-track {
			background: rgba(0,0,0,0.1);
		}
		.sidebar::-webkit-scrollbar-thumb {
			background: rgba(255,255,255,0.3);
			border-radius: 3px;
		}
		.sidebar::-webkit-scrollbar-thumb:hover {
			background: rgba(255,255,255,0.5);
		}
		.sidebar .logo {
			padding: 20px;
			text-align: center;
			border-bottom: 1px solid rgba(255,255,255,0.2);
			margin-bottom: 20px;
		}
		.sidebar .logo i {
			font-size: 2rem;
			margin-bottom: 10px;
		}
		.sidebar .nav-link {
			color: white;
			padding: 12px 20px;
			display: flex;
			align-items: center;
			transition: background-color 0.3s;
		}
		.sidebar .nav-link:hover,
		.sidebar .nav-link.active {
			background-color: rgba(0,0,0,0.2);
			color: white;
		}
		.sidebar .nav-link i {
			margin-right: 10px;
			width: 20px;
		}
		.sidebar .submenu {
			background-color: rgba(0,0,0,0.1);
			padding-left: 0;
		}
		.sidebar .submenu .nav-link {
			padding-left: 50px;
			font-size: 0.9rem;
		}
		.sidebar .nav-link .ms-auto {
			margin-left: auto;
		}
		.sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"] .bi-chevron-down {
			transform: rotate(180deg);
			transition: transform 0.3s;
		}
		.sidebar .nav-link[data-bs-toggle="collapse"] .bi-chevron-down {
			transition: transform 0.3s;
		}
		.sidebar .nav-link.active {
			scroll-margin-top: 20px;
		}
		.main-content {
			margin-left: 250px;
			padding: 20px;
			margin-top: 60px;
		}
		.stat-card {
			background: white;
			border-radius: 8px;
			padding: 20px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			text-align: center;
			transition: transform 0.2s;
		}
		.stat-card:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(0,0,0,0.15);
		}
		.stat-card .number {
			font-size: 2.5rem;
			font-weight: bold;
			color: #28a745;
		}
		.stat-card .label {
			color: #666;
			margin-top: 10px;
			font-size: 0.9rem;
		}
		.module-grid {
			display: grid;
			grid-template-columns: repeat(6, 1fr);
			gap: 15px;
			margin-top: 20px;
		}
		.module-tile {
			background: white;
			border-radius: 8px;
			padding: 20px;
			text-align: center;
			cursor: pointer;
			transition: all 0.3s;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			text-decoration: none;
			color: #333;
		}
		.module-tile:hover {
			background: #28a745;
			color: white;
			transform: translateY(-3px);
			box-shadow: 0 4px 8px rgba(0,0,0,0.2);
		}
		.module-tile i {
			font-size: 2rem;
			margin-bottom: 10px;
			display: block;
		}
		.calendar-section, .noticeboard-section {
			background: white;
			border-radius: 8px;
			padding: 20px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			margin-top: 20px;
		}
		.calendar-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 15px;
		}
		.calendar-nav {
			display: flex;
			gap: 10px;
		}
		.calendar-grid {
			display: grid;
			grid-template-columns: repeat(7, 1fr);
			gap: 5px;
		}
		.calendar-day {
			padding: 10px;
			text-align: center;
			border: 1px solid #ddd;
			border-radius: 4px;
		}
		.calendar-day.header {
			background: #f8f9fa;
			font-weight: bold;
		}
		.notice-item {
			padding: 10px;
			border-bottom: 1px solid #eee;
			display: flex;
			align-items: start;
			gap: 10px;
		}
		.notice-item:last-child {
			border-bottom: none;
		}
		.notice-item i {
			color: #28a745;
			margin-top: 5px;
		}
		@media (max-width: 1200px) {
			.module-grid {
				grid-template-columns: repeat(4, 1fr);
			}
		}
		@media (max-width: 768px) {
			.sidebar {
				width: 200px;
			}
			.main-content {
				margin-left: 200px;
			}
			.module-grid {
				grid-template-columns: repeat(3, 1fr);
			}
		}
		@media (max-width: 576px) {
			.top-navbar { position: static; left: 0; right: 0; }
			.sidebar { position: static; width: 100%; height: auto; top: auto; left: auto; padding: 12px 0; }
			.main-content { margin-left: 0; margin-top: 0; padding: 24px 16px; }
			.module-grid { grid-template-columns: repeat(2, 1fr); }
		}
	</style>
</head>
<body>
	<!-- Top Navigation Bar -->
	<nav class="top-navbar d-flex justify-content-between align-items-center">
		<div>
			<h5 class="mb-0"><?php echo t('hospital_management_system'); ?></h5>
		</div>
		<div class="d-flex align-items-center gap-3">
			<div class="dropdown">
				<button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
					<?php echo t('select_language'); ?>
				</button>
				<ul class="dropdown-menu">
					<li><a class="dropdown-item <?php echo get_current_lang() === 'en' ? 'active' : ''; ?>" href="/hms/public/set-language.php?lang=en">English</a></li>
					<li><a class="dropdown-item <?php echo get_current_lang() === 'am' ? 'active' : ''; ?>" href="/hms/public/set-language.php?lang=am">አማርኛ (Amharic)</a></li>
				</ul>
			</div>
			<div class="dropdown">
				<button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
					<?php echo t('account'); ?>
				</button>
				<ul class="dropdown-menu">
					<li><a class="dropdown-item" href="/hms/public/account/password.php"><?php echo t('change_password'); ?></a></li>
					<li><hr class="dropdown-divider"></li>
					<li><a class="dropdown-item" href="/hms/public/logout.php"><?php echo t('logout'); ?></a></li>
				</ul>
			</div>
		</div>
	</nav>

	<!-- Sidebar -->
	<div class="sidebar">
		<div class="logo">
			<i class="bi bi-heart-pulse-fill"></i>
			<div><strong>HMS</strong></div>
		</div>
		<nav class="nav flex-column">
			<?php
			// Get current page URL to determine active menu item
			$current_url = $_SERVER['REQUEST_URI'] ?? '';
			$current_path = parse_url($current_url, PHP_URL_PATH);
			
			// Function to check if a link should be active
			function is_active($href, $current_path) {
				if ($href === '#') return false;
				$href_path = parse_url($href, PHP_URL_PATH);
				// Exact match
				if ($current_path === $href_path) return true;
				// For pharmacy, also match sub-pages (vendors, categories, products)
				if ($href_path === '/hms/public/pharmacy/index.php') {
					return strpos($current_path, '/hms/public/pharmacy/') === 0;
				}
				// For pharmacists management
				if ($href_path === '/hms/public/pharmacists/index.php') {
					return strpos($current_path, '/hms/public/pharmacists/') === 0;
				}
				// For nurses management
				if ($href_path === '/hms/public/nurses/index.php') {
					return strpos($current_path, '/hms/public/nurses/') === 0;
				}
				// For accountants management
				if ($href_path === '/hms/public/accountants/index.php') {
					return strpos($current_path, '/hms/public/accountants/') === 0;
				}
				// For laboratorists management
				if ($href_path === '/hms/public/laboratorists/index.php') {
					return strpos($current_path, '/hms/public/laboratorists/') === 0;
				}
				// For pharmacist dashboard
				if ($href_path === '/hms/public/dashboard-pharmacist.php') {
					return $current_path === $href_path;
				}
				// For other sections, check if current path starts with the href path
				return strpos($current_path, $href_path) === 0;
			}
			
			// Check if pharmacy submenu should be expanded
			$pharmacy_active = is_active('/hms/public/pharmacy/index.php', $current_path) || 
			                   is_active('/hms/public/pharmacists/index.php', $current_path) ||
			                   is_active('/hms/public/dashboard-pharmacist.php', $current_path) ||
			                   strpos($current_path, '/hms/public/pharmacy/') === 0;
			?>
            <a class="nav-link <?php echo is_active('/hms/public/dashboard-admin.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/dashboard-admin.php">
                <i class="bi bi-speedometer2"></i> <?php echo t('dashboard'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/staff/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/staff/index.php">
                <i class="bi bi-people-fill"></i> <?php echo t('staff'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/departments/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/departments/index.php">
                <i class="bi bi-building"></i> <?php echo t('department'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/doctors/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/doctors/index.php">
                <i class="bi bi-people"></i> <?php echo t('doctor'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/patients/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/patients/index.php">
                <i class="bi bi-person"></i> <?php echo t('patient'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/nurses/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/nurses/index.php">
                <i class="bi bi-plus-circle"></i> <?php echo t('nurse'); ?>
            </a>
            <a class="nav-link <?php echo $pharmacy_active ? 'active' : ''; ?>" href="#" data-bs-toggle="collapse" data-bs-target="#pharmacySubmenu" aria-expanded="<?php echo $pharmacy_active ? 'true' : 'false'; ?>">
                <i class="bi bi-bag"></i> <?php echo t('pharmacy'); ?> <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse <?php echo $pharmacy_active ? 'show' : ''; ?>" id="pharmacySubmenu">
                <div class="submenu">
                    <a class="nav-link <?php echo is_active('/hms/public/pharmacy/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/pharmacy/index.php">
                        <i class="bi bi-bag-check"></i> <?php echo t('manage_pharmacy'); ?>
                    </a>
                    <a class="nav-link <?php echo is_active('/hms/public/pharmacists/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/pharmacists/index.php">
                        <i class="bi bi-person-badge"></i> <?php echo t('manage_pharmacists'); ?>
                    </a>
                </div>
            </div>
            <a class="nav-link <?php echo is_active('/hms/public/laboratorists/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/laboratorists/index.php">
                <i class="bi bi-flask"></i> <?php echo t('laboratorist'); ?>
            </a>
            <a class="nav-link <?php echo is_active('/hms/public/accountants/index.php', $current_path) ? 'active' : ''; ?>" href="/hms/public/accountants/index.php">
                <i class="bi bi-wallet2"></i> <?php echo t('accountant'); ?>
            </a>
            <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#monitorSubmenu">
                <i class="bi bi-crosshair"></i> <?php echo t('monitor_hospital'); ?> <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <a class="nav-link" href="#" data-bs-toggle="collapse" data-bs-target="#settingsSubmenu">
                <i class="bi bi-gear"></i> <?php echo t('settings'); ?> <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <a class="nav-link" href="#">
                <i class="bi bi-person-circle"></i> <?php echo t('profile'); ?>
            </a>
		</nav>
	</div>

	<!-- Main Content -->
	<div class="main-content">

