<?php
/**
 * Migration: Create invoices table for patient billing
 * Access: http://localhost/hms/public/setup/migrate_add_billing.php
 */
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$success = [];
$errors = [];

echo '<h2>HMS - Migration: Create invoices table</h2>';

try {
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        invoice_number VARCHAR(40) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('Unpaid','Paid','Cancelled','Partial') DEFAULT 'Unpaid',
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inv_patient_id (patient_id),
        CONSTRAINT fk_inv_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $success[] = '✓ invoices table is ready';
} catch (PDOException $e) {
    $errors[] = '✗ Failed to create invoices table: ' . $e->getMessage();
}

if ($success) {
    echo "<div style='background:#d4edda;padding:10px;border:1px solid #c3e6cb'><ul>";
    foreach ($success as $s) echo '<li>' . htmlspecialchars($s) . '</li>';
    echo '</ul></div>';
}
if ($errors) {
    echo "<div style='background:#f8d7da;padding:10px;border:1px solid #f5c6cb'><ul>";
    foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>';
    echo '</ul></div>';
}

echo "<p><a href='/hms/public/dashboard-patient.php'>← Back to Patient Dashboard</a></p>";