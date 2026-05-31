<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Admin','Doctor','Nurse','Pharmacist','Laboratorist','Accountant','Receptionist']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { redirect('/hms/public/patients/index.php'); }

$name = sanitize($patient['first_name'] . ' ' . $patient['last_name']);
$phone = sanitize($patient['phone']);
$address = sanitize($patient['address']);
$dob = $patient['dob'] ? date('d/m/Y', strtotime($patient['dob'])) : 'N/A';
$age = 'N/A';
if (!empty($patient['dob'])) {
    try {
        $birth = new DateTime($patient['dob']);
        $age = $birth->diff(new DateTime())->y . ' Years';
    } catch (Throwable $e) { $age = 'N/A'; }
}
$recorded = isset($patient['created_at']) ? date('d/m/Y - H:i', strtotime($patient['created_at'])) : 'N/A';
$display_id = !empty($patient['patient_code']) ? sanitize($patient['patient_code']) : sprintf('%04d', (int)$patient['id']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <h2 class="mb-3"><?php echo $name; ?>'s Profile</h2>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <div class="display-4 text-secondary"><i class="bi bi-person-circle"></i></div>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-5">Patient ID</dt><dd class="col-7"><?php echo $display_id; ?></dd>
                        <dt class="col-5">Full Name</dt><dd class="col-7"><?php echo $name; ?></dd>
                        <dt class="col-5">Mobile</dt><dd class="col-7"><?php echo $phone ?: 'N/A'; ?></dd>
                        <dt class="col-5">Address</dt><dd class="col-7"><?php echo $address ?: 'N/A'; ?></dd>
                        <dt class="col-5">Date Of Birth</dt><dd class="col-7"><?php echo $dob; ?></dd>
                        <dt class="col-5">Age</dt><dd class="col-7"><?php echo sanitize($age); ?></dd>
                        <dt class="col-5">Date Recorded</dt><dd class="col-7"><?php echo sanitize($recorded); ?></dd>
                    </dl>
                    <?php if (!empty($patient['medical_history'])) { ?>
                        <hr>
                        <div>
                            <div class="fw-semibold mb-1">Medical History</div>
                            <div class="text-muted" style="white-space:pre-line"><?php echo sanitize($patient['medical_history']); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-prescription" type="button" role="tab">Prescription</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-vitals" type="button" role="tab">Vitals</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-lab" type="button" role="tab">Lab Records</button></li>
            </ul>
            <div class="tab-content">
                <div id="tab-prescription" class="tab-pane fade show active" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <p class="text-muted mb-2">Add or view prescriptions for this patient.</p>
                        <div class="alert alert-info mb-0">This section is a UI placeholder. Persistence endpoints can be added on request.</div>
                    </div></div>
                </div>
                <div id="tab-vitals" class="tab-pane fade" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <p class="text-muted mb-2">Record and track vitals such as blood pressure, pulse, temperature.</p>
                        <div class="alert alert-info mb-0">UI placeholder ready for integration.</div>
                    </div></div>
                </div>
                <div id="tab-lab" class="tab-pane fade" role="tabpanel">
                    <div class="card"><div class="card-body">
                        <p class="text-muted mb-2">View laboratory results associated with this patient.</p>
                        <a class="btn btn-outline-primary" href="/hms/public/appointments/index.php">View Appointments</a>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>