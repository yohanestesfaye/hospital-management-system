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
        INDEX(patient_id), INDEX(invoice_number), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

// Filters
$search = get('search', '');
$date_filter = get('date_filter', 'today');
$payment_method_filter = get('payment_method', '');
$status_filter = get('status', '');

$where = [];
$params = [];

if ($search) {
    $where[] = "(CONCAT(pat.first_name, ' ', pat.last_name) LIKE ? OR i.invoice_number LIKE ? OR p.receipt_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($date_filter === 'today') {
    $where[] = "DATE(p.created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where[] = "DATE(p.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $where[] = "DATE(p.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

if ($payment_method_filter) {
    $where[] = "p.payment_method = ?";
    $params[] = $payment_method_filter;
}

if ($status_filter) {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$limit = (int)get('limit', 25);
if (!in_array($limit, [10, 25, 50, 100], true)) {
    $limit = 25;
}
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Count total
$count_sql = "SELECT COUNT(*) AS c FROM payments p 
    LEFT JOIN invoices i ON p.invoice_id = i.id 
    JOIN patients pat ON p.patient_id = pat.id 
    $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

// Get transactions
$transactions = [];
$sql = "SELECT p.*, i.invoice_number, i.status AS invoice_status, 
    CONCAT(pat.first_name, ' ', pat.last_name) AS patient_name,
    u.name AS processed_by_name
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.processed_by = u.id
    $where_sql
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$total_pages = max(1, (int)ceil($total / $limit));

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-clock-history"></i> Transaction History</h2>
    </div>

    <!-- Filters -->
    <div class="card card-modern mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Patient, Invoice, Receipt..." value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date Range</label>
                    <select name="date_filter" class="form-select">
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="">All Methods</option>
                        <option value="Cash" <?php echo $payment_method_filter === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                        <option value="Card" <?php echo $payment_method_filter === 'Card' ? 'selected' : ''; ?>>Card</option>
                        <option value="Mobile Money" <?php echo $payment_method_filter === 'Mobile Money' ? 'selected' : ''; ?>>Mobile Money</option>
                        <option value="Bank Transfer" <?php echo $payment_method_filter === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                        <option value="Insurance" <?php echo $payment_method_filter === 'Insurance' ? 'selected' : ''; ?>>Insurance</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Unpaid" <?php echo $status_filter === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="Partial" <?php echo $status_filter === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="/hms/public/payments/history.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card card-modern">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Transactions (<?php echo $total; ?> total)</h5>
            <div class="d-flex align-items-center gap-2">
                <select name="limit" class="form-select form-select-sm" style="width: auto;" onchange="window.location.href = updateURLParam('limit', this.value)">
                    <?php foreach ([10, 25, 50, 100] as $n) { ?>
                        <option value="<?php echo $n; ?>" <?php echo $limit === $n ? 'selected' : ''; ?>>Show <?php echo $n; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-modern">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Invoice #</th>
                            <th>Patient</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Cashier</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)) { ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No transactions found</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($transactions as $txn) { ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                    <td><code><?php echo sanitize($txn['invoice_number'] ?? 'N/A'); ?></code></td>
                                    <td><?php echo sanitize($txn['patient_name']); ?></td>
                                    <td class="fw-bold text-success"><?php echo format_currency((float)$txn['amount']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($txn['payment_method']); ?></span></td>
                                    <td>
                                        <?php
                                        $status = $txn['invoice_status'] ?? 'Paid';
                                        $status_colors = [
                                            'Paid' => 'success',
                                            'Unpaid' => 'warning',
                                            'Partial' => 'info'
                                        ];
                                        $color = $status_colors[$status] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo sanitize($status); ?></span>
                                    </td>
                                    <td><?php echo sanitize($txn['processed_by_name'] ?? 'System'); ?></td>
                                    <td>
                                        <?php if ($txn['receipt_number']) { ?>
                                            <a href="/hms/public/payments/receipt.php?id=<?php echo (int)$txn['id']; ?>" 
                                               class="text-decoration-none" target="_blank">
                                                <code class="small"><?php echo sanitize($txn['receipt_number']); ?></code>
                                                <i class="bi bi-box-arrow-up-right ms-1"></i>
                                            </a>
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination mb-0 justify-content-center">
                            <?php for ($p = 1; $p <= $total_pages; $p++) { ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>">
                                        <?php echo $p; ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
function updateURLParam(param, value) {
    const url = new URL(window.location);
    url.searchParams.set(param, value);
    return url.toString();
}
</script>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

