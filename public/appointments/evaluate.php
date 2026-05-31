<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Doctor']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();
try { $db->query('SELECT 1 FROM medical_records LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS medical_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        record_type VARCHAR(50) NOT NULL,
        diagnosis TEXT NULL,
        treatment_plan TEXT NULL,
        notes TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$appointment_id = (int)get('id', 0);
if ($appointment_id <= 0) { redirect('/hms/public/appointments/index.php'); }

$stmt = $db->prepare("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS pid,
                             CONCAT(d.first_name,' ',d.last_name) AS doctor_name
                      FROM appointments a
                      JOIN patients p ON p.id = a.patient_id
                      JOIN doctors d ON d.id = a.doctor_id
                      WHERE a.id = ? LIMIT 1");
$stmt->execute([$appointment_id]);
$appointment = $stmt->fetch();
if (!$appointment) { redirect('/hms/public/appointments/index.php'); }

$doctor_id = null;
$current_name = isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : '';
if ($current_name !== '') {
    $normalized = preg_replace('/^Dr\.\s*/i', '', $current_name);
    $stmt2 = $db->prepare('SELECT id FROM doctors WHERE CONCAT(first_name, " ", last_name) = ? LIMIT 1');
    $stmt2->execute([$normalized]);
    $row2 = $stmt2->fetch();
    if ($row2) { $doctor_id = (int)$row2['id']; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $symptoms = (string)post('symptoms');
    $diagnosis = (string)post('diagnosis');
    $treatment = (string)post('treatment_plan');
    $notes = (string)post('notes');

    if (trim($symptoms) === '') { $errors[] = 'Symptoms are required'; }
    if (trim($diagnosis) === '') { $errors[] = 'Diagnosis is required'; }
    if (trim($notes) === '') { $errors[] = 'Notes are required'; }

    if (!$errors) {
        $full_notes = "Symptoms:\n" . $symptoms . "\n\n" . $notes;
        $stmt3 = $db->prepare('INSERT INTO medical_records(patient_id,record_type,diagnosis,treatment_plan,notes,created_by) VALUES (?,?,?,?,?,?)');
        $stmt3->execute([(int)$appointment['patient_id'], 'Diagnosis', $diagnosis, $treatment, $full_notes, (int)$_SESSION['user_id']]);
        try {
            $stmt4 = $db->prepare("UPDATE appointments SET status = 'Completed', patient_location = 'With Doctor' WHERE id = ?");
            $stmt4->execute([$appointment_id]);
        } catch (Throwable $e) { }

        redirect('/hms/public/medical_records/index.php');
    }
}

include __DIR__ . '/../../includes/doctor-header.php';
?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h5 mb-0">Evaluate Appointment</h1>
                <a class="btn btn-outline-secondary btn-sm" href="/hms/public/appointments/index.php">Back</a>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header bg-light">
                    <div class="fw-semibold">Appointment Details</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><div class="text-muted small">Patient</div><div><?php echo sanitize($appointment['patient_name']); ?></div></div>
                        <div class="col-md-4"><div class="text-muted small">Doctor</div><div><?php echo sanitize($appointment['doctor_name']); ?></div></div>
                        <div class="col-md-4"><div class="text-muted small">Date & Time</div><div><?php echo sanitize($appointment['appointment_date']); ?></div></div>
                    </div>
                </div>
            </div>

            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>

            <div class="card shadow-sm">
                <div class="card-header bg-light"><div class="fw-semibold">Record Diagnosis</div></div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label form-required">Symptoms</label>
                            <textarea name="symptoms" rows="4" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-required">Diagnosis</label>
                            <textarea name="diagnosis" rows="4" class="form-control" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Treatment Plan</label>
                            <textarea name="treatment_plan" rows="4" class="form-control"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-required">Notes</label>
                            <textarea name="notes" rows="6" class="form-control" required></textarea>
                        </div>
                        <div>
                            <button class="btn btn-success">Save Diagnosis</button>
                            <a class="btn btn-secondary" href="/hms/public/appointments/index.php">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
<?php include __DIR__ . '/../../includes/admin-footer.php'; ?>