<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$payment_id = (int)get('id');
if (!$payment_id) {
    redirect('/hms/public/payments/history.php');
}

// Get payment details
$stmt = $db->prepare("SELECT p.*, i.invoice_number, 
    CONCAT(pat.first_name, ' ', pat.last_name) AS patient_name,
    pat.phone, pat.email, pat.address,
    u.name AS processed_by_name
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    JOIN patients pat ON p.patient_id = pat.id
    LEFT JOIN users u ON p.processed_by = u.id
    WHERE p.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    redirect('/hms/public/payments/history.php');
}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-modern">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h3 class="mb-1"><?php echo sanitize($env['app_name']); ?></h3>
                        <p class="text-muted mb-0">Payment Receipt</p>
                    </div>
                    
                    <hr>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Receipt Number:</strong><br>
                            <code><?php echo sanitize($payment['receipt_number'] ?? 'N/A'); ?></code>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Date:</strong><br>
                            <?php echo date('F d, Y H:i', strtotime($payment['created_at'])); ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <strong>Patient Information:</strong><br>
                        <?php echo sanitize($payment['patient_name']); ?><br>
                        <?php if ($payment['phone']) { ?>
                            Phone: <?php echo sanitize($payment['phone']); ?><br>
                        <?php } ?>
                        <?php if ($payment['address']) { ?>
                            Address: <?php echo sanitize($payment['address']); ?>
                        <?php } ?>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Invoice Number:</strong><br>
                        <code><?php echo sanitize($payment['invoice_number'] ?? 'N/A'); ?></code>
                    </div>
                    
                    <hr>
                    
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Payment Received</td>
                                    <td class="text-end fw-bold"><?php echo format_currency((float)$payment['amount']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Method:</strong> <?php echo sanitize($payment['payment_method']); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Total Paid</th>
                                    <th class="text-end text-success"><?php echo format_currency((float)$payment['amount']); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <?php if ($payment['notes']) { ?>
                        <div class="mb-3">
                            <strong>Notes:</strong><br>
                            <?php echo nl2br(sanitize($payment['notes'])); ?>
                        </div>
                    <?php } ?>
                    
                    <hr>
                    
                    <div class="text-muted small">
                        <p class="mb-0">Processed by: <?php echo sanitize($payment['processed_by_name'] ?? 'System'); ?></p>
                        <p class="mb-0">Thank you for your payment!</p>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <button onclick="window.print()" class="btn btn-primary">
                            <i class="bi bi-printer"></i> Print Receipt
                        </button>
                        <a href="/hms/public/payments/history.php" class="btn btn-outline-secondary">Back to History</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .top-navbar, .btn, nav { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

