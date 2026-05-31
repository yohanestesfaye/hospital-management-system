<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$allowed_roles = ['Nurse','Receptionist','Pharmacist','Laboratorist'];

// Ensure columns exist
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'license_number'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN license_number VARCHAR(100) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'certification'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN certification VARCHAR(150) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'shift'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN shift VARCHAR(50) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'desk_number'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN desk_number VARCHAR(50) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'specialty_area'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN specialty_area VARCHAR(150) NULL"); } } catch (Throwable $e) {}

// Handle actions: create, update, delete
$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'create') {
        $username = trim((string)post('username'));
        $name = trim((string)post('name'));
        $password = (string)post('password');
        $account_type = (string)post('account_type');
        $license_number = trim((string)post('license_number')) ?: null;
        $certification = trim((string)post('certification')) ?: null;
        $shift = trim((string)post('shift')) ?: null;
        $desk_number = trim((string)post('desk_number')) ?: null;
        $specialty_area = trim((string)post('specialty_area')) ?: null;

        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($name === '') { $errors[] = 'Name is required.'; }
        if ($password === '') { $errors[] = 'Password is required.'; }
        if (!in_array($account_type, $allowed_roles, true)) { $errors[] = 'Invalid role selected.'; }

        // Unique username check
        if (!$errors) {
            $stmt = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ((int)$stmt->fetch()['c'] > 0) {
                $errors[] = 'Username already exists.';
            }
        }

        if (!$errors) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare('INSERT INTO users(username, name, password_hash, account_type, license_number, certification, shift, desk_number, specialty_area) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$username, $name, $hash, $account_type, $license_number, $certification, $shift, $desk_number, $specialty_area]);
            redirect('/hms/public/staff/index.php?msg=created');
        }
    } elseif ($action === 'update') {
        $id = (int)post('id');
        $username = trim((string)post('username'));
        $name = trim((string)post('name'));
        $password = (string)post('password');
        $account_type = (string)post('account_type');
        $license_number = trim((string)post('license_number')) ?: null;
        $certification = trim((string)post('certification')) ?: null;
        $shift = trim((string)post('shift')) ?: null;
        $desk_number = trim((string)post('desk_number')) ?: null;
        $specialty_area = trim((string)post('specialty_area')) ?: null;

        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($name === '') { $errors[] = 'Name is required.'; }
        if (!in_array($account_type, $allowed_roles, true)) { $errors[] = 'Invalid role selected.'; }

        // Unique username check excluding current
        if (!$errors) {
            $stmt = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ? AND id <> ?');
            $stmt->execute([$username, $id]);
            if ((int)$stmt->fetch()['c'] > 0) {
                $errors[] = 'Username already exists.';
            }
        }

        if (!$errors) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('UPDATE users SET username=?, name=?, password_hash=?, account_type=?, license_number=?, certification=?, shift=?, desk_number=?, specialty_area=? WHERE id=?');
                $stmt->execute([$username, $name, $hash, $account_type, $license_number, $certification, $shift, $desk_number, $specialty_area, $id]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username=?, name=?, account_type=?, license_number=?, certification=?, shift=?, desk_number=?, specialty_area=? WHERE id=?');
                $stmt->execute([$username, $name, $account_type, $license_number, $certification, $shift, $desk_number, $specialty_area, $id]);
            }
            redirect('/hms/public/staff/index.php?msg=updated');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/staff/index.php?msg=deleted');
    }
}

// Read params
$q = (string)get('q', '');
$limit = (int)get('limit', 10);
if (!in_array($limit, [10,25,50,100], true)) { $limit = 10; }
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Queries
$placeholders = implode(',', array_fill(0, count($allowed_roles), '?'));
$params = $allowed_roles;
$where = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $where = ' AND (username LIKE ? OR name LIKE ?)';
    $params = array_merge($params, [$like, $like]);
}

$count_sql = "SELECT COUNT(*) AS c FROM users WHERE account_type IN ($placeholders)" . ($where ? $where : '');
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$list_sql = "SELECT id, username, name, account_type FROM users WHERE account_type IN ($placeholders)" . ($where ? $where : '') . " ORDER BY account_type, name LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$stmt = $db->prepare($list_sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$edit_id = (int)get('edit', 0);
$edit_row = null;
if ($edit_id) {
    $st = $db->prepare('SELECT id, username, name, account_type, license_number, certification, shift, desk_number, specialty_area FROM users WHERE id = ?');
    $st->execute([$edit_id]);
    $edit_row = $st->fetch();
    if (!$edit_row || !in_array($edit_row['account_type'], $allowed_roles, true)) {
        $edit_row = null;
    }
}

