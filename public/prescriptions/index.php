<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Doctor','Pharmacist','Nurse','Receptionist','Laboratorist','Patient']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Bootstrap table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        medication VARCHAR(200) NULL,
        pharmaceutical_id INT NULL,
        dosage VARCHAR(100) NULL,
        message TEXT NULL,
        details TEXT NULL,
        dispensed TINYINT(1) DEFAULT 0,
        dispensed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id), INDEX(doctor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'medication'")->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN medication VARCHAR(200) NULL"); }
    if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'pharmaceutical_id'")->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN pharmaceutical_id INT NULL"); }
    if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'dosage'")->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN dosage VARCHAR(100) NULL"); }
    if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'message'")->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN message TEXT NULL"); }
    if ($db->query("SHOW COLUMNS FROM prescriptions LIKE 'details'")->fetch()) { $db->exec("ALTER TABLE prescriptions MODIFY COLUMN details TEXT NULL"); }
    if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'dispensed_by'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN dispensed_by INT NULL"); }
} catch (Throwable $e) { }

// Data migration: ensure prescriptions.doctor_id points to doctors.id, not users.id
try {
    $db->exec("UPDATE prescriptions p JOIN users u ON u.id = p.doctor_id SET p.doctor_id = u.doctor_id WHERE u.doctor_id IS NOT NULL AND p.doctor_id <> u.doctor_id");
} catch (Throwable $e) { }

$q = get('q','');
$limit = (int)get('limit',10); if(!in_array($limit,[10,25,50,100],true)){ $limit=10; }
$page = max(1,(int)get('page',1)); $offset = ($page-1)*$limit;

$where = '';$params=[];
$doctor_id = (int)get('doctor_id', 0);
$pharmacist = trim((string)get('pharmacist',''));
$mine = (int)get('mine', 0) === 1 ? 1 : 0;
try { $doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM doctors ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $doctors = []; }
if ($q !== '') { $like = '%' . $q . '%'; $where = 'WHERE COALESCE(p.medication,"") LIKE ? OR COALESCE(p.dosage,"") LIKE ? OR COALESCE(p.message,"") LIKE ? OR COALESCE(p.details,"") LIKE ?'; $params = [$like,$like,$like,$like]; }
$dispensed = get('dispensed','');
if ($dispensed !== '') { $d = (int)$dispensed; $where = $where ? $where . ' AND p.dispensed = ?' : 'WHERE p.dispensed = ?'; $params[] = $d; }
if ($doctor_id > 0) { $cond = '(p.doctor_id = ? OR EXISTS (SELECT 1 FROM users uu WHERE uu.id = p.doctor_id AND uu.doctor_id = ?))'; $where = $where ? $where . ' AND ' . $cond : 'WHERE ' . $cond; $params[] = $doctor_id; $params[] = $doctor_id; }
if (current_account_type()==='Doctor') { $did = resolve_doctor_id($db); if ($did) { $cond = '(p.doctor_id = ? OR EXISTS (SELECT 1 FROM users uu WHERE uu.id = p.doctor_id AND uu.doctor_id = ?))'; $where = $where ? $where . ' AND ' . $cond : 'WHERE ' . $cond; $params[] = $did; $params[] = $did; } }
if (current_account_type()==='Patient') { $pid = resolve_patient_id($db); if ($pid) { $cond = 'p.patient_id = ?'; $where = $where ? $where . ' AND ' . $cond : 'WHERE ' . $cond; $params[] = $pid; } }
if ($pharmacist !== '') { $cond = 'COALESCE(u2.name,"") LIKE ?'; $where = $where ? $where . ' AND ' . $cond : 'WHERE ' . $cond; $params[] = '%'.$pharmacist.'%'; }
if ($mine === 1 && current_account_type()==='Pharmacist') { $cond = 'p.dispensed_by = ?'; $where = $where ? $where . ' AND ' . $cond : 'WHERE ' . $cond; $params[] = current_user_id(); }

$count_from = 'FROM prescriptions p';
if ($pharmacist !== '') { $count_from = 'FROM prescriptions p LEFT JOIN users u2 ON u2.id = p.dispensed_by'; }
$count_sql = 'SELECT COUNT(*) AS c ' . $count_from . ' ' . $where;
$stmt = $db->prepare($count_sql); $stmt->execute($params); $total = (int)$stmt->fetch()['c'];

$order = 'ORDER BY ' . ((isset($d) && $d===1) ? 'p.dispensed_at' : 'p.created_at') . ' DESC';
$list_sql = 'SELECT p.*, CONCAT(pa.first_name," ",pa.last_name) AS patient_name, COALESCE(CONCAT(d.first_name," ",d.last_name), udoc.name) AS doctor_name, ph.name AS pharma_name, ph.stock_qty AS stock_qty, ph.unit_price AS pharma_unit_price, u2.name AS pharmacist_name FROM prescriptions p JOIN patients pa ON pa.id = p.patient_id LEFT JOIN doctors d ON d.id = p.doctor_id LEFT JOIN users udoc ON udoc.id = p.doctor_id LEFT JOIN pharmaceuticals ph ON ph.id = p.pharmaceutical_id LEFT JOIN users u2 ON u2.id = p.dispensed_by ' . $where . ' ' . $order . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($list_sql); $stmt->execute($params); $items = $stmt->fetchAll();

