<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Doctor','Laboratorist','LabManager']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Bootstrap table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS lab_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        test_name VARCHAR(200) NOT NULL,
        result TEXT,
        result_date DATETIME NULL,
        requested_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { }

// Ensure extended lab order columns exist for workflow
foreach ([
    ["requested_by INT NULL", "requested_by"],
    ["processed_by INT NULL", "processed_by"],
    ["priority ENUM('Urgent','Routine') DEFAULT 'Routine'", "priority"],
    ["status ENUM('Pending','Ready','Reviewed') DEFAULT 'Pending'", "status"],
    ["notes TEXT NULL", "notes"],
] as $col) {
    try {
        $exists = $db->query("SHOW COLUMNS FROM lab_results LIKE '".$col[1]."'")->fetch() !== false;
        if(!$exists){ $db->exec("ALTER TABLE lab_results ADD COLUMN " . $col[0]); }
    } catch (Throwable $e) {}
}

try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'doctor_id'")->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN doctor_id INT NULL"); } } catch (Throwable $e) {}

$q = get('q','');
$limit=(int)get('limit',10); if(!in_array($limit,[10,25,50,100],true)){ $limit=10; }
$page=max(1,(int)get('page',1)); $offset=($page-1)*$limit;

$where=''; $params=[];
if($q!==''){ $like='%'.$q.'%'; $where='WHERE lr.test_name LIKE ?'; $params=[$like]; }
$acct=current_account_type();
$doctor_id = (int)get('doctor_id', 0);
$is_admin = current_account_type()==='Admin';
if (!$is_admin) { $doctor_id = 0; }
try { $doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM doctors ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $doctors = []; }
if($acct==='Doctor'){
    $cond='lr.requested_by = ?';
    if($where){ $where .= ' AND ' . $cond; } else { $where = 'WHERE ' . $cond; }
    $params[] = current_user_id();
} elseif ($is_admin && $doctor_id > 0) {
    $cond = 'EXISTS (SELECT 1 FROM users uu WHERE uu.id = lr.requested_by AND uu.doctor_id = ?)';
    if($where){ $where .= ' AND ' . $cond; } else { $where = 'WHERE ' . $cond; }
    $params[] = $doctor_id;
}

$count_sql='SELECT COUNT(*) AS c FROM lab_results lr '.$where;
$stmt=$db->prepare($count_sql); $stmt->execute($params); $total=(int)$stmt->fetch()['c'];

$list_sql='SELECT lr.*, CONCAT(p.first_name," ",p.last_name) AS patient_name, u.name AS doctor_name, u2.name AS technician_name FROM lab_results lr JOIN patients p ON p.id=lr.patient_id LEFT JOIN users u ON u.id = lr.requested_by LEFT JOIN users u2 ON u2.id = lr.processed_by ' . ($where?$where:'') . ' ORDER BY COALESCE(lr.result_date, lr.created_at) DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset;
$stmt=$db->prepare($list_sql); $stmt->execute($params); $items=$stmt->fetchAll();

