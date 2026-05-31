<?php
require_once __DIR__ . '/../../includes/auth.php';
require_any_account_type(['Doctor','Nurse']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure table exists
try { $db->query('SELECT 1 FROM medical_records LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS medical_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        record_type VARCHAR(50) NOT NULL,
        diagnosis TEXT NULL,
        treatment_plan TEXT NULL,
        notes TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { if (!$db->query("SHOW COLUMNS FROM medical_records LIKE 'diagnosis'" )->fetch()) { $db->exec("ALTER TABLE medical_records ADD COLUMN diagnosis TEXT NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM medical_records LIKE 'treatment_plan'" )->fetch()) { $db->exec("ALTER TABLE medical_records ADD COLUMN treatment_plan TEXT NULL"); } } catch (Throwable $e) {}

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $patient_id=(int)post('patient_id');
    $record_type=(string)post('record_type');
    $diagnosis=(string)post('diagnosis');
    $treatment=(string)post('treatment_plan');
    $notes=(string)post('notes');
    if(!$patient_id){ $errors[]='Patient is required'; }
    if(trim($record_type)===''){ $errors[]='Type is required'; }
    if(trim($notes)===''){ $errors[]='Notes are required'; }
    if(!$errors){
        $stmt=$db->prepare('INSERT INTO medical_records(patient_id,record_type,diagnosis,treatment_plan,notes,created_by) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$patient_id,$record_type,$diagnosis,$treatment,$notes,(int)$_SESSION['user_id']]);
        redirect('/hms/public/medical_records/index.php');
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Add Medical Record</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/medical_records/index.php"><i class="bi bi-list"></i> Records</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-required">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach($patients as $p){ ?><option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-required">Record Type</label>
                        <select name="record_type" class="form-select" required>
                            <option value="">Select type</option>
                            <option>Diagnosis</option>
                            <option>Vitals</option>
                            <option>Treatment Plan</option>
                            <option>Report</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Diagnosis</label>
                        <textarea name="diagnosis" rows="4" class="form-control"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Treatment Plan</label>
                        <textarea name="treatment_plan" rows="4" class="form-control"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label form-required">Notes</label>
                        <textarea name="notes" rows="6" class="form-control" required></textarea>
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Save</button><a class="btn btn-secondary" href="/hms/public/medical_records/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>