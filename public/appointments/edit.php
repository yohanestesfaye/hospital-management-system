<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Receptionist','Doctor']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();
$doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name, specialty FROM doctors ORDER BY first_name, last_name")->fetchAll();

$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM appointments WHERE id = ?');
$stmt->execute([$id]);
$appointment = $stmt->fetch();
if (!$appointment) {
	redirect('/hms/public/appointments/index.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$patient_id = (int)post('patient_id');
	$doctor_id = (int)post('doctor_id');
	$appointment_date = post('appointment_date');
	$status = post('status');
	$notes = post('notes');

	if (!$patient_id) { $errors[] = 'Patient is required'; }
	if (!$doctor_id) { $errors[] = 'Doctor is required'; }
	if (!$appointment_date) { $errors[] = 'Date and time are required'; }

if (!$errors) {
    $role = current_account_type();
    if ($role === 'Admin') {
        $stmt = $db->prepare('UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, status=?, notes=? WHERE id=?');
        $stmt->execute([$patient_id,$doctor_id,$appointment_date,$status,$notes,$id]);
    } elseif ($role === 'Receptionist') {
        // Receptionist can edit core details but not status
        $status = $appointment['status'];
        $stmt = $db->prepare('UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, status=?, notes=? WHERE id=?');
        $stmt->execute([$patient_id,$doctor_id,$appointment_date,$status,$notes,$id]);
    } else { // Doctor
        // Doctor can only update status (approve/cancel/complete) and notes
        $patient_id = $appointment['patient_id'];
        $doctor_id = $appointment['doctor_id'];
        $appointment_date = $appointment['appointment_date'];
        // Limit status transitions for doctor
        $allowed = ['Scheduled','Cancelled','Completed'];
        if (!in_array($status, $allowed, true)) { $status = $appointment['status']; }
        $stmt = $db->prepare('UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, status=?, notes=? WHERE id=?');
        $stmt->execute([$patient_id,$doctor_id,$appointment_date,$status,$notes,$id]);
    }
    redirect('/hms/public/appointments/index.php');
}
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h1 class="h4 mb-0">Edit Appointment</h1>
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
            <?php if (current_account_type() !== 'Doctor') { ?>
            <div class="col-md-6">
                <label class="form-label form-required">Patient</label>
                <select name="patient_id" class="form-select" required>
                    <?php foreach ($patients as $p) { ?>
                        <option value="<?php echo (int)$p['id']; ?>" <?php echo $appointment['patient_id']==$p['id']?'selected':''; ?>><?php echo sanitize($p['name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label form-required">Doctor</label>
                <select name="doctor_id" class="form-select" required>
                    <?php foreach ($doctors as $d) { ?>
                        <option value="<?php echo (int)$d['id']; ?>" <?php echo $appointment['doctor_id']==$d['id']?'selected':''; ?>><?php echo sanitize($d['name'] . ' (' . $d['specialty'] . ')'); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label form-required">Date & Time</label>
                <input type="datetime-local" name="appointment_date" class="form-control" value="<?php echo str_replace(' ', 'T', sanitize($appointment['appointment_date'])); ?>" required>
            </div>
            <?php } ?>

            <?php if (current_account_type() === 'Admin') { ?>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option <?php echo $appointment['status']==='Pending'?'selected':''; ?>>Pending</option>
                    <option <?php echo $appointment['status']==='Scheduled'?'selected':''; ?>>Scheduled</option>
                    <option <?php echo $appointment['status']==='Completed'?'selected':''; ?>>Completed</option>
                    <option <?php echo $appointment['status']==='Cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
            </div>
            <?php } elseif (current_account_type() === 'Doctor') { ?>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option <?php echo $appointment['status']==='Scheduled'?'selected':''; ?>>Scheduled</option>
                    <option <?php echo $appointment['status']==='Cancelled'?'selected':''; ?>>Cancelled</option>
                    <option <?php echo $appointment['status']==='Completed'?'selected':''; ?>>Completed</option>
                </select>
            </div>
            <?php } ?>
			<div class="col-12">
				<label class="form-label">Notes</label>
				<textarea name="notes" rows="3" class="form-control"><?php echo sanitize($appointment['notes']); ?></textarea>
			</div>
			<div class="col-12">
				<button class="btn btn-primary">Update</button>
				<a class="btn btn-secondary" href="/hms/public/appointments/index.php">Cancel</a>
			</div>
		</div>
	</form>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>


