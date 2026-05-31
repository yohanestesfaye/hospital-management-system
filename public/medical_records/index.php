<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Doctor','Nurse']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Bootstrap table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS medical_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        record_type VARCHAR(50) NOT NULL,
        diagnosis TEXT NULL,
        treatment_plan TEXT NULL,
        notes TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$db->query("SHOW COLUMNS FROM medical_records LIKE 'diagnosis'" )->fetch()) { $db->exec("ALTER TABLE medical_records ADD COLUMN diagnosis TEXT NULL"); }
    if (!$db->query("SHOW COLUMNS FROM medical_records LIKE 'treatment_plan'" )->fetch()) { $db->exec("ALTER TABLE medical_records ADD COLUMN treatment_plan TEXT NULL"); }
} catch (Throwable $e) { }

$q = get('q','');
$limit=(int)get('limit',10); if(!in_array($limit,[10,25,50,100],true)){ $limit=10; }
$page=max(1,(int)get('page',1)); $offset=($page-1)*$limit;

$whereParts=[]; $params=[];
if($q!==''){ $like='%'.$q.'%'; $whereParts[]='(mr.record_type LIKE ? OR mr.notes LIKE ?)'; $params=[$like,$like]; }
$acct=current_account_type();
if(in_array($acct,['Doctor','Nurse'],true)){ $whereParts[]='mr.created_by = ?'; $params[]=current_user_id(); }
$where = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$count_sql='SELECT COUNT(*) AS c FROM medical_records mr '.$where;
$stmt=$db->prepare($count_sql); $stmt->execute($params); $total=(int)$stmt->fetch()['c'];

$list_sql='SELECT mr.*, CONCAT(p.first_name," ",p.last_name) AS patient_name FROM medical_records mr JOIN patients p ON p.id=mr.patient_id ' . ($where?$where:'') . ' ORDER BY mr.created_at DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset;
$stmt=$db->prepare($list_sql); $stmt->execute($params); $items=$stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Medical Records</h1>
        <?php if (in_array(current_account_type(), ['Doctor','Nurse'], true)) { ?><a class="btn btn-primary" href="/hms/public/medical_records/create.php">Add Record</a><?php } ?>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light"><div class="d-flex align-items-center"><div class="me-auto fw-semibold">Records</div><?php if (in_array(current_account_type(), ['Doctor','Nurse'], true)) { ?><a class="btn btn-sm btn-primary" href="/hms/public/medical_records/create.php"><i class="bi bi-plus-lg"></i> Add</a><?php } ?></div></div>
        <div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col-sm-9 col-md-7"><input type="text" class="form-control" name="q" placeholder="Search" value="<?php echo sanitize($q); ?>"></div>
                <div class="col-sm-3 col-md-3"><select name="limit" class="form-select" onchange="this.form.submit()"><?php foreach([10,25,50,100] as $n){ ?><option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option><?php } ?></select></div>
                <div class="col-sm-12 col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle"><thead class="table-light"><tr>
                    <th style="width:60px">#</th><th>Patient</th><th>Type</th><th>Diagnosis</th><th>Treatment Plan</th><th>Date</th>
                </tr></thead><tbody>
                    <?php $i=$offset+1; foreach($items as $row){ ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
                            <td><?php echo sanitize($row['record_type']); ?></td>
                            <td><?php echo nl2br(sanitize($row['diagnosis'])); ?></td>
                            <td><?php echo nl2br(sanitize($row['treatment_plan'])); ?></td>
                            <td><?php echo sanitize(date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if(!$items){ ?><tr><td colspan="5" class="text-center text-muted">No records found</td></tr><?php } ?>
                </tbody></table>
            </div>
            <?php $total_pages=max(1,(int)ceil($total/$limit)); ?>
            <nav><ul class="pagination"><?php for($p=1;$p<=$total_pages;$p++){ $active=$p===$page?'active':''; ?><li class="page-item <?php echo $active; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a></li><?php } ?></ul></nav>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
