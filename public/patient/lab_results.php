<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Patient');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Check mapping availability
$has_mapping = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch() !== false;
$patient_id = null;
if ($has_mapping) {
    $stmt = $db->prepare('SELECT patient_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $patient_id = $row ? (int)$row['patient_id'] : null;
}

// Ensure lab_results table exists
$lab_ready = false;
try {
    $db->query('SELECT 1 FROM lab_results LIMIT 1');
    $lab_ready = true;
} catch (PDOException $e) { $lab_ready = false; }

$results = [];
if ($lab_ready && $patient_id) {
    $stmt = $db->prepare("SELECT * FROM lab_results WHERE patient_id = ? ORDER BY COALESCE(result_date, created_at) DESC");
    $stmt->execute([$patient_id]);
    $results = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">My Lab Results</h1>
    </div>
    <?php if (!$has_mapping) { ?>
        <div class="alert alert-info">Your account isn’t linked to a patient record yet. Please contact reception. Staff can run the <a href="/hms/public/setup/migrate_link_user_patient.php" class="alert-link">link migration</a>.</div>
    <?php } elseif (!$lab_ready) { ?>
        <div class="alert alert-info">Lab module not initialized. Staff can run <a href="/hms/public/setup/migrate_add_lab_results.php" class="alert-link">this migration</a>.</div>
    <?php } elseif (!$patient_id) { ?>
        <div class="alert alert-warning">No linked patient record found for your account. Please contact reception to link it.</div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Test</th>
                        <th>Result</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r) { ?>
                        <?php
                            $badge = 'secondary';
                            if ($r['status'] === 'Ready') { $badge = 'info'; }
                            elseif ($r['status'] === 'Reviewed') { $badge = 'success'; }
                        ?>
                        <tr>
                            <td><?php echo sanitize($r['result_date'] ?: substr($r['created_at'],0,10)); ?></td>
                            <td><?php echo sanitize($r['test_name']); ?></td>
                            <td><?php echo sanitize(trim(($r['result_value'] ?? '') . ' ' . ($r['result_unit'] ?? ''))); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($r['status']); ?></span></td>
                            <td><?php echo sanitize($r['notes'] ?? ''); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$results) { ?>
                        <tr><td colspan="5" class="text-center text-muted">No lab results found</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>