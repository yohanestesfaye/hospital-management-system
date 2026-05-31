<?php
/**
 * Migration: Create lab_results table for storing patient lab results
 * Access: http://localhost/hms/public/setup/migrate_add_lab_results.php
 */
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$success = [];
$errors = [];

echo '<h2>HMS - Migration: Create lab_results table</h2>';

try {
    $db->exec("CREATE TABLE IF NOT EXISTS lab_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        test_name VARCHAR(150) NOT NULL,
        result_value VARCHAR(150) NULL,
        result_unit VARCHAR(30) NULL,
        status ENUM('Pending','Ready','Reviewed') DEFAULT 'Pending',
        result_date DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lab_patient_id (patient_id),
        CONSTRAINT fk_lab_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $success[] = '✓ lab_results table is ready';
} catch (PDOException $e) {
    $errors[] = '✗ Failed to create lab_results table: ' . $e->getMessage();
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