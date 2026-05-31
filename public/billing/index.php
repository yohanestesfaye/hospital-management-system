<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();
// Ensure extended billing schema
try { $db->exec("ALTER TABLE bills ADD COLUMN source VARCHAR(30) NULL"); } catch (Throwable $e) {}
try { $db->exec("ALTER TABLE bills ADD COLUMN approved_by INT NULL"); } catch (Throwable $e) {}
try { $db->exec("ALTER TABLE bills ADD COLUMN invoice_id INT NULL"); } catch (Throwable $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NULL,
    patient_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'Unpaid',
    source VARCHAR(30) NULL,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_date DATETIME NULL,
    receipt_number VARCHAR(50) NULL,
    INDEX(patient_id), INDEX(invoice_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
try { $db->exec("CREATE TABLE IF NOT EXISTS finance_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    reference VARCHAR(100) NULL,
    source VARCHAR(30) NULL,
    description VARCHAR(255) NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}

// Bootstrap table
try {
    $db->exec("CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'Unpaid',
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_date DATETIME NULL,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { }

$q = get('q','');
$limit=(int)get('limit',10); if(!in_array($limit,[10,25,50,100],true)){ $limit=10; }
$page=max(1,(int)get('page',1)); $offset=($page-1)*$limit;

$where=''; $params=[];
if($q!==''){ $like='%'.$q.'%'; $where='WHERE CONCAT(p.first_name," ",p.last_name) LIKE ?'; $params=[$like]; }

$count_sql='SELECT COUNT(*) AS c FROM bills b JOIN patients p ON p.id=b.patient_id '.$where;
$stmt=$db->prepare($count_sql); $stmt->execute($params); $total=(int)$stmt->fetch()['c'];

$list_sql='SELECT b.*, CONCAT(p.first_name," ",p.last_name) AS patient_name FROM bills b JOIN patients p ON p.id=b.patient_id ' . ($where?$where:'') . ' ORDER BY b.issued_date DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset;
$stmt=$db->prepare($list_sql); $stmt->execute($params); $items=$stmt->fetchAll();

include __DIR__ . '/../../includes/accountant-header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-receipt-cutoff"></i> Billing Management</h2>
        <a class="btn btn-primary btn-modern" href="/hms/public/billing/new.php">
            <i class="bi bi-plus-lg"></i> Generate Bill
        </a>
    </div>
    <div class="card card-modern mb-3">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex align-items-center">
                <h5 class="mb-0 me-auto"><i class="bi bi-list-ul"></i> Bills</h5>
                <a class="btn btn-sm btn-primary" href="/hms/public/billing/new.php"><i class="bi bi-plus-lg"></i> New</a>
            </div>
        </div>
        <div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col-sm-9 col-md-7"><input type="text" class="form-control" name="q" placeholder="Search patient" value="<?php echo sanitize($q); ?>"></div>
                <div class="col-sm-3 col-md-3"><select name="limit" class="form-select" onchange="this.form.submit()"><?php foreach([10,25,50,100] as $n){ ?><option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option><?php } ?></select></div>
                <div class="col-sm-12 col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle table-modern"><thead><tr>
                    <th style="width:60px">#</th><th>Patient</th><th>Amount</th><th>Status</th><th>Issued</th><th style="width:260px">Options</th>
                </tr></thead><tbody>
                    <?php $i=$offset+1; foreach($items as $row){ $badge = ($row['status']==='Paid')?'success':(($row['status']==='Approved'||$row['status']==='Invoiced')?'info':'warning'); ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
                            <td><?php echo number_format((float)$row['amount'],2); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($row['status']); ?></span></td>
                            <td><?php echo sanitize(date('d/m/Y H:i', strtotime($row['issued_date']))); ?></td>
                            <td>
                                <?php if ($row['status']==='Unpaid') { ?>
                                    <a class="btn btn-sm btn-outline-primary" href="/hms/public/billing/approve.php?id=<?php echo (int)$row['id']; ?>">Approve</a>
                                <?php } ?>
                                <?php if ($row['status']==='Approved' && empty($row['invoice_id'])) { ?>
                                    <a class="btn btn-sm btn-primary" href="/hms/public/billing/finalize_invoice.php?id=<?php echo (int)$row['id']; ?>">Finalize</a>
                                <?php } ?>
                                <?php if ($row['status']!=='Paid') { ?>
                                    <a class="btn btn-sm btn-success" href="/hms/public/billing/mark_paid.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Mark this bill and invoice as paid?');"><i class="bi bi-cash-coin"></i></a>
                                <?php } else { ?><span class="text-muted small">Paid</span><?php } ?>
                                <a class="btn btn-sm btn-outline-secondary" href="/hms/public/billing/daily_report.php">Daily Report</a>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if(!$items){ ?><tr><td colspan="6" class="text-center text-muted">No bills found</td></tr><?php } ?>
                </tbody></table>
            </div>
            <?php $total_pages=max(1,(int)ceil($total/$limit)); ?>
            <nav><ul class="pagination"><?php for($p=1;$p<=$total_pages;$p++){ $active=$p===$page?'active':''; ?><li class="page-item <?php echo $active; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a></li><?php } ?></ul></nav>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>
