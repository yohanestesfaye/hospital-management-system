<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id=(int)get('id');
if($id){
    // Update bill status
    $stmt=$db->prepare('UPDATE bills SET status = "Paid", paid_date = NOW() WHERE id = ? AND status <> "Paid"');
    $stmt->execute([$id]);
    // Sync invoice if exists
    try {
        $row = $db->prepare('SELECT b.invoice_id, b.patient_id, b.amount, b.source FROM bills b WHERE b.id = ?');
        $row->execute([$id]);
        $b = $row->fetch();
        if ($b && (int)($b['invoice_id'] ?? 0) === 0) {
            // Try to locate invoice linked via bill_id
            $findInv = $db->prepare('SELECT id FROM invoices WHERE bill_id = ? ORDER BY id DESC LIMIT 1');
            $findInv->execute([$id]);
            $invRow = $findInv->fetch();
            if ($invRow && isset($invRow['id'])) {
                $b['invoice_id'] = (int)$invRow['id'];
                // Update bill to remember invoice for future operations
                try {
                    $linkStmt = $db->prepare('UPDATE bills SET invoice_id = ? WHERE id = ?');
                    $linkStmt->execute([(int)$b['invoice_id'], $id]);
                } catch (Throwable $e) {}
            }
        }

        if ($b && (int)$b['invoice_id']) {
            // Ensure invoices table exists
            $db->exec('CREATE TABLE IF NOT EXISTS invoices (id INT AUTO_INCREMENT PRIMARY KEY, bill_id INT NULL, patient_id INT NOT NULL, invoice_number VARCHAR(50) NOT NULL, amount DECIMAL(10,2) NOT NULL, status VARCHAR(20) DEFAULT "Unpaid", source VARCHAR(30) NULL, due_date DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, paid_date DATETIME NULL, receipt_number VARCHAR(50) NULL, INDEX(patient_id), INDEX(invoice_number)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            // Generate receipt number
            $receipt = 'RCPT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$b['invoice_id'], true)),0,6));
            $upd = $db->prepare('UPDATE invoices SET status = "Paid", paid_date = NOW(), receipt_number = ? WHERE id = ?');
            $upd->execute([$receipt, (int)$b['invoice_id']]);
            // Ensure finance_ledger exists and record payment
            $db->exec('CREATE TABLE IF NOT EXISTS finance_ledger (id INT AUTO_INCREMENT PRIMARY KEY, patient_id INT NOT NULL, reference VARCHAR(100) NULL, source VARCHAR(30) NULL, description VARCHAR(255) NULL, amount DECIMAL(10,2) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(patient_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $refRow = $db->prepare('SELECT invoice_number FROM invoices WHERE id = ?');
            $refRow->execute([(int)$b['invoice_id']]);
            $inv = $refRow->fetch();
            $reference = $inv ? (string)$inv['invoice_number'] : ('BILL#'.$id);
            $ins = $db->prepare('INSERT INTO finance_ledger(patient_id,reference,source,description,amount) VALUES (?,?,?,?,?)');
            $ins->execute([(int)$b['patient_id'],$reference,(string)($b['source'] ?? 'General'),'Payment received',(float)$b['amount']]);
        }
    } catch (Throwable $e) { }
}
redirect('/hms/public/billing/index.php');
