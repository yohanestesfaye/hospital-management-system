<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Receptionist');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

$errors = [];
$notices = [];

$has_photo = $db->query("SHOW COLUMNS FROM patients LIKE 'photo_url'")->fetch() !== false;
if (!$has_photo) { try { $db->exec("ALTER TABLE patients ADD COLUMN photo_url VARCHAR(255) NULL"); } catch (Throwable $e) {} }
$has_ptype = $db->query("SHOW COLUMNS FROM patients LIKE 'patient_type'")->fetch() !== false;
if (!$has_ptype) { try { $db->exec("ALTER TABLE patients ADD COLUMN patient_type ENUM('New','Existing') NULL"); } catch (Throwable $e) {} }
$has_paytype = $db->query("SHOW COLUMNS FROM patients LIKE 'payment_type'")->fetch() !== false;
if (!$has_paytype) { try { $db->exec("ALTER TABLE patients ADD COLUMN payment_type ENUM('Cash','Insurance') NULL"); } catch (Throwable $e) {} }
$has_ins_provider = $db->query("SHOW COLUMNS FROM patients LIKE 'insurance_provider'")->fetch() !== false;
if (!$has_ins_provider) { try { $db->exec("ALTER TABLE patients ADD COLUMN insurance_provider VARCHAR(120) NULL"); } catch (Throwable $e) {} }
$has_ins_id = $db->query("SHOW COLUMNS FROM patients LIKE 'insurance_id'")->fetch() !== false;
if (!$has_ins_id) { try { $db->exec("ALTER TABLE patients ADD COLUMN insurance_id VARCHAR(120) NULL"); } catch (Throwable $e) {} }
$has_patient_code = $db->query("SHOW COLUMNS FROM patients LIKE 'patient_code'")->fetch() !== false;
if (!$has_patient_code) { try { $db->exec("ALTER TABLE patients ADD COLUMN patient_code VARCHAR(32) NULL"); } catch (Throwable $e) {} }

$col_patient_location = $db->query("SHOW COLUMNS FROM appointments LIKE 'patient_location'")->fetch() !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    if ($action === 'register_patient') {
        $first_name = trim((string)post('first_name'));
        $last_name = trim((string)post('last_name'));
        $gender = trim((string)post('gender'));
        $dob = trim((string)post('dob'));
        $phone = trim((string)post('phone'));
        $email = trim((string)post('email'));
        $address = trim((string)post('address'));
        $patient_type = trim((string)post('patient_type'));
        $payment_type = trim((string)post('payment_type'));
        $insurance_provider = trim((string)post('insurance_provider'));
        $insurance_id = trim((string)post('insurance_id'));
        $photo_url = null;
        if (isset($_FILES['photo']) && is_array($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? 4) === 0) {
            $upload_dir = __DIR__ . '/uploads/patient_photos';
            if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $fname = 'p_' . uniqid() . ($ext?('.' . $ext):'');
            $dest = $upload_dir . '/' . $fname;
            if (@move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) { $photo_url = '/hms/public/uploads/patient_photos/' . $fname; }
        }
        if ($first_name === '' || $last_name === '' || $gender === '' || $dob === '') { $errors[] = 'Please fill required fields.'; }
        if ($gender && !in_array($gender, ['Male','Female'], true)) { $errors[] = 'Gender must be Male or Female'; }
        if (!$errors) {
            $cols = ['first_name','last_name','gender','dob','phone','email','address'];
            $vals = [$first_name,$last_name,$gender,$dob,$phone,$email,$address];
            if ($has_photo) { $cols[] = 'photo_url'; $vals[] = $photo_url; }
            if ($has_ptype) { $cols[] = 'patient_type'; $vals[] = $patient_type ?: null; }
            if ($has_paytype) { $cols[] = 'payment_type'; $vals[] = $payment_type ?: null; }
            if ($has_ins_provider) { $cols[] = 'insurance_provider'; $vals[] = $insurance_provider ?: null; }
            if ($has_ins_id) { $cols[] = 'insurance_id'; $vals[] = $insurance_id ?: null; }
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO patients(' . implode(',', $cols) . ') VALUES(' . $placeholders . ')';
            $st = $db->prepare($sql);
            $st->execute($vals);
            $pid = (int)$db->lastInsertId();
            try {
                $code = sprintf('%04d', $pid);
                $upd = $db->prepare('UPDATE patients SET patient_code = ? WHERE id = ?');
                $upd->execute([$code, $pid]);
            } catch (Throwable $e) {}
            $notices[] = 'registered:' . $pid;
        }
    } elseif ($action === 'create_appointment') {
        $patient_id = (int)post('patient_id');
        $doctor_id = (int)post('doctor_id');
        $appointment_date = post('appointment_date');
        $notes = post('notes');
        if (!$patient_id || !$doctor_id || !$appointment_date) { $errors[] = 'Appointment requires patient, doctor, date.'; }
        if (!$errors) {
            $conflictStmt = $db->prepare('SELECT COUNT(*) AS c FROM appointments WHERE doctor_id = ? AND appointment_date = ?');
            $conflictStmt->execute([$doctor_id, $appointment_date]);
            $conflict = (int)$conflictStmt->fetch()['c'];
            if ($conflict > 0) { $errors[] = 'Doctor not available at selected time.'; }
        }
        if (!$errors) {
            $stmt = $db->prepare('INSERT INTO appointments(patient_id,doctor_id,appointment_date,status,notes) VALUES (?,?,?,?,?)');
            $stmt->execute([$patient_id,$doctor_id,$appointment_date,'Pending',$notes]);
            $notices[] = 'appointment_created';
        }
    } elseif ($action === 'direct_patient' && $col_patient_location) {
        $appointment_id = (int)post('appointment_id');
        if ($appointment_id) {
            $stmt = $db->prepare('UPDATE appointments SET patient_location = ? WHERE id = ?');
            $stmt->execute(['With Doctor',$appointment_id]);
            $notices[] = 'directed:' . $appointment_id;
        }
    }
}

