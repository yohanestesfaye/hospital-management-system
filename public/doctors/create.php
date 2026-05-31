<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

try {
    $exists = $db->query("SHOW COLUMNS FROM doctors LIKE 'license_number'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE doctors ADD COLUMN license_number VARCHAR(100) NULL"); }
} catch (Throwable $e) {}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$first_name = post('first_name');
	$last_name = post('last_name');
	$specialty = post('specialty');
	$phone = post('phone');
    $email = post('email');
    $license_number = post('license_number');

	if (!$first_name) { $errors[] = 'First name is required'; }
	if (!$last_name) { $errors[] = 'Last name is required'; }
	if (!$specialty) { $errors[] = 'Specialty is required'; }

	if (!$errors) {
        $stmt = $db->prepare('INSERT INTO doctors(first_name,last_name,specialty,phone,email,license_number) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$first_name,$last_name,$specialty,$phone,$email,$license_number]);
		redirect('/hms/public/doctors/index.php');
	}
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center">
            <h1 class="h6 mb-0">Add Doctor</h1>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/doctors/index.php"><i class="bi bi-list"></i> Doctor List</a>
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
			<div class="col-md-6">
				<label class="form-label form-required">Specialty</label>
				<input type="text" name="specialty" class="form-control" required>
			</div>
			<div class="col-md-3">
				<label class="form-label">Phone</label>
				<input type="text" name="phone" class="form-control">
			</div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">License Number</label>
                <input type="text" name="license_number" class="form-control">
            </div>
			<div class="col-12">
				<button class="btn btn-primary">Save</button>
				<a class="btn btn-secondary" href="/hms/public/doctors/index.php">Cancel</a>
			</div>
        </div>
    </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>


