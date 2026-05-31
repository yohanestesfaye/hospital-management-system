<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config.php';
?>
<style>
.topbar-pro{background:linear-gradient(90deg,#0d6efd,#6f42c1);color:#fff;position:sticky;top:0;z-index:1030;box-shadow:0 6px 20px rgba(13,110,253,.25)}
.topbar-pro .navbar-brand{color:#fff;font-weight:700;letter-spacing:.3px}
.topbar-pro .nav-link{color:rgba(255,255,255,.85);padding:.5rem .75rem;border-radius:.5rem}
.topbar-pro .nav-link:hover{color:#fff;background:rgba(255,255,255,.12)}
.topbar-pro .nav-link.active{color:#fff;background:rgba(255,255,255,.18)}
.topbar-pro .nav-icon{font-size:1rem;margin-right:.35rem}
.topbar-pro .btn-outline-light{border-color:rgba(255,255,255,.7);color:#fff}
.topbar-pro .btn-outline-light:hover{background:rgba(255,255,255,.15);color:#fff}
.topbar-pro .user-name{color:#fff;font-weight:600}
.topbar-pro .navbar-toggler{border-color:rgba(255,255,255,.6)}
.topbar-pro .navbar-toggler:focus{box-shadow:0 0 0 .2rem rgba(255,255,255,.25)}
.topbar-pro .navbar-toggler-icon{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(255,255,255,0.9)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E")}
@media (max-width: 576px){
  .topbar-pro .navbar-brand{font-size:1rem}
  .topbar-pro .nav-link{padding:.4rem .6rem}
}
</style>
<nav class="navbar navbar-expand-lg topbar-pro">
	<div class="container-fluid">
		<?php
		$account_type = current_account_type();
		$dashboard_map = [
			'Admin' => 'dashboard-admin.php',
			'Doctor' => 'dashboard-doctor.php',
			'Patient' => 'dashboard-patient.php',
			'Nurse' => 'dashboard-nurse.php',
			'Pharmacist' => 'dashboard-pharmacist.php',
			'Laboratorist' => 'dashboard-laboratorist.php',
			'Accountant' => 'dashboard-accountant.php',
			'Receptionist' => 'dashboard-receptionist.php',
		];
		$dashboard_url = isset($dashboard_map[$account_type]) ? '/hms/public/' . $dashboard_map[$account_type] : '/hms/public/dashboard.php';
		$current_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
		$icons = [
			'Dashboard' => 'bi-speedometer2',
			'My Appointments' => 'bi-calendar-check',
			'My Lab Results' => 'bi-beaker',
			'My Billing' => 'bi-credit-card',
			'Notifications' => 'bi-bell',
			'Staff' => 'bi-people-fill',
			'Patients' => 'bi-people',
			'Doctors' => 'bi-person-badge',
			'Prescriptions' => 'bi-capsule',
			'Lab Results' => 'bi-beaker',
			'Medical Records' => 'bi-journal-medical',
			'Appointments' => 'bi-calendar-check',
			'Book Appointment' => 'bi-calendar-plus',
			'Verify Appointments' => 'bi-check2-circle',
			'Billing' => 'bi-wallet2',
			'Pharmacy Vendors' => 'bi-building',
			'Pharm Categories' => 'bi-tags',
			'Pharmaceuticals' => 'bi-capsule',
			'Profile' => 'bi-person-circle',
		];
		// Define role-based menu items
		$menu_items = [
			[
				'label' => 'Dashboard',
				'href' => $dashboard_url,
				'roles' => ['Admin','Doctor','Patient','Nurse','Pharmacist','Laboratorist','Accountant','Receptionist']
			],
			[
				'label' => 'My Appointments',
				'href' => '/hms/public/patient/appointments.php',
				'roles' => ['Patient']
			],
			[
				'label' => 'My Lab Results',
				'href' => '/hms/public/patient/lab_results.php',
				'roles' => ['Patient']
			],
			[
				'label' => 'My Billing',
				'href' => '/hms/public/patient/billing.php',
				'roles' => ['Patient']
			],
			[
				'label' => 'Notifications',
				'href' => '/hms/public/patient/notifications.php',
				'roles' => ['Patient']
			],
			[
				'label' => 'Staff',
				'href' => '/hms/public/staff/index.php',
				'roles' => ['Admin']
			],
            [
                'label' => 'Patients',
                'href' => '/hms/public/patients/index.php',
                'roles' => ['Admin','Receptionist']
            ],
            [
                'label' => 'Doctors',
                'href' => '/hms/public/doctors/index.php',
                'roles' => ['Admin']
            ],
            [
                'label' => 'Prescriptions',
                'href' => '/hms/public/prescriptions/index.php',
                'roles' => ['Doctor','Pharmacist','Nurse','Admin']
            ],
            [
                'label' => 'Lab Results',
                'href' => '/hms/public/lab_results/index.php',
                'roles' => ['Doctor','Laboratorist','Admin']
            ],
            [
                'label' => 'Medical Records',
                'href' => '/hms/public/medical_records/index.php',
                'roles' => ['Doctor','Nurse','Admin']
            ],
            [
                'label' => 'Appointments',
                'href' => '/hms/public/appointments/index.php',
                'roles' => ['Admin','Doctor','Nurse','Pharmacist','Laboratorist','Accountant','Receptionist']
            ],
			[
				'label' => 'Book Appointment',
				'href' => '/hms/public/appointments/create.php',
				'roles' => ['Admin','Receptionist']
			],
            [
                'label' => 'Verify Appointments',
                'href' => '/hms/public/appointments/verify.php',
                'roles' => ['Receptionist']
            ],
            [
                'label' => 'Billing',
                'href' => '/hms/public/billing/index.php',
                'roles' => ['Accountant']
            ],
            [
                'label' => 'Pharmacy Vendors',
                'href' => '/hms/public/pharmacy/vendors/index.php',
                'roles' => ['Admin']
            ],
            [
                'label' => 'Pharm Categories',
                'href' => '/hms/public/pharmacy/categories/index.php',
                'roles' => ['Admin']
            ],
            [
                'label' => 'Pharmaceuticals',
                'href' => '/hms/public/pharmacy/products/index.php',
                'roles' => ['Admin']
            ],
			[
				'label' => 'Profile',
				'href' => '/hms/public/account/profile.php',
				'roles' => ['Patient','Doctor','Nurse','Pharmacist','Laboratorist','Accountant','Receptionist','Admin']
			],
		];
		?>
		<a class="navbar-brand" href="<?php echo $dashboard_url; ?>">HMS</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav me-auto mb-2 mb-lg-0">
				<?php foreach ($menu_items as $item) { ?>
					<?php if (in_array($account_type, $item['roles'], true)) { $active = ($current_path === parse_url($item['href'], PHP_URL_PATH)) ? 'active' : ''; $icon = isset($icons[$item['label']]) ? $icons[$item['label']] : null; ?>
						<li class="nav-item"><a class="nav-link <?php echo $active; ?>" href="<?php echo $item['href']; ?>"><?php if ($icon) { ?><i class="bi <?php echo $icon; ?> nav-icon"></i><?php } ?><?php echo sanitize($item['label']); ?></a></li>
					<?php } ?>
				<?php } ?>
			</ul>
			<div class="d-flex align-items-center">
				<span class="user-name me-3"><?php echo sanitize(current_user_name()); ?></span>
				<a class="btn btn-outline-light btn-sm me-2" href="/hms/public/account/password.php">Change Password</a>
				<a class="btn btn-outline-light btn-sm" href="/hms/public/logout.php">Logout</a>
			</div>
		</div>
	</div>
</nav>
