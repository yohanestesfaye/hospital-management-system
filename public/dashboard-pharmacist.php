<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Pharmacist');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

$stats = [
	'patients_total' => (int)$db->query('SELECT COUNT(*) AS c FROM patients')->fetch()['c'],
	'appointments_today' => (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetch()['c'],
];
$pending_count = (int)$db->query('SELECT COUNT(*) AS c FROM prescriptions WHERE dispensed = 0')->fetch()['c'];
$dispensed_today = (int)$db->query("SELECT COUNT(*) AS c FROM prescriptions WHERE dispensed = 1 AND DATE(dispensed_at) = CURDATE()")->fetch()['c'];
$thr = (int)($env['low_stock_threshold'] ?? 5);
$low_stock_count = (int)$db->query("SELECT COUNT(*) AS c FROM pharmaceuticals WHERE stock_qty <= " . $thr)->fetch()['c'];
// Ensure prescription pricing columns exist
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'quantity'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN quantity INT DEFAULT 1"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'unit_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'total_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}

$pending = $db->query("SELECT p.*, CONCAT(pa.first_name,' ',pa.last_name) AS patient_name, COALESCE(ph.name, p.medication) AS med_name, ph.stock_qty FROM prescriptions p JOIN patients pa ON pa.id = p.patient_id LEFT JOIN pharmaceuticals ph ON ph.id = p.pharmaceutical_id WHERE p.dispensed = 0 ORDER BY p.created_at DESC LIMIT 10")->fetchAll();

// Get prescription billing stats
$prescription_revenue_today = 0;
$prescription_count_today = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(p.total_price), 0) AS total 
        FROM prescriptions p 
        WHERE p.dispensed = 1 AND DATE(p.dispensed_at) = CURDATE()");
    $stmt->execute();
    $stats = $stmt->fetch();
    $prescription_count_today = (int)$stats['c'];
    $prescription_revenue_today = (float)$stats['total'];
} catch (Throwable $e) {}
$low_stock = $db->query("SELECT name, sku, stock_qty FROM pharmaceuticals WHERE stock_qty <= " . $thr . " ORDER BY stock_qty ASC LIMIT 10")->fetchAll();


