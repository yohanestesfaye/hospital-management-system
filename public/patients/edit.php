<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Receptionist');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

try {
    $exists = $db->query("SHOW COLUMNS FROM patients LIKE 'medical_history'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE patients ADD COLUMN medical_history TEXT NULL"); }
} catch (Throwable $e) {}

$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) {
	redirect('/hms/public/patients/index.php');
}

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
        $stmt = $db->prepare('UPDATE patients SET first_name=?, last_name=?, gender=?, dob=?, phone=?, email=?, address=?, medical_history=? WHERE id=?');
        $stmt->execute([$first_name,$last_name,$gender,$dob,$phone,$email,$address,$medical_history,$id]);
		redirect('/hms/public/patients/index.php');
	}
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center">
            <h1 class="h6 mb-0">Edit Patient</h1>
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
				<input type="text" name="first_name" class="form-control" value="<?php echo sanitize($patient['first_name']); ?>" required>
			</div>
			<div class="col-md-6">
				<label class="form-label form-required">Last Name</label>
				<input type="text" name="last_name" class="form-control" value="<?php echo sanitize($patient['last_name']); ?>" required>
			</div>
			<div class="col-md-4">
				<label class="form-label form-required">Gender</label>
				<select name="gender" class="form-select" required>
					<option <?php echo $patient['gender']==='Male'?'selected':''; ?>>Male</option>
					<option <?php echo $patient['gender']==='Female'?'selected':''; ?>>Female</option>
				</select>
			</div>
			<div class="col-md-4">
				<label class="form-label form-required">Date of Birth</label>
				<input type="date" name="dob" class="form-control" value="<?php echo sanitize($patient['dob']); ?>" required>
			</div>
			<div class="col-md-4">
				<label class="form-label">Phone</label>
				<input type="text" name="phone" class="form-control" value="<?php echo sanitize($patient['phone']); ?>">
			</div>
			<div class="col-md-6">
				<label class="form-label">Email</label>
				<input type="email" name="email" class="form-control" value="<?php echo sanitize($patient['email']); ?>">
			</div>
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo sanitize($patient['address']); ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Medical History</label>
                <textarea name="medical_history" rows="4" class="form-control"><?php echo sanitize(isset($patient['medical_history']) ? $patient['medical_history'] : ''); ?></textarea>
            </div>
			<div class="col-12">
				<button class="btn btn-primary">Update</button>
				<a class="btn btn-secondary" href="/hms/public/patients/index.php">Cancel</a>
			</div>
        </div>
    </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>


