<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Receptionist']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();
$doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name, specialty FROM doctors ORDER BY first_name, last_name")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$patient_id = (int)post('patient_id');
	$doctor_id = (int)post('doctor_id');
	$appointment_date = post('appointment_date');
	$notes = post('notes');

	if (!$patient_id) { $errors[] = 'Patient is required'; }
	if (!$doctor_id) { $errors[] = 'Doctor is required'; }
	if (!$appointment_date) { $errors[] = 'Date and time are required'; }

    if (!$errors) {
        // Check doctor availability: prevent double booking at the exact date-time
        $conflictStmt = $db->prepare('SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ? AND appointment_date = ?');
        $conflictStmt->execute([$doctor_id, $appointment_date]);
        $conflict = (int)$conflictStmt->fetch()['c'];
        if ($conflict > 0) {
            $errors[] = 'Selected doctor is not available at the chosen time.';
        } else {
            // New appointments are Pending until approved by an Admin
            $stmt = $db->prepare('INSERT INTO appointments(patient_id,doctor_id,appointment_date,status,notes) VALUES (?,?,?,?,?)');
            $stmt->execute([$patient_id,$doctor_id,$appointment_date,'Pending',$notes]);
            redirect('/hms/public/appointments/index.php');
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h4 mb-0">Add Appointment</h1>
	</div>
	<?php if ($errors) { ?>
		<div class="alert alert-danger">
			<ul class="mb-0">
				<?php foreach ($errors as $e) { ?><li><?php echo sanitize($e); ?></li><?php } ?>
			</ul>
		</div>
	<?php } ?>
	<form method="post" autocomplete="off">
		<div class="row g-3">
			<div class="col-md-6">
				<label class="form-label form-required">Patient</label>
				<select name="patient_id" class="form-select" required>
					<option value="">Select patient</option>
					<?php foreach ($patients as $p) { ?>
						<option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="col-md-6">
				<label class="form-label form-required">Doctor</label>
				<select name="doctor_id" class="form-select" required>
					<option value="">Select doctor</option>
					<?php foreach ($doctors as $d) { ?>
						<option value="<?php echo (int)$d['id']; ?>"><?php echo sanitize($d['name'] . ' (' . $d['specialty'] . ')'); ?></option>
					<?php } ?>
				</select>
			</div>
			<div class="col-md-6">
				<label class="form-label form-required">Date & Time</label>
				<input type="datetime-local" name="appointment_date" class="form-control" required>
			</div>
			<div class="col-12">
				<label class="form-label">Notes</label>
				<textarea name="notes" rows="3" class="form-control"></textarea>
			</div>
			<div class="col-12">
				<button class="btn btn-primary">Save</button>
				<a class="btn btn-secondary" href="/hms/public/appointments/index.php">Cancel</a>
			</div>
		</div>
	</form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>


