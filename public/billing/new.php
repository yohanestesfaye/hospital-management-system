<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure table exists
try { $db->query('SELECT 1 FROM bills LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'Unpaid',
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_date DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $patient_id=(int)post('patient_id');
    $amount=(float)post('amount');
    if(!$patient_id){ $errors[]='Patient is required'; }
    if($amount<=0){ $errors[]='Amount must be greater than zero'; }
    if(!$errors){
        try { $db->exec("ALTER TABLE bills ADD COLUMN source VARCHAR(30) NULL"); } catch (Throwable $e) {}
        $source = in_array(post('source'), ['Doctor','Pharmacy','Lab','Other'], true) ? post('source') : 'Doctor';
        $stmt=$db->prepare('INSERT INTO bills(patient_id,amount,source) VALUES (?,?,?)');
        $stmt->execute([$patient_id,$amount,$source]);
        redirect('/hms/public/billing/index.php');
    }
}

include __DIR__ . '/../../includes/accountant-header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Generate Bill</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/billing/index.php"><i class="bi bi-list"></i> Bills</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label form-required">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach($patients as $p){ ?><option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label form-required">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-6"><label class="form-label">Source</label>
                        <select name="source" class="form-select">
                            <option value="Doctor">Doctor</option>
                            <option value="Pharmacy">Pharmacy</option>
                            <option value="Lab">Lab</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-12"><button class="btn btn-primary">Create</button><a class="btn btn-secondary" href="/hms/public/billing/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>
