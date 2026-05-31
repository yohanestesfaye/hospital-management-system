<?php
require_once __DIR__ . '/../../includes/auth.php';
// Patients module is staff-only. Exclude Patient role.
require_any_account_type(['Admin','Receptionist']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$can_edit = current_account_type() === 'Receptionist';
$is_admin = current_account_type() === 'Admin';
$can_view_list = $is_admin;

// Ensure medical_history column exists
try {
    $exists = $db->query("SHOW COLUMNS FROM patients LIKE 'medical_history'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE patients ADD COLUMN medical_history TEXT NULL"); }
} catch (Throwable $e) {}

// Ensure patient_code column exists
try {
    $exists = $db->query("SHOW COLUMNS FROM patients LIKE 'patient_code'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE patients ADD COLUMN patient_code VARCHAR(32) NULL"); }
} catch (Throwable $e) {}

// Handle actions: create, update, delete (Admin/Receptionist only)
$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $action = post('action');
    if ($action === 'create') {
        $first_name = trim((string)post('first_name'));
        $last_name = trim((string)post('last_name'));
        $gender = trim((string)post('gender'));
        $dob = trim((string)post('dob'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $address = trim((string)post('address'));
        $medical_history = trim((string)post('medical_history'));

        if ($first_name === '') { $errors[] = 'First name is required.'; }
        if ($last_name === '') { $errors[] = 'Last name is required.'; }
        if ($gender === '') { $errors[] = 'Gender is required.'; }
        if ($gender && !in_array($gender, ['Male','Female'], true)) { $errors[] = 'Gender must be Male or Female'; }
        if ($dob === '') { $errors[] = 'Date of birth is required.'; }

        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO patients(first_name, last_name, gender, dob, phone, email, address, medical_history) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$first_name, $last_name, $gender, $dob, $phone, $email, $address, $medical_history]);
            $new_id = (int)$db->lastInsertId();
            try {
                $code = sprintf('%04d', $new_id);
                $upd = $db->prepare('UPDATE patients SET patient_code = ? WHERE id = ?');
                $upd->execute([$code, $new_id]);
            } catch (Throwable $e) {}
            redirect('/hms/public/patients/index.php?msg=created');
        }
    } elseif ($action === 'update') {
        $id = (int)post('id');
        $first_name = trim((string)post('first_name'));
        $last_name = trim((string)post('last_name'));
        $gender = trim((string)post('gender'));
        $dob = trim((string)post('dob'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $address = trim((string)post('address'));
        $medical_history = trim((string)post('medical_history'));

        if ($first_name === '') { $errors[] = 'First name is required.'; }
        if ($last_name === '') { $errors[] = 'Last name is required.'; }
        if ($gender === '') { $errors[] = 'Gender is required.'; }
        if ($gender && !in_array($gender, ['Male','Female'], true)) { $errors[] = 'Gender must be Male or Female'; }
        if ($dob === '') { $errors[] = 'Date of birth is required.'; }

        if (!$errors) {
            $stmt = $db->prepare('UPDATE patients SET first_name = ?, last_name = ?, gender = ?, dob = ?, phone = ?, email = ?, address = ?, medical_history = ? WHERE id = ?');
            $stmt->execute([$first_name, $last_name, $gender, $dob, $phone, $email, $address, $medical_history, $id]);
            redirect('/hms/public/patients/index.php?msg=updated');
        }
    } elseif ($action === 'delete' && $is_admin) {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM patients WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/patients/index.php?msg=deleted');
    }
}

// Read params
$q = (string)get('q', '');
$limit = (int)get('limit', 10);
if (!in_array($limit, [10,25,50,100], true)) { $limit = 10; }
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Queries (Admin-only list visibility)
$where = '';
$params = [];
$total = 0;
$patients = [];
if ($can_view_list) {
    if ($q !== '') {
        $like = '%' . $q . '%';
        if (ctype_digit(trim($q))) {
            $id = (int)trim($q);
            $where = 'WHERE id = ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?';
            $params = [$id, $like, $like, $like];
        } else {
            $where = 'WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?';
            $params = [$like, $like, $like];
        }
    }

    $count_sql = 'SELECT COUNT(*) AS c FROM patients ' . $where;
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['c'];

    $list_sql = 'SELECT * FROM patients ' . $where . ' ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    $stmt = $db->prepare($list_sql);
    $stmt->execute($params);
    $patients = $stmt->fetchAll();
}

$edit_id = (int)get('edit', 0);
$edit_row = null;
if ($edit_id && $can_edit) {
    $st = $db->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$edit_id]);
    $edit_row = $st->fetch();
}

