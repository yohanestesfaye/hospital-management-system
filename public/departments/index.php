<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure departments table exists (lightweight bootstrap)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Throwable $e) {
    // ignore; page will still render with message
}

// Handle actions: create, update, delete
$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'create') {
        $name = trim((string)post('name'));
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Department name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO departments (name, description) VALUES (?, ?)');
            $stmt->execute([$name, $description]);
            redirect('/hms/public/departments/index.php?msg=created');
        }
    } elseif ($action === 'update') {
        $id = (int)post('id');
        $name = trim((string)post('name'));
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Department name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('UPDATE departments SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description, $id]);
            redirect('/hms/public/departments/index.php?msg=updated');
        }
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/departments/index.php?msg=deleted');
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
    $where = 'WHERE name LIKE ? OR description LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like];
}

$count_sql = 'SELECT COUNT(*) AS c FROM departments ' . $where;
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

$list_sql = 'SELECT * FROM departments ' . $where . ' ORDER BY id DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($list_sql);
$stmt->execute($params);
$departments = $stmt->fetchAll();

$edit_id = (int)get('edit', 0);
$edit_row = null;
if ($edit_id) {
    $st = $db->prepare('SELECT * FROM departments WHERE id = ?');
    $st->execute([$edit_id]);
    $edit_row = $st->fetch();
}

include __DIR__ . '/../../includes/admin-header.php';
?>
	<h2 class="mb-4">Manage Department</h2>

	<?php if (get('msg') === 'created') { ?>
		<div class="alert alert-success">Department created successfully.</div>
	<?php } elseif (get('msg') === 'updated') { ?>
		<div class="alert alert-success">Department updated successfully.</div>
	<?php } elseif (get('msg') === 'deleted') { ?>
		<div class="alert alert-warning">Department deleted.</div>
	<?php } ?>
	<?php if ($errors) { ?>
		<div class="alert alert-danger mb-3">
			<?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
		</div>
	<?php } ?>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-list" type="button" role="tab">Department List</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo $edit_row ? '' : ''; ?>" data-bs-toggle="tab" data-bs-target="#tab-add" type="button" role="tab">Add Department</button>
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
				<a href="#tab-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Department</a>
			</div>

			<div class="table-responsive">
				<table class="table table-striped align-middle">
					<thead>
						<tr>
							<th style="width:70px">#</th>
							<th>Department Name</th>
							<th>Description</th>
							<th style="width:140px">Options</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($departments as $row) { ?>
						<tr>
							<td><?php echo (int)$row['id']; ?></td>
							<td><?php echo sanitize($row['name']); ?></td>
							<td><?php echo sanitize($row['description']); ?></td>
							<td>
								<a class="btn btn-sm btn-outline-primary" href="/hms/public/departments/index.php?edit=<?php echo (int)$row['id']; ?>"><i class="bi bi-pencil"></i></a>
								<form action="/hms/public/departments/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this department?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
								</form>
							</td>
						</tr>
						<?php } ?>
						<?php if (!$departments) { ?>
						<tr><td colspan="4" class="text-center text-muted">No departments found</td></tr>
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
				<div class="card-header">Add Department</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="create">
						<div class="mb-3">
							<label class="form-label">Department Name</label>
							<input type="text" class="form-control" name="name" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea class="form-control" name="description" rows="3"></textarea>
						</div>
						<button class="btn btn-success">Create</button>
					</form>
				</div>
			</div>
		</div>

		<?php if ($edit_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Department</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update">
						<input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Department Name</label>
							<input type="text" class="form-control" name="name" value="<?php echo sanitize($edit_row['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea class="form-control" name="description" rows="3"><?php echo sanitize($edit_row['description']); ?></textarea>
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/departments/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>