<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Receptionist');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
$message = '';
$appointment = null;

// Check if patient_location column exists
$column_exists = false;
try {
	$stmt = $db->query("SHOW COLUMNS FROM appointments LIKE 'patient_location'");
	$column_exists = $stmt->fetch() !== false;
} catch (PDOException $e) {
	$column_exists = false;
}

if ($id) {
	// Get appointment details
	$stmt = $db->prepare("
		SELECT a.*, 
		       p.id AS patient_id, p.first_name AS patient_first_name, p.last_name AS patient_last_name, p.phone AS patient_phone,
		       d.id AS doctor_id, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, d.specialty
		FROM appointments a
		JOIN patients p ON a.patient_id = p.id
		JOIN doctors d ON a.doctor_id = d.id
		WHERE a.id = ?
	");
	$stmt->execute([$id]);
	$appointment = $stmt->fetch();
	
	if (!$appointment) {
		redirect('/hms/public/appointments/verify.php');
	}
	
	// Set default if column doesn't exist
	if (!$column_exists) {
		$appointment['patient_location'] = 'Waiting';
	}
	
	// Handle form submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (!$column_exists) {
			$message = 'error_column';
		} else {
			$patient_location = post('patient_location');
			
			if (in_array($patient_location, ['Waiting', 'With Doctor'])) {
				try {
					$update_stmt = $db->prepare("UPDATE appointments SET patient_location = ? WHERE id = ?");
					$update_stmt->execute([$patient_location, $id]);
					$message = 'success';
					// Refresh appointment data
					$stmt->execute([$id]);
					$appointment = $stmt->fetch();
				} catch (PDOException $e) {
					$message = 'error';
				}
			} else {
				$message = 'error';
			}
		}
	}
} else {
	redirect('/hms/public/appointments/verify.php');
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h4 mb-0">Direct Patient</h1>
		<a class="btn btn-secondary" href="/hms/public/dashboard-receptionist.php">← Back to Dashboard</a>
	</div>
	
	<?php if ($message === 'success') { ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<strong>Success!</strong> Patient location has been updated.
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php } elseif ($message === 'error') { ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<strong>Error!</strong> Please select a valid location.
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php } elseif ($message === 'error_column') { ?>
		<div class="alert alert-warning alert-dismissible fade show" role="alert">
			<strong>Warning!</strong> The patient_location column is missing from the database. 
			Please run the migration script: <a href="/hms/public/setup/migrate_patient_location.php" class="alert-link">Add patient_location Column</a>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php } ?>
	
	<?php if (!$column_exists) { ?>
		<div class="alert alert-warning">
			<strong>⚠ Database Migration Required:</strong> The patient_location column is missing. 
			Please run the migration script to enable patient direction features: 
			<a href="/hms/public/setup/migrate_patient_location.php" class="btn btn-sm btn-warning">Run Migration</a>
		</div>
	<?php } ?>
	
	<?php if ($appointment) { ?>
		<div class="card shadow-sm">
			<div class="card-header bg-primary text-white">
				<h5 class="mb-0">Direct Patient to Location</h5>
			</div>
			<div class="card-body">
				<div class="row mb-4">
					<div class="col-md-6">
						<h6>Patient Information</h6>
						<p class="mb-1"><strong>Name:</strong> <?php echo sanitize($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></p>
						<p class="mb-1"><strong>Phone:</strong> <?php echo sanitize($appointment['patient_phone'] ?? 'N/A'); ?></p>
					</div>
					<div class="col-md-6">
						<h6>Doctor Information</h6>
						<p class="mb-1"><strong>Name:</strong> <?php echo sanitize($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></p>
						<p class="mb-1"><strong>Specialty:</strong> <?php echo sanitize($appointment['specialty']); ?></p>
					</div>
				</div>
				
				<div class="row mb-4">
					<div class="col-md-12">
						<h6>Appointment Details</h6>
						<p class="mb-1"><strong>Date & Time:</strong> <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></p>
						<p class="mb-1"><strong>Current Location:</strong> 
							<?php if (isset($appointment['patient_location'])) { ?>
								<span class="badge bg-<?php echo $appointment['patient_location'] === 'With Doctor' ? 'success' : 'warning'; ?>">
									<?php echo sanitize($appointment['patient_location']); ?>
								</span>
							<?php } else { ?>
								<span class="badge bg-secondary">N/A</span>
							<?php } ?>
						</p>
					</div>
				</div>
				
				<hr>
				
				<form method="post" <?php echo !$column_exists ? 'onsubmit="alert(\'Please run the migration script first!\'); return false;"' : ''; ?>>
					<div class="mb-3">
						<label class="form-label form-required">Direct Patient To:</label>
						<select name="patient_location" class="form-select" required <?php echo !$column_exists ? 'disabled' : ''; ?>>
							<option value="">Select location...</option>
							<option value="Waiting" <?php echo (isset($appointment['patient_location']) && $appointment['patient_location'] === 'Waiting') ? 'selected' : ''; ?>>Waiting Area</option>
							<option value="With Doctor" <?php echo (isset($appointment['patient_location']) && $appointment['patient_location'] === 'With Doctor') ? 'selected' : ''; ?>>Doctor's Office</option>
						</select>
						<small class="form-text text-muted">Select where to direct the patient based on appointment verification.</small>
					</div>
					
					<div class="alert alert-info">
						<strong>Note:</strong> 
						<ul class="mb-0">
							<li><strong>Waiting Area:</strong> Patient should wait until the doctor is ready.</li>
							<li><strong>Doctor's Office:</strong> Patient can proceed to see the doctor immediately.</li>
						</ul>
					</div>
					
					<div class="d-flex gap-2">
						<button type="submit" class="btn btn-primary">Update Location</button>
						<a href="/hms/public/appointments/verify.php?id=<?php echo (int)$id; ?>" class="btn btn-secondary">Back to Verification</a>
						<a href="/hms/public/dashboard-receptionist.php" class="btn btn-outline-secondary">Dashboard</a>
					</div>
				</form>
			</div>
		</div>
	<?php } ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

