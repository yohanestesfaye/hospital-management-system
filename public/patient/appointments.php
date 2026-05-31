<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Patient');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Check mapping column exists
$has_mapping = false;
try {
    $c = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch();
    $has_mapping = $c !== false;
} catch (PDOException $e) { $has_mapping = false; }

$patient_id = null;
if ($has_mapping) {
    $stmt = $db->prepare('SELECT patient_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    $patient_id = $row ? (int)$row['patient_id'] : null;
}

$appointments = [];
if ($has_mapping && $patient_id) {
    $stmt = $db->prepare("SELECT a.*, CONCAT(d.first_name,' ',d.last_name) AS doctor_name, d.specialty
                          FROM appointments a
                          JOIN doctors d ON d.id = a.doctor_id
                          WHERE a.patient_id = ?
                          ORDER BY a.appointment_date DESC");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">My Appointments</h1>
    </div>
    <?php if (!$has_mapping) { ?>
        <div class="alert alert-info">Your account is not yet configured for online schedules. Please ask reception to run the migration and link your patient record. <a href="/hms/public/setup/migrate_link_user_patient.php" class="alert-link">Migration link</a></div>
    <?php } elseif (!$patient_id) { ?>
        <div class="alert alert-warning">We could not find a patient record linked to your account. Please contact reception to link your profile.</div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Doctor</th>
                        <th>Specialty</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a) { ?>
                        <?php
                            $badge = 'secondary';
                            if ($a['status'] === 'Scheduled') { $badge = 'primary'; }
                            elseif ($a['status'] === 'Completed') { $badge = 'success'; }
                            elseif ($a['status'] === 'Cancelled') { $badge = 'secondary'; }
                            elseif ($a['status'] === 'Pending') { $badge = 'warning'; }
                        ?>
                        <tr>
                            <td><?php echo sanitize($a['appointment_date']); ?></td>
                            <td><?php echo sanitize($a['doctor_name']); ?></td>
                            <td><?php echo sanitize($a['specialty']); ?></td>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($a['status']); ?></span></td>
                            <td><?php echo sanitize($a['notes']); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (!$appointments) { ?>
                        <tr><td colspan="5" class="text-center text-muted">No appointments found</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>