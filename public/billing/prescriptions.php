<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure tables exist
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
        quantity INT DEFAULT 1,
        unit_price DECIMAL(10,2) DEFAULT 0,
        total_price DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id), INDEX(doctor_id)
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
        INDEX(patient_id), INDEX(invoice_number), INDEX(prescription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

/**
 * Create (or retrieve) an invoice for the given prescription row.
 * Returns invoice metadata array or null on failure.
 */
function create_invoice_for_prescription(PDO $db, array $row) {
    $prescriptionId = isset($row['id']) ? (int)$row['id'] : 0;
    if ($prescriptionId <= 0) { return null; }

    // If invoice already exists in DB, return it.
    try {
        $existing = $db->prepare("SELECT id, invoice_number, status, amount, bill_id FROM invoices WHERE prescription_id = ? LIMIT 1");
        $existing->execute([$prescriptionId]);
        $invoiceRow = $existing->fetch();
        if ($invoiceRow) {
            return [
                'invoice_id' => (int)$invoiceRow['id'],
                'invoice_number' => $invoiceRow['invoice_number'],
                'invoice_status' => $invoiceRow['status'],
                'invoice_amount' => (float)$invoiceRow['amount'],
                'bill_id' => (int)($invoiceRow['bill_id'] ?? 0)
            ];
        }
    } catch (Throwable $e) { }

    $patientId = isset($row['patient_id']) ? (int)$row['patient_id'] : 0;
    $dispensedAt = isset($row['dispensed_at']) && $row['dispensed_at'] ? $row['dispensed_at'] : date('Y-m-d H:i:s');
    $quantity = isset($row['quantity']) ? (int)$row['quantity'] : 1;
    if ($quantity <= 0) { $quantity = 1; }
    $unitPrice = isset($row['unit_price']) ? (float)$row['unit_price'] : 0;
    $totalPrice = isset($row['total_price']) ? (float)$row['total_price'] : 0;
    if ($totalPrice <= 0 && $unitPrice > 0) {
        $totalPrice = $unitPrice * $quantity;
    }
    if ($totalPrice <= 0) { return null; }

    try {
        $db->beginTransaction();

        // If invoice was created between the earlier check and now, fetch again.
        $existing = $db->prepare("SELECT id, invoice_number, status, amount, bill_id FROM invoices WHERE prescription_id = ? LIMIT 1 FOR UPDATE");
        $existing->execute([$prescriptionId]);
        $invoiceRow = $existing->fetch();
        if ($invoiceRow) {
            $db->commit();
            return [
                'invoice_id' => (int)$invoiceRow['id'],
                'invoice_number' => $invoiceRow['invoice_number'],
                'invoice_status' => $invoiceRow['status'],
                'invoice_amount' => (float)$invoiceRow['amount'],
                'bill_id' => (int)($invoiceRow['bill_id'] ?? 0)
            ];
        }

        // Find existing pharmacy bill (without invoice) matching patient + amount + date
        $billStmt = $db->prepare("SELECT id, amount, status, issued_date, paid_date FROM bills
            WHERE patient_id = ?
              AND source = 'Pharmacy'
              AND (invoice_id IS NULL OR invoice_id = 0)
              AND ABS(amount - ?) < 0.01
              AND DATE(issued_date) = DATE(?)
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, issued_date, ?)) ASC
            LIMIT 1 FOR UPDATE");
        $billStmt->execute([$patientId, $totalPrice, $dispensedAt, $dispensedAt]);
        $bill = $billStmt->fetch();

        if ($bill) {
            $billId = (int)$bill['id'];
            $billStatus = 'Paid';
            $billPaidDate = $bill['paid_date'] ?? date('Y-m-d H:i:s');
            $db->prepare("UPDATE bills SET status = 'Paid', paid_date = ? WHERE id = ?")->execute([$billPaidDate, $billId]);
        } else {
            // Create bill if none found
            $billInsert = $db->prepare("INSERT INTO bills(patient_id, amount, status, source, issued_date, paid_date) VALUES (?,?,?,?,?,NOW())");
            $billInsert->execute([$patientId, $totalPrice, 'Paid', 'Pharmacy', $dispensedAt]);
            $billId = (int)$db->lastInsertId();
            $billStatus = 'Paid';
            $billPaidDate = date('Y-m-d H:i:s');
        }

        $invoiceStatus = 'Paid';
        $invoiceNumber = 'INV-PRX-' . date('Ymd', strtotime($dispensedAt)) . '-' . strtoupper(substr(md5(uniqid((string)$prescriptionId, true)), 0, 6));
        $dueDate = date('Y-m-d', strtotime($dispensedAt . ' +7 days'));
        $paidDate = ($invoiceStatus === 'Paid') ? ($billPaidDate ?? date('Y-m-d H:i:s')) : null;
        $receiptNumber = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$prescriptionId . $patientId, true)), 0, 6));

        $invoiceStmt = $db->prepare("INSERT INTO invoices (bill_id, patient_id, invoice_number, amount, status, source, due_date, created_at, paid_date, receipt_number, prescription_id)
            VALUES (?, ?, ?, ?, ?, 'Pharmacy', ?, ?, ?, ?, ?)");
        $invoiceStmt->execute([
            $billId,
            $patientId,
            $invoiceNumber,
            $totalPrice,
            $invoiceStatus,
            $dueDate,
            $dispensedAt,
            $paidDate,
            $receiptNumber,
            $prescriptionId
        ]);
        $invoiceId = (int)$db->lastInsertId();

        // Link bill to invoice
        $updateBill = $db->prepare("UPDATE bills SET invoice_id = ?, status = 'Paid' WHERE id = ?");
        $updateBill->execute([$invoiceId, $billId]);

        // Ensure prescription total price and unit price are stored
        if ($unitPrice <= 0) {
            $unitPrice = $totalPrice / $quantity;
            if ($unitPrice <= 0) { $unitPrice = $totalPrice; }
        }
        $updatePrescription = $db->prepare("UPDATE prescriptions SET total_price = ?, unit_price = CASE WHEN unit_price > 0 THEN unit_price ELSE ? END WHERE id = ?");
        $updatePrescription->execute([$totalPrice, $unitPrice, $prescriptionId]);

        // Record auto payment for legacy data
        try {
            $paymentCheck = $db->prepare("SELECT id FROM payments WHERE invoice_id = ? LIMIT 1");
            $paymentCheck->execute([$invoiceId]);
            if (!$paymentCheck->fetch()) {
                $processedBy = current_user_id() ?: 0;
                $paymentInsert = $db->prepare("INSERT INTO payments(invoice_id, patient_id, amount, payment_method, receipt_number, processed_by, notes)
                    VALUES (?, ?, ?, 'Cash', ?, ?, 'Auto payment (legacy sync)')");
                $paymentInsert->execute([$invoiceId, $patientId, $totalPrice, $receiptNumber, $processedBy]);
            }
        } catch (Throwable $e) {}

        $db->commit();
        return [
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'invoice_status' => $invoiceStatus,
            'invoice_amount' => $totalPrice,
            'bill_id' => $billId
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
    return null;
}

/**
 * Ensure invoice status reflects actual payments/bill status.
 */
function sync_invoice_status(PDO $db, array $invoice) {
    $invoiceId = (int)($invoice['invoice_id'] ?? 0);
    if ($invoiceId <= 0) { return $invoice; }

    $invoiceStatus = $invoice['invoice_status'] ?? 'Unpaid';
    $invoiceAmount = (float)($invoice['invoice_amount'] ?? 0);

    // If already paid, nothing to do
    if ($invoiceStatus === 'Paid') {
        return $invoice;
    }

    try {
        // Sum payments for this invoice
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS paid_total FROM payments WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $paidTotal = (float)$stmt->fetch()['paid_total'];

        if ($invoiceAmount > 0 && $paidTotal >= $invoiceAmount - 0.01) {
            $receipt = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$invoiceId, true)), 0, 6));
            $upd = $db->prepare("UPDATE invoices SET status = 'Paid', paid_date = NOW(), receipt_number = ? WHERE id = ?");
            $upd->execute([$receipt, $invoiceId]);
            $invoice['invoice_status'] = 'Paid';
        }
    } catch (Throwable $e) {
        // ignore
    }

    return $invoice;
}