$stats = [
    'patients_today' => 0,
    'appointments_today' => 0,
    'waiting_queue' => 0,
    'new_registrations' => 0,
    'checked_in' => 0,
];
$stats['patients_today'] = (int)$db->query("SELECT COUNT(*) AS c FROM patients WHERE DATE(created_at)=CURDATE()")->fetch()['c'];
$stats['appointments_today'] = (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date)=CURDATE()")->fetch()['c'];
if ($col_patient_location) {
    $stats['waiting_queue'] = (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date)=CURDATE() AND status IN ('Scheduled','Pending') AND patient_location='Waiting'")->fetch()['c'];
    $stats['checked_in'] = (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date)=CURDATE() AND patient_location='With Doctor'")->fetch()['c'];
}
$stats['new_registrations'] = $stats['patients_today'];

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();
$doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name, specialty FROM doctors ORDER BY first_name, last_name")->fetchAll();

$queue = $db->query("SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name, CONCAT(d.first_name,' ',d.last_name) AS doctor_name, d.specialty FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id WHERE DATE(a.appointment_date)=CURDATE() AND a.status IN ('Scheduled','Pending') ORDER BY a.appointment_date ASC")->fetchAll();

$patient_q = (string)get('patient_q','');
$search_results = [];
if ($patient_q !== '') {
    if (ctype_digit(trim($patient_q))) {
        $idv = (int)trim($patient_q);
        $stmt = $db->prepare('SELECT * FROM patients WHERE id = ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 50');
        $like = '%' . $patient_q . '%';
        $stmt->execute([$idv,$like,$like,$like]);
    } else {
        $stmt = $db->prepare('SELECT * FROM patients WHERE first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 50');
        $like = '%' . $patient_q . '%';
        $stmt->execute([$like,$like,$like]);
    }
    $search_results = $stmt->fetchAll();
}