if ($is_admin) {
    include __DIR__ . '/../../includes/admin-header.php';
} else {
    include __DIR__ . '/../../includes/receptionist-header.php';
}
?>
	<h2 class="mb-4">Manage Patient</h2>

	<?php if (get('msg') === 'created') { ?>
		<div class="alert alert-success">Patient created successfully.</div>
	<?php } elseif (get('msg') === 'updated') { ?>
		<div class="alert alert-success">Patient updated successfully.</div>
	<?php } elseif (get('msg') === 'deleted') { ?>
		<div class="alert alert-warning">Patient deleted.</div>
	<?php } ?>
	<?php if ($errors) { ?>
		<div class="alert alert-danger mb-3">
			<?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
		</div>
	<?php } ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <?php if ($can_view_list) { ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $edit_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">Patient List</button>
        </li>
        <?php } ?>
        <?php if ($can_edit) { ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $edit_row ? '' : ($can_view_list ? '' : 'active'); ?>" data-bs-toggle="tab" data-bs-target="#tab-add" type="button" role="tab">Add Patient</button>
        </li>
        <?php } ?>
        <?php if ($edit_row) { ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit" type="button" role="tab">Edit</button>
        </li>
        <?php } ?>
    </ul>

	<div class="tab-content">
        <?php if ($can_view_list) { ?>
        <div class="tab-pane fade <?php echo $edit_row ? '' : 'show active'; ?>" id="tab-list" role="tabpanel">
			<div class="d-flex justify-content-between align-items-center mb-3">
				<form class="row g-2" method="get">
					<div class="col-auto">
						<input type="text" class="form-control" name="q" placeholder="Search" value="<?php echo sanitize($q); ?>">
					</div>
					<div class="col-auto">
						<select name="limit" class="form-select" onchange="this.form.submit()">
							<?php foreach ([10,25,50,100] as $n) { ?>
								<option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option>
							<?php } ?>
						</select>
					</div>
					<div class="col-auto">
						<button class="btn btn-outline-secondary">Search</button>
					</div>
				</form>
				<?php if ($can_edit) { ?>
					<a href="#tab-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Patient</a>
				<?php } ?>
			</div>

            <div class="table-responsive">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th style="width:70px">#</th>
							<th>Patient Name</th>
							<th>Gender</th>
							<th>DOB</th>
							<th>Phone</th>
							<th style="width:140px">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php $i = $offset + 1; foreach ($patients as $p) { ?>
                        <tr>
                            <td><?php echo sprintf('%04d', (int)$p['id']); ?></td>
                            <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$p['id']; ?>" class="text-decoration-none"><?php echo sanitize($p['first_name'] . ' ' . $p['last_name']); ?></a></td>
                            <td><?php echo sanitize($p['gender']); ?></td>
                            <td><?php echo sanitize($p['dob']); ?></td>
                            <td><?php echo sanitize($p['phone']); ?></td>
							<td>
								<?php if ($can_edit) { ?>
									<a class="btn btn-sm btn-outline-info" href="/hms/public/patients/show.php?id=<?php echo (int)$p['id']; ?>" title="View"><i class="bi bi-eye"></i></a>
									<a class="btn btn-sm btn-outline-primary" href="/hms/public/patients/index.php?edit=<?php echo (int)$p['id']; ?>" title="Edit"><i class="bi bi-pencil"></i></a>
								<?php } ?>
                                <?php if ($can_edit) { ?>
                                    <form action="/hms/public/patients/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this patient? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php } ?>
							</td>
						</tr>
						<?php } ?>
						<?php if (!$patients) { ?>
						<tr><td colspan="6" class="text-center text-muted">No patients found</td></tr>
						<?php } ?>
					</tbody>
				</table>
        </div>
        <?php } ?>

			<?php $total_pages = max(1, (int)ceil($total / $limit)); ?>
			<nav>
				<ul class="pagination">
					<?php for ($p = 1; $p <= $total_pages; $p++) { $active = $p === $page ? 'active' : ''; ?>
						<li class="page-item <?php echo $active; ?>">
							<a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a>
						</li>
					<?php } ?>
				</ul>
			</nav>
		</div>

		<?php if ($can_edit) { ?>
		<div class="tab-pane fade" id="tab-add" role="tabpanel">
			<div class="card">
				<div class="card-header">Add Patient</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="create">
						<div class="mb-3">
							<label class="form-label">First Name</label>
							<input type="text" class="form-control" name="first_name" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Last Name</label>
							<input type="text" class="form-control" name="last_name" required>
						</div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
						<div class="mb-3">
							<label class="form-label">Date of Birth</label>
							<input type="date" class="form-control" name="dob" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Phone</label>
							<input type="text" class="form-control" name="phone">
						</div>
						<div class="mb-3">
							<label class="form-label">Email</label>
							<input type="email" class="form-control" name="email">
						</div>
						<div class="mb-3">
							<label class="form-label">Address</label>
							<input type="text" class="form-control" name="address">
						</div>
						<div class="mb-3">
							<label class="form-label">Medical History</label>
							<textarea class="form-control" name="medical_history" rows="3"></textarea>
						</div>
						<button class="btn btn-success">Create</button>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>

		<?php if ($edit_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Patient</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update">
						<input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">First Name</label>
							<input type="text" class="form-control" name="first_name" value="<?php echo sanitize($edit_row['first_name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Last Name</label>
							<input type="text" class="form-control" name="last_name" value="<?php echo sanitize($edit_row['last_name']); ?>" required>
						</div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male" <?php echo $edit_row['gender']==='Male'?'selected':''; ?>>Male</option>
                                <option value="Female" <?php echo $edit_row['gender']==='Female'?'selected':''; ?>>Female</option>
                            </select>
                        </div>
						<div class="mb-3">
							<label class="form-label">Date of Birth</label>
							<input type="date" class="form-control" name="dob" value="<?php echo sanitize($edit_row['dob']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Phone</label>
							<input type="text" class="form-control" name="phone" value="<?php echo sanitize($edit_row['phone']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Email</label>
							<input type="email" class="form-control" name="email" value="<?php echo sanitize($edit_row['email']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Address</label>
							<input type="text" class="form-control" name="address" value="<?php echo sanitize($edit_row['address']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Medical History</label>
							<textarea class="form-control" name="medical_history" rows="3"><?php echo sanitize($edit_row['medical_history']); ?></textarea>
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/patients/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