include __DIR__ . '/../../includes/admin-header.php';
?>
	<h2 class="mb-4">Manage Staff</h2>

	<?php if (get('msg') === 'created') { ?>
		<div class="alert alert-success">Staff created successfully.</div>
	<?php } elseif (get('msg') === 'updated') { ?>
		<div class="alert alert-success">Staff updated successfully.</div>
	<?php } elseif (get('msg') === 'deleted') { ?>
		<div class="alert alert-warning">Staff deleted.</div>
	<?php } ?>
	<?php if ($errors) { ?>
		<div class="alert alert-danger mb-3">
			<?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
		</div>
	<?php } ?>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">Staff List</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-add" type="button" role="tab">Add Staff</button>
		</li>
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
				<a href="#tab-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Staff</a>
			</div>

			<div class="table-responsive">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th style="width:70px">#</th>
							<th>Username</th>
							<th>Name</th>
							<th>Role</th>
							<th style="width:140px">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php $i = $offset + 1; foreach ($users as $u) { ?>
						<tr>
							<td><?php echo $i++; ?></td>
							<td><?php echo sanitize($u['username']); ?></td>
							<td><?php echo sanitize($u['name']); ?></td>
							<td><?php echo sanitize($u['account_type']); ?></td>
							<td>
								<a class="btn btn-sm btn-outline-primary" href="/hms/public/staff/index.php?edit=<?php echo (int)$u['id']; ?>"><i class="bi bi-pencil"></i></a>
								<form action="/hms/public/staff/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this staff account?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
								</form>
							</td>
						</tr>
						<?php } ?>
						<?php if (!$users) { ?>
						<tr><td colspan="5" class="text-center text-muted">No staff found</td></tr>
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

		<div class="tab-pane fade" id="tab-add" role="tabpanel">
			<div class="card">
				<div class="card-header">Add Staff</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="create">
						<div class="mb-3">
							<label class="form-label">Username</label>
							<input type="text" class="form-control" name="username" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" class="form-control" name="name" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Role</label>
							<select name="account_type" class="form-select" required>
								<option value="">Select role</option>
								<?php foreach ($allowed_roles as $role) { ?>
									<option value="<?php echo sanitize($role); ?>"><?php echo sanitize($role); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">Password</label>
							<input type="password" class="form-control" name="password" required>
						</div>
						<div class="mb-3 role-field d-none" data-role="Pharmacist">
							<label class="form-label">License Number</label>
							<input type="text" class="form-control" name="license_number">
						</div>
						<div class="mb-3 role-field d-none" data-role="Nurse">
							<label class="form-label">Certification</label>
							<input type="text" class="form-control" name="certification">
						</div>
						<div class="mb-3 role-field d-none" data-role="Nurse">
							<label class="form-label">Shift</label>
							<input type="text" class="form-control" name="shift" placeholder="Day/Night">
						</div>
						<div class="mb-3 role-field d-none" data-role="Receptionist">
							<label class="form-label">Desk Number</label>
							<input type="text" class="form-control" name="desk_number">
						</div>
						<div class="mb-3 role-field d-none" data-role="Laboratorist">
							<label class="form-label">Specialty Area</label>
							<input type="text" class="form-control" name="specialty_area" placeholder="Hematology, Biochemistry, etc.">
						</div>
						<button class="btn btn-success">Create</button>
					</form>
					<script>
					document.addEventListener('DOMContentLoaded', function(){
						var select = document.querySelector('select[name=account_type]');
						var fields = document.querySelectorAll('.role-field');
						function update(){
							var role = select.value;
							fields.forEach(function(el){ el.classList.toggle('d-none', el.getAttribute('data-role')!==role); });
						}
						select.addEventListener('change', update);
						update();
					});
					</script>
				</div>
			</div>
		</div>

		<?php if ($edit_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Staff</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update">
						<input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Username</label>
							<input type="text" class="form-control" name="username" value="<?php echo sanitize($edit_row['username']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" class="form-control" name="name" value="<?php echo sanitize($edit_row['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Role</label>
							<select name="account_type" class="form-select" required>
								<?php foreach ($allowed_roles as $role) { ?>
									<option value="<?php echo sanitize($role); ?>" <?php echo $edit_row['account_type']===$role?'selected':''; ?>><?php echo sanitize($role); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">Password (leave blank to keep)</label>
							<input type="password" class="form-control" name="password" placeholder="Enter new password to reset">
						</div>
						<div class="mb-3 role-field d-none" data-role="Pharmacist">
							<label class="form-label">License Number</label>
							<input type="text" class="form-control" name="license_number" value="<?php echo sanitize($edit_row['license_number']); ?>">
						</div>
						<div class="mb-3 role-field d-none" data-role="Nurse">
							<label class="form-label">Certification</label>
							<input type="text" class="form-control" name="certification" value="<?php echo sanitize($edit_row['certification']); ?>">
						</div>
						<div class="mb-3 role-field d-none" data-role="Nurse">
							<label class="form-label">Shift</label>
							<input type="text" class="form-control" name="shift" value="<?php echo sanitize($edit_row['shift']); ?>">
						</div>
						<div class="mb-3 role-field d-none" data-role="Receptionist">
							<label class="form-label">Desk Number</label>
							<input type="text" class="form-control" name="desk_number" value="<?php echo sanitize($edit_row['desk_number']); ?>">
						</div>
						<div class="mb-3 role-field d-none" data-role="Laboratorist">
							<label class="form-label">Specialty Area</label>
							<input type="text" class="form-control" name="specialty_area" value="<?php echo sanitize($edit_row['specialty_area']); ?>">
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/staff/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
					<script>
					document.addEventListener('DOMContentLoaded', function(){
						var select = document.querySelector('select[name=account_type]');
						var fields = document.querySelectorAll('.role-field');
						function update(){
							var role = select.value;
							fields.forEach(function(el){ el.classList.toggle('d-none', el.getAttribute('data-role')!==role); });
						}
						select.addEventListener('change', update);
						update();
					});
					</script>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