include __DIR__ . '/../includes/pharmacist-header.php';
try {
	if (!$db->query("SHOW INDEX FROM prescriptions WHERE Key_name='idx_dispensed'")->fetch()) { $db->exec("CREATE INDEX idx_dispensed ON prescriptions(dispensed)"); }
	if (!$db->query("SHOW INDEX FROM prescriptions WHERE Key_name='idx_dispensed_at'")->fetch()) { $db->exec("CREATE INDEX idx_dispensed_at ON prescriptions(dispensed_at)"); }
	if (!$db->query("SHOW INDEX FROM pharmaceuticals WHERE Key_name='idx_stock_qty'")->fetch()) { $db->exec("CREATE INDEX idx_stock_qty ON pharmaceuticals(stock_qty)"); }
	if (!$db->query("SHOW INDEX FROM appointments WHERE Key_name='idx_appointment_date'")->fetch()) { $db->exec("CREATE INDEX idx_appointment_date ON appointments(appointment_date)"); }
} catch (Throwable $e) { }
?>
<div class="container-fluid py-4 pharmacist-dashboard"><?php $section = get('section',''); ?>
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h4 mb-0">Pharmacist Dashboard</h1>
		<span class="badge bg-danger">Pharmacist</span>
	</div>
	<?php $msg = get('msg',''); $error = get('error',''); 
	if ($msg==='out_of_stock') { ?>
		<div class="alert alert-warning">Selected item is out of stock. Please restock before dispensing.</div>
	<?php } elseif ($msg==='dispensed') { 
		$details = get('details', '');
	?>
		<div class="alert alert-success alert-dismissible fade show">
			<i class="bi bi-check-circle"></i> <strong>Prescription dispensed successfully!</strong><br>
			<?php if ($details) { ?>
				<small><?php echo sanitize($details); ?></small><br>
			<?php } ?>
			Bill and invoice created for accountant. Prescription removed from pending list.
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php } elseif ($error) { ?>
		<div class="alert alert-danger alert-dismissible fade show">
			<i class="bi bi-exclamation-triangle"></i> Error: <?php echo sanitize($error); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php } ?>

	<div class="row g-3 mb-3">
		<div class="col-md-3">
			<div class="card shadow-sm border-warning"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-muted">Pending Prescriptions</div><div class="h4 mb-0"><?php echo (int)$pending_count; ?></div></div><i class="bi bi-clipboard-pulse text-warning fs-2"></i></div></div>
		</div>
		<div class="col-md-3">
			<div class="card shadow-sm border-success"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-muted">Dispensed Today</div><div class="h4 mb-0"><?php echo (int)$dispensed_today; ?></div></div><i class="bi bi-check2-circle text-success fs-2"></i></div></div>
		</div>
		<div class="col-md-3">
            <div class="card shadow-sm border-info"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-muted">Revenue Today</div><div class="h4 mb-0 text-success"><?php echo format_currency($prescription_revenue_today); ?></div></div><i class="bi bi-cash-coin text-info fs-2"></i></div></div>
		</div>
		<div class="col-md-3">
			<div class="card shadow-sm border-danger"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-muted">Low Stock Items</div><div class="h4 mb-0"><?php echo (int)$low_stock_count; ?></div></div><i class="bi bi-exclamation-triangle text-danger fs-2"></i></div></div>
		</div>
	</div>
	<div class="row g-3">
		<div class="col-md-12">
			<div class="card shadow-sm">
				<div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Pharmacy Management</h5>
					<form class="d-flex align-items-center gap-2" method="get">
						<input type="hidden" name="section" value="">
						<select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
							<?php $status = get('status','pending'); foreach(['pending'=>'Pending','dispensed'=>'Dispensed Today'] as $k=>$v){ ?><option value="<?php echo $k; ?>" <?php echo $status===$k?'selected':''; ?>><?php echo $v; ?></option><?php } ?>
						</select>
						<input type="date" name="date" class="form-control form-control-sm" value="<?php echo sanitize(get('date','')); ?>">
						<button class="btn btn-light btn-sm">Apply</button>
					</form>
				</div>
				<div class="card-body">
					<?php 
					$status = get('status','pending'); $date = get('date','');
					$sort_by = get('sort_by','time'); $sort_dir = strtolower(get('sort_dir','desc'))==='asc'?'ASC':'DESC';
					// For pending view, only show non-dispensed prescriptions
					// For dispensed view, only show dispensed prescriptions
					$where = $status==='dispensed' ? 'p.dispensed = 1' : 'p.dispensed = 0';
					$params = [];
					if ($date !== '') { $where .= ($status==='dispensed' ? ' AND DATE(p.dispensed_at) = ?' : ' AND DATE(p.created_at) = ?'); $params[] = $date; }
					$order = ($sort_by==='patient'?'patient_name':($sort_by==='med'?'med_name':($status==='dispensed'?'p.dispensed_at':'p.created_at'))) . ' ' . $sort_dir;
					$sql = 'SELECT p.*, CONCAT(pa.first_name, \' \' , pa.last_name) AS patient_name, COALESCE(ph.name, p.medication) AS med_name, ph.stock_qty, ph.unit_price AS pharma_unit_price FROM prescriptions p JOIN patients pa ON pa.id = p.patient_id LEFT JOIN pharmaceuticals ph ON ph.id = p.pharmaceutical_id WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT 10';
					$stmt = $db->prepare($sql); $stmt->execute($params); $work_items = $stmt->fetchAll();
					?>
					<div class="table-responsive">
						<table class="table table-hover align-middle">
							<thead class="table-light"><tr>
							<th><a href="?status=<?php echo urlencode($status); ?>&date=<?php echo urlencode($date); ?>&sort_by=patient&sort_dir=<?php echo $sort_by==='patient'&&$sort_dir==='ASC'?'desc':'asc'; ?>">Patient</a></th>
							<th><a href="?status=<?php echo urlencode($status); ?>&date=<?php echo urlencode($date); ?>&sort_by=med&sort_dir=<?php echo $sort_by==='med'&&$sort_dir==='ASC'?'desc':'asc'; ?>">Medication</a></th>
							<th>Dosage</th>
							<th>Qty</th>
							<th>Price</th>
							<th><a href="?status=<?php echo urlencode($status); ?>&date=<?php echo urlencode($date); ?>&sort_by=time&sort_dir=<?php echo $sort_by==='time'&&$sort_dir==='ASC'?'desc':'asc'; ?>">Time</a></th>
							<th>Stock</th>
							<th style="width:160px">Options</th>
						</tr></thead>
							<tbody>
								<?php foreach ($work_items as $row) { $in_stock = isset($row['stock_qty']) ? (int)$row['stock_qty'] > 0 : null; $time_val = ($status==='dispensed') ? $row['dispensed_at'] : $row['created_at']; ?>
								<tr>
									<td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
									<td><?php echo sanitize($row['med_name']); ?></td>
									<td><?php echo sanitize($row['dosage']); ?></td>
									<td><?php echo (int)($row['quantity'] ?? 1); ?></td>
									<td>
										<?php 
										$unit_price = (float)($row['unit_price'] ?? $row['pharma_unit_price'] ?? 0);
										$quantity = max(1, (int)($row['quantity'] ?? 1));
										$total_price = (float)($row['total_price'] ?? ($quantity * $unit_price));
										if ($total_price > 0) {
                                            echo '<strong class="text-success">' . format_currency($total_price) . '</strong>';
										} else {
											echo '<span class="text-muted">-</span>';
										}
										?>
									</td>
								<td><?php echo sanitize($time_val); ?></td>
								<td><?php echo $in_stock===null?'<span class="badge bg-secondary">N/A</span>':($in_stock?'<span class="badge bg-success">In Stock</span>':'<span class="badge bg-danger">Out of Stock</span>'); ?></td>
									<td>
										<?php if ($in_stock===false) { ?>
											<span class="btn btn-sm btn-secondary disabled">Out of Stock</span>
										<?php } else { ?>
											<a class="btn btn-sm btn-success" href="/hms/public/prescriptions/dispense.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Mark as dispensed?');"><i class="bi bi-check2-circle"></i> Dispense</a>
										<?php } ?>
									</td>
								</tr>
								<?php } ?>
							<?php if (!$work_items) { ?><tr><td colspan="8" class="text-center text-muted">No data — <a href="/hms/public/prescriptions/index.php">view all prescriptions</a></td></tr><?php } ?>
							</tbody>
						</table>
					</div>
					<div class="mt-2">
						<a href="/hms/public/prescriptions/index.php" class="btn btn-outline-danger">Open Pharmacy Worklist</a>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php if ($section==='lowstock') { ?>
	<div class="row g-3 mt-3" id="lowstock">
		<div class="col-md-12">
			<div class="card shadow-sm">
				<div class="card-header bg-light"><h5 class="mb-0">Low Stock Inventory</h5></div>
				<div class="card-body">
					<div class="table-responsive">
						<table class="table table-hover align-middle">
							<thead class="table-light"><tr><th>Name</th><th>SKU</th><th>Stock</th></tr></thead>
							<tbody>
								<?php foreach ($low_stock as $it) { ?>
								<tr><td><?php echo sanitize($it['name']); ?></td><td><?php echo sanitize($it['sku']); ?></td><td><span class="badge bg-<?php echo (int)$it['stock_qty']>0?'warning':'danger'; ?>"><?php echo (int)$it['stock_qty']; ?></span></td></tr>
								<?php } ?>
								<?php if (!$low_stock) { ?><tr><td colspan="3" class="text-center text-muted">No low stock items</td></tr><?php } ?>
							</tbody>
						</table>
					</div>
					<a href="/hms/public/pharmacy/products/index.php?low=1" class="btn btn-outline-secondary">Open Low Stock List</a>
				</div>
			</div>
		</div>
	</div>

	<?php } else { ?>
	<?php } ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

