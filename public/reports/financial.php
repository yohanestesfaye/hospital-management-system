<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure tables exist
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NULL,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash','Card','Mobile Money','Insurance','Bank Transfer') NOT NULL,
        receipt_number VARCHAR(50) NULL,
        processed_by INT NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(invoice_id), INDEX(patient_id), INDEX(processed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

try { 
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
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
        payment_method VARCHAR(30) NULL,
        processed_by INT NULL,
        prescription_id INT NULL,
        INDEX(patient_id), INDEX(invoice_number), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}
// Ensure legacy installations have required columns
try { $db->exec("ALTER TABLE invoices ADD COLUMN source VARCHAR(30) NULL"); } catch (Throwable $e) {}
try { $db->exec("ALTER TABLE invoices ADD COLUMN prescription_id INT NULL"); } catch (Throwable $e) {}

$report_type = get('type', 'daily');
$date = get('date', date('Y-m-d'));
$start_date = get('start_date', date('Y-m-d', strtotime('-7 days')));
$end_date = get('end_date', date('Y-m-d'));

// Calculate report data
$report_data = [];

if ($report_type === 'daily') {
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(p.amount), 0) AS total_revenue,
        COUNT(DISTINCT p.id) AS total_payments,
        COUNT(DISTINCT CASE WHEN i.source = 'Pharmacy' THEN p.id END) AS pharmacy_count,
        COUNT(DISTINCT CASE WHEN i.source = 'Lab' THEN p.id END) AS lab_count,
        COUNT(DISTINCT CASE WHEN i.source = 'Doctor' THEN p.id END) AS doctor_count,
        COALESCE(SUM(CASE WHEN i.source = 'Pharmacy' THEN p.amount ELSE 0 END), 0) AS pharmacy_revenue,
        COALESCE(SUM(CASE WHEN i.source = 'Lab' THEN p.amount ELSE 0 END), 0) AS lab_revenue,
        COALESCE(SUM(CASE WHEN i.source = 'Doctor' THEN p.amount ELSE 0 END), 0) AS doctor_revenue
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        WHERE DATE(p.created_at) = ?");
    $stmt->execute([$date]);
    $report_data = $stmt->fetch();
} elseif ($report_type === 'weekly' || $report_type === 'monthly') {
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(p.amount), 0) AS total_revenue,
        COUNT(DISTINCT p.id) AS total_payments,
        COALESCE(SUM(CASE WHEN i.source = 'Pharmacy' THEN p.amount ELSE 0 END), 0) AS pharmacy_revenue,
        COALESCE(SUM(CASE WHEN i.source = 'Lab' THEN p.amount ELSE 0 END), 0) AS lab_revenue,
        COALESCE(SUM(CASE WHEN i.source = 'Doctor' THEN p.amount ELSE 0 END), 0) AS doctor_revenue
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        WHERE DATE(p.created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $report_data = $stmt->fetch();
}

// Department income summary
$department_summary = [];
try {
    $stmt = $db->prepare("SELECT 
        COALESCE(i.source, 'Other') AS department,
        COUNT(DISTINCT p.id) AS payment_count,
        COALESCE(SUM(p.amount), 0) AS total_revenue
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        GROUP BY department
        ORDER BY total_revenue DESC");
    $stmt->execute([$start_date, $end_date]);
    $department_summary = $stmt->fetchAll();
} catch (Throwable $e) {}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-graph-up-arrow"></i> Financial Reports</h2>
    </div>

    <!-- Report Filters -->
    <div class="card card-modern mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Report</option>
                        <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly Report</option>
                        <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Report</option>
                    </select>
                </div>
                <?php if ($report_type === 'daily') { ?>
                    <div class="col-md-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo sanitize($date); ?>">
                    </div>
                <?php } else { ?>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo sanitize($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo sanitize($end_date); ?>">
                    </div>
                <?php } ?>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Generate</button>
                    <button type="button" class="btn btn-outline-success" onclick="exportPDF()">
                        <i class="bi bi-file-earmark-pdf"></i> Export PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Summary -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Revenue</div>
                    <div class="h3 mb-0 fw-bold text-success"><?php echo format_currency((float)($report_data['total_revenue'] ?? 0)); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Payments</div>
                    <div class="h3 mb-0 fw-bold text-primary"><?php echo (int)($report_data['total_payments'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Pharmacy Revenue</div>
                    <div class="h3 mb-0 fw-bold" style="color: #8b5cf6;"><?php echo format_currency((float)($report_data['pharmacy_revenue'] ?? 0)); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Lab Revenue</div>
                    <div class="h3 mb-0 fw-bold" style="color: #ec4899;"><?php echo format_currency((float)($report_data['lab_revenue'] ?? 0)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Department Income Summary -->
    <div class="card card-modern">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Department Income Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Department</th>
                            <th>Payment Count</th>
                            <th>Total Revenue</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_rev = (float)($report_data['total_revenue'] ?? 0);
                        foreach ($department_summary as $dept) { 
                            $percentage = $total_rev > 0 ? (($dept['total_revenue'] / $total_rev) * 100) : 0;
                        ?>
                            <tr>
                                <td><strong><?php echo sanitize($dept['department']); ?></strong></td>
                                <td><?php echo (int)$dept['payment_count']; ?></td>
                                <td class="fw-bold text-success"><?php echo format_currency((float)$dept['total_revenue']); ?></td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($department_summary)) { ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No data available</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportPDF() {
    window.print();
    // In a real implementation, you would generate a PDF server-side
    alert('PDF export functionality would be implemented here. For now, use browser print.');
}
</script>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