$is_pharmacist = current_account_type() === 'Pharmacist';
if ($is_pharmacist) {
    include __DIR__ . '/../../includes/pharmacist-header.php';
} else {
    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/nav.php';
}
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Prescriptions</h1>
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
            Bill and invoice created for accountant.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } elseif ($error) { ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> Error: <?php echo sanitize($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } ?>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light"><div class="d-flex align-items-center"><div class="me-auto fw-semibold">Prescription List</div>
            <?php if (current_account_type()==='Doctor') { ?><a class="btn btn-sm btn-primary" href="/hms/public/prescriptions/create.php?overlay=1"><i class="bi bi-plus-lg"></i> Add</a><?php } ?>
        </div></div>
        <div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col-sm-9 col-md-7"><input type="text" class="form-control" name="q" placeholder="Search" value="<?php echo sanitize($q); ?>"></div>
                <div class="col-sm-3 col-md-3">
                    <select name="limit" class="form-select" onchange="this.form.submit()">
                        <?php foreach([10,25,50,100] as $n){ ?><option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option><?php } ?>
                    </select>
                </div>
                <?php if (in_array(current_account_type(), ['Admin','Pharmacist','Receptionist','Laboratorist'], true)) { ?>
                <div class="col-sm-12 col-md-3">
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Doctors</option>
                        <?php foreach ($doctors as $dr) { $sel = $doctor_id===(int)$dr['id']?'selected':''; ?>
                            <option value="<?php echo (int)$dr['id']; ?>" <?php echo $sel; ?>><?php echo sanitize($dr['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="col-sm-12 col-md-3">
                    <input type="text" class="form-control" name="pharmacist" placeholder="Pharmacist name" value="<?php echo sanitize($pharmacist); ?>">
                </div>
                <?php if (current_account_type()==='Pharmacist') { ?>
                <div class="col-sm-12 col-md-2 form-check d-flex align-items-center">
                    <input class="form-check-input me-2" type="checkbox" name="mine" value="1" id="mine" <?php echo $mine===1?'checked':''; ?> onchange="this.form.submit()">
                    <label class="form-check-label" for="mine">Dispensed by me</label>
                </div>
                <?php } ?>
                <div class="col-sm-12 col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr>
                        <th style="width:60px">#</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Stock</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Message</th>
                        <th>Dispensed</th>
                        <th>Pharmacist</th>
                        <th style="width:180px">Options</th>
                    </tr></thead>
                    <tbody>
                        <?php $i=$offset+1; foreach($items as $row){ ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
                            <td><?php echo sanitize($row['doctor_name']); ?></td>
                            <td><?php echo sanitize($row['pharma_name'] ?: $row['medication']); ?></td>
                            <td><?php echo sanitize($row['dosage']); ?></td>
                            <td><?php $in_stock = isset($row['stock_qty']) ? ((int)$row['stock_qty'] > 0) : null; echo $in_stock===null?'<span class="badge bg-secondary">N/A</span>':($in_stock?'<span class="badge bg-success">In Stock</span>':'<span class="badge bg-danger">Out of Stock</span>'); ?></td>
                            <td><?php echo (int)($row['quantity'] ?? 1); ?></td>
                            <td>
                                <?php 
                                $unit_price = (float)($row['unit_price'] ?? $row['pharma_unit_price'] ?? 0);
                                $quantity = max(1, (int)($row['quantity'] ?? 1));
                                $total_price = (float)($row['total_price'] ?? ($quantity * $unit_price));
                                if ($total_price > 0) {
                                    echo '<strong class="text-success">' . format_currency($total_price) . '</strong><br>';
                                    echo '<small class="text-muted">' . format_currency($unit_price) . ' × ' . $quantity . '</small>';
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo nl2br(sanitize($row['message'] ?: $row['details'])); ?></td>
                            <td>
                                <?php if ((int)$row['dispensed'] === 1) { ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php } else { ?>
                                    <span class="badge bg-warning text-dark">No</span>
                                <?php } ?>
                            </td>
                            <td><?php echo sanitize($row['pharmacist_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ($is_pharmacist && (int)$row['dispensed']===0) { ?>
                                    <?php if ($in_stock===false) { ?>
                                        <span class="btn btn-sm btn-secondary disabled">Out of Stock</span>
                                    <?php } else { ?>
                                        <a class="btn btn-sm btn-success" href="/hms/public/prescriptions/dispense.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Mark as dispensed?');"><i class="bi bi-check2-circle"></i> Dispense</a>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted small">View only</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                        <?php if (!$items) { ?><tr><td colspan="12" class="text-center text-muted">No prescriptions found</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
            <?php $total_pages = max(1,(int)ceil($total/$limit)); ?>
            <nav><ul class="pagination">
                <?php for($p=1;$p<=$total_pages;$p++){ $active=$p===$page?'active':''; ?>
                    <li class="page-item <?php echo $active; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>&doctor_id=<?php echo (int)$doctor_id; ?>&pharmacist=<?php echo urlencode($pharmacist); ?>&mine=<?php echo (int)$mine; ?>"><?php echo (int)$p; ?></a></li>
                <?php } ?>
            </ul></nav>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
