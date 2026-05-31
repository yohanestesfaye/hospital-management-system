<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
	<meta charset="UTF-8">
	<title><?php echo sanitize($env['app_name']); ?> &mdash; Doctor Workspace</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
	<link href="/hms/public/assets/css/styles.css" rel="stylesheet">
	<style>
		:root {
			--doctor-primary: #0b7285;
			--doctor-primary-dark: #075465;
			--doctor-sidebar-bg: #0e5060;
			--doctor-accent: #20c997;
		}
		body {
			background-color: #f6f7fb;
		}
		.top-navbar {
			background: var(--doctor-primary);
			color: #fff;
			padding: 0.75rem 1.5rem;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			position: fixed;
			top: 0;
			left: 250px;
			right: 0;
			z-index: 1000;
		}
		.sidebar {
			position: fixed;
			top: 0;
			left: 0;
			width: 250px;
			height: 100vh;
			background: var(--doctor-sidebar-bg);
			color: #fff;
			display: flex;
			flex-direction: column;
			padding-top: 1.25rem;
			box-shadow: 2px 0 12px rgba(0,0,0,0.15);
			z-index: 1100;
		}
		.sidebar .logo {
			text-align: center;
			margin-bottom: 1.5rem;
		}
		.sidebar .logo i {
			font-size: 2rem;
			color: var(--doctor-accent);
		}
		.sidebar .logo div {
			font-weight: 600;
			margin-top: 0.25rem;
		}
		.sidebar .nav-link {
			color: rgba(255,255,255,0.85);
			padding: 0.85rem 1.25rem;
			display: flex;
			align-items: center;
			gap: 0.65rem;
			font-size: 0.95rem;
			transition: background .2s, color .2s;
		}
		.sidebar .nav-link:hover,
		.sidebar .nav-link.active {
			background: rgba(255,255,255,0.08);
			color: #fff;
		}
		.sidebar .nav-link i {
			font-size: 1.05rem;
			width: 1.25rem;
		}
		.main-content {
			margin-left: 250px;
			padding: 100px 24px 32px;
		}
		@media (max-width: 992px) {
			.sidebar {
				width: 210px;
			}
			.top-navbar {
				left: 210px;
			}
			.main-content {
				margin-left: 210px;
				padding: 90px 16px 24px;
			}
		}
		@media (max-width: 768px) {
			.sidebar {
				position: static;
				width: 100%;
				height: auto;
			}
			.top-navbar {
				position: static;
				left: 0;
				right: 0;
			}
			.main-content {
				margin-left: 0;
				padding: 24px 16px;
			}
		}
	</style>
</head>
<body>
	<div class="sidebar">
		<div class="logo">
			<i class="bi bi-stethoscope"></i>
			<div>Doctor Panel</div>
		</div>
		<nav class="nav flex-column">
			<?php
			$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $doctor_nav = [
                ['label' => 'dashboard', 'icon' => 'bi-speedometer2', 'href' => '/hms/public/dashboard-doctor.php'],
                ['label' => 'manage_appointments', 'icon' => 'bi-calendar-check', 'href' => '/hms/public/appointments/index.php'],
                ['label' => 'manage_prescription', 'icon' => 'bi-capsule', 'href' => '/hms/public/prescriptions/index.php'],
                ['label' => 'medical_records', 'icon' => 'bi-journal-medical', 'href' => '/hms/public/medical_records/index.php'],
                ['label' => 'lab_results', 'icon' => 'bi-beaker', 'href' => '/hms/public/lab_results/index.php'],
                ['label' => 'profile', 'icon' => 'bi-person-circle', 'href' => '/hms/public/account/profile.php'],
            ];
            foreach ($doctor_nav as $item) {
                $active = $current_path === parse_url($item['href'], PHP_URL_PATH) ? 'active' : '';
                echo '<a class="nav-link ' . $active . '" href="' . $item['href'] . '"><i class="bi ' . $item['icon'] . '"></i>' . sanitize(t($item['label'])) . '</a>';
            }
            $is_dashboard = ($current_path === '/hms/public/dashboard-doctor.php');
            if ($is_dashboard) {
                echo '<a class="nav-link" href="#" data-bs-toggle="offcanvas" data-bs-target="#orderLabModal"><i class="bi bi-clipboard-plus"></i>' . sanitize(t('order_lab_test')) . '</a>';
            } else {
                echo '<a class="nav-link" href="/hms/public/dashboard-doctor.php?order=1"><i class="bi bi-clipboard-plus"></i>' . sanitize(t('order_lab_test')) . '</a>';
            }
            ?>
			<a class="nav-link mt-auto" href="/hms/public/logout.php"><i class="bi bi-box-arrow-right"></i><?php echo t('logout'); ?></a>
		</nav>
	</div>

	<nav class="top-navbar d-flex justify-content-between align-items-center">
		<div>
			<strong><?php echo sanitize($env['app_name']); ?></strong>
			<span class="ms-2 text-warning text-uppercase small">Doctor Workspace</span>
		</div>
		<div class="d-flex align-items-center gap-3">
			<span class="fw-semibold"><?php echo sanitize(current_user_name()); ?></span>
			<a class="btn btn-outline-light btn-sm" href="/hms/public/account/password.php"><?php echo t('change_password'); ?></a>
		</div>
	</nav>

	<div class="main-content">

