<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Nurse');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

$nurse_name = current_user_name();
try { $patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $patients = []; }
try { $doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM doctors ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $doctors = []; }

// Ensure vital_signs table exists
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

// Handle AJAX persist of vitals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_vitals') {
    header('Content-Type: application/json');
    $patient_id = (int)post('patient_id');
    $doctor_id = (int)post('doctor_id');
    $triage = (string)post('triage');
    $temp = (string)post('temp');
    $pulse = (string)post('pulse');
    $bp = (string)post('bp');
    $resp = (string)post('resp');
    $spo2 = (string)post('spo2');
    $weight = (string)post('weight');
    $height = (string)post('height');
    $recorded_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $ok = false; $msg = '';
    if (!$patient_id || !$doctor_id) { echo json_encode(['ok'=>false,'msg'=>'Patient and doctor required']); exit; }
    try {
        $stmt = $db->prepare('INSERT INTO vital_signs(patient_id,doctor_id,triage,temp,pulse,bp,resp,spo2,weight,height,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$patient_id,$doctor_id,$triage ?: null,$temp ?: null,$pulse ?: null,$bp ?: null,$resp ?: null,$spo2 ?: null,$weight ?: null,$height ?: null,$recorded_by]);
        $ok = true;
    } catch (Throwable $e) { $msg = $e->getMessage(); }
    echo json_encode(['ok'=>$ok,'msg'=>$msg]);
    exit;
}

