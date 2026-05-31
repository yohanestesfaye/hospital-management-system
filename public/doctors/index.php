<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$is_admin = current_account_type() === 'Admin';

// Ensure license_number column exists
try {
    $exists = $db->query("SHOW COLUMNS FROM doctors LIKE 'license_number'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE doctors ADD COLUMN license_number VARCHAR(100) NULL"); }
} catch (Throwable $e) {}

try {
    $exists = $db->query("SHOW COLUMNS FROM users LIKE 'doctor_id'")->fetch() !== false;
    if (!$exists) { $db->exec("ALTER TABLE users ADD COLUMN doctor_id INT NULL"); }
} catch (Throwable $e) {}

// Handle actions: create, update, delete (Admin only)
$errors = [];
$notice = '';
$reassign_id = 0;
$block_counts = ['appointments'=>0,'prescriptions'=>0];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    $action = post('action');
    if ($action === 'create') {
        $first_name = trim((string)post('first_name'));
        $last_name = trim((string)post('last_name'));
        $specialty = trim((string)post('specialty'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $license_number = trim((string)post('license_number'));
        $username = trim((string)post('username'));
        $password = (string)post('password');

        if ($first_name === '') { $errors[] = 'First name is required.'; }
        if ($last_name === '') { $errors[] = 'Last name is required.'; }
        if ($specialty === '') { $errors[] = 'Specialty is required.'; }

        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO doctors(first_name, last_name, specialty, phone, email, license_number) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$first_name, $last_name, $specialty, $phone, $email, $license_number]);
            $doctor_id = (int)$db->lastInsertId();

            if ($username !== '' && $password !== '') {
                $st = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
                $st->execute([$username]);
                if ((int)$st->fetch()['c'] > 0) {
                    $errors[] = 'Username already exists.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $display_name = trim($first_name . ' ' . $last_name);
                    $ins = $db->prepare('INSERT INTO users(username, name, password_hash, account_type, doctor_id) VALUES (?,?,?,?,?)');
                    $ins->execute([$username, $display_name, $hash, 'Doctor', $doctor_id]);
                }
            }

            if (!$errors) { redirect('/hms/public/doctors/index.php?msg=created'); }
        }
    } elseif ($action === 'update') {
        $id = (int)post('id');
        $first_name = trim((string)post('first_name'));
        $last_name = trim((string)post('last_name'));
        $specialty = trim((string)post('specialty'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $license_number = trim((string)post('license_number'));

        if ($first_name === '') { $errors[] = 'First name is required.'; }
        if ($last_name === '') { $errors[] = 'Last name is required.'; }
        if ($specialty === '') { $errors[] = 'Specialty is required.'; }

        if (!$errors) {
            $stmt = $db->prepare('UPDATE doctors SET first_name = ?, last_name = ?, specialty = ?, phone = ?, email = ?, license_number = ? WHERE id = ?');
            $stmt->execute([$first_name, $last_name, $specialty, $phone, $email, $license_number, $id]);
            redirect('/hms/public/doctors/index.php?msg=updated');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $blockers = [];
        try {
            $st = $db->prepare('SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ?');
            $st->execute([$id]);
            $c = (int)$st->fetch()['c'];
            if ($c > 0) { $blockers[] = 'appointments (' . $c . ')'; }
        } catch (Throwable $e) {}
        try {
            $st = $db->prepare('SELECT COUNT(*) AS c FROM prescriptions WHERE doctor_id = ?');
            $st->execute([$id]);
            $c = (int)$st->fetch()['c'];
            if ($c > 0) { $blockers[] = 'prescriptions (' . $c . ')'; }
        } catch (Throwable $e) {}

        if ($blockers) {
            $errors[] = 'Cannot delete doctor: related ' . implode(', ', $blockers) . ' exist. Reassign or remove these records first.';
            $reassign_id = $id;
            foreach ($blockers as $b) {
                if (strpos($b,'appointments')!==false) { $block_counts['appointments'] = (int)filter_var($b, FILTER_SANITIZE_NUMBER_INT); }
                if (strpos($b,'prescriptions')!==false) { $block_counts['prescriptions'] = (int)filter_var($b, FILTER_SANITIZE_NUMBER_INT); }
            }
        } else {
            try {
                $stmt = $db->prepare('DELETE FROM doctors WHERE id = ?');
                $stmt->execute([$id]);
                try { $db->prepare('DELETE FROM users WHERE account_type = ? AND doctor_id = ?')->execute(['Doctor',$id]); } catch (Throwable $e) {}
                redirect('/hms/public/doctors/index.php?msg=deleted');
            } catch (Throwable $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reassign_delete') {
        $id = (int)post('id');
        $new_doctor_id = (int)post('new_doctor_id');
        if (!$id || !$new_doctor_id || $id === $new_doctor_id) { $errors[] = 'Select a different doctor to reassign to.'; }
        if (!$errors) {
            try {
                $db->prepare('UPDATE appointments SET doctor_id = ? WHERE doctor_id = ?')->execute([$new_doctor_id, $id]);
            } catch (Throwable $e) { $errors[] = 'Failed to reassign appointments: ' . $e->getMessage(); }
            try {
                $db->prepare('UPDATE prescriptions SET doctor_id = ? WHERE doctor_id = ?')->execute([$new_doctor_id, $id]);
            } catch (Throwable $e) { $errors[] = 'Failed to reassign prescriptions: ' . $e->getMessage(); }
        }
        if (!$errors) {
            try {
                $db->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
                try { $db->prepare('DELETE FROM users WHERE account_type = ? AND doctor_id = ?')->execute(['Doctor',$id]); } catch (Throwable $e) {}
                redirect('/hms/public/doctors/index.php?msg=reassigned_deleted');
            } catch (Throwable $e) { $errors[] = 'Delete failed: ' . $e->getMessage(); }
        } else {
            $reassign_id = $id;
        }
    } elseif ($action === 'create_login') {
        $id = (int)post('id');
        $username = trim((string)post('username'));
        $password = (string)post('password');
        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($password === '') { $errors[] = 'Password is required.'; }
        if (!$errors) {
            $st = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
            $st->execute([$username]);
            if ((int)$st->fetch()['c'] > 0) {
                $errors[] = 'Username already exists.';
            }
        }
        if (!$errors) {
            $st2 = $db->prepare('SELECT first_name, last_name FROM doctors WHERE id = ?');
            $st2->execute([$id]);
            $dr = $st2->fetch();
            if ($dr) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $display_name = trim(($dr['first_name'] ?? '') . ' ' . ($dr['last_name'] ?? ''));
                $ins = $db->prepare('INSERT INTO users(username, name, password_hash, account_type, doctor_id) VALUES (?,?,?,?,?)');
                $ins->execute([$username, $display_name, $hash, 'Doctor', $id]);
                redirect('/hms/public/doctors/index.php?edit='.(int)$id.'&msg=login_created');
            } else {
                $errors[] = 'Doctor not found.';
            }
        }
    }
}

// Read params
$q = (string)get('q', '');
$limit = (int)get('limit', 10);
if (!in_array($limit, [10,25,50,100], true)) { $limit = 10; }
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Queries
$where = '';
$params = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = 'WHERE first_name LIKE ? OR last_name LIKE ? OR specialty LIKE ?';
    $params = [$like, $like, $like];
}

$count_sql = 'SELECT COUNT(*) AS c FROM doctors ' . $where;
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$list_sql = 'SELECT * FROM doctors ' . $where . ' ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($list_sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

$edit_id = (int)get('edit', 0);
$edit_row = null;
if ($edit_id && $is_admin) {
    $st = $db->prepare('SELECT * FROM doctors WHERE id = ?');
    $st->execute([$edit_id]);
    $edit_row = $st->fetch();
}

include __DIR__ . '/../../includes/admin-header.php';
?>
	<h2 class="mb-4">Manage Doctor</h2>

	<?php if (get('msg') === 'created') { ?>
		<div class="alert alert-success">Doctor created successfully.</div>
	<?php } elseif (get('msg') === 'updated') { ?>
		<div class="alert alert-success">Doctor updated successfully.</div>
	<?php } elseif (get('msg') === 'deleted') { ?>
		<div class="alert alert-warning">Doctor deleted.</div>
	<?php } ?>
<?php if ($errors) { ?>
        <div class="alert alert-danger mb-3">
            <?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
        </div>
        <?php if ($reassign_id) { 
            $doctor_choices = [];
            try { $doctor_choices = $db->query('SELECT id, CONCAT(first_name, " ", last_name) AS name FROM doctors WHERE id <> ' . (int)$reassign_id . ' ORDER BY first_name, last_name')->fetchAll(); } catch (Throwable $e) { $doctor_choices = []; }
        ?>
        <div class="card card-body border-warning mb-3">
            <div class="fw-semibold mb-2">Reassign records to another doctor, then delete</div>
            <div class="small text-muted mb-2">Appointments: <?php echo (int)$block_counts['appointments']; ?>, Prescriptions: <?php echo (int)$block_counts['prescriptions']; ?></div>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="reassign_delete">
                <input type="hidden" name="id" value="<?php echo (int)$reassign_id; ?>">
                <div class="col-sm-6">
                    <label class="form-label">Reassign To</label>
                    <select name="new_doctor_id" class="form-select" required>
                        <option value="">Select doctor</option>
                        <?php foreach ($doctor_choices as $dc) { ?>
                            <option value="<?php echo (int)$dc['id']; ?>"><?php echo sanitize($dc['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-sm-6">
                    <button class="btn btn-warning">Reassign & Delete</button>
                </div>
            </form>
        </div>
        <?php } ?>
    <?php } ?>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">Doctor List</button>
		</li>
		<?php if ($is_admin) { ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-add" type="button" role="tab">Add Doctor</button>
		</li>
		<?php } ?>
		<?php if ($edit_row) { ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit" type="button" role="tab">Edit</button>
		</li>
		<?php } ?>
	</ul>

	<div class="tab-content">
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
				<?php if ($is_admin) { ?>
					<a href="#tab-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Doctor</a>
				<?php } ?>
			</div>

			<div class="table-responsive">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th style="width:70px">#</th>
							<th>Doctor Name</th>
							<th>Department</th>
							<th style="width:140px">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php $i = $offset + 1; foreach ($doctors as $d) { ?>
						<tr>
							<td><?php echo $i++; ?></td>
							<td><?php echo sanitize($d['first_name'] . ' ' . $d['last_name']); ?></td>
							<td><?php echo sanitize($d['specialty']); ?></td>
							<td>
								<?php if ($is_admin) { ?>
									<a class="btn btn-sm btn-outline-primary" href="/hms/public/doctors/index.php?edit=<?php echo (int)$d['id']; ?>"><i class="bi bi-pencil"></i></a>
									<form action="/hms/public/doctors/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this doctor?');">
										<input type="hidden" name="action" value="delete">
										<input type="hidden" name="id" value="<?php echo (int)$d['id']; ?>">
										<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
									</form>
								<?php } else { ?>
									<span class="text-muted small">View only</span>
								<?php } ?>
							</td>
						</tr>
						<?php } ?>
						<?php if (!$doctors) { ?>
						<tr><td colspan="4" class="text-center text-muted">No doctors found</td></tr>
						<?php } ?>
					</tbody>
				</table>
			</div>

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

		<?php if ($is_admin) { ?>
		<div class="tab-pane fade" id="tab-add" role="tabpanel">
        <div class="card">
            <div class="card-header">Add Doctor</div>
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
                        <label class="form-label">Specialty</label>
                        <input type="text" class="form-control" name="specialty" required>
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
                        <label class="form-label">License Number</label>
                        <input type="text" class="form-control" name="license_number">
                    </div>
                    <hr class="my-3">
                    <div class="mb-2 fw-semibold">Doctor Login (optional)</div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="doctor username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="set password">
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
            <div class="card-header">Edit Doctor</div>
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
							<label class="form-label">Specialty</label>
							<input type="text" class="form-control" name="specialty" value="<?php echo sanitize($edit_row['specialty']); ?>" required>
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
							<label class="form-label">License Number</label>
							<input type="text" class="form-control" name="license_number" value="<?php echo sanitize($edit_row['license_number']); ?>">
						</div>
                        <button class="btn btn-primary">Update</button>
                        <a href="/hms/public/doctors/index.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                    <?php
                        $user_row = null;
                        try {
                            $st = $db->prepare('SELECT id, username, name FROM users WHERE account_type = ? AND doctor_id = ? LIMIT 1');
                            $st->execute(['Doctor', (int)$edit_row['id']]);
                            $user_row = $st->fetch();
                        } catch (Throwable $e) {}
                    ?>
                    <hr class="my-4">
                    <div class="mb-2 fw-semibold">Doctor Login</div>
                    <?php if ($user_row) { ?>
                        <div class="alert alert-info">Login exists: <strong><?php echo sanitize($user_row['username']); ?></strong></div>
                    <?php } else { ?>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="create_login">
                            <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
                            <div class="col-md-4">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                            <div class="col-md-4 align-self-end">
                                <button class="btn btn-success">Create Login</button>
                            </div>
                        </form>
                    <?php } ?>
            </div>
        </div>
        </div>
        <?php } ?>
    </div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
