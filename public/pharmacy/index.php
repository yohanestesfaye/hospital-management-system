<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();
$is_admin = current_account_type() === 'Admin';

// Ensure tables exist
try { $db->exec("CREATE TABLE IF NOT EXISTS pharm_vendors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, phone VARCHAR(50) NULL, email VARCHAR(150) NULL, address VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS pharm_categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, description TEXT NULL, vendor_id INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(vendor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS pharmaceuticals (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(200) NOT NULL, sku VARCHAR(100) NULL, category_id INT NULL, vendor_id INT NULL, unit_price DECIMAL(10,2) DEFAULT 0, stock_qty INT DEFAULT 0, expiry_date DATE NULL, description TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(category_id), INDEX(vendor_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM pharmaceuticals LIKE 'expiry_date'")->fetch()) { $db->exec("ALTER TABLE pharmaceuticals ADD COLUMN expiry_date DATE NULL"); } } catch (Throwable $e) {}

// Handle actions: create, update, delete
$errors = [];
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'create_vendor') {
        $name = trim((string)post('name'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $address = trim((string)post('address'));
        if ($name === '') { $errors[] = 'Vendor name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO pharm_vendors (name, phone, email, address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $phone, $email, $address]);
            redirect('/hms/public/pharmacy/index.php?msg=vendor_created');
        }
    } elseif ($action === 'update_vendor') {
        $id = (int)post('id');
        $name = trim((string)post('name'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $address = trim((string)post('address'));
        if ($name === '') { $errors[] = 'Vendor name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('UPDATE pharm_vendors SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $email, $address, $id]);
            redirect('/hms/public/pharmacy/index.php?msg=vendor_updated');
        }
    } elseif ($action === 'delete_vendor') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM pharm_vendors WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/pharmacy/index.php?msg=vendor_deleted');
    } elseif ($action === 'create_category') {
        $name = trim((string)post('name'));
        $vendor_id = (int)post('vendor_id');
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Category name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO pharm_categories (name, description, vendor_id) VALUES (?, ?, ?)');
            $stmt->execute([$name, $description, $vendor_id ?: null]);
            redirect('/hms/public/pharmacy/index.php?msg=category_created');
        }
    } elseif ($action === 'update_category') {
        $id = (int)post('id');
        $name = trim((string)post('name'));
        $vendor_id = (int)post('vendor_id');
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Category name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('UPDATE pharm_categories SET name = ?, description = ?, vendor_id = ? WHERE id = ?');
            $stmt->execute([$name, $description, $vendor_id ?: null, $id]);
            redirect('/hms/public/pharmacy/index.php?msg=category_updated');
        }
    } elseif ($action === 'delete_category') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM pharm_categories WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/pharmacy/index.php?msg=category_deleted');
    } elseif ($action === 'create_product') {
        $name = trim((string)post('name'));
        $sku = trim((string)post('sku'));
        $category_id = (int)post('category_id');
        $vendor_id = (int)post('vendor_id');
        $unit_price = (float)post('unit_price');
        $stock_qty = (int)post('stock_qty');
        $expiry_date = trim((string)post('expiry_date'));
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Pharmaceutical name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO pharmaceuticals (name, sku, category_id, vendor_id, unit_price, stock_qty, expiry_date, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $sku, $category_id ?: null, $vendor_id ?: null, $unit_price, $stock_qty, $expiry_date ?: null, $description]);
            redirect('/hms/public/pharmacy/index.php?msg=product_created');
        }
    } elseif ($action === 'update_product') {
        $id = (int)post('id');
        $name = trim((string)post('name'));
        $sku = trim((string)post('sku'));
        $category_id = (int)post('category_id');
        $vendor_id = (int)post('vendor_id');
        $unit_price = (float)post('unit_price');
        $stock_qty = (int)post('stock_qty');
        $expiry_date = trim((string)post('expiry_date'));
        $description = trim((string)post('description'));
        if ($name === '') { $errors[] = 'Pharmaceutical name is required.'; }
        if (!$errors) {
            $stmt = $db->prepare('UPDATE pharmaceuticals SET name = ?, sku = ?, category_id = ?, vendor_id = ?, unit_price = ?, stock_qty = ?, expiry_date = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $sku, $category_id ?: null, $vendor_id ?: null, $unit_price, $stock_qty, $expiry_date ?: null, $description, $id]);
            redirect('/hms/public/pharmacy/index.php?msg=product_updated');
        }
    } elseif ($action === 'delete_product') {
        $id = (int)post('id');
        $stmt = $db->prepare('DELETE FROM pharmaceuticals WHERE id = ?');
        $stmt->execute([$id]);
        redirect('/hms/public/pharmacy/index.php?msg=product_deleted');
    }
}

