<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Laboratorist','LabManager']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure table exists
try { $db->query('SELECT 1 FROM lab_results LIMIT 1'); } catch (PDOException $e) {
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
// Ensure extended columns exist for compatibility
foreach ([
    ["requested_by INT NULL", "requested_by"],
    ["processed_by INT NULL", "processed_by"],
    ["priority ENUM('Urgent','Routine') DEFAULT 'Routine'", "priority"],
    ["status ENUM('Pending','Ready','Reviewed') DEFAULT 'Pending'", "status"],
    ["notes TEXT NULL", "notes"],
] as $col) {
    try { $exists = $db->query("SHOW COLUMNS FROM lab_results LIKE '".$col[1]."'")->fetch() !== false; if(!$exists){ $db->exec("ALTER TABLE lab_results ADD COLUMN " . $col[0]); } } catch (Throwable $e) {}
}

try { $db->exec("ALTER TABLE lab_results MODIFY COLUMN status ENUM('Pending','Ready','Reviewed','Completed') DEFAULT 'Pending'"); } catch (Throwable $e) { }
$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();
try { $doctors = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM doctors ORDER BY first_name, last_name")->fetchAll(); } catch (Throwable $e) { $doctors = []; }
$default_patient = (int)get('patient_id', 0);
$default_test = (string)get('test_name', '');

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $patient_id=(int)post('patient_id');
    $test_name=(string)post('test_name');
    $result=(string)post('result');
    $result_date=(string)post('result_date');
    $doctor_id=(int)post('doctor_id');
    $requested_by = null;
    if ($doctor_id > 0) {
        try {
            $st = $db->prepare("SELECT id FROM users WHERE account_type = 'Doctor' AND doctor_id = ? LIMIT 1");
            $st->execute([$doctor_id]);
            $r = $st->fetch();
            if ($r) { $requested_by = (int)$r['id']; }
        } catch (Throwable $e) {}
    }
    if(!$patient_id){ $errors[]='Patient is required'; }
    if(trim($test_name)===''){ $errors[]='Test name is required'; }
    if(!$errors){
        $has = function($name) use ($db) {
            try { return $db->query("SHOW COLUMNS FROM lab_results LIKE '".$name."'")->fetch() !== false; } catch (Throwable $e) { return false; }
        };
        $existing_id = null;
        try {
            $st = $db->prepare("SELECT id FROM lab_results WHERE patient_id = ? AND test_name = ? AND COALESCE(status,'Pending')='Pending' ORDER BY created_at DESC LIMIT 1");
            $st->execute([$patient_id, $test_name]);
            $row = $st->fetch();
            if ($row) { $existing_id = (int)$row['id']; }
        } catch (Throwable $e) {}

        if ($existing_id) {
            $fields = [];
            $vals = [];
            if($has('result')){ $fields[]='result = ?'; $vals[]=$result; }
            if($has('result_value')){ $fields[]='result_value = ?'; $vals[]=$result; }
            if($has('result_unit')){ $fields[]='result_unit = ?'; $vals[]=''; }
            if($has('result_date')){ $fields[]='result_date = ?'; $vals[]=$result_date ?: null; }
            if($has('status')){ $fields[]="status = 'Completed'"; }
            if($has('requested_by')){ $fields[]='requested_by = ?'; $vals[]=$requested_by; }
            if($has('processed_by')){ $fields[]='processed_by = ?'; $vals[]=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null; }
            $sql = 'UPDATE lab_results SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $vals[] = $existing_id;
            $db->prepare($sql)->execute($vals);
        } else {
            $cols=['patient_id','test_name'];
            $vals=[$patient_id,$test_name];
            if($has('processed_by')){ $cols[]='processed_by'; $vals[]=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null; }
            if($has('requested_by')){ $cols[]='requested_by'; $vals[]=$requested_by; }
            if($has('result')){ $cols[]='result'; $vals[]=$result; }
            if($has('result_value')){ $cols[]='result_value'; $vals[]=$result; }
            if($has('result_unit')){ $cols[]='result_unit'; $vals[]=''; }
            if($has('result_date')){ $cols[]='result_date'; $vals[]=$result_date ?: null; }
            if($has('status')){ $cols[]='status'; $vals[]='Completed'; }
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO lab_results(' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
            $stmt=$db->prepare($sql);
            $stmt->execute($vals);
        }
        redirect('/hms/public/lab_results/index.php');
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Add Lab Result</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/lab_results/index.php"><i class="bi bi-list"></i> Results</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-required">Patient</label>
                        <?php if($default_patient){ ?>
                            <select class="form-select" disabled>
                                <?php foreach($patients as $p){ ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $default_patient===(int)$p['id']?'selected':''; ?>><?php echo sanitize($p['name']); ?></option><?php } ?>
                            </select>
                            <input type="hidden" name="patient_id" value="<?php echo (int)$default_patient; ?>">
                        <?php } else { ?>
                            <select name="patient_id" class="form-select" required>
                                <option value="">Select patient</option>
                                <?php foreach($patients as $p){ ?><option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option><?php } ?>
                            </select>
                        <?php } ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-required">Test Name</label>
                        <?php if($default_test!==''){ ?>
                            <input type="text" class="form-control" value="<?php echo sanitize($default_test); ?>" disabled>
                            <input type="hidden" name="test_name" value="<?php echo sanitize($default_test); ?>">
                        <?php } else { ?>
                            <input type="text" name="test_name" class="form-control" required>
                        <?php } ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ordering Doctor</label>
                        <select name="doctor_id" class="form-select">
                            <option value="">Select doctor</option>
                            <?php foreach($doctors as $d){ ?>
                                <option value="<?php echo (int)$d['id']; ?>"><?php echo sanitize($d['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Result Date</label>
                        <input type="datetime-local" name="result_date" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Result</label>
                        <textarea name="result" rows="5" class="form-control"></textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Save</button><a class="btn btn-secondary" href="/hms/public/lab_results/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