include __DIR__ . '/../includes/nurse-header.php';
?>
<div class="container-fluid py-4 nurse-dashboard">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Nurse Dashboard</h1>
        <span class="badge bg-success">Nurse</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-lg-2">
            <div class="card shadow-sm stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Total Patients Under Care</div>
                            <div class="h3 mb-0" id="stat-total-care">0</div>
                        </div>
                        <span class="icon-circle icon-primary"><i class="bi bi-people"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card shadow-sm stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Pending Vitals</div>
                            <div class="h3 mb-0" id="stat-pending-vitals">0</div>
                        </div>
                        <span class="icon-circle icon-primary"><i class="bi bi-clipboard-pulse"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-2">
            <div class="card shadow-sm stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Medications To Administer</div>
                            <div class="h3 mb-0" id="stat-medications">0</div>
                        </div>
                        <span class="icon-circle icon-warning"><i class="bi bi-capsule"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Ward Occupancy Status</div>
                            <div class="h3 mb-0" id="stat-ward-occupancy">0%</div>
                        </div>
                        <span class="icon-circle icon-success"><i class="bi bi-hospital"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Emergency Patients Waiting</div>
                            <div class="h3 mb-0" id="stat-emergency-waiting">0</div>
                        </div>
                        <span class="icon-circle icon-danger"><i class="bi bi-exclamation-triangle"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm nurse-section-card" id="section-vitals">
                <div class="card-header bg-primary text-white"><div class="section-title"><i class="bi bi-heart-pulse"></i><span>Triage & Vitals</span></div></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6 col-md-8"><input type="text" class="form-control search-input" id="searchPatientVitals" placeholder="Search Patient"></div>
                        <div class="col-sm-6 col-md-4"><button class="btn btn-outline-secondary w-100" id="btnFindPatientVitals">Search</button></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label">Patient</label>
                            <select id="vitals-patient-id" class="form-select">
                                <option value="">Select patient</option>
                                <?php foreach($patients as $p){ ?>
                                    <option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <label class="form-label">Doctor</label>
                            <select id="vitals-doctor-id" class="form-select">
                                <option value="">Select doctor</option>
                                <?php foreach($doctors as $d){ ?>
                                    <option value="<?php echo (int)$d['id']; ?>"><?php echo sanitize($d['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <form id="vitalsForm" autocomplete="off">
                        <div class="row g-2 mb-2">
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-temp" placeholder="Temperature (°C)" required></div>
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-pulse" placeholder="Pulse (/min)" required></div>
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-bp" placeholder="BP (mmHg)" required></div>
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-resp" placeholder="Respiration (/min)" required></div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-spo2" placeholder="SpO2 (%)" required></div>
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-weight" placeholder="Weight (kg)"></div>
                            <div class="col-sm-6 col-md-3"><input type="text" class="form-control" id="vital-height" placeholder="Height (cm)"></div>
                            <div class="col-sm-6 col-md-3">
                                <select class="form-select" id="triage-category" required>
                                    <option value="">Triage Category</option>
                                    <option value="Mild">Mild</option>
                                    <option value="Moderate">Moderate</option>
                                    <option value="Severe">Severe</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Vitals</button>
                            <button type="button" class="btn btn-outline-primary" id="sendToDoctor">Send to Doctor</button>
                        </div>
                    </form>
                    <div class="mt-3">
                        <div class="fw-semibold mb-2">Pending Vitals Queue</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="pendingVitalsTable">
                                <thead class="table-light"><tr><th>Patient</th><th>Triage</th><th>Recorded</th><th>Vitals</th><th>Options</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm nurse-section-card" id="section-ward">
                <div class="card-header bg-light"><div class="section-title"><i class="bi bi-hospital"></i><span>Inpatient Ward Management</span></div></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-8"><input type="text" class="form-control search-input" id="searchWard" placeholder="Search admitted patients"></div>
                        <div class="col-sm-4"><button class="btn btn-outline-secondary w-100" id="btnSearchWard">Search</button></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="wardTable">
                            <thead class="table-light"><tr><th>Patient</th><th>Bed/Room</th><th>Status</th><th style="width:240px">Options</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm nurse-section-card" id="section-mar">
                <div class="card-header bg-light"><div class="section-title"><i class="bi bi-capsule"></i><span>Medication Administration (MAR)</span></div></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-8"><input type="text" class="form-control search-input" id="searchMar" placeholder="Search medications"></div>
                        <div class="col-sm-4"><button class="btn btn-outline-secondary w-100" id="btnSearchMar">Search</button></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="marTable">
                            <thead class="table-light"><tr><th>Patient</th><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Time Due</th><th style="width:220px">Options</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm nurse-section-card" id="section-lab">
                <div class="card-header bg-light"><div class="section-title"><i class="bi bi-beaker"></i><span>Lab & Diagnostic Coordination</span></div></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="labTable">
                            <thead class="table-light"><tr><th>Patient</th><th>Test</th><th>Status</th><th style="width:220px">Options</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm nurse-section-card" id="section-emergency">
                <div class="card-header bg-danger text-white"><div class="section-title"><i class="bi bi-activity"></i><span>Emergency Triage Area</span></div></div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-sm-8"><input type="text" class="form-control search-input" id="searchEmergency" placeholder="Search incoming emergencies"></div>
                        <div class="col-sm-4"><button class="btn btn-outline-light w-100" id="btnSearchEmergency">Search</button></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="emergencyTable">
                            <thead class="table-light"><tr><th>Patient</th><th>Chief Complaint</th><th>Triage</th><th style="width:220px">Options</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light"><h5 class="mb-0">Access Rules</h5></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Nurses cannot prescribe medication.</li>
                <li>Nurses cannot dispense medicine.</li>
                <li>Nurses cannot modify doctor medical records.</li>
                <li>Nurses may only add nursing notes, vitals, and ward updates.</li>
            </ul>
        </div>
    </div>
</div>

<div class="modal fade" id="recordVitalsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Record Vital Signs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="quickVitalsForm">
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><input type="text" class="form-control" id="quick-patient" placeholder="Patient name" required></div>
                        <div class="col-md-6">
                            <select class="form-select" id="quick-triage" required>
                                <option value="">Triage Category</option>
                                <option value="Mild">Mild</option>
                                <option value="Moderate">Moderate</option>
                                <option value="Severe">Severe</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4"><input type="text" class="form-control" id="quick-temp" placeholder="Temp"></div>
                        <div class="col-md-4"><input type="text" class="form-control" id="quick-bp" placeholder="BP"></div>
                        <div class="col-md-4"><input type="text" class="form-control" id="quick-spo2" placeholder="SpO2"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-primary" id="saveQuickVitals">Save</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="nursingNotesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Nursing Notes</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="nursingNotesForm">
                    <div class="mb-2"><input type="text" class="form-control" id="notes-patient" placeholder="Patient name" required></div>
                    <div class="mb-2"><textarea class="form-control" id="notes-text" rows="4" placeholder="Notes" required></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-primary" id="saveNursingNotes">Save</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateWardModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Update Ward/Bed Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="updateWardForm">
                    <div class="mb-2"><input type="text" class="form-control" id="ward-patient" placeholder="Patient name" required></div>
                    <div class="mb-2"><input type="text" class="form-control" id="ward-bed" placeholder="Bed/Room" required></div>
                    <div class="mb-2">
                        <select class="form-select" id="ward-status" required>
                            <option value="Stable">Stable</option>
                            <option value="Critical">Critical</option>
                            <option value="Under Observation">Under Observation</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-primary" id="saveWardUpdate">Save</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="medAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Medication Administration Log</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="medAdminForm">
                    <div class="mb-2"><input type="text" class="form-control" id="mar-patient" placeholder="Patient name" required></div>
                    <div class="mb-2"><input type="text" class="form-control" id="mar-medication" placeholder="Medication" required></div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><input type="text" class="form-control" id="mar-dosage" placeholder="Dosage" required></div>
                        <div class="col-md-6"><input type="text" class="form-control" id="mar-frequency" placeholder="Frequency" required></div>
                    </div>
                    <div class="mb-2"><input type="time" class="form-control" id="mar-time" required></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-primary" id="saveMedAdmin">Save</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="emergencyTriageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Emergency Triage Form</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="emergencyForm">
                    <div class="mb-2"><input type="text" class="form-control" id="em-patient" placeholder="Patient name" required></div>
                    <div class="mb-2"><input type="text" class="form-control" id="em-complaint" placeholder="Chief complaint" required></div>
                    <div class="mb-2">
                        <select class="form-select" id="em-triage" required>
                            <option value="Mild">Mild</option>
                            <option value="Moderate">Moderate</option>
                            <option value="Severe">Severe</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-danger" id="saveEmergency">Save</button></div>
        </div>
    </div>
</div>

<script>
const nurseName = <?php echo json_encode($nurse_name); ?>;
const doctorsList = <?php echo json_encode($doctors); ?>;

const state = {
    pendingVitals: [],
    wardPatients: [
        { patient: 'John Doe', bed: 'Ward A - Bed 12', status: 'Under Observation' },
        { patient: 'Jane Smith', bed: 'Ward B - Bed 7', status: 'Stable' },
        { patient: 'Alex Brown', bed: 'Ward C - Bed 3', status: 'Critical' }
    ],
    mar: [
        { patient: 'John Doe', medication: 'Amoxicillin', dosage: '500 mg', frequency: '8 hourly', due: '10:00', administered: false, adminBy: '', adminAt: '' },
        { patient: 'Jane Smith', medication: 'Paracetamol', dosage: '1 g', frequency: '6 hourly', due: '12:00', administered: false, adminBy: '', adminAt: '' }
    ],
    labs: [
        { patient: 'Alex Brown', test: 'CBC', status: 'Pending Sample' },
        { patient: 'John Doe', test: 'Chest X-Ray', status: 'Result Pending' }
    ],
    emergencies: [
        { patient: 'Unknown Male', complaint: 'Severe chest pain', triage: 'Severe' }
    ],
    wardCapacity: { totalBeds: 60 }
};

function renderStats() {
    document.getElementById('stat-total-care').textContent = String(state.wardPatients.length);
    document.getElementById('stat-pending-vitals').textContent = String(state.pendingVitals.length);
    document.getElementById('stat-medications').textContent = String(state.mar.filter(x => !x.administered).length);
    const usedBeds = state.wardPatients.length;
    const occupancy = state.wardCapacity.totalBeds ? Math.round((usedBeds / state.wardCapacity.totalBeds) * 100) : 0;
    document.getElementById('stat-ward-occupancy').textContent = occupancy + '%';
    document.getElementById('stat-emergency-waiting').textContent = String(state.emergencies.length);
}

function renderPendingVitals(filter = '') {
    const tbody = document.querySelector('#pendingVitalsTable tbody');
    tbody.innerHTML = '';
    state.pendingVitals.filter(x => x.patient.toLowerCase().includes(filter.toLowerCase())).forEach((x, idx) => {
        const tr = document.createElement('tr');
        const doctorOptions = ['<option value="">Select doctor</option>'].concat((doctorsList||[]).map(d => `<option value="${d.id}">${d.name}</option>`)).join('');
        const v = x.vitals || {};
        const vSummary = [
            v.temp?`T:${v.temp}°C`:'' ,
            v.pulse?`P:${v.pulse}/min`:'' ,
            v.bp?`BP:${v.bp}`:'',
            v.resp?`R:${v.resp}/min`:'',
            v.spo2?`SpO2:${v.spo2}%`:'',
            v.weight?`Wt:${v.weight}kg`:'',
            v.height?`Ht:${v.height}cm`:''
        ].filter(Boolean).join(', ');
        tr.innerHTML = `<td>${x.patient}</td><td><span class="badge bg-${x.triage==='Severe'?'danger':x.triage==='Moderate'?'warning text-dark':'info'}">${x.triage}</span></td><td>${x.recordedAt}</td><td>${vSummary || '-'}</td><td><div class="d-flex align-items-center gap-2"><select class="form-select form-select-sm doctor-select" data-index="${idx}">${doctorOptions}</select><button class="btn btn-sm btn-outline-success" data-action="sent" data-index="${idx}"><i class="bi bi-send"></i> Send</button></div></td>`;
        tbody.appendChild(tr);
    });
}

function renderWard(filter = '') {
    const tbody = document.querySelector('#wardTable tbody');
    tbody.innerHTML = '';
    state.wardPatients.filter(x => x.patient.toLowerCase().includes(filter.toLowerCase())).forEach(x => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${x.patient}</td><td>${x.bed}</td><td><span class="badge bg-${x.status==='Critical'?'danger':x.status==='Stable'?'success':'secondary'}">${x.status}</span></td><td><div class="btn-group"><button class="btn btn-sm btn-outline-primary" data-action="condition" data-patient="${x.patient}"><i class="bi bi-activity"></i> Update Condition</button><button class="btn btn-sm btn-outline-secondary" data-action="note" data-patient="${x.patient}"><i class="bi bi-journal-text"></i> Add Nursing Notes</button><button class="btn btn-sm btn-outline-warning" data-action="review" data-patient="${x.patient}"><i class="bi bi-bell"></i> Request Doctor Review</button></div></td>`;
        tbody.appendChild(tr);
    });
}

function renderMar(filter = '') {
    const tbody = document.querySelector('#marTable tbody');
    tbody.innerHTML = '';
    state.mar.filter(x => `${x.patient} ${x.medication}`.toLowerCase().includes(filter.toLowerCase())).forEach(x => {
        const badge = x.administered ? '<span class="badge bg-success">Administered</span>' : '<span class="badge bg-warning text-dark">Due</span>';
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${x.patient}</td><td>${x.medication}</td><td>${x.dosage}</td><td>${x.frequency}</td><td>${x.due}</td><td><div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-success" data-action="admin" data-patient="${x.patient}" ${x.administered?'disabled':''}><i class="bi bi-check2-circle"></i> Mark as Administered</button>${badge}<span class="small text-muted">${x.administered?`by ${x.adminBy} at ${x.adminAt}`:''}</span></div></td>`;
        tbody.appendChild(tr);
    });
}

function renderLabs() {
    const tbody = document.querySelector('#labTable tbody');
    tbody.innerHTML = '';
    state.labs.forEach(x => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${x.patient}</td><td>${x.test}</td><td><span class="badge bg-${x.status.includes('Pending')?'warning text-dark':'info'}">${x.status}</span></td><td><div class="btn-group"><button class="btn btn-sm btn-outline-success" data-action="sample" data-patient="${x.patient}"><i class="bi bi-droplet"></i> Mark Sample Collected</button><button class="btn btn-sm btn-outline-primary" data-action="notify" data-patient="${x.patient}"><i class="bi bi-envelope"></i> Notify Doctor</button></div></td>`;
        tbody.appendChild(tr);
    });
}

function renderEmergencies(filter = '') {
    const tbody = document.querySelector('#emergencyTable tbody');
    tbody.innerHTML = '';
    state.emergencies.filter(x => `${x.patient} ${x.complaint}`.toLowerCase().includes(filter.toLowerCase())).forEach(x => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${x.patient}</td><td>${x.complaint}</td><td><span class="badge bg-${x.triage==='Severe'?'danger':x.triage==='Moderate'?'warning text-dark':'info'}">${x.triage}</span></td><td><div class="btn-group"><button class="btn btn-sm btn-outline-danger" data-action="notifyDoctor" data-patient="${x.patient}"><i class="bi bi-telephone"></i> Notify On-Call Doctor</button><button class="btn btn-sm btn-outline-secondary" data-action="stabilize" data-patient="${x.patient}"><i class="bi bi-shield-plus"></i> Stabilization Steps</button></div></td>`;
        tbody.appendChild(tr);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    function notifyDoctor(patient, triage, doctorName) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success mt-3';
        alertDiv.textContent = `Vitals for ${patient} (${triage}) sent to ${doctorName ? doctorName : 'doctor'}.`;
        document.querySelector('#section-vitals .card-body').appendChild(alertDiv);
        setTimeout(() => alertDiv.remove(), 2500);
    }
    function persistVitals(patientId, doctorId, triage, v) {
        if (!patientId || !doctorId) return Promise.resolve(false);
        const params = new URLSearchParams();
        params.append('action','record_vitals');
        params.append('patient_id', String(patientId));
        params.append('doctor_id', String(doctorId));
        params.append('triage', triage || '');
        params.append('temp', v.temp || '');
        params.append('pulse', v.pulse || '');
        params.append('bp', v.bp || '');
        params.append('resp', v.resp || '');
        params.append('spo2', v.spo2 || '');
        params.append('weight', v.weight || '');
        params.append('height', v.height || '');
        return fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: params.toString() })
            .then(r => r.json()).catch(() => ({ok:false}));
    }
    renderStats();
    renderPendingVitals();
    renderWard();
    renderMar();
    renderLabs();
    renderEmergencies();

    const sectionIds = ['section-vitals','section-ward','section-mar','section-lab','section-emergency'];

    function updateActiveSidebar(sectionId) {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;
        const links = sidebar.querySelectorAll('.nav-link');
        links.forEach(link => {
            const href = link.getAttribute('href') || '';
            const matches = href.includes('#section-') && href.endsWith('#' + sectionId);
            if (matches) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    function showSection(sectionId) {
        sectionIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (id === sectionId) {
                el.classList.remove('d-none');
            } else {
                el.classList.add('d-none');
            }
        });
        updateActiveSidebar(sectionId);
        try { localStorage.setItem('nurseActiveSection', sectionId); } catch (e) {}
    }

    const fromHash = (location.hash || '').replace('#','');
    const fromPref = (function(){ try { return localStorage.getItem('nurseActiveSection') || ''; } catch(e){ return ''; } })();
    const initialSection = sectionIds.includes(fromHash) ? fromHash : (sectionIds.includes(fromPref) ? fromPref : 'section-vitals');
    showSection(initialSection);

    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            const href = link.getAttribute('href') || '';
            if (href.includes('#section-')) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = href.substring(href.indexOf('#') + 1);
                    if (sectionIds.includes(target)) {
                        showSection(target);
                        history.replaceState(null, '', '#' + target);
                    }
                });
            }
        });
    }

    document.getElementById('vitalsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const sel = document.getElementById('vitals-patient-id');
        let patient = '';
        if (sel && sel.value) { patient = sel.options[sel.selectedIndex].text; }
        else { patient = document.getElementById('searchPatientVitals').value || 'Patient'; }
        const triage = document.getElementById('triage-category').value || 'Mild';
        const recordedAt = new Date().toLocaleString();
        const vitals = {
            temp: document.getElementById('vital-temp').value || '',
            pulse: document.getElementById('vital-pulse').value || '',
            bp: document.getElementById('vital-bp').value || '',
            resp: document.getElementById('vital-resp').value || '',
            spo2: document.getElementById('vital-spo2').value || '',
            weight: document.getElementById('vital-weight').value || '',
            height: document.getElementById('vital-height').value || ''
        };
        state.pendingVitals.push({ patient, triage, recordedAt, vitals });
        renderPendingVitals();
        renderStats();
        const doctorSel = document.getElementById('vitals-doctor-id');
        const doctorName = doctorSel && doctorSel.value ? doctorSel.options[doctorSel.selectedIndex].text : '';
        const patientId = sel && sel.value ? parseInt(sel.value, 10) : 0;
        const doctorId = doctorSel && doctorSel.value ? parseInt(doctorSel.value, 10) : 0;
        persistVitals(patientId, doctorId, triage, vitals).then(resp => { /* optional: handle resp */ });
        notifyDoctor(patient, triage, doctorName);
        this.reset();
        if (sel) { sel.value = ''; }
        if (doctorSel) { doctorSel.value = ''; }
    });

    document.getElementById('sendToDoctor').addEventListener('click', function() {
        const last = state.pendingVitals[state.pendingVitals.length - 1];
        const p = last ? last.patient : 'Patient';
        const t = last ? last.triage : 'Mild';
        const doctorSel = document.getElementById('vitals-doctor-id');
        const doctorName = doctorSel && doctorSel.value ? doctorSel.options[doctorSel.selectedIndex].text : '';
        const sel = document.getElementById('vitals-patient-id');
        const patientId = sel && sel.value ? parseInt(sel.value, 10) : 0;
        const doctorId = doctorSel && doctorSel.value ? parseInt(doctorSel.value, 10) : 0;
        const vitals = last && last.vitals ? last.vitals : {temp:'',pulse:'',bp:'',resp:'',spo2:'',weight:'',height:''};
        persistVitals(patientId, doctorId, t, vitals).then(resp => { /* optional */ });
        notifyDoctor(p, t, doctorName);
    });

    document.getElementById('saveQuickVitals').addEventListener('click', function() {
        const patient = document.getElementById('quick-patient').value;
        const triage = document.getElementById('quick-triage').value;
        if (!patient || !triage) return;
        const vitals = {
            temp: document.getElementById('quick-temp').value || '',
            bp: document.getElementById('quick-bp').value || '',
            spo2: document.getElementById('quick-spo2').value || ''
        };
        state.pendingVitals.push({ patient, triage, recordedAt: new Date().toLocaleString(), vitals });
        renderPendingVitals();
        renderStats();
        document.getElementById('quickVitalsForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('recordVitalsModal')).hide();
    });

    document.getElementById('saveNursingNotes').addEventListener('click', function() {
        const patient = document.getElementById('notes-patient').value;
        const text = document.getElementById('notes-text').value;
        if (!patient || !text) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-info';
        alertDiv.textContent = 'Nursing note added.';
        document.querySelector('.container').prepend(alertDiv);
        setTimeout(() => alertDiv.remove(), 2000);
        document.getElementById('nursingNotesForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('nursingNotesModal')).hide();
    });

    document.getElementById('saveWardUpdate').addEventListener('click', function() {
        const patient = document.getElementById('ward-patient').value;
        const bed = document.getElementById('ward-bed').value;
        const status = document.getElementById('ward-status').value;
        if (!patient || !bed || !status) return;
        const existing = state.wardPatients.find(x => x.patient.toLowerCase() === patient.toLowerCase());
        if (existing) { existing.bed = bed; existing.status = status; } else { state.wardPatients.push({ patient, bed, status }); }
        renderWard();
        renderStats();
        document.getElementById('updateWardForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('updateWardModal')).hide();
    });

    document.getElementById('saveMedAdmin').addEventListener('click', function() {
        const patient = document.getElementById('mar-patient').value;
        const medication = document.getElementById('mar-medication').value;
        const dosage = document.getElementById('mar-dosage').value;
        const frequency = document.getElementById('mar-frequency').value;
        const due = document.getElementById('mar-time').value;
        if (!patient || !medication || !dosage || !frequency || !due) return;
        state.mar.push({ patient, medication, dosage, frequency, due, administered: false, adminBy: '', adminAt: '' });
        renderMar();
        renderStats();
        document.getElementById('medAdminForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('medAdminModal')).hide();
    });

    document.getElementById('saveEmergency').addEventListener('click', function() {
        const patient = document.getElementById('em-patient').value;
        const complaint = document.getElementById('em-complaint').value;
        const triage = document.getElementById('em-triage').value;
        if (!patient || !complaint || !triage) return;
        state.emergencies.push({ patient, complaint, triage });
        renderEmergencies();
        renderStats();
        document.getElementById('emergencyForm').reset();
        bootstrap.Modal.getInstance(document.getElementById('emergencyTriageModal')).hide();
    });

    document.getElementById('searchPatientVitals').addEventListener('input', function() { renderPendingVitals(this.value); });
    document.getElementById('searchWard').addEventListener('input', function() { renderWard(this.value); });
    document.getElementById('searchMar').addEventListener('input', function() { renderMar(this.value); });
    document.getElementById('searchEmergency').addEventListener('input', function() { renderEmergencies(this.value); });

    document.getElementById('marTable').addEventListener('click', function(e) {
        const btn = e.target.closest('button[data-action="admin"]');
        if (!btn) return;
        const patient = btn.getAttribute('data-patient');
        const item = state.mar.find(x => x.patient === patient && !x.administered);
        if (item) {
            item.administered = true;
            item.adminBy = nurseName;
            item.adminAt = new Date().toLocaleString();
            renderMar();
            renderStats();
        }
    });

    document.getElementById('labTable').addEventListener('click', function(e) {
        const btnSample = e.target.closest('button[data-action="sample"]');
        const btnNotify = e.target.closest('button[data-action="notify"]');
        if (btnSample) {
            const patient = btnSample.getAttribute('data-patient');
            const item = state.labs.find(x => x.patient === patient);
            if (item) { item.status = 'Sample Collected'; renderLabs(); }
        }
        if (btnNotify) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-primary';
            alertDiv.textContent = 'Doctor notified.';
            document.querySelector('.container').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        }
    });

    document.getElementById('wardTable').addEventListener('click', function(e) {
        const actionBtn = e.target.closest('button[data-action]');
        if (!actionBtn) return;
        const action = actionBtn.getAttribute('data-action');
        const patient = actionBtn.getAttribute('data-patient');
        if (action === 'condition') {
            const item = state.wardPatients.find(x => x.patient === patient);
            if (item) { item.status = 'Under Observation'; renderWard(); }
        } else if (action === 'note') {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info';
            alertDiv.textContent = 'Note added.';
            document.querySelector('.container').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        } else if (action === 'review') {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-warning';
            alertDiv.textContent = 'Doctor review requested.';
            document.querySelector('.container').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        }
    });

    document.getElementById('emergencyTable').addEventListener('click', function(e) {
        const btnNotify = e.target.closest('button[data-action="notifyDoctor"]');
        const btnStabilize = e.target.closest('button[data-action="stabilize"]');
        if (btnNotify) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger';
            alertDiv.textContent = 'On-call doctor notified.';
            document.querySelector('.container').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        }
        if (btnStabilize) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-secondary';
            alertDiv.textContent = 'Stabilization protocol started.';
            document.querySelector('.container').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        }
    });
});
    document.getElementById('pendingVitalsTable').addEventListener('click', function(e) {
        const btn = e.target.closest('button[data-action="sent"]');
        if (!btn) return;
        const idx = parseInt(btn.getAttribute('data-index'), 10);
        const row = btn.closest('tr');
        const select = row ? row.querySelector('select.doctor-select') : null;
        const doctorName = select && select.value ? select.options[select.selectedIndex].text : '';
        const item = state.pendingVitals[idx];
        if (!item) return;
        notifyDoctor(item.patient, item.triage, doctorName);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
