<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure tables exist
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS insurance_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        patient_id INT NOT NULL,
        insurance_provider VARCHAR(100) NULL,
        claim_number VARCHAR(50) NULL,
        status ENUM('Pending','Submitted','Approved','Rejected','Paid') DEFAULT 'Pending',
        submitted_date DATETIME NULL,
        approved_date DATETIME NULL,
        amount DECIMAL(10,2) NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(invoice_id), INDEX(patient_id), INDEX(status)
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

// Handle actions
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    
    if ($action === 'create_claim') {
        $invoice_id = (int)post('invoice_id');
        $insurance_provider = post('insurance_provider');
        $notes = post('notes');
        
        if (!$invoice_id) {
            $errors[] = 'Invoice is required';
        }
        
        if (empty($errors)) {
            // Get invoice details
            $stmt = $db->prepare('SELECT * FROM invoices WHERE id = ?');
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if ($invoice) {
                // Check if claim already exists
                $stmt = $db->prepare('SELECT id FROM insurance_claims WHERE invoice_id = ?');
                $stmt->execute([$invoice_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'Claim already exists for this invoice';
                } else {
                    $claim_number = 'CLM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$invoice_id, true)), 0, 6));
                    $stmt = $db->prepare('INSERT INTO insurance_claims (invoice_id, patient_id, insurance_provider, claim_number, amount, status, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $invoice_id,
                        (int)$invoice['patient_id'],
                        $insurance_provider,
                        $claim_number,
                        (float)$invoice['amount'],
                        'Pending',
                        $notes
                    ]);
                    $success = 'Insurance claim created successfully';
                }
            } else {
                $errors[] = 'Invoice not found';
            }
        }
    } elseif ($action === 'update_status') {
        $claim_id = (int)post('claim_id');
        $status = post('status');
        
        if (!in_array($status, ['Pending', 'Submitted', 'Approved', 'Rejected', 'Paid'], true)) {
            $errors[] = 'Invalid status';
        }
        
        if (empty($errors)) {
            $stmt = $db->prepare('UPDATE insurance_claims SET status = ?, submitted_date = CASE WHEN ? = "Submitted" THEN NOW() ELSE submitted_date END, approved_date = CASE WHEN ? = "Approved" OR ? = "Paid" THEN NOW() ELSE approved_date END WHERE id = ?');
            $stmt->execute([$status, $status, $status, $status, $claim_id]);
            
            // If paid, update invoice
            if ($status === 'Paid') {
                $stmt = $db->prepare('SELECT invoice_id FROM insurance_claims WHERE id = ?');
                $stmt->execute([$claim_id]);
                $claim = $stmt->fetch();
                if ($claim) {
                    $db->prepare('UPDATE invoices SET status = "Paid", paid_date = NOW(), payment_method = "Insurance" WHERE id = ?')
                        ->execute([(int)$claim['invoice_id']]);
                }
            }
            
            $success = 'Claim status updated';
        }
    }
}