/**
 * Ensure every dispensed prescription has a linked bill + invoice.
 * This back-fills legacy records created before the automatic linkage.
 */
function ensure_prescription_invoices(PDO $db) {
    try {
        $stmt = $db->query("SELECT p.id, p.patient_id, p.dispensed_at, p.quantity, p.unit_price, COALESCE(p.total_price, p.quantity * p.unit_price) AS total_price
            FROM prescriptions p
            LEFT JOIN invoices i ON i.prescription_id = p.id
            WHERE p.dispensed = 1 AND (i.id IS NULL)");
        $missing = $stmt->fetchAll();
    } catch (Throwable $e) {
        return;
    }

    foreach ($missing as $row) {
        create_invoice_for_prescription($db, $row);
    }
}

ensure_prescription_invoices($db);

// Filters
$search = get('search', '');
$patient_id = (int)get('patient_id', 0);
$status_filter = get('status', 'all'); // all, paid, unpaid

$where = [];
$params = [];

if ($search) {
    $where[] = "(CONCAT(pa.first_name, ' ', pa.last_name) LIKE ? OR p.medication LIKE ? OR i.invoice_number LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($patient_id > 0) {
    $where[] = "p.patient_id = ?";
    $params[] = $patient_id;
}

if ($status_filter === 'paid') {
    $where[] = "i.status = 'Paid'";
} elseif ($status_filter === 'unpaid') {
    $where[] = "(i.status = 'Unpaid' OR i.status IS NULL)";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get prescription billing data
$prescriptions = [];
try {
    // Build WHERE clause properly
    $where_clauses = ['p.dispensed = 1'];
    $where_params = [];
    
    if ($search) {
        $where_clauses[] = "(CONCAT(pa.first_name, ' ', pa.last_name) LIKE ? OR p.medication LIKE ? OR i.invoice_number LIKE ?)";
        $like = '%' . $search . '%';
        $where_params[] = $like;
        $where_params[] = $like;
        $where_params[] = $like;
    }
    
    if ($patient_id > 0) {
        $where_clauses[] = "p.patient_id = ?";
        $where_params[] = $patient_id;
    }
    
    if ($status_filter === 'paid') {
        $where_clauses[] = "i.status = 'Paid'";
    } elseif ($status_filter === 'unpaid') {
        $where_clauses[] = "(i.status = 'Unpaid' OR i.status IS NULL)";
    }
    
    $where_final = 'WHERE ' . implode(' AND ', $where_clauses);
    
    $sql = "SELECT p.*, 
        CONCAT(pa.first_name, ' ', pa.last_name) AS patient_name,
        pa.id AS patient_id,
        i.id AS invoice_id,
        i.invoice_number,
        i.status AS invoice_status,
        i.amount AS invoice_amount,
        i.created_at AS invoice_date,
        ph.name AS pharma_name,
        ph.unit_price AS pharma_unit_price
        FROM prescriptions p
        JOIN patients pa ON p.patient_id = pa.id
        LEFT JOIN invoices i ON i.prescription_id = p.id
        LEFT JOIN pharmaceuticals ph ON ph.id = p.pharmaceutical_id
        $where_final
        ORDER BY p.dispensed_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($where_params);
    $prescriptions = $stmt->fetchAll();
} catch (Throwable $e) {
    // Log error for debugging
    error_log("Prescription billing query error: " . $e->getMessage());
}

// Ensure each prescription has an invoice (handles edge cases where backfill missed one)
foreach ($prescriptions as &$prx) {
    if (empty($prx['invoice_id'])) {
        $created = create_invoice_for_prescription($db, $prx);
        if ($created) {
            $prx['invoice_id'] = $created['invoice_id'];
            $prx['invoice_number'] = $created['invoice_number'];
            $prx['invoice_status'] = $created['invoice_status'];
            $prx['invoice_amount'] = $created['invoice_amount'];
        }
    }

    if (!empty($prx['invoice_id'])) {
        $synced = sync_invoice_status($db, [
            'invoice_id' => $prx['invoice_id'],
            'invoice_status' => $prx['invoice_status'] ?? null,
            'invoice_amount' => $prx['invoice_amount'] ?? ($prx['total_price'] ?? null)
        ]);
        $prx['invoice_status'] = $synced['invoice_status'] ?? $prx['invoice_status'];
    }
}
unset($prx);

// Get patients for filter
$patients = [];
try {
    $stmt = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM patients ORDER BY first_name, last_name");
    $patients = $stmt->fetchAll();
} catch (Throwable $e) {}

// Calculate totals
$total_revenue = 0;
$total_unpaid = 0;
foreach ($prescriptions as $prx) {
    $amount = (float)($prx['invoice_amount'] ?? $prx['total_price'] ?? 0);
    if ($prx['invoice_status'] === 'Paid') {
        $total_revenue += $amount;
    } else {
        $total_unpaid += $amount;
    }
}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-prescription2"></i> Prescription Billing</h2>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Prescription Revenue</div>
                    <div class="h3 mb-0 fw-bold text-success"><?php echo format_currency($total_revenue); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Unpaid Prescriptions</div>
                    <div class="h3 mb-0 fw-bold text-warning"><?php echo format_currency($total_unpaid); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="text-muted small mb-1">Total Prescriptions</div>
                    <div class="h3 mb-0 fw-bold text-primary"><?php echo count($prescriptions); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card card-modern mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Patient, Medication, Invoice..." value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Patient</label>
                    <select name="patient_id" class="form-select">
                        <option value="0">All Patients</option>
                        <?php foreach ($patients as $p) { ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo $patient_id === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($p['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Prescription Billing Table -->
    <div class="card card-modern">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0">Prescription Billing Details</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-modern">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Medication</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                            <th>Invoice #</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prescriptions)) { ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No prescription billing records found</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($prescriptions as $prx) { 
                                $quantity = max(1, (int)($prx['quantity'] ?? 1));
                                $unit_price = (float)($prx['unit_price'] ?? $prx['pharma_unit_price'] ?? 0);
                                $total_price = (float)($prx['total_price'] ?? ($quantity * $unit_price));
                                $invoice_status = $prx['invoice_status'] ?? 'Unpaid';
                            ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($prx['dispensed_at'])); ?></td>
                                    <td>
                                        <a href="/hms/public/patients/show.php?id=<?php echo (int)$prx['patient_id']; ?>">
                                            <?php echo sanitize($prx['patient_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo sanitize($prx['pharma_name'] ?: $prx['medication']); ?></td>
                                    <td><?php echo $quantity; ?></td>
                                    <td><?php echo format_currency($unit_price); ?></td>
                                    <td class="fw-bold text-success"><?php echo format_currency($total_price); ?></td>
                                    <td>
                                        <?php if ($prx['invoice_number']) { ?>
                                            <code><?php echo sanitize($prx['invoice_number']); ?></code>
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_colors = [
                                            'Paid' => 'success',
                                            'Unpaid' => 'warning',
                                            'Partial' => 'info'
                                        ];
                                        $color = $status_colors[$invoice_status] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo sanitize($invoice_status); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($prx['invoice_id'] && $invoice_status === 'Unpaid') { ?>
                                            <a href="/hms/public/payments/collect.php?invoice_id=<?php echo (int)$prx['invoice_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="bi bi-cash-coin"></i> Collect Payment
                                            </a>
                                        <?php } elseif ($invoice_status === 'Paid') { ?>
                                            <span class="text-muted small">Paid</span>
                                        <?php } else { ?>
                                            <span class="text-muted small">No Invoice</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