// Common lookups
$vendors = [];
$categories = [];
try { $vendors = $db->query('SELECT id, name FROM pharm_vendors ORDER BY name')->fetchAll(); } catch(Throwable $e){}
try { $categories = $db->query('SELECT id, name FROM pharm_categories ORDER BY name')->fetchAll(); } catch(Throwable $e){}

// Read params for vendors
$qv = get('qv', '');
$lv = (int)get('lv', 10);
if (!in_array($lv, [10,25,50,100], true)) { $lv = 10; }
$pv = max(1, (int)get('pv', 1));
$vendor_offset = ($pv - 1) * $lv;

$vendor_where = '';
$vendor_params = [];
if ($qv !== '') {
    $vendor_where = 'WHERE name LIKE ?';
    $vendor_params = ['%' . $qv . '%'];
}

$vendor_count_sql = 'SELECT COUNT(*) AS c FROM pharm_vendors ' . $vendor_where;
$stmt = $db->prepare($vendor_count_sql);
$stmt->execute($vendor_params);
$vendor_total = (int)$stmt->fetch()['c'];

$vendor_list_sql = 'SELECT * FROM pharm_vendors ' . $vendor_where . ' ORDER BY created_at DESC LIMIT ' . (int)$lv . ' OFFSET ' . (int)$vendor_offset;
$stmt = $db->prepare($vendor_list_sql);
$stmt->execute($vendor_params);
$vendor_items = $stmt->fetchAll();

// Read params for categories
$qc = get('qc', '');
$lc = (int)get('lc', 10);
if (!in_array($lc, [10,25,50,100], true)) { $lc = 10; }
$pc = max(1, (int)get('pc', 1));
$cat_offset = ($pc - 1) * $lc;

$cat_where = '';
$cat_params = [];
if ($qc !== '') {
    $cat_where = 'WHERE pc.name LIKE ?';
    $cat_params = ['%' . $qc . '%'];
}

$cat_count_sql = 'SELECT COUNT(*) AS c FROM pharm_categories pc ' . $cat_where;
$stmt = $db->prepare($cat_count_sql);
$stmt->execute($cat_params);
$cat_total = (int)$stmt->fetch()['c'];

$cat_list_sql = 'SELECT pc.*, pv.name AS vendor_name FROM pharm_categories pc LEFT JOIN pharm_vendors pv ON pv.id = pc.vendor_id ' . $cat_where . ' ORDER BY pc.created_at DESC LIMIT ' . (int)$lc . ' OFFSET ' . (int)$cat_offset;
$stmt = $db->prepare($cat_list_sql);
$stmt->execute($cat_params);
$cat_items = $stmt->fetchAll();

// Read params for products
$qp = get('qp', '');
$lp = (int)get('lp', 10);
if (!in_array($lp, [10,25,50,100], true)) { $lp = 10; }
$pp = max(1, (int)get('pp', 1));
$prod_offset = ($pp - 1) * $lp;

$prod_where = '';
$prod_params = [];
if ($qp !== '') {
    $prod_where = 'WHERE ph.name LIKE ? OR ph.sku LIKE ?';
    $prod_params = ['%' . $qp . '%', '%' . $qp . '%'];
}

$prod_count_sql = 'SELECT COUNT(*) AS c FROM pharmaceuticals ph ' . $prod_where;
$stmt = $db->prepare($prod_count_sql);
$stmt->execute($prod_params);
$prod_total = (int)$stmt->fetch()['c'];

$prod_list_sql = 'SELECT ph.*, pc.name AS category_name, pv.name AS vendor_name FROM pharmaceuticals ph LEFT JOIN pharm_categories pc ON pc.id = ph.category_id LEFT JOIN pharm_vendors pv ON pv.id = ph.vendor_id ' . ($prod_where ? $prod_where : '') . ' ORDER BY ph.created_at DESC LIMIT ' . (int)$lp . ' OFFSET ' . (int)$prod_offset;
$stmt = $db->prepare($prod_list_sql);
$stmt->execute($prod_params);
$prod_items = $stmt->fetchAll();

