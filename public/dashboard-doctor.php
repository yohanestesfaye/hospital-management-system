<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Doctor');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();
$errors = [];

// Ensure lab_results table and required columns exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS lab_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        test_name VARCHAR(200) NOT NULL,
        result TEXT,
        result_date DATETIME NULL,
        requested_by INT NULL,
        priority ENUM('Urgent','Routine') DEFAULT 'Routine',
        status ENUM('Pending','Ready','Reviewed') DEFAULT 'Pending',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { }
// Backfill columns if table exists with older schema
foreach ([
    ["requested_by INT NULL", "requested_by"],
    ["priority ENUM('Urgent','Routine') DEFAULT 'Routine'", "priority"],
    ["status ENUM('Pending','Ready','Reviewed') DEFAULT 'Pending'", "status"],
    ["notes TEXT NULL", "notes"],
] as $col) {
    try {
        $exists = $db->query("SHOW COLUMNS FROM lab_results LIKE '" . $col[1] . "'")->fetch() !== false;
        if (!$exists) { $db->exec("ALTER TABLE lab_results ADD COLUMN " . $col[0]); }
    } catch (Throwable $e) {}
}
$current_name = isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : '';
$doctor_id = null;
if ($current_name !== '') {
    $normalized = preg_replace('/^Dr\.\s*/i', '', $current_name);
    $stmt = $db->prepare('SELECT id FROM doctors WHERE CONCAT(first_name, " ", last_name) = ? LIMIT 1');
    $stmt->execute([$normalized]);
    $row = $stmt->fetch();
    if ($row) { $doctor_id = (int)$row['id']; }
}
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Ensure vital_signs table exists for nursing vitals
try {
    $db->exec("CREATE TABLE IF NOT EXISTS vital_signs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NULL,
        triage VARCHAR(32) NULL,
        temp VARCHAR(32) NULL,
        pulse VARCHAR(32) NULL,
        bp VARCHAR(32) NULL,
        resp VARCHAR(32) NULL,
        spo2 VARCHAR(32) NULL,
        weight VARCHAR(32) NULL,
        height VARCHAR(32) NULL,
        recorded_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(patient_id), INDEX(doctor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Handle lab test orders (Doctor only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'order_lab_test') {
    $patient_id = (int)post('patient_id');
    $test_name = trim((string)post('test_name'));
    $priority = in_array(post('priority'), ['Urgent','Routine'], true) ? post('priority') : 'Routine';
    $notes = trim((string)post('notes'));
    if (!$patient_id) { $errors[] = 'Patient is required'; }
    if ($test_name === '') { $errors[] = 'Test name is required'; }
    if (!$errors) {
        // Build schema-agnostic INSERT based on available columns
        $has = function($name) use ($db) {
            try { return $db->query("SHOW COLUMNS FROM lab_results LIKE '".$name."'")->fetch() !== false; } catch (Throwable $e) { return false; }
        };
        $cols = ['patient_id','test_name'];
        $vals = [$patient_id,$test_name];
        if ($has('requested_by')) { $cols[]='requested_by'; $vals[]=$user_id; }
        if ($has('priority')) { $cols[]='priority'; $vals[]=$priority; }
        if ($has('status')) { $cols[]='status'; $vals[]='Pending'; }
        if ($has('notes')) { $cols[]='notes'; $vals[]=($notes ?: null); }
        if ($has('result')) { $cols[]='result'; $vals[]=null; }
        if ($has('result_date')) { $cols[]='result_date'; $vals[]=null; }
        if ($has('result_value')) { $cols[]='result_value'; $vals[]=null; }
        if ($has('result_unit')) { $cols[]='result_unit'; $vals[]=null; }
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO lab_results(' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
        $stmt = $db->prepare($sql);
        $stmt->execute($vals);
    }
}
$stats = [
    'appointments_today' => (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date) = CURDATE()" )->fetch()['c'],
    'patients_total' => (int)$db->query('SELECT COUNT(*) AS c FROM patients')->fetch()['c'],
];
if ($doctor_id) {
    $stmt = $db->prepare("SELECT a.id, a.appointment_date, a.status, p.first_name, p.last_name, p.phone
                           FROM appointments a JOIN patients p ON a.patient_id = p.id
                           WHERE DATE(a.appointment_date) = CURDATE() AND a.doctor_id = ?
                           ORDER BY a.appointment_date ASC LIMIT 8");
    $stmt->execute([$doctor_id]);
    $today_appointments = $stmt->fetchAll();
} else {
$today_appointments = $db->query("SELECT a.id, a.appointment_date, a.status, p.first_name, p.last_name, p.phone
                                      FROM appointments a JOIN patients p ON a.patient_id = p.id
                                      WHERE DATE(a.appointment_date) = CURDATE()
                                      ORDER BY a.appointment_date ASC LIMIT 8")->fetchAll();
}

include __DIR__ . '/../includes/doctor-header.php';
?>
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h4 mb-0">Doctor Dashboard</h1>
                <span class="badge bg-info">Doctor</span>
            </div>
            <?php if ($errors) { echo '<div class="alert alert-danger">' . implode('<br>', array_map('sanitize',$errors)) . '</div>'; } ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card shadow-sm border-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Today's Appointments</div>
                                    <div class="h3 mb-0"><?php echo $stats['appointments_today']; ?></div>
                                </div>
                                <div class="display-6 text-info">📅</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow-sm border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small">Total Patients</div>
                                    <div class="h3 mb-0"><?php echo $stats['patients_total']; ?></div>
                                </div>
                                <div class="display-6 text-success">👥</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php $has_today = !empty($today_appointments); ?>
            <div class="row g-3">
                <div class="col-lg-12">
                    <?php if ($has_today) { ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Today's Schedule</h5>
                        </div>
                        <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($today_appointments as $apt) { ?>
                                            <tr>
                                                <td><?php echo sanitize(date('H:i', strtotime($apt['appointment_date']))); ?></td>
                                                <td><?php echo sanitize($apt['first_name'] . ' ' . $apt['last_name']); ?></td>
                                                <td><?php echo sanitize($apt['phone'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $apt['status'] === 'Scheduled' ? 'primary' : ($apt['status'] === 'Completed' ? 'success' : 'secondary'); ?>">
                                                        <?php echo sanitize($apt['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($apt['status'] !== 'Completed') { ?>
                                                        <a class="btn btn-sm btn-success" href="/hms/public/appointments/evaluate.php?id=<?php echo (int)$apt['id']; ?>">Evaluate</a>
                                                    <?php } else { ?>
                                                        <span class="text-success fw-semibold"><i class="bi bi-check-lg me-1"></i> Evaluated</span>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                        </div>
                    </div>
                    <?php } ?>
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Recent Vitals From Nursing</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                $recent_vitals = [];
                                try {
                                    if ($doctor_id) {
                                        $st = $db->prepare("SELECT vs.id, vs.triage, vs.temp, vs.pulse, vs.bp, vs.resp, vs.spo2, vs.weight, vs.height, vs.created_at,
                                                                      CONCAT(p.first_name,' ',p.last_name) AS patient_name
                                                           FROM vital_signs vs JOIN patients p ON p.id = vs.patient_id
                                                           WHERE vs.doctor_id = ? ORDER BY vs.created_at DESC LIMIT 12");
                                        $st->execute([$doctor_id]);
                                        $recent_vitals = $st->fetchAll();
                                    }
                                } catch (Throwable $e) {}
                            ?>
                            <?php if ($recent_vitals) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>Patient</th><th>Triage</th><th>Vitals</th><th>Recorded</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($recent_vitals as $v) { 
                                        $summary = [];
                                        if ($v['temp']) { $summary[] = 'T:' . $v['temp'] . '°C'; }
                                        if ($v['pulse']) { $summary[] = 'P:' . $v['pulse'] . '/min'; }
                                        if ($v['bp']) { $summary[] = 'BP:' . $v['bp']; }
                                        if ($v['resp']) { $summary[] = 'R:' . $v['resp'] . '/min'; }
                                        if ($v['spo2']) { $summary[] = 'SpO2:' . $v['spo2'] . '%'; }
                                        if ($v['weight']) { $summary[] = 'Wt:' . $v['weight'] . 'kg'; }
                                        if ($v['height']) { $summary[] = 'Ht:' . $v['height'] . 'cm'; }
                                        $sum_text = implode(', ', $summary);
                                    ?>
                                        <tr>
                                            <td><?php echo sanitize($v['patient_name']); ?></td>
                                            <td><span class="badge bg-<?php echo $v['triage']==='Severe'?'danger':($v['triage']==='Moderate'?'warning text-dark':'info'); ?>"><?php echo sanitize($v['triage'] ?: ''); ?></span></td>
                                            <td><?php echo sanitize($sum_text ?: '-'); ?></td>
                                            <td><?php echo sanitize(substr($v['created_at'],0,16)); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php } else { ?>
                                <p class="text-muted mb-0">No vitals received from nursing.</p>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="card shadow-sm">
                        <div class="card-header bg-light d-flex align-items-center">
                            <h5 class="mb-0">Recent Lab Orders</h5>
                            <a class="btn btn-sm btn-primary ms-auto" href="#" data-bs-toggle="offcanvas" data-bs-target="#orderLabModal"><i class="bi bi-clipboard-plus"></i> <?php echo t('order_lab_test'); ?></a>
                        </div>
                        <div class="card-body">
                            <?php
                                $recent_orders = [];
                                try {
                                    $st = $db->prepare("SELECT lr.id, lr.test_name, COALESCE(lr.priority,'Routine') AS priority, COALESCE(lr.status,'Pending') AS status, lr.created_at, CONCAT(p.first_name,' ',p.last_name) AS patient_name FROM lab_results lr JOIN patients p ON p.id = lr.patient_id WHERE lr.requested_by = ? ORDER BY lr.created_at DESC LIMIT 8");
                                    $st->execute([$user_id]);
                                    $recent_orders = $st->fetchAll();
                                } catch (Throwable $e) {}
                            ?>
                            <?php if ($recent_orders) { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead><tr><th>Patient</th><th>Test</th><th>Priority</th><th>Status</th><th>Date</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($recent_orders as $r) { ?>
                                        <tr>
                                            <td><?php echo sanitize($r['patient_name']); ?></td>
                                            <td><?php echo sanitize($r['test_name']); ?></td>
                                            <td><span class="badge bg-<?php echo ($r['priority']==='Urgent')?'danger':'secondary'; ?>"><?php echo sanitize($r['priority']); ?></span></td>
                                            <td><span class="badge bg-<?php echo ($r['status']==='Pending')?'warning':($r['status']==='Ready'?'info':'success'); ?>"><?php echo sanitize($r['status']); ?></span></td>
                                            <td><?php echo sanitize(substr($r['created_at'],0,16)); ?></td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php } else { ?>
                                <p class="text-muted mb-0">No recent lab orders.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                $patients = [];
                if ($doctor_id) {
                    try {
                        $stp = $db->prepare("SELECT DISTINCT p.id, CONCAT(p.first_name,' ',p.last_name) AS name
                                             FROM patients p
                                             JOIN appointments a ON a.patient_id = p.id
                                             WHERE a.doctor_id = ?
                                             ORDER BY p.first_name, p.last_name");
                        $stp->execute([$doctor_id]);
                        $patients = $stp->fetchAll();
                    } catch (Throwable $e) {}
                }
                if (!$patients) {
                    try { $patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $patients = []; }
                }
                $test_types = ['Blood Test','Urine Test','Stool Test','Serology','Hematology','Chemistry','Diagnostic'];
                $auto_open = (int)get('order',0) === 1;
            ?>
            <div class="offcanvas offcanvas-end lab-order-offcanvas" tabindex="-1" id="orderLabModal">
                <div class="offcanvas-header bg-primary text-white">
                    <h5 class="offcanvas-title"><?php echo t('order_lab_test'); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <form id="orderLabForm" method="post" autocomplete="off">
                        <input type="hidden" name="action" value="order_lab_test">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label">Patient</label>
                                <select name="patient_id" class="form-select form-select-sm" required>
                                    <option value="">Select patient</option>
                                    <?php foreach ($patients as $p) { ?>
                                        <option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Test Name</label>
                                <select name="test_name" class="form-select form-select-sm" required>
                                    <option value="">Select test</option>
                                    <?php foreach ($test_types as $t) { ?>
                                        <option value="<?php echo sanitize($t); ?>"><?php echo sanitize($t); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="Routine">Routine</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Clinical notes or instructions"></textarea>
                            </div>
                        </div>
                    </form>
                    <div class="mt-3 d-flex justify-content-between">
                        <a class="btn btn-outline-secondary me-auto" href="/hms/public/lab_results/index.php">View Lab Orders</a>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">Cancel</button>
                            <button type="submit" form="orderLabForm" class="btn btn-primary">Send Request</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function(){
                    <?php if ($auto_open) { ?>
                    var modalEl = document.getElementById('orderLabModal');
                    if (modalEl) { var m = new bootstrap.Offcanvas(modalEl); m.show(); }
                    <?php } ?>
                });
            </script>
        </div>
<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
