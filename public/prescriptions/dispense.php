<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Pharmacist');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'dispensed_by'")->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN dispensed_by INT NULL"); } } catch (Throwable $e) {}

// Ensure tables exist
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'Unpaid',
        source VARCHAR(30) NULL,
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_date DATETIME NULL,
        INDEX(patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

try { 
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT NULL,
        patient_id INT NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'Unpaid',
        source VARCHAR(30) NULL,
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_date DATETIME NULL,
        receipt_number VARCHAR(50) NULL,
        payment_method VARCHAR(30) NULL,
        processed_by INT NULL,
        prescription_id INT NULL,
        INDEX(patient_id), INDEX(invoice_number), INDEX(prescription_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM invoices LIKE 'bill_id'")->fetch()) { $db->exec("ALTER TABLE invoices ADD COLUMN bill_id INT NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM bills LIKE 'invoice_id'")->fetch()) { $db->exec("ALTER TABLE bills ADD COLUMN invoice_id INT NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM bills LIKE 'paid_date'")->fetch()) { $db->exec("ALTER TABLE bills ADD COLUMN paid_date DATETIME NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM invoices LIKE 'paid_date'")->fetch()) { $db->exec("ALTER TABLE invoices ADD COLUMN paid_date DATETIME NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM invoices LIKE 'receipt_number'")->fetch()) { $db->exec("ALTER TABLE invoices ADD COLUMN receipt_number VARCHAR(50) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM bills LIKE 'source'")->fetch()) { $db->exec("ALTER TABLE bills ADD COLUMN source VARCHAR(30) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM invoices LIKE 'prescription_id'")->fetch()) { $db->exec("ALTER TABLE invoices ADD COLUMN prescription_id INT NULL"); } } catch (Throwable $e) {}
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NULL,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash','Card','Mobile Money','Insurance','Bank Transfer') NOT NULL,
        receipt_number VARCHAR(50) NULL,
        processed_by INT NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(invoice_id), INDEX(patient_id), INDEX(processed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'quantity'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN quantity INT DEFAULT 1"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'unit_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN unit_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM prescriptions LIKE 'total_price'" )->fetch()) { $db->exec("ALTER TABLE prescriptions ADD COLUMN total_price DECIMAL(10,2) DEFAULT 0"); } } catch (Throwable $e) {}

$committed=false;
$error_message = '';
$success_details = '';
$id=(int)get('id');
$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if($id){
    try {
        // Ensure we're not in a transaction already
        try {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        } catch (Throwable $ignore) {}
        
        $db->beginTransaction();
        
        // Get prescription details including pricing
        $q=$db->prepare('SELECT p.*, ph.unit_price AS current_unit_price, ph.name AS pharma_name FROM prescriptions p 
            LEFT JOIN pharmaceuticals ph ON ph.id = p.pharmaceutical_id 
            WHERE p.id = ?');
        $q->execute([$id]);
        $prescription = $q->fetch();
        
        if (!$prescription) {
            throw new Exception('Prescription not found (ID: ' . $id . ')');
        }
        
        if ((int)$prescription['dispensed'] === 1) {
            throw new Exception('Prescription already dispensed');
        }
        
        // Calculate total price
        $quantity = max(1, (int)($prescription['quantity'] ?? 1));
        $unit_price = (float)($prescription['unit_price'] ?? 0);
        
        // If unit_price is 0, try to get from pharmaceutical
        if ($unit_price <= 0 && (int)($prescription['pharmaceutical_id'] ?? 0) > 0) {
            $unit_price = (float)($prescription['current_unit_price'] ?? 0);
        }
        
        $total_price = $quantity * $unit_price;
        
        // If still 0, set a minimum of 0.01 for tracking
        if ($total_price <= 0) {
            $total_price = 0.01; // Minimum amount for tracking
        }
        
        // Update prescription to dispensed FIRST (before creating bill)
        if ($uid) {
            $stmt=$db->prepare('UPDATE prescriptions SET dispensed = 1, dispensed_at = NOW(), dispensed_by = ? WHERE id = ? AND dispensed = 0');
            $stmt->execute([$uid, $id]);
            $rows_updated = $stmt->rowCount();
        } else {
            $stmt=$db->prepare('UPDATE prescriptions SET dispensed = 1, dispensed_at = NOW() WHERE id = ? AND dispensed = 0');
            $stmt->execute([$id]);
            $rows_updated = $stmt->rowCount();
        }
        
        if ($rows_updated === 0) {
            throw new Exception('Failed to update prescription status - prescription may have been dispensed by another user');
        }

        // Update stock
        if($prescription && (int)($prescription['pharmaceutical_id'] ?? 0) > 0){
            $pid=(int)$prescription['pharmaceutical_id'];
            $stock_stmt = $db->prepare('UPDATE pharmaceuticals SET stock_qty = GREATEST(stock_qty - ?, 0) WHERE id = ?');
            $stock_stmt->execute([$quantity, $pid]);
        }
        
        // Create bill
        
        $bill_stmt = $db->prepare('INSERT INTO bills(patient_id, amount, status, source, paid_date) VALUES (?, ?, ?, ?, NOW())');
        $bill_result = $bill_stmt->execute([(int)$prescription['patient_id'], $total_price, 'Paid', 'Pharmacy']);
        
        if (!$bill_result) {
            $error_info = $bill_stmt->errorInfo();
            throw new Exception('Failed to create bill: ' . ($error_info[2] ?? 'Unknown error'));
        }
        
        $bill_id = (int)$db->lastInsertId();
        
        if ($bill_id === 0) {
            // Try alternative method
            $check_bill = $db->prepare('SELECT id FROM bills WHERE patient_id = ? AND amount = ? AND source = "Pharmacy" ORDER BY id DESC LIMIT 1');
            $check_bill->execute([(int)$prescription['patient_id'], $total_price]);
            $bill_check = $check_bill->fetch();
            if ($bill_check) {
                $bill_id = (int)$bill_check['id'];
            } else {
                throw new Exception('Failed to get bill ID after creation');
            }
        }
        
        // Create invoice
        
        $invoice_number = 'INV-PRX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$id . time(), true)), 0, 6));
        $due_date = date('Y-m-d', strtotime('+7 days'));
        $receiptNumber = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$id . time(), true)), 0, 6));
        $invoice_stmt = $db->prepare('INSERT INTO invoices(bill_id, patient_id, invoice_number, amount, status, source, due_date, paid_date, receipt_number, prescription_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)');
        $invoice_result = $invoice_stmt->execute([
            $bill_id, 
            (int)$prescription['patient_id'], 
            $invoice_number, 
            $total_price, 
            'Paid', 
            'Pharmacy', 
            $due_date, 
            $receiptNumber,
            $id
        ]);
        
        if (!$invoice_result) {
            $error_info = $invoice_stmt->errorInfo();
            throw new Exception('Failed to create invoice: ' . ($error_info[2] ?? 'Unknown error'));
        }
        
        $invoice_id = (int)$db->lastInsertId();
        
        if ($invoice_id === 0) {
            // Try alternative method
            $check_inv = $db->prepare('SELECT id FROM invoices WHERE bill_id = ? AND prescription_id = ? ORDER BY id DESC LIMIT 1');
            $check_inv->execute([$bill_id, $id]);
            $inv_check = $check_inv->fetch();
            if ($inv_check) {
                $invoice_id = (int)$inv_check['id'];
            } else {
                throw new Exception('Failed to get invoice ID after creation');
            }
        }
        
        // Ensure bill links to created invoice (needed for mark_paid flow) and stays marked paid
        try {
            $link_stmt = $db->prepare('UPDATE bills SET invoice_id = ?, status = \'Paid\', paid_date = NOW() WHERE id = ?');
            $link_stmt->execute([$invoice_id, $bill_id]);
        } catch (Throwable $e) {}

        // Update prescription total_price if it was 0
        if ($total_price > 0 && (float)($prescription['total_price'] ?? 0) <= 0) {
            $update_price_stmt = $db->prepare('UPDATE prescriptions SET total_price = ?, unit_price = ? WHERE id = ?');
            $update_price_stmt->execute([$total_price, $unit_price, $id]);
        }
        
        // Record auto payment entry if none exists
        try {
            $checkPay = $db->prepare('SELECT id FROM payments WHERE invoice_id = ? LIMIT 1');
            $checkPay->execute([$invoice_id]);
            if (!$checkPay->fetch()) {
                $processedBy = current_user_id() ?: ($uid ?? 0);
                $payStmt = $db->prepare('INSERT INTO payments(invoice_id, patient_id, amount, payment_method, receipt_number, processed_by, notes) VALUES (?,?,?,?,?,?,?)');
                $payStmt->execute([$invoice_id, (int)$prescription['patient_id'], $total_price, 'Cash', $receiptNumber, $processedBy, 'Auto payment at dispense']);
            }
        } catch (Throwable $e) {}

        try {
            if ($db->inTransaction()) { $db->commit(); }
        } catch (Throwable $ignore) {}
        $committed=true;
        $success_details = "Bill #{$bill_id} and Invoice #{$invoice_id} created successfully";
    } catch (PDOException $e) {
        try { if ($db->inTransaction()) { $db->rollBack(); } } catch (Throwable $ignore) {}
        $error_message = 'Database error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')';
    } catch (Throwable $e) {
        try { if ($db->inTransaction()) { $db->rollBack(); } } catch (Throwable $ignore) {}
        $error_message = $e->getMessage();
    }
}
$ref = $_SERVER['HTTP_REFERER'] ?? '/hms/public/prescriptions/index.php';
if ($committed) {
    if (strpos($ref,'?')!==false) { 
        $ref .= '&msg=dispensed'; 
    } else { 
        $ref .= '?msg=dispensed'; 
    }
    if ($success_details) {
        $ref .= '&details=' . urlencode($success_details);
    }
} elseif ($error_message) {
    if (strpos($ref,'?')!==false) { 
        $ref .= '&error=' . urlencode($error_message); 
    } else { 
        $ref .= '?error=' . urlencode($error_message); 
    }
}
redirect($ref);