// Get edit rows
$edit_vendor_id = (int)get('edit_vendor', 0);
$edit_vendor_row = null;
if ($edit_vendor_id) {
    $st = $db->prepare('SELECT * FROM pharm_vendors WHERE id = ?');
    $st->execute([$edit_vendor_id]);
    $edit_vendor_row = $st->fetch();
}

$edit_category_id = (int)get('edit_category', 0);
$edit_category_row = null;
if ($edit_category_id) {
    $st = $db->prepare('SELECT * FROM pharm_categories WHERE id = ?');
    $st->execute([$edit_category_id]);
    $edit_category_row = $st->fetch();
}

$edit_product_id = (int)get('edit_product', 0);
$edit_product_row = null;
if ($edit_product_id) {
    $st = $db->prepare('SELECT * FROM pharmaceuticals WHERE id = ?');
    $st->execute([$edit_product_id]);
    $edit_product_row = $st->fetch();
}

include __DIR__ . '/../../includes/' . ($is_admin ? 'admin-header.php' : 'pharmacist-header.php');
?>
	<h2 class="mb-4">Manage Pharmacy</h2>

	<?php if (get('msg') === 'vendor_created') { ?>
		<div class="alert alert-success">Vendor created successfully.</div>
	<?php } elseif (get('msg') === 'vendor_updated') { ?>
		<div class="alert alert-success">Vendor updated successfully.</div>
	<?php } elseif (get('msg') === 'vendor_deleted') { ?>
		<div class="alert alert-warning">Vendor deleted.</div>
	<?php } elseif (get('msg') === 'category_created') { ?>
		<div class="alert alert-success">Category created successfully.</div>
	<?php } elseif (get('msg') === 'category_updated') { ?>
		<div class="alert alert-success">Category updated successfully.</div>
	<?php } elseif (get('msg') === 'category_deleted') { ?>
		<div class="alert alert-warning">Category deleted.</div>
	<?php } elseif (get('msg') === 'product_created') { ?>
		<div class="alert alert-success">Pharmaceutical created successfully.</div>
	<?php } elseif (get('msg') === 'product_updated') { ?>
		<div class="alert alert-success">Pharmaceutical updated successfully.</div>
	<?php } elseif (get('msg') === 'product_deleted') { ?>
		<div class="alert alert-warning">Pharmaceutical deleted.</div>
	<?php } ?>
	<?php if ($errors) { ?>
		<div class="alert alert-danger mb-3">
			<?php foreach ($errors as $er) { echo '<div>' . sanitize($er) . '</div>'; } ?>
		</div>
	<?php } ?>

	<ul class="nav nav-tabs mb-3" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link <?php echo ($edit_vendor_row || $edit_category_row || $edit_product_row) ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-vendors" type="button" role="tab">Vendors</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-categories" type="button" role="tab">Categories</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-products" type="button" role="tab">Pharmaceuticals</button>
		</li>
		<?php if ($is_admin && $edit_vendor_row) { ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit-vendor" type="button" role="tab">Edit Vendor</button>
		</li>
		<?php } ?>
		<?php if ($is_admin && $edit_category_row) { ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit-category" type="button" role="tab">Edit Category</button>
		</li>
		<?php } ?>
		<?php if ($is_admin && $edit_product_row) { ?>
		<li class="nav-item" role="presentation">
			<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-edit-product" type="button" role="tab">Edit Pharmaceutical</button>
		</li>
		<?php } ?>
	</ul>

	<div class="tab-content">
		<!-- Vendors Tab -->
		<div class="tab-pane fade <?php echo ($edit_vendor_row || $edit_category_row || $edit_product_row) ? '' : 'show active'; ?>" id="tab-vendors" role="tabpanel">
			<ul class="nav nav-tabs mb-3" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link <?php echo $edit_vendor_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-vendors-list" type="button" role="tab">Vendor List</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vendors-add" type="button" role="tab">Add Vendor</button>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade <?php echo $edit_vendor_row ? '' : 'show active'; ?>" id="tab-vendors-list" role="tabpanel">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<form class="row g-2" method="get">
							<div class="col-auto">
								<input type="text" class="form-control" name="qv" placeholder="Search" value="<?php echo sanitize($qv); ?>">
							</div>
							<div class="col-auto">
								<select name="lv" class="form-select" onchange="this.form.submit()">
									<?php foreach ([10,25,50,100] as $n) { ?>
										<option value="<?php echo $n; ?>" <?php echo $lv===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option>
									<?php } ?>
								</select>
							</div>
							<div class="col-auto">
								<button class="btn btn-outline-secondary">Search</button>
							</div>
						</form>
						<a href="#tab-vendors-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Vendor</a>
					</div>

					<div class="table-responsive">
						<table class="table table-striped align-middle">
							<thead>
								<tr>
									<th style="width:70px">#</th>
									<th>Name</th>
									<th>Phone</th>
									<th>Email</th>
									<th>Address</th>
									<?php if ($is_admin) { ?><th style="width:140px">Options</th><?php } ?>
								</tr>
							</thead>
							<tbody>
								<?php $i = $vendor_offset + 1; foreach ($vendor_items as $row) { ?>
								<tr>
									<td><?php echo $i++; ?></td>
									<td><?php echo sanitize($row['name']); ?></td>
									<td><?php echo sanitize($row['phone']); ?></td>
									<td><?php echo sanitize($row['email']); ?></td>
									<td><?php echo sanitize($row['address']); ?></td>
<?php if ($is_admin) { ?>
									<td>
										<a class="btn btn-sm btn-outline-primary" href="/hms/public/pharmacy/index.php?edit_vendor=<?php echo (int)$row['id']; ?>"><i class="bi bi-pencil"></i></a>
										<form action="/hms/public/pharmacy/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this vendor?');">
											<input type="hidden" name="action" value="delete_vendor">
											<input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
											<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
										</form>
									</td>
<?php } ?>
								</tr>
								<?php } ?>
								<?php if (!$vendor_items) { ?>
									<tr><td colspan="<?php echo $is_admin ? 6 : 5; ?>" class="text-center text-muted">No vendors found</td></tr>
									<?php } ?>
							</tbody>
						</table>
					</div>

					<?php $total_pages = max(1, (int)ceil($vendor_total / $lv)); ?>
					<nav>
						<ul class="pagination">
							<?php for ($p = 1; $p <= $total_pages; $p++) { $active = $p === $pv ? 'active' : ''; ?>
								<li class="page-item <?php echo $active; ?>">
									<a class="page-link" href="?qv=<?php echo urlencode($qv); ?>&lv=<?php echo (int)$lv; ?>&pv=<?php echo (int)$p; ?>#tab-vendors"><?php echo (int)$p; ?></a>
								</li>
							<?php } ?>
						</ul>
					</nav>
				</div>

