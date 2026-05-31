<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure tables exist
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
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id), INDEX(action), INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

$errors = [];
$success = false;
$selected_invoice = null;

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = (int)post('invoice_id');
    $amount_paid = (float)post('amount_paid');
    $payment_method = post('payment_method');
    $notes = post('notes');
    
    if (!$invoice_id) {
        $errors[] = 'Please select an invoice';
    }
    if ($amount_paid <= 0) {
        $errors[] = 'Amount must be greater than zero';
    }
    if (!in_array($payment_method, ['Cash', 'Card', 'Mobile Money', 'Insurance', 'Bank Transfer'], true)) {
        $errors[] = 'Invalid payment method';
    }
    
    if (empty($errors)) {
        // Get invoice details
        $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ? AND status = "Unpaid"');
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();
        
        if ($invoice) {
            if ($amount_paid > (float)$invoice['amount']) {
                $errors[] = 'Amount paid cannot exceed invoice amount';
            } else {
                $db->beginTransaction();
                try {
                    // Generate receipt number
                    $receipt_number = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$invoice_id, true)), 0, 6));
                    
                    // Create payment record
                    $stmt = $db->prepare('INSERT INTO payments (invoice_id, patient_id, amount, payment_method, receipt_number, processed_by, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $invoice_id,
                        (int)$invoice['patient_id'],
                        $amount_paid,
                        $payment_method,
                        $receipt_number,
                        current_user_id(),
                        $notes
                    ]);
                    $payment_id = (int)$db->lastInsertId();
                    
                    // Update invoice status
                    if ($amount_paid >= (float)$invoice['amount']) {
                        $stmt = $db->prepare('UPDATE invoices SET status = "Paid", paid_date = NOW(), receipt_number = ?, payment_method = ?, processed_by = ? WHERE id = ?');
                        $stmt->execute([$receipt_number, $payment_method, current_user_id(), $invoice_id]);
                    } else {
                        // Partial payment
                        $stmt = $db->prepare('UPDATE invoices SET status = "Partial", receipt_number = ?, payment_method = ?, processed_by = ? WHERE id = ?');
                        $stmt->execute([$receipt_number, $payment_method, current_user_id(), $invoice_id]);
                    }
                    
                    // Log audit
                    $stmt = $db->prepare('INSERT INTO audit_logs (user_id, user_name, action, entity_type, entity_id, details, ip_address) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        current_user_id(),
                        current_user_name(),
                        'Payment Collected',
                        'Invoice',
                        $invoice_id,
                        "Payment of {$amount_paid} via {$payment_method} for invoice #{$invoice['invoice_number']}",
                        $_SERVER['REMOTE_ADDR'] ?? null
                    ]);
                    
                    $db->commit();
                    $success = true;
                    $selected_invoice = null; // Reset selection
                    // Store receipt number and payment ID for success message
                    $_SESSION['last_receipt_number'] = $receipt_number;
                    $_SESSION['last_payment_id'] = $payment_id;
                } catch (Throwable $e) {
                    try { if ($db->inTransaction()) { $db->rollBack(); } } catch (Throwable $ignore) {}
                    $errors[] = 'Payment processing failed: ' . $e->getMessage();
                }
            }
        } else {
            $errors[] = 'Invoice not found or already paid';
        }
    }
}

// Get unpaid invoices
$unpaid_invoices = [];
try {
    $stmt = $db->prepare("SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        WHERE i.status = 'Unpaid' 
        ORDER BY i.created_at DESC");
    $stmt->execute();
    $unpaid_invoices = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get selected invoice details
if (isset($_GET['invoice_id'])) {
    $invoice_id = (int)$_GET['invoice_id'];
    $stmt = $db->prepare('SELECT i.*, CONCAT(p.first_name, " ", p.last_name) AS patient_name 
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        WHERE i.id = ?');
    $stmt->execute([$invoice_id]);
    $selected_invoice = $stmt->fetch();
}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-cash-coin"></i> Payment Collection</h2>
        <a href="/hms/public/payments/history.php" class="btn btn-outline-secondary">View History</a>
    </div>

    <?php 
    $receipt_number = $_SESSION['last_receipt_number'] ?? null;
    $payment_id = $_SESSION['last_payment_id'] ?? null;
    if ($success && $receipt_number) {
        unset($_SESSION['last_receipt_number'], $_SESSION['last_payment_id']);
    ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> Payment processed successfully! Receipt number: <strong><?php echo sanitize($receipt_number); ?></strong>
            <?php if ($payment_id) { ?>
                <a href="/hms/public/payments/receipt.php?id=<?php echo $payment_id; ?>" class="btn btn-sm btn-outline-success ms-2" target="_blank">
                    View Receipt
                </a>
            <?php } ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php } ?>

    <?php if ($errors) { ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sanitize($error); ?></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <div class="row g-4">
        <!-- Invoice Selection -->
        <div class="col-lg-5">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-receipt"></i> Select Invoice</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if (empty($unpaid_invoices)) { ?>
                            <div class="text-center text-muted py-4">No unpaid invoices</div>
                        <?php } else { ?>
                            <?php foreach ($unpaid_invoices as $inv) { ?>
                                <a href="?invoice_id=<?php echo (int)$inv['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $selected_invoice && $selected_invoice['id'] == $inv['id'] ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo sanitize($inv['patient_name']); ?></h6>
                                            <small class="text-muted">Invoice #<?php echo sanitize($inv['invoice_number']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-success"><?php echo format_currency((float)$inv['amount']); ?></div>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </a>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="col-lg-7">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-credit-card"></i> Process Payment</h5>
                </div>
                <div class="card-body">
                    <?php if ($selected_invoice) { ?>
                        <div class="alert alert-info mb-4">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>Invoice #<?php echo sanitize($selected_invoice['invoice_number']); ?></strong><br>
                                    <small>Patient: <?php echo sanitize($selected_invoice['patient_name']); ?></small>
                                </div>
                                <div class="text-end">
                                    <div class="h4 mb-0 text-primary"><?php echo format_currency((float)$selected_invoice['amount']); ?></div>
                                    <small class="text-muted">Total Due</small>
                                </div>
                            </div>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="invoice_id" value="<?php echo (int)$selected_invoice['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label form-required">Amount Received</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?php echo sanitize($env['currency_symbol']); ?></span>
                                    <input type="number" step="0.01" min="0" max="<?php echo (float)$selected_invoice['amount']; ?>" 
                                           name="amount_paid" class="form-control" 
                                           value="<?php echo (float)$selected_invoice['amount']; ?>" required>
                                </div>
                                <small class="text-muted">Maximum: <?php echo format_currency((float)$selected_invoice['amount']); ?></small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label form-required">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select method</option>
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Insurance">Insurance</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle"></i> Process Payment & Generate Receipt
                                </button>
                                <a href="/hms/public/payments/collect.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php } else { ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-arrow-left-circle" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-3">Please select an invoice from the list to process payment</p>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

