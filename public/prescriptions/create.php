<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Doctor');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

try { $db->query('SELECT 1 FROM prescriptions LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        medication VARCHAR(200) NULL,
        pharmaceutical_id INT NULL,
        dosage VARCHAR(100) NULL,
        message TEXT NULL,
        details TEXT NULL,
        dispensed TINYINT(1) DEFAULT 0,
        dispensed_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'medication'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN medication VARCHAR(200) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'pharmaceutical_id'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN pharmaceutical_id INT NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'dosage'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN dosage VARCHAR(100) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'message'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN message TEXT NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'quantity'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN quantity INT DEFAULT 1"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'unit_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'total_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}

$patients = $db->query("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM patients ORDER BY first_name, last_name")->fetchAll();
$drugs = [];
try {
    $db->exec("CREATE TABLE IF NOT EXISTS pharmaceuticals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        sku VARCHAR(100) NULL,
        category_id INT NULL,
        vendor_id INT NULL,
        unit_price DECIMAL(10,2) DEFAULT 0,
        stock_qty INT DEFAULT 0,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $drugs = $db->query("SELECT id, name, sku, unit_price, stock_qty FROM pharmaceuticals ORDER BY name ASC")->fetchAll();
} catch (Throwable $e) {}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $patient_id = (int)post('patient_id');
    $pharmaceutical_id = (int)post('pharmaceutical_id');
    $medication = (string)post('medication');
    $dosage = (string)post('dosage');
    $message = (string)post('message');
    $quantity = max(1, (int)post('quantity', 1));
    if(!$patient_id){ $errors[]='Patient is required'; }
    if(!$pharmaceutical_id && trim($medication)===''){ $errors[]='Medication is required'; }
    if(trim($dosage)===''){ $errors[]='Dosage is required'; }
    if($quantity <= 0){ $errors[]='Quantity must be greater than zero'; }
    if(!$errors){
        $unit_price = 0;
        // If a drug ID was chosen, snapshot its name and price
        if ($pharmaceutical_id > 0) {
            $stmtn = $db->prepare('SELECT name, unit_price FROM pharmaceuticals WHERE id = ?');
            $stmtn->execute([$pharmaceutical_id]);
            $rown = $stmtn->fetch();
            if ($rown) {
                if (trim($medication) === '') {
                    $medication = (string)$rown['name'];
                }
                $unit_price = (float)$rown['unit_price'];
            }
        }
        $total_price = $quantity * $unit_price;
        $did = resolve_doctor_id($db);
        if(!$did){ $did = (int)($_SESSION['user_id'] ?? 0); }
        $stmt=$db->prepare('INSERT INTO prescriptions(patient_id,doctor_id,pharmaceutical_id,medication,dosage,message,quantity,unit_price,total_price) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$patient_id,$did,$pharmaceutical_id ?: null,$medication,$dosage,$message,$quantity,$unit_price,$total_price]);
        redirect('/hms/public/prescriptions/index.php');
    }
}

include __DIR__ . '/../../includes/header.php';
?>
<?php $role = current_account_type(); $accent = '#0b7285'; $accentDark = '#075465'; if($role==='Admin'){ $accent='#28a745'; $accentDark='#1f7a34'; } elseif($role==='Doctor'){ $accent='#0b7285'; $accentDark='#075465'; } elseif($role==='Nurse'){ $accent='#ffc107'; $accentDark='#e0a800'; } elseif($role==='Pharmacist'){ $accent='#0d6efd'; $accentDark='#0b5ed7'; } $overlay = (current_account_type()==='Doctor' && get('overlay','')==='1'); ?>
<style>
.rx-hero{background:linear-gradient(90deg,var(--accent),var(--accent-dark));color:#fff;border-radius:16px;padding:16px 20px;box-shadow:0 8px 16px rgba(0,0,0,.08)}
.rx-card{border:0;border-radius:16px;box-shadow:0 12px 24px rgba(0,0,0,.08)}
.btn-accent{background-color:var(--accent);border-color:var(--accent);color:#fff}
.btn-accent:hover{background-color:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
.rx-overlay{position:fixed;inset:0;background:rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center;padding:24px;z-index:2000}
.rx-modal{width:100%;max-width:980px}
</style>
<div class="<?php echo $overlay?'rx-overlay':''; ?>" style="--accent: <?php echo $accent; ?>; --accent-dark: <?php echo $accentDark; ?>;">
<div class="<?php echo $overlay?'rx-modal':''; ?> container py-4">
    <div class="card rx-card mb-3">
        <div class="card-header rx-hero d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">Add Prescription</div>
            <a class="btn btn-sm btn-light" href="/hms/public/prescriptions/index.php">Prescription List</a>
        </div>
        <div class="card-body">
            <?php if ($errors) { ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label form-required">Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">Select patient</option>
                            <?php foreach($patients as $p){ ?>
                                <option value="<?php echo (int)$p['id']; ?>"><?php echo sanitize($p['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-required">Medication</label>
                        <select name="pharmaceutical_id" id="pharmaceutical_id" class="form-select" onchange="updatePrice()">
                            <option value="">Select medication</option>
                            <?php foreach($drugs as $d){ ?>
                                <option value="<?php echo (int)$d['id']; ?>" 
                                        data-price="<?php echo number_format((float)$d['unit_price'], 2); ?>"
                                        data-name="<?php echo htmlspecialchars($d['name']); ?>">
                                    <?php echo sanitize($d['name']); ?><?php echo $d['sku']? ' ('.sanitize($d['sku']).')':''; ?>
                                    <?php if((float)$d['unit_price'] > 0) { ?> - <?php echo format_currency((float)$d['unit_price']); ?><?php } ?>
                                </option>
                            <?php } ?>
                        </select>
                        <div class="form-text">If not in list, type a custom name below.</div>
                        <input type="text" name="medication" id="medication" class="form-control mt-2" placeholder="Custom medication name (optional)">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label form-required">Dosage</label>
                        <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg × 2/day" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-required">Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required onchange="calculateTotal()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Unit Price</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo sanitize($env['currency_symbol']); ?></span>
                            <input type="text" id="unit_price_display" class="form-control" readonly value="0.00">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Total Price</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo sanitize($env['currency_symbol']); ?></span>
                            <input type="text" id="total_price_display" class="form-control fw-bold text-success" readonly value="0.00">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <textarea name="message" rows="4" class="form-control" placeholder="Usage instructions or notes"></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-accent">Save</button>
                        <a class="btn btn-outline-secondary" href="/hms/public/prescriptions/index.php">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
<script>
function updatePrice() {
    const select = document.getElementById('pharmaceutical_id');
    const selectedOption = select.options[select.selectedIndex];
    const unitPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-price') || 0) : 0;
    const medicationName = selectedOption ? selectedOption.getAttribute('data-name') || '' : '';
    
    document.getElementById('unit_price_display').value = unitPrice.toFixed(2);
    
    // Auto-fill medication name if custom field is empty
    if (medicationName && !document.getElementById('medication').value) {
        document.getElementById('medication').value = medicationName;
    }
    
    calculateTotal();
}

function calculateTotal() {
    const quantity = parseInt(document.getElementById('quantity').value) || 0;
    const unitPrice = parseFloat(document.getElementById('unit_price_display').value) || 0;
    const total = quantity * unitPrice;
    document.getElementById('total_price_display').value = total.toFixed(2);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePrice();
});
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
