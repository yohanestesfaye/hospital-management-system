<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

// Get statistics for all roles
$stats = [
	'doctors' => (int)$db->query('SELECT COUNT(*) AS c FROM doctors')->fetch()['c'],
	'patients' => (int)$db->query('SELECT COUNT(*) AS c FROM patients')->fetch()['c'],
	'nurses' => (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE account_type = 'Nurse'")->fetch()['c'],
	'pharmacists' => (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE account_type = 'Pharmacist'")->fetch()['c'],
	'laboratorists' => (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE account_type = 'Laboratorist'")->fetch()['c'],
	'accountants' => (int)$db->query("SELECT COUNT(*) AS c FROM users WHERE account_type = 'Accountant'")->fetch()['c'],
	'appointments_today' => (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch()['c'],
	'appointments_total' => (int)$db->query('SELECT COUNT(*) AS c FROM appointments')->fetch()['c'],
];

// Noticeboard data (based on reference logic)
$notices = [];
try {
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute(['noticeboard']);
    $exists = (int)$stmt->fetch()['c'] > 0;
    if ($exists) {
        $q = $db->query('SELECT notice_id, notice_title, notice, create_timestamp FROM noticeboard ORDER BY create_timestamp DESC LIMIT 10');
        $notices = $q->fetchAll();
    }
} catch (Throwable $e) {
    $notices = [];
}

// Calendar data for current month
$now = new DateTime('now');
$month = (int)$now->format('n');
$year = (int)$now->format('Y');
$month_name = $now->format('F');
$days_in_month = (int)cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day_w = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month))); // 0=Sun

include __DIR__ . '/../includes/admin-header.php';
?>
		<h2 class="mb-4">Admin Dashboard</h2>
		
		<!-- Summary Statistics -->
		<div class="row g-3 mb-4">
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['doctors']; ?></div>
					<div class="label">Doctor</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['patients']; ?></div>
					<div class="label">Patient</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['nurses']; ?></div>
					<div class="label">Nurse</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['pharmacists']; ?></div>
					<div class="label">Pharmacist</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['laboratorists']; ?></div>
					<div class="label">Laboratorist</div>
				</div>
			</div>
			<div class="col-md-2">
				<div class="stat-card">
					<div class="number"><?php echo $stats['accountants']; ?></div>
					<div class="label">Accountant</div>
				</div>
			</div>
		</div>

		<!-- Functional Modules Grid -->
		<div class="module-grid">
			<a href="/hms/public/departments/index.php" class="module-tile">
				<i class="bi bi-building"></i>
				<div>Department</div>
			</a>
			<a href="/hms/public/doctors/index.php" class="module-tile">
				<i class="bi bi-person-badge"></i>
				<div>Doctor</div>
			</a>
			<a href="/hms/public/patients/index.php" class="module-tile">
				<i class="bi bi-person"></i>
				<div>Patient</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-heart-pulse"></i>
				<div>Nurse</div>
			</a>
			<a href="/hms/public/pharmacy/vendors/index.php" class="module-tile">
				<i class="bi bi-truck"></i>
				<div>Pharmacy Vendors</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-flask"></i>
				<div>Laboratorist</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-calculator"></i>
				<div>Accountant</div>
			</a>
			<a href="/hms/public/appointments/index.php" class="module-tile">
				<i class="bi bi-arrow-left-right"></i>
				<div>Appointment</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-building"></i>
				<div>Payment</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-droplet"></i>
				<div>Blood Bank</div>
			</a>
			<a href="/hms/public/pharmacy/categories/index.php" class="module-tile">
				<i class="bi bi-tags"></i>
				<div>Pharm Categories</div>
			</a>
			<a href="/hms/public/pharmacy/products/index.php" class="module-tile">
				<i class="bi bi-capsule"></i>
				<div>Pharmaceuticals</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-clipboard"></i>
				<div>Noticeboard</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-gear"></i>
				<div>Settings</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-globe"></i>
				<div>Language</div>
			</a>
			<a href="#" class="module-tile">
				<i class="bi bi-download"></i>
				<div>Backup</div>
			</a>
		</div>

		<!-- Pharmacy Quick Actions -->
		<div class="card shadow-sm mt-3">
			<div class="card-header bg-light d-flex align-items-center">
				<h5 class="mb-0">Pharmacy Admin</h5>
			</div>
			<div class="card-body">
				<div class="d-flex flex-wrap gap-2">
					<a class="btn btn-primary" href="/hms/public/pharmacy/categories/create.php"><i class="bi bi-plus-lg"></i> Add Pharm Category</a>
					<a class="btn btn-outline-primary" href="/hms/public/pharmacy/categories/index.php"><i class="bi bi-list"></i> View Categories</a>
					<a class="btn btn-primary" href="/hms/public/pharmacy/products/create.php"><i class="bi bi-plus-lg"></i> Add Pharmaceutical</a>
					<a class="btn btn-outline-primary" href="/hms/public/pharmacy/products/index.php"><i class="bi bi-list"></i> View Pharmaceuticals</a>
					<a class="btn btn-outline-secondary" href="/hms/public/pharmacy/vendors/create.php"><i class="bi bi-building"></i> Add Vendor</a>
					<a class="btn btn-outline-secondary" href="/hms/public/pharmacy/vendors/index.php"><i class="bi bi-building"></i> View Vendors</a>
				</div>
			</div>
		</div>

		<!-- Bottom Section: Calendar and Noticeboard -->
		<div class="row g-3 mt-3">
			<div class="col-md-6">
				<div class="calendar-section">
					<h5 class="mb-3">Calendar Schedule</h5>
					<div class="calendar-header">
						<div class="calendar-nav">
							<button class="btn btn-sm btn-outline-secondary">month</button>
							<button class="btn btn-sm btn-outline-secondary">week</button>
							<button class="btn btn-sm btn-outline-secondary">day</button>
						</div>
						<div class="d-flex align-items-center gap-2">
							<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i></button>
							<span><strong><?php echo sanitize($month_name . ' ' . $year); ?></strong></span>
							<button class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-right"></i></button>
						</div>
					</div>
					<div class="calendar-grid">
						<div class="calendar-day header">Sun</div>
						<div class="calendar-day header">Mon</div>
						<div class="calendar-day header">Tue</div>
						<div class="calendar-day header">Wed</div>
						<div class="calendar-day header">Thu</div>
						<div class="calendar-day header">Fri</div>
						<div class="calendar-day header">Sat</div>
						<?php
						for ($i = 0; $i < $first_day_w; $i++) {
							echo '<div class="calendar-day"></div>';
						}
						for ($d = 1; $d <= $days_in_month; $d++) {
							echo '<div class="calendar-day">' . $d . '</div>';
						}
						?>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="noticeboard-section">
					<h5 class="mb-3">Noticeboard</h5>
					<div style="max-height: 400px; overflow-y: auto;">
						<?php if (!empty($notices)) { foreach ($notices as $n) { ?>
							<div class="notice-item">
								<i class="bi bi-info-circle"></i>
								<div>
									<div><strong><?php echo sanitize($n['notice_title'] ?? ''); ?></strong></div>
									<div class="text-muted small">
										<?php echo sanitize($n['notice'] ?? ''); ?>
										<?php
										if (isset($n['create_timestamp'])) {
											$ts = is_numeric($n['create_timestamp']) ? (int)$n['create_timestamp'] : strtotime((string)$n['create_timestamp']);
											if ($ts) { echo ' · ' . sanitize(date('M d, Y', $ts)); }
										}
										?>
									</div>
								</div>
							</div>
						<?php } } else { ?>
							<div class="text-muted">No notices available.</div>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
