<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

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
        $license_number = trim((string)post('license_number')) ?: null;
        $certification = trim((string)post('certification')) ?: null;
        $shift = trim((string)post('shift')) ?: null;
        $desk_number = trim((string)post('desk_number')) ?: null;
        $specialty_area = trim((string)post('specialty_area')) ?: null;

        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($name === '') { $errors[] = 'Name is required.'; }
        if ($password === '') { $errors[] = 'Password is required.'; }

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
            $stmt->execute([$username, $name, $hash, 'Pharmacist', $license_number, $certification, $shift, $desk_number, $specialty_area]);
            redirect('/hms/public/pharmacists/index.php?msg=created');
        }
    } elseif ($action === 'update') {
        $id = (int)post('id');
        $username = trim((string)post('username'));
        $name = trim((string)post('name'));
        $password = (string)post('password');
        $license_number = trim((string)post('license_number')) ?: null;
        $certification = trim((string)post('certification')) ?: null;
        $shift = trim((string)post('shift')) ?: null;
        $desk_number = trim((string)post('desk_number')) ?: null;
        $specialty_area = trim((string)post('specialty_area')) ?: null;

        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($name === '') { $errors[] = 'Name is required.'; }

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
                $stmt = $db->prepare('UPDATE users SET username=?, name=?, password_hash=?, license_number=?, certification=?, shift=?, desk_number=?, specialty_area=? WHERE id=?');
                $stmt->execute([$username, $name, $hash, $license_number, $certification, $shift, $desk_number, $specialty_area, $id]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username=?, name=?, license_number=?, certification=?, shift=?, desk_number=?, specialty_area=? WHERE id=?');
                $stmt->execute([$username, $name, $license_number, $certification, $shift, $desk_number, $specialty_area, $id]);
            }
            redirect('/hms/public/pharmacists/index.php?msg=updated');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND account_type = ?');
        $stmt->execute([$id, 'Pharmacist']);
        redirect('/hms/public/pharmacists/index.php?msg=deleted');
    }
}

// Read params
$q = (string)get('q', '');
$limit = (int)get('limit', 10);
if (!in_array($limit, [10,25,50,100], true)) { $limit = 10; }
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Queries - only Pharmacist accounts
$where = 'WHERE account_type = ?';
$params = ['Pharmacist'];
if ($q !== '') {
    $like = '%' . $q . '%';
    $where .= ' AND (username LIKE ? OR name LIKE ?)';
    $params = array_merge($params, [$like, $like]);
}

$count_sql = "SELECT COUNT(*) AS c FROM users " . $where;
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$list_sql = "SELECT id, username, name, account_type, license_number, certification, shift, desk_number, specialty_area FROM users " . $where . " ORDER BY name LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$stmt = $db->prepare($list_sql);
$stmt->execute($params);
$pharmacists = $stmt->fetchAll();

$edit_id = (int)get('edit', 0);
$edit_row = null;
if ($edit_id) {
    $st = $db->prepare('SELECT id, username, name, account_type, license_number, certification, shift, desk_number, specialty_area FROM users WHERE id = ? AND account_type = ?');
    $st->execute([$edit_id, 'Pharmacist']);
    $edit_row = $st->fetch();
    if (!$edit_row) {
        $edit_row = null;
    }
}

include __DIR__ . '/../../includes/admin-header.php';
?>
	<h2 class="mb-4">Manage Pharmacist</h2>

	<?php if (get('msg') === 'created') { ?>
		<div class="alert alert-success">Pharmacist created successfully.</div>
	<?php } elseif (get('msg') === 'updated') { ?>
		<div class="alert alert-success">Pharmacist updated successfully.</div>
	<?php } elseif (get('msg') === 'deleted') { ?>
		<div class="alert alert-warning">Pharmacist deleted.</div>
	<?php } ?>
	<?php if ($errors) { ?>
		<div class="alert alert-danger mb-3">
			<?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
		</div>
	<?php } ?>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">Pharmacist List</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-add" type="button" role="tab">Add Pharmacist</button>
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
				<a href="#tab-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Pharmacist</a>
			</div>

			<div class="table-responsive">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th style="width:70px">#</th>
							<th>Username</th>
							<th>Name</th>
							<th>License Number</th>
							<th>Certification</th>
							<th style="width:140px">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php $i = $offset + 1; foreach ($pharmacists as $p) { ?>
						<tr>
							<td><?php echo $i++; ?></td>
							<td><?php echo sanitize($p['username']); ?></td>
							<td><?php echo sanitize($p['name']); ?></td>
							<td><?php echo sanitize($p['license_number'] ?: 'N/A'); ?></td>
							<td><?php echo sanitize($p['certification'] ?: 'N/A'); ?></td>
							<td>
								<a class="btn btn-sm btn-outline-primary" href="/hms/public/pharmacists/index.php?edit=<?php echo (int)$p['id']; ?>"><i class="bi bi-pencil"></i></a>
								<form action="/hms/public/pharmacists/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this pharmacist account?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
								</form>
							</td>
						</tr>
						<?php } ?>
						<?php if (!$pharmacists) { ?>
						<tr><td colspan="6" class="text-center text-muted">No pharmacists found</td></tr>
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
				<div class="card-header">Add Pharmacist</div>
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
							<label class="form-label">Password</label>
							<input type="password" class="form-control" name="password" required>
						</div>
						<div class="mb-3">
							<label class="form-label">License Number</label>
							<input type="text" class="form-control" name="license_number" placeholder="Pharmacy license number">
						</div>
						<div class="mb-3">
							<label class="form-label">Certification</label>
							<input type="text" class="form-control" name="certification" placeholder="Professional certification">
						</div>
						<div class="mb-3">
							<label class="form-label">Shift</label>
							<input type="text" class="form-control" name="shift" placeholder="Day/Night">
						</div>
						<div class="mb-3">
							<label class="form-label">Desk Number</label>
							<input type="text" class="form-control" name="desk_number" placeholder="Desk/Station number">
						</div>
						<div class="mb-3">
							<label class="form-label">Specialty Area</label>
							<input type="text" class="form-control" name="specialty_area" placeholder="e.g., Prescription Management, Inventory">
						</div>
						<button class="btn btn-success">Create</button>
					</form>
				</div>
			</div>
		</div>

		<?php if ($edit_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Pharmacist</div>
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
							<label class="form-label">Password (leave blank to keep)</label>
							<input type="password" class="form-control" name="password" placeholder="Enter new password to reset">
						</div>
						<div class="mb-3">
							<label class="form-label">License Number</label>
							<input type="text" class="form-control" name="license_number" value="<?php echo sanitize($edit_row['license_number']); ?>" placeholder="Pharmacy license number">
						</div>
						<div class="mb-3">
							<label class="form-label">Certification</label>
							<input type="text" class="form-control" name="certification" value="<?php echo sanitize($edit_row['certification']); ?>" placeholder="Professional certification">
						</div>
						<div class="mb-3">
							<label class="form-label">Shift</label>
							<input type="text" class="form-control" name="shift" value="<?php echo sanitize($edit_row['shift']); ?>" placeholder="Day/Night">
						</div>
						<div class="mb-3">
							<label class="form-label">Desk Number</label>
							<input type="text" class="form-control" name="desk_number" value="<?php echo sanitize($edit_row['desk_number']); ?>" placeholder="Desk/Station number">
						</div>
						<div class="mb-3">
							<label class="form-label">Specialty Area</label>
							<input type="text" class="form-control" name="specialty_area" value="<?php echo sanitize($edit_row['specialty_area']); ?>" placeholder="e.g., Prescription Management, Inventory">
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/pharmacists/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>