// Get insurance invoices
$insurance_invoices = [];
try {
    $stmt = $db->prepare("SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        ic.id AS claim_id, ic.claim_number, ic.status AS claim_status, ic.insurance_provider
        FROM invoices i 
        JOIN patients p ON i.patient_id = p.id 
        LEFT JOIN insurance_claims ic ON i.id = ic.invoice_id
        WHERE i.source = 'Insurance' OR ic.id IS NOT NULL
        ORDER BY i.created_at DESC");
    $stmt->execute();
    $insurance_invoices = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get pending claims
$pending_claims = [];
try {
    $stmt = $db->prepare("SELECT ic.*, i.invoice_number, CONCAT(p.first_name, ' ', p.last_name) AS patient_name
        FROM insurance_claims ic
        JOIN invoices i ON ic.invoice_id = i.id
        JOIN patients p ON ic.patient_id = p.id
        WHERE ic.status IN ('Pending', 'Submitted')
        ORDER BY ic.created_at DESC");
    $stmt->execute();
    $pending_claims = $stmt->fetchAll();
} catch (Throwable $e) {}

$filter = get('filter', 'all');
if ($filter === 'pending') {
    $insurance_invoices = array_filter($insurance_invoices, function($inv) {
        return !isset($inv['claim_id']) || in_array($inv['claim_status'], ['Pending', 'Submitted']);
    });
}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-shield-check"></i> Insurance Claims Management</h2>
        <div class="btn-group">
            <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
            <a href="?filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending</a>
        </div>
    </div>

    <?php if ($success) { ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?php echo sanitize($success); ?>
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
        <!-- Pending Claims -->
        <div class="col-lg-8">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Insurance Claims</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Claim #</th>
                                    <th>Invoice #</th>
                                    <th>Patient</th>
                                    <th>Provider</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pending_claims)) { ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No pending insurance claims</td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($pending_claims as $claim) { ?>
                                        <tr>
                                            <td><code><?php echo sanitize($claim['claim_number']); ?></code></td>
                                            <td><code><?php echo sanitize($claim['invoice_number']); ?></code></td>
                                            <td><?php echo sanitize($claim['patient_name']); ?></td>
                                            <td><?php echo sanitize($claim['insurance_provider'] ?? 'N/A'); ?></td>
                                            <td class="fw-bold"><?php echo format_currency((float)$claim['amount']); ?></td>
                                            <td>
                                                <?php
                                                $status_colors = [
                                                    'Pending' => 'warning',
                                                    'Submitted' => 'info',
                                                    'Approved' => 'success',
                                                    'Rejected' => 'danger',
                                                    'Paid' => 'success'
                                                ];
                                                $color = $status_colors[$claim['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo sanitize($claim['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" 
                                                            data-bs-target="#statusModal<?php echo (int)$claim['id']; ?>">
                                                        Update Status
                                                    </button>
                                                    <a href="/hms/public/reports/insurance.php?claim_id=<?php echo (int)$claim['id']; ?>" 
                                                       class="btn btn-outline-info" target="_blank">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                </div>
                                                
                                                <!-- Status Update Modal -->
                                                <div class="modal fade" id="statusModal<?php echo (int)$claim['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="update_status">
                                                                <input type="hidden" name="claim_id" value="<?php echo (int)$claim['id']; ?>">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Update Claim Status</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Status</label>
                                                                        <select name="status" class="form-select" required>
                                                                            <option value="Pending" <?php echo $claim['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="Submitted" <?php echo $claim['status'] === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                                                            <option value="Approved" <?php echo $claim['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                                                            <option value="Rejected" <?php echo $claim['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                                            <option value="Paid" <?php echo $claim['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-primary">Update</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
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

        <!-- Create New Claim -->
        <div class="col-lg-4">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create Insurance Claim</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_claim">
                        
                        <div class="mb-3">
                            <label class="form-label form-required">Invoice</label>
                            <select name="invoice_id" class="form-select" required>
                                <option value="">Select invoice</option>
                                <?php
                                $stmt = $db->prepare("SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name 
                                    FROM invoices i 
                                    JOIN patients p ON i.patient_id = p.id 
                                    WHERE i.status = 'Unpaid' AND i.source = 'Insurance'
                                    AND NOT EXISTS (SELECT 1 FROM insurance_claims WHERE invoice_id = i.id)
                                    ORDER BY i.created_at DESC");
                                $stmt->execute();
                                $available_invoices = $stmt->fetchAll();
                                foreach ($available_invoices as $inv) {
                                    echo '<option value="' . (int)$inv['id'] . '">';
                                    echo sanitize($inv['invoice_number']) . ' - ' . sanitize($inv['patient_name']) . ' (' . format_currency((float)$inv['amount']) . ')';
                                    echo '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Insurance Provider</label>
                            <input type="text" name="insurance_provider" class="form-control" placeholder="e.g., NHIS, Private Insurance">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle"></i> Create Claim
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