include __DIR__ . '/../includes/receptionist-header.php';
?>
<div class="container-fluid py-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Receptionist Dashboard</h1>
        <span class="badge bg-primary">Receptionist</span>
    </div>
    <?php if ($errors) { echo '<div class="alert alert-danger">' . implode('<br>', array_map('sanitize',$errors)) . '</div>'; } ?>
    <?php foreach ($notices as $n) { echo '<div class="alert alert-success">' . sanitize($n) . '</div>'; } ?>
    <div class="row g-3 mb-3">
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">Total Patients Today</div><div class="h3 mb-0"><?php echo (int)$stats['patients_today']; ?></div></div><span class="icon-circle"><i class="bi bi-people text-primary"></i></span></div></div></div></div>
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">Appointments Today</div><div class="h3 mb-0"><?php echo (int)$stats['appointments_today']; ?></div></div><span class="icon-circle"><i class="bi bi-calendar-check text-success"></i></span></div></div></div></div>
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">Patients Waiting</div><div class="h3 mb-0"><?php echo (int)$stats['waiting_queue']; ?></div></div><span class="icon-circle"><i class="bi bi-hourglass-split text-warning"></i></span></div></div></div></div>
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">New Registrations</div><div class="h3 mb-0"><?php echo (int)$stats['new_registrations']; ?></div></div><span class="icon-circle"><i class="bi bi-person-plus text-info"></i></span></div></div></div></div>
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">Completed Check-ins</div><div class="h3 mb-0"><?php echo (int)$stats['checked_in']; ?></div></div><span class="icon-circle"><i class="bi bi-check2-circle text-success"></i></span></div></div></div></div>
        <div class="col-md-4 col-lg-2"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><div><div class="text-muted small">Notifications</div><div class="h3 mb-0"><?php echo (int)max(0,count($queue)); ?></div></div><span class="icon-circle"><i class="bi bi-bell text-danger"></i></span></div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">Register New Patient</h5></div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="action" value="register_patient">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">First Name</label><input type="text" class="form-control" name="first_name" required></div>
                            <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" class="form-control" name="last_name" required></div>
                            <div class="col-md-6"><label class="form-label">DOB</label><input type="date" class="form-control" name="dob" required></div>
                            <div class="col-md-6"><label class="form-label">Sex</label><select name="gender" class="form-select" required><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="text" class="form-control" name="phone"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
                            <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" name="address"></div>
                            <div class="col-md-6"><label class="form-label">Patient Type</label><select name="patient_type" class="form-select"><option value="">Select</option><option value="New">New</option><option value="Existing">Existing</option></select></div>
                            <div class="col-md-6"><label class="form-label">Payment Type</label><select name="payment_type" class="form-select"><option value="">Select</option><option value="Cash">Cash</option><option value="Insurance">Insurance</option></select></div>
                            <div class="col-md-6"><label class="form-label">Insurance Provider</label><input type="text" class="form-control" name="insurance_provider"></div>
                            <div class="col-md-6"><label class="form-label">Insurance ID</label><input type="text" class="form-control" name="insurance_id"></div>
                            <div class="col-12"><label class="form-label">Photo</label><input type="file" class="form-control" name="photo" accept="image/*"></div>
                            <div class="col-12 d-flex gap-2"><button class="btn btn-success">Save & Print Card</button><a class="btn btn-secondary" href="/hms/public/patients/index.php">Open Patients</a></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white"><h5 class="mb-0">Appointment Scheduling</h5></div>
                <div class="card-body">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="action" value="create_appointment">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Patient</label><select name="patient_id" class="form-select" required><option value="">Select patient</option><?php foreach($patients as $p){ echo '<option value="' . (int)$p['id'] . '">' . sanitize($p['name']) . '</option>'; } ?></select></div>
                            <div class="col-md-6"><label class="form-label">Doctor</label><select name="doctor_id" class="form-select" required><option value="">Select doctor</option><?php foreach($doctors as $d){ echo '<option value="' . (int)$d['id'] . '">' . sanitize($d['name'] . ' (' . $d['specialty'] . ')') . '</option>'; } ?></select></div>
                            <div class="col-md-6"><label class="form-label">Date & Time</label><input type="datetime-local" name="appointment_date" class="form-control" required></div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control"></textarea></div>
                            <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Create</button><a class="btn btn-outline-secondary" href="/hms/public/appointments/index.php">Open Appointments</a></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="section-search">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light"><h5 class="mb-0">Search Patients</h5></div>
                <div class="card-body">
                    <form class="row g-2 mb-3" method="get">
                        <div class="col-sm-8"><input type="text" class="form-control" name="patient_q" placeholder="Search by ID, name, phone" value="<?php echo sanitize($patient_q); ?>"></div>
                        <div class="col-sm-4"><button class="btn btn-outline-secondary w-100">Search</button></div>
                    </form>
                    <?php if ($patient_q !== '') { ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th style="width:80px">ID</th><th>Name</th><th>Phone</th><th>DOB</th><th>Status</th><th style="width:220px">Options</th></tr></thead>
                            <tbody>
                                <?php foreach ($search_results as $row) { $status = 'Active'; ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo sanitize($row['phone']); ?></td>
                                    <td><?php echo sanitize($row['dob']); ?></td>
                                    <td><span class="badge bg-success"><?php echo $status; ?></span></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-info" href="/hms/public/patients/show.php?id=<?php echo (int)$row['id']; ?>">View</a>
                                        <a class="btn btn-sm btn-outline-primary" href="/hms/public/patients/index.php?edit=<?php echo (int)$row['id']; ?>">Edit</a>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php if (!$search_results) { echo '<tr><td colspan="6" class="text-center text-muted">No results</td></tr>'; } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="section-queue">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light"><h5 class="mb-0">Patient Queue</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Specialty</th><th>Status</th><th style="width:260px">Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($queue as $apt) { $badge = 'secondary'; if ($apt['status']==='Scheduled') { $badge='primary'; } elseif ($apt['status']==='Completed') { $badge='success'; } elseif ($apt['status']==='Pending') { $badge='warning'; } ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($apt['appointment_date'])); ?></td>
                                    <td><?php echo sanitize($apt['patient_name']); ?></td>
                                    <td><?php echo sanitize($apt['doctor_name']); ?></td>
                                    <td><?php echo sanitize($apt['specialty']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($apt['status']); ?></span>
                                        <?php if ($col_patient_location) { ?><span class="badge bg-info ms-2"><?php echo sanitize($apt['patient_location']); ?></span><?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($col_patient_location && $apt['patient_location']!=='With Doctor') { ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="direct_patient">
                                            <input type="hidden" name="appointment_id" value="<?php echo (int)$apt['id']; ?>">
                                            <button class="btn btn-sm btn-success">Move to Consulting Room</button>
                                        </form>
                                        <?php } ?>
                                        <a class="btn btn-sm btn-outline-primary" href="/hms/public/appointments/verify.php?id=<?php echo (int)$apt['id']; ?>">Verify</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="/hms/public/appointments/edit.php?id=<?php echo (int)$apt['id']; ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-info" href="javascript:void(0)" onclick="printSlip(<?php echo (int)$apt['id']; ?>)">Print Visit Slip</a>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php if (!$queue) { echo '<tr><td colspan="6" class="text-center text-muted">No patients in queue</td></tr>'; } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="section-reports">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light"><h5 class="mb-0">Reports</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Daily Registrations</div><div class="h4 mb-0"><?php echo (int)$stats['patients_today']; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Appointments Today</div><div class="h4 mb-0"><?php echo (int)$stats['appointments_today']; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Queue Size</div><div class="h4 mb-0"><?php echo (int)$stats['waiting_queue']; ?></div></div></div>
                        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">Checked-in</div><div class="h4 mb-0"><?php echo (int)$stats['checked_in']; ?></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="printArea" class="d-none"></div>
<script>
function printSlip(id){
    const row = <?php echo json_encode($queue, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>.find(x=>parseInt(x.id)===parseInt(id));
    if(!row){ return; }
    const html = '<div style="font-family:Arial;padding:16px;">'+
        '<h3 style="margin:0 0 8px;">Visit Slip</h3>'+
        '<div>Patient: '+<?php echo json_encode(''); ?>+ row.patient_name + '</div>'+
        '<div>Doctor: ' + row.doctor_name + ' (' + row.specialty + ')</div>'+
        '<div>Time: ' + new Date(row.appointment_date.replace(' ','T')).toLocaleString() + '</div>'+
        '<div>Status: ' + row.status + '</div>'+
        '</div>';
    const w = window.open('', 'PRINT', 'height=600,width=400');
    w.document.write('<html><head><title>Visit Slip</title></head><body>'+html+'</body></html>');
    w.document.close();
    w.focus();
    w.print();
    w.close();
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
