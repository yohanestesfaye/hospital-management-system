<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$date = get('date', date('Y-m-d'));
$start = $date . ' 00:00:00';
$end = $date . ' 23:59:59';

try { $db->query('SELECT 1 FROM invoices LIMIT 1'); } catch (PDOException $e) { $db->exec('CREATE TABLE IF NOT EXISTS invoices (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NULL, patient_id INT NOT NULL, invoice_number VARCHAR(50) NOT NULL, amount DECIMAL(10,2) NOT NULL, status VARCHAR(20) DEFAULT "Unpaid", source VARCHAR(30) NULL, due_date DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, paid_date DATETIME NULL, receipt_number VARCHAR(50) NULL, INDEX(patient_id), INDEX(invoice_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'); }

$rows = [];
$stmt = $db->prepare('SELECT i.invoice_number, i.amount, i.status, i.source, i.created_at, i.paid_date, p.id AS patient_id, CONCAT(p.first_name, " ", p.last_name) AS patient_name FROM invoices i JOIN patients p ON p.id = i.patient_id WHERE i.created_at BETWEEN ? AND ? ORDER BY i.created_at ASC');
$stmt->execute([$start,$end]);
$rows = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h5 mb-0">Daily Financial Report</h1>
        <form class="d-flex align-items-center" method="get">
            <input type="date" class="form-control form-control-sm me-2" name="date" value="<?php echo sanitize($date); ?>">
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
        </form>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr>
                        <th>Patient</th><th>Patient ID</th><th>Invoice</th><th>Source</th><th>Amount</th><th>Status</th><th>Created</th><th>Paid</th>
                    </tr></thead>
                    <tbody>
                        <?php $total=0.0; foreach($rows as $r){ $total += (float)$r['amount']; $badge = $r['status']==='Paid'?'success':($r['status']==='Partial'?'info':'warning'); ?>
                        <tr>
                            <td><?php echo sanitize($r['patient_name']); ?></td>
                            <td><?php echo (int)$r['patient_id']; ?></td>
                            <td><?php echo sanitize($r['invoice_number']); ?></td>
                            <td><?php echo sanitize($r['source'] ?? ''); ?></td>
                            <td><?php echo number_format((float)$r['amount'],2); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($r['status']); ?></span></td>
                            <td><?php echo sanitize(substr($r['created_at'],0,16)); ?></td>
                            <td><?php echo sanitize($r['paid_date'] ? substr($r['paid_date'],0,16) : '—'); ?></td>
                        </tr>
                        <?php } ?>
                        <?php if(!$rows){ ?><tr><td colspan="8" class="text-center text-muted">No records for selected date</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-2">
                <strong>Total: </strong> <?php echo number_format($total,2); ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