<?php if ($is_admin) { ?>
				<div class="tab-pane fade" id="tab-vendors-add" role="tabpanel">
					<div class="card">
						<div class="card-header">Add Vendor</div>
						<div class="card-body">
							<form method="post">
								<input type="hidden" name="action" value="create_vendor">
								<div class="mb-3">
									<label class="form-label">Vendor Name</label>
									<input type="text" class="form-control" name="name" required>
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
								<button class="btn btn-success">Create</button>
							</form>
						</div>
					</div>
				</div>
<?php } ?>
			</div>
		</div>

		<!-- Categories Tab -->
		<div class="tab-pane fade" id="tab-categories" role="tabpanel">
			<ul class="nav nav-tabs mb-3" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link <?php echo $edit_category_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-categories-list" type="button" role="tab">Category List</button>
				</li>
<?php if ($is_admin) { ?>
				<li class="nav-item" role="presentation">
					<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-categories-add" type="button" role="tab">Add Category</button>
				</li>
				<?php } ?>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade <?php echo $edit_category_row ? '' : 'show active'; ?>" id="tab-categories-list" role="tabpanel">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<form class="row g-2" method="get">
							<div class="col-auto">
								<input type="text" class="form-control" name="qc" placeholder="Search" value="<?php echo sanitize($qc); ?>">
							</div>
							<div class="col-auto">
								<select name="lc" class="form-select" onchange="this.form.submit()">
									<?php foreach ([10,25,50,100] as $n) { ?>
										<option value="<?php echo $n; ?>" <?php echo $lc===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option>
									<?php } ?>
								</select>
							</div>
							<div class="col-auto">
								<button class="btn btn-outline-secondary">Search</button>
							</div>
						</form>
						<?php if ($is_admin) { ?><a href="#tab-categories-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Category</a><?php } ?>
					</div>

					<div class="table-responsive">
						<table class="table table-striped align-middle">
							<thead>
								<tr>
									<th style="width:70px">#</th>
									<th>Category Name</th>
									<th>Vendor</th>
									<th>Description</th>
									<?php if ($is_admin) { ?><th style="width:140px">Options</th><?php } ?>
								</tr>
							</thead>
							<tbody>
								<?php $i = $cat_offset + 1; foreach ($cat_items as $row) { ?>
								<tr>
									<td><?php echo $i++; ?></td>
									<td><?php echo sanitize($row['name']); ?></td>
									<td><?php echo sanitize($row['vendor_name'] ?: 'N/A'); ?></td>
									<td><?php echo nl2br(sanitize($row['description'])); ?></td>
<?php if ($is_admin) { ?>
									<td>
										<a class="btn btn-sm btn-outline-primary" href="/hms/public/pharmacy/index.php?edit_category=<?php echo (int)$row['id']; ?>"><i class="bi bi-pencil"></i></a>
										<form action="/hms/public/pharmacy/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this category?');">
											<input type="hidden" name="action" value="delete_category">
											<input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
											<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
										</form>
									</td>
									<?php } ?>
								</tr>
								<?php } ?>
								<?php if (!$cat_items) { ?>
								<tr><td colspan="5" class="text-center text-muted">No categories found</td></tr>
								<?php } ?>
							</tbody>
						</table>
					</div>

					<?php $total_pages = max(1, (int)ceil($cat_total / $lc)); ?>
					<nav>
						<ul class="pagination">
							<?php for ($p = 1; $p <= $total_pages; $p++) { $active = $p === $pc ? 'active' : ''; ?>
								<li class="page-item <?php echo $active; ?>">
									<a class="page-link" href="?qc=<?php echo urlencode($qc); ?>&lc=<?php echo (int)$lc; ?>&pc=<?php echo (int)$p; ?>#tab-categories"><?php echo (int)$p; ?></a>
								</li>
							<?php } ?>
						</ul>
					</nav>
				</div>

				<div class="tab-pane fade" id="tab-categories-add" role="tabpanel">
					<div class="card">
						<div class="card-header">Add Category</div>
						<div class="card-body">
							<form method="post">
								<input type="hidden" name="action" value="create_category">
								<div class="mb-3">
									<label class="form-label">Category Name</label>
									<input type="text" class="form-control" name="name" required>
								</div>
								<div class="mb-3">
									<label class="form-label">Vendor</label>
									<select name="vendor_id" class="form-select">
										<option value="">Select vendor</option>
										<?php foreach ($vendors as $v) { ?>
											<option value="<?php echo (int)$v['id']; ?>"><?php echo sanitize($v['name']); ?></option>
										<?php } ?>
									</select>
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
			</div>
		</div>

		<!-- Products Tab -->
		<div class="tab-pane fade" id="tab-products" role="tabpanel">
			<ul class="nav nav-tabs mb-3" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link <?php echo $edit_product_row ? '' : 'active'; ?>" data-bs-toggle="tab" data-bs-target="#tab-products-list" type="button" role="tab">Pharmaceutical List</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-products-add" type="button" role="tab">Add Pharmaceutical</button>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade <?php echo $edit_product_row ? '' : 'show active'; ?>" id="tab-products-list" role="tabpanel">
					<div class="d-flex justify-content-between align-items-center mb-3">
						<form class="row g-2" method="get">
							<div class="col-auto">
								<input type="text" class="form-control" name="qp" placeholder="Search name or SKU" value="<?php echo sanitize($qp); ?>">
							</div>
							<div class="col-auto">
								<select name="lp" class="form-select" onchange="this.form.submit()">
									<?php foreach ([10,25,50,100] as $n) { ?>
										<option value="<?php echo $n; ?>" <?php echo $lp===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option>
									<?php } ?>
								</select>
							</div>
							<div class="col-auto">
								<button class="btn btn-outline-secondary">Search</button>
							</div>
						</form>
						<a href="#tab-products-add" class="btn btn-primary" data-bs-toggle="tab">+ Add Pharmaceutical</a>
					</div>

					<div class="table-responsive">
						<table class="table table-striped align-middle">
							<thead>
                                <tr>
                                    <th style="width:70px">#</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Vendor</th>
                                    <th>Unit Price</th>
                                    <th>Stock</th>
                                    <th>Expiry</th>
                                    <th style="width:140px">Options</th>
                                </tr>
							</thead>
							<tbody>
								<?php $i = $prod_offset + 1; foreach ($prod_items as $row) { ?>
								<tr>
									<td><?php echo $i++; ?></td>
									<td><?php echo sanitize($row['name']); ?></td>
									<td><?php echo sanitize($row['sku']); ?></td>
									<td><?php echo sanitize($row['category_name'] ?: 'N/A'); ?></td>
									<td><?php echo sanitize($row['vendor_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo number_format((float)$row['unit_price'], 2); ?></td>
                                    <td><?php echo (int)$row['stock_qty']; ?></td>
                                    <td><?php $exp = $row['expiry_date'] ?? null; if ($exp) { $days = (int)floor((strtotime($exp) - time())/86400); if ($days < 0) { echo '<span class="badge bg-danger">Expired</span> <small>'.sanitize($exp).'</small>'; } elseif ($days <= 30) { echo '<span class="badge bg-warning text-dark">'.$days.' days</span> <small>'.sanitize($exp).'</small>'; } else { echo '<span class="badge bg-secondary">'.sanitize($exp).'</span>'; } } else { echo 'N/A'; } ?></td>
<?php if ($is_admin) { ?>
									<td>
										<a class="btn btn-sm btn-outline-primary" href="/hms/public/pharmacy/index.php?edit_product=<?php echo (int)$row['id']; ?>"><i class="bi bi-pencil"></i></a>
										<form action="/hms/public/pharmacy/index.php" method="post" class="d-inline" onsubmit="return confirm('Delete this pharmaceutical?');">
											<input type="hidden" name="action" value="delete_product">
											<input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
											<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
										</form>
									</td>
									<?php } ?>
								</tr>
								<?php } ?>
								<?php if (!$prod_items) { ?>
                                <tr><td colspan="9" class="text-center text-muted">No pharmaceuticals found</td></tr>
								<?php } ?>
							</tbody>
						</table>
					</div>

					<?php $total_pages = max(1, (int)ceil($prod_total / $lp)); ?>
					<nav>
						<ul class="pagination">
							<?php for ($p = 1; $p <= $total_pages; $p++) { $active = $p === $pp ? 'active' : ''; ?>
								<li class="page-item <?php echo $active; ?>">
									<a class="page-link" href="?qp=<?php echo urlencode($qp); ?>&lp=<?php echo (int)$lp; ?>&pp=<?php echo (int)$p; ?>#tab-products"><?php echo (int)$p; ?></a>
								</li>
							<?php } ?>
						</ul>
					</nav>
				</div>

				<div class="tab-pane fade" id="tab-products-add" role="tabpanel">
					<div class="card">
						<div class="card-header">Add Pharmaceutical</div>
						<div class="card-body">
							<form method="post">
								<input type="hidden" name="action" value="create_product">
								<div class="mb-3">
									<label class="form-label">Name</label>
									<input type="text" class="form-control" name="name" required>
								</div>
								<div class="mb-3">
									<label class="form-label">SKU</label>
									<input type="text" class="form-control" name="sku">
								</div>
								<div class="mb-3">
									<label class="form-label">Category</label>
									<select name="category_id" class="form-select">
										<option value="">Select category</option>
										<?php foreach ($categories as $c) { ?>
											<option value="<?php echo (int)$c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
										<?php } ?>
									</select>
								</div>
								<div class="mb-3">
									<label class="form-label">Vendor</label>
									<select name="vendor_id" class="form-select">
										<option value="">Select vendor</option>
										<?php foreach ($vendors as $v) { ?>
											<option value="<?php echo (int)$v['id']; ?>"><?php echo sanitize($v['name']); ?></option>
										<?php } ?>
									</select>
								</div>
                                <div class="mb-3">
                                    <label class="form-label">Unit Price</label>
                                    <input type="number" step="0.01" min="0" class="form-control" name="unit_price">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Stock Qty</label>
                                    <input type="number" step="1" min="0" class="form-control" name="stock_qty">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" name="expiry_date">
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
			</div>
		</div>

		<!-- Edit Vendor Tab -->
		<?php if ($edit_vendor_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit-vendor" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Vendor</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update_vendor">
						<input type="hidden" name="id" value="<?php echo (int)$edit_vendor_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Vendor Name</label>
							<input type="text" class="form-control" name="name" value="<?php echo sanitize($edit_vendor_row['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Phone</label>
							<input type="text" class="form-control" name="phone" value="<?php echo sanitize($edit_vendor_row['phone']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Email</label>
							<input type="email" class="form-control" name="email" value="<?php echo sanitize($edit_vendor_row['email']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Address</label>
							<input type="text" class="form-control" name="address" value="<?php echo sanitize($edit_vendor_row['address']); ?>">
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/pharmacy/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>

		<!-- Edit Category Tab -->
		<?php if ($edit_category_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit-category" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Category</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update_category">
						<input type="hidden" name="id" value="<?php echo (int)$edit_category_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Category Name</label>
							<input type="text" class="form-control" name="name" value="<?php echo sanitize($edit_category_row['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Vendor</label>
							<select name="vendor_id" class="form-select">
								<option value="">Select vendor</option>
								<?php foreach ($vendors as $v) { ?>
									<option value="<?php echo (int)$v['id']; ?>" <?php echo (int)$edit_category_row['vendor_id'] === (int)$v['id'] ? 'selected' : ''; ?>><?php echo sanitize($v['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<textarea class="form-control" name="description" rows="3"><?php echo sanitize($edit_category_row['description']); ?></textarea>
						</div>
						<button class="btn btn-primary">Update</button>
						<a href="/hms/public/pharmacy/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>

		<!-- Edit Product Tab -->
		<?php if ($edit_product_row) { ?>
		<div class="tab-pane fade show active" id="tab-edit-product" role="tabpanel">
			<div class="card">
				<div class="card-header">Edit Pharmaceutical</div>
				<div class="card-body">
					<form method="post">
						<input type="hidden" name="action" value="update_product">
						<input type="hidden" name="id" value="<?php echo (int)$edit_product_row['id']; ?>">
						<div class="mb-3">
							<label class="form-label">Name</label>
							<input type="text" class="form-control" name="name" value="<?php echo sanitize($edit_product_row['name']); ?>" required>
						</div>
						<div class="mb-3">
							<label class="form-label">SKU</label>
							<input type="text" class="form-control" name="sku" value="<?php echo sanitize($edit_product_row['sku']); ?>">
						</div>
						<div class="mb-3">
							<label class="form-label">Category</label>
							<select name="category_id" class="form-select">
								<option value="">Select category</option>
								<?php foreach ($categories as $c) { ?>
									<option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$edit_product_row['category_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
								<?php } ?>
							</select>
						</div>
						<div class="mb-3">
							<label class="form-label">Vendor</label>
							<select name="vendor_id" class="form-select">
								<option value="">Select vendor</option>
								<?php foreach ($vendors as $v) { ?>
									<option value="<?php echo (int)$v['id']; ?>" <?php echo (int)$edit_product_row['vendor_id'] === (int)$v['id'] ? 'selected' : ''; ?>><?php echo sanitize($v['name']); ?></option>
								<?php } ?>
							</select>
						</div>
                        <div class="mb-3">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="unit_price" value="<?php echo (float)$edit_product_row['unit_price']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Stock Qty</label>
                            <input type="number" step="1" min="0" class="form-control" name="stock_qty" value="<?php echo (int)$edit_product_row['stock_qty']; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" class="form-control" name="expiry_date" value="<?php echo sanitize($edit_product_row['expiry_date'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?php echo sanitize($edit_product_row['description']); ?></textarea>
                        </div>
                        <button class="btn btn-primary">Update</button>
						<a href="/hms/public/pharmacy/index.php" class="btn btn-outline-secondary">Cancel</a>
					</form>
				</div>
			</div>
		</div>
		<?php } ?>
	</div>

<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>
