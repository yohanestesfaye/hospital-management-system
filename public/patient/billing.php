<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Patient');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$has_mapping = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch() !== false;
$patient_id = null;
if ($has_mapping) {
    $stmt = $db->prepare('SELECT patient_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $patient_id = $row ? (int)$row['patient_id'] : null;
}

$billing_ready = true;
try { $db->query('SELECT 1 FROM invoices LIMIT 1'); } catch (PDOException $e) { $billing_ready = false; }

$invoices = [];
if ($billing_ready && $patient_id) {
    $stmt = $db->prepare('SELECT * FROM invoices WHERE patient_id = ? ORDER BY created_at DESC');
    $stmt->execute([$patient_id]);
    $invoices = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">My Billing</h1>
    </div>
    <?php if (!$has_mapping) { ?>
        <div class="alert alert-info">Your account isn’t linked to a patient record yet. Please contact reception. Staff can run the <a href="/hms/public/setup/migrate_link_user_patient.php" class="alert-link">link migration</a>.</div>
    <?php } elseif (!$billing_ready) { ?>
        <div class="alert alert-info">Billing module not initialized. Staff can run <a href="/hms/public/setup/migrate_add_billing.php" class="alert-link">this migration</a>.</div>
    <?php } elseif (!$patient_id) { ?>
        <div class="alert alert-warning">No linked patient record found for your account. Please contact reception to link it.</div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Due Date</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv) { ?>
                        <?php
                            $badge = 'secondary';
                            if ($inv['status'] === 'Unpaid') { $badge = 'warning'; }
                            elseif ($inv['status'] === 'Paid') { $badge = 'success'; }
                            elseif ($inv['status'] === 'Partial') { $badge = 'info'; }
                        ?>
                        <tr>
                            <td><?php echo sanitize($inv['invoice_number']); ?></td>
                            <td><?php echo number_format((float)$inv['amount'], 2); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($inv['status']); ?></span></td>
                            <td><?php echo sanitize($inv['due_date'] ?? ''); ?></td>
                            <td><?php echo sanitize($inv['created_at']); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$invoices) { ?>
                        <tr><td colspan="5" class="text-center text-muted">No invoices found</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>