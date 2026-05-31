<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
if ($id) {
    try {
        // Ensure tables
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
            INDEX(patient_id), INDEX(invoice_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Load bill
        $r = $db->prepare('SELECT b.*, CONCAT(p.first_name," ",p.last_name) AS patient_name FROM bills b JOIN patients p ON p.id=b.patient_id WHERE b.id = ?');
        $r->execute([$id]);
        $b = $r->fetch();
        if ($b && ($b['status']==='Approved' || $b['status']==='Unpaid')) {
            // Generate invoice number
            $invNo = 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string)$id, true)),0,6));
            $due = date('Y-m-d', strtotime('+7 days'));
            $ins = $db->prepare('INSERT INTO invoices(bill_id,patient_id,invoice_number,amount,status,source,due_date) VALUES (?,?,?,?,?,?,?)');
            $ins->execute([$id,(int)$b['patient_id'],$invNo,(float)$b['amount'],'Unpaid',(string)($b['source'] ?? 'General'),$due]);
            $invoice_id = (int)$db->lastInsertId();
            $up = $db->prepare('UPDATE bills SET status = "Invoiced", invoice_id = ? WHERE id = ?');
            $up->execute([$invoice_id,$id]);
        }
    } catch (Throwable $e) { }
}
redirect('/hms/public/billing/index.php');