$pending_total=0; $ready_total=0; $reviewed_total=0; $urgent_total=0;
try { $sql='SELECT COUNT(*) AS c FROM lab_results lr ' . ($where? $where . ' AND ' : 'WHERE ') . "COALESCE(lr.status,'Pending')='Pending'"; $st=$db->prepare($sql); $st->execute($params); $pending_total=(int)$st->fetch()['c']; } catch (Throwable $e) { }
try { $sql='SELECT COUNT(*) AS c FROM lab_results lr ' . ($where? $where . ' AND ' : 'WHERE ') . "COALESCE(lr.status,'Pending')='Ready'"; $st=$db->prepare($sql); $st->execute($params); $ready_total=(int)$st->fetch()['c']; } catch (Throwable $e) { }
try { $sql='SELECT COUNT(*) AS c FROM lab_results lr ' . ($where? $where . ' AND ' : 'WHERE ') . "COALESCE(lr.status,'Pending')='Reviewed'"; $st=$db->prepare($sql); $st->execute($params); $reviewed_total=(int)$st->fetch()['c']; } catch (Throwable $e) { }
try { $sql='SELECT COUNT(*) AS c FROM lab_results lr ' . ($where? $where . ' AND ' : 'WHERE ') . "COALESCE(lr.priority,'Routine')='Urgent'"; $st=$db->prepare($sql); $st->execute($params); $urgent_total=(int)$st->fetch()['c']; } catch (Throwable $e) { }

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<style>
.lab-hero{background:linear-gradient(90deg,#6f42c1,#20c997);color:#fff;border-radius:12px;padding:18px 20px;box-shadow:0 6px 20px rgba(0,0,0,.12)}
.lab-hero .icon{font-size:2rem}
.lab-hero-title{font-size:1.25rem;font-weight:600}
.lab-stats .card{border:0;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.08)}
.lab-stats .card .display-6{font-size:2rem}
.lab-stats .stat-label{color:#6c757d}
.badge-priority{background:#6c757d}
</style>
<div class="container py-4">
    <div class="lab-hero mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-beaker icon"></i>
                <div>
                    <div class="lab-hero-title">Lab Orders & Results</div>
                    <div class="small text-white-50">Filtered view based on role and search criteria</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (current_account_type()==='Laboratorist') { ?><a class="btn btn-light" href="/hms/public/lab_results/create.php"><i class="bi bi-plus-lg"></i> Add Result</a><?php } ?>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 lab-stats">
        <div class="col-md-3">
            <div class="card border-primary"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="stat-label small">Pending</div><div class="h4 mb-0"><?php echo (int)$pending_total; ?></div></div><i class="bi bi-hourglass-split text-primary display-6"></i></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-info"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="stat-label small">Ready</div><div class="h4 mb-0"><?php echo (int)$ready_total; ?></div></div><i class="bi bi-check2-circle text-info display-6"></i></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-success"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="stat-label small">Reviewed</div><div class="h4 mb-0"><?php echo (int)$reviewed_total; ?></div></div><i class="bi bi-journal-check text-success display-6"></i></div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="stat-label small">Urgent</div><div class="h4 mb-0"><?php echo (int)$urgent_total; ?></div></div><i class="bi bi-exclamation-triangle text-danger display-6"></i></div></div>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light"><div class="d-flex align-items-center"><div class="me-auto fw-semibold">Results</div><?php if (current_account_type()==='Laboratorist') { ?><a class="btn btn-sm btn-primary" href="/hms/public/lab_results/create.php"><i class="bi bi-plus-lg"></i> Add</a><?php } ?></div></div>
        <div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col-sm-9 col-md-7"><input type="text" class="form-control" name="q" placeholder="Search" value="<?php echo sanitize($q); ?>"></div>
                <div class="col-sm-3 col-md-3"><select name="limit" class="form-select" onchange="this.form.submit()"><?php foreach([10,25,50,100] as $n){ ?><option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option><?php } ?></select></div>
                <?php if ($is_admin) { ?>
                <div class="col-sm-12 col-md-3">
                    <select name="doctor_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Doctors</option>
                        <?php foreach ($doctors as $dr) { $sel = $doctor_id===(int)$dr['id']?'selected':''; ?>
                            <option value="<?php echo (int)$dr['id']; ?>" <?php echo $sel; ?>><?php echo sanitize($dr['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } ?>
                <div class="col-sm-12 col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle"><thead class="table-light"><tr>
                    <th style="width:60px">#</th><th>Patient</th><th>Test</th><th>Priority</th><th>Ordering Doctor</th><th>Status</th><th>Result</th><th>Date</th><th>Processed By</th>
                </tr></thead><tbody>
                    <?php $i=$offset+1; foreach($items as $row){ ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
                            <td><?php echo sanitize($row['test_name']); ?></td>
                            <td><span class="badge bg-<?php echo ($row['priority']??'Routine')==='Urgent'?'danger':'secondary'; ?>"><?php echo sanitize($row['priority'] ?? 'Routine'); ?></span></td>
                            <td><?php echo sanitize($row['doctor_name'] ?? '—'); ?></td>
                            <td><span class="badge bg-<?php $st=$row['status']??'Pending'; echo $st==='Pending'?'warning':($st==='Ready'?'info':'success'); ?>"><?php echo sanitize($st); ?></span></td>
                            <?php $disp = isset($row['result']) ? (string)$row['result'] : trim(((string)($row['result_value'] ?? '')) . ' ' . ((string)($row['result_unit'] ?? ''))); ?>
                            <td><?php echo nl2br(sanitize($disp)); ?></td>
                            <td><?php echo sanitize($row['result_date'] ? date('d/m/Y H:i', strtotime($row['result_date'])) : substr($row['created_at'],0,16)); ?></td>
                            <td><?php echo sanitize($row['technician_name'] ?? '—'); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if(!$items){ ?><tr><td colspan="5" class="text-center text-muted">No results found</td></tr><?php } ?>
                </tbody></table>
            </div>
            <?php $total_pages=max(1,(int)ceil($total/$limit)); ?>
            <nav><ul class="pagination"><?php for($p=1;$p<=$total_pages;$p++){ $active=$p===$page?'active':''; ?><li class="page-item <?php echo $active; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a></li><?php } ?></ul></nav>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
