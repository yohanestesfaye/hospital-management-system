<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Receptionist');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

try {
    $exists = $db->query("SHOW COLUMNS FROM patients LIKE 'medical_history'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE patients ADD COLUMN medical_history TEXT NULL"); }
} catch (Throwable $e) {}

try {
    $exists = $db->query("SHOW COLUMNS FROM patients LIKE 'patient_code'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE patients ADD COLUMN patient_code VARCHAR(32) NULL"); }
} catch (Throwable $e) {}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$first_name = post('first_name');
	$last_name = post('last_name');
	$gender = post('gender');
	$dob = post('dob');
	$phone = post('phone');
	$email = post('email');
    $address = post('address');
    $medical_history = post('medical_history');

	if (!$first_name) { $errors[] = 'First name is required'; }
	if (!$last_name) { $errors[] = 'Last name is required'; }
	if (!$gender) { $errors[] = 'Gender is required'; }
	if ($gender && !in_array($gender, ['Male','Female'], true)) { $errors[] = 'Gender must be Male or Female'; }
	if (!$dob) { $errors[] = 'Date of birth is required'; }

if (!$errors) {
        $stmt = $db->prepare('INSERT INTO patients(first_name,last_name,gender,dob,phone,email,address,medical_history) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$first_name,$last_name,$gender,$dob,$phone,$email,$address,$medical_history]);
        $new_id = (int)$db->lastInsertId();
        try {
            $code = sprintf('%04d', $new_id);
            $upd = $db->prepare('UPDATE patients SET patient_code = ? WHERE id = ?');
            $upd->execute([$code, $new_id]);
        } catch (Throwable $e) {}
        redirect('/hms/public/patients/create.php?created=1&id=' . $new_id);
}
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center">
            <h1 class="h6 mb-0">Add Patient</h1>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/patients/index.php"><i class="bi bi-list"></i> Patient List</a>
        </div>
        <div class="card-body">
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
				<label class="form-label form-required">First Name</label>
				<input type="text" name="first_name" class="form-control" required>
			</div>
			<div class="col-md-6">
				<label class="form-label form-required">Last Name</label>
				<input type="text" name="last_name" class="form-control" required>
			</div>
			<div class="col-md-4">
				<label class="form-label form-required">Gender</label>
				<select name="gender" class="form-select" required>
					<option value="">Select</option>
					<option>Male</option>
					<option>Female</option>
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label form-required">Date of Birth</label>
				<input type="date" name="dob" class="form-control" required>
			</div>
			<div class="col-md-4">
				<label class="form-label">Phone</label>
				<input type="text" name="phone" class="form-control">
			</div>
			<div class="col-md-6">
				<label class="form-label">Email</label>
				<input type="email" name="email" class="form-control">
			</div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Medical History</label>
                <textarea name="medical_history" rows="4" class="form-control" placeholder="Allergies, chronic conditions, previous treatments"></textarea>
            </div>
			<div class="col-12">
				<button class="btn btn-primary">Save</button>
				<a class="btn btn-secondary" href="/hms/public/patients/index.php">Cancel</a>
			</div>
        </div>
    </form>
        </div>
    </div>
</div>
<?php $created = get('created') === '1'; if ($created) { ?>
<div class="modal fade" id="patientCreatedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center p-4">
            <div class="display-4 text-success mb-2"><i class="bi bi-check-circle"></i></div>
            <h3 class="mb-1">Success</h3>
            <p class="text-muted mb-3">Patient Details Added</p>
            <div class="d-grid gap-2">
                <a href="/hms/public/patients/index.php" class="btn btn-primary">OK</a>
            </div>
        </div>
    </div>
 </div>
 <script>
 document.addEventListener('DOMContentLoaded', function() {
   var el = document.getElementById('patientCreatedModal');
   if (el) { new bootstrap.Modal(el, {backdrop:'static', keyboard:false}).show(); }
 });
 </script>
<?php } ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>


