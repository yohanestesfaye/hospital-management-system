<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Receptionist');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
$message = '';
$appointment = null;
$is_verified = false;

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
	
	if ($appointment) {
		// Check if doctor is assigned to this patient (via appointments)
		$check_stmt = $db->prepare("
			SELECT COUNT(*) AS count 
			FROM appointments 
			WHERE patient_id = ? AND doctor_id = ? AND status = 'Scheduled'
		");
		$check_stmt->execute([$appointment['patient_id'], $appointment['doctor_id']]);
		$result = $check_stmt->fetch();
		$is_verified = ($result['count'] > 0);
		
		if ($is_verified) {
			$message = 'success';
		} else {
			$message = 'error';
		}
	}
} else {
	// Show list of appointments to verify
	$appointments = $db->query("
		SELECT a.*, 
		       CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
		       CONCAT(d.first_name, ' ', d.last_name) AS doctor_name, d.specialty
		FROM appointments a
		JOIN patients p ON a.patient_id = p.id
		JOIN doctors d ON a.doctor_id = d.id
		WHERE DATE(a.appointment_date) = CURDATE() AND a.status = 'Scheduled'
		ORDER BY a.appointment_date ASC
	")->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h4 mb-0">Verify Appointment</h1>
		<a class="btn btn-secondary" href="/hms/public/dashboard-receptionist.php">← Back to Dashboard</a>
	</div>
	
	<?php if ($id && $appointment) { ?>
		<div class="card shadow-sm">
			<div class="card-header bg-primary text-white">
				<h5 class="mb-0">Appointment Verification</h5>
			</div>
			<div class="card-body">
				<div class="row mb-3">
					<div class="col-md-6">
						<h6>Patient Information</h6>
						<p class="mb-1"><strong>Name:</strong> <?php echo sanitize($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></p>
						<p class="mb-1"><strong>Phone:</strong> <?php echo sanitize($appointment['patient_phone'] ?? 'N/A'); ?></p>
						<p class="mb-0"><strong>Patient ID:</strong> <?php echo (int)$appointment['patient_id']; ?></p>
					</div>
					<div class="col-md-6">
						<h6>Doctor Information</h6>
						<p class="mb-1"><strong>Name:</strong> <?php echo sanitize($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></p>
						<p class="mb-1"><strong>Specialty:</strong> <?php echo sanitize($appointment['specialty']); ?></p>
						<p class="mb-0"><strong>Doctor ID:</strong> <?php echo (int)$appointment['doctor_id']; ?></p>
					</div>
				</div>
				
				<div class="row mb-3">
					<div class="col-md-6">
						<h6>Appointment Details</h6>
						<p class="mb-1"><strong>Date & Time:</strong> <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></p>
						<p class="mb-0"><strong>Status:</strong> <span class="badge bg-primary"><?php echo sanitize($appointment['status']); ?></span></p>
					</div>
				</div>
				
				<hr>
				
				<?php if ($is_verified) { ?>
					<div class="alert alert-success">
						<h5 class="alert-heading">✅ Verification Successful!</h5>
						<p class="mb-0">The doctor <strong><?php echo sanitize($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></strong> is assigned to patient <strong><?php echo sanitize($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></strong> for this appointment.</p>
					</div>
					<div class="d-flex gap-2">
						<a href="/hms/public/appointments/direct.php?id=<?php echo (int)$id; ?>" class="btn btn-success">Direct Patient</a>
						<a href="/hms/public/appointments/verify.php" class="btn btn-secondary">Verify Another</a>
					</div>
				<?php } else { ?>
					<div class="alert alert-danger">
						<h5 class="alert-heading">❌ Verification Failed</h5>
						<p class="mb-0">The doctor is not properly assigned to this patient. Please check the appointment details or contact the administrator.</p>
					</div>
					<div class="d-flex gap-2">
						<a href="/hms/public/appointments/edit.php?id=<?php echo (int)$id; ?>" class="btn btn-warning">Edit Appointment</a>
						<a href="/hms/public/appointments/verify.php" class="btn btn-secondary">Verify Another</a>
					</div>
				<?php } ?>
			</div>
		</div>
	<?php } else { ?>
		<div class="card shadow-sm">
			<div class="card-header bg-primary text-white">
				<h5 class="mb-0">Today's Appointments - Select to Verify</h5>
			</div>
			<div class="card-body">
				<?php if (count($appointments) > 0) { ?>
					<div class="table-responsive">
						<table class="table table-hover">
							<thead>
								<tr>
									<th>Time</th>
									<th>Patient</th>
									<th>Doctor</th>
									<th>Specialty</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($appointments as $apt) { ?>
									<tr>
										<td><?php echo date('H:i', strtotime($apt['appointment_date'])); ?></td>
										<td><?php echo sanitize($apt['patient_name']); ?></td>
										<td><?php echo sanitize($apt['doctor_name']); ?></td>
										<td><?php echo sanitize($apt['specialty']); ?></td>
										<td>
											<a href="/hms/public/appointments/verify.php?id=<?php echo (int)$apt['id']; ?>" class="btn btn-sm btn-primary">Verify</a>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } else { ?>
					<p class="text-muted mb-0">No appointments scheduled for today.</p>
				<?php } ?>
			</div>
		</div>
	<?php } ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

