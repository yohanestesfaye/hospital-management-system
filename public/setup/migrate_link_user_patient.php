<?php
/**
 * Migration: Link users to patients by adding users.patient_id
 * Access: http://localhost/hms/public/setup/migrate_link_user_patient.php
 */
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$success = [];
$errors = [];

echo '<h2>HMS - Migration: Add users.patient_id</h2>';

try {
    $col = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch();
    if ($col) {
        $success[] = '✓ Column users.patient_id already exists';
    } else {
        $db->exec("ALTER TABLE users ADD COLUMN patient_id INT NULL DEFAULT NULL AFTER account_type");
        $success[] = '✓ Added users.patient_id column';
        // Try to add a foreign key if patients table exists
        try {
            // MySQL needs an index on the FK column
            $db->exec("ALTER TABLE users ADD INDEX idx_users_patient_id (patient_id)");
        } catch (PDOException $e) {
            // ignore if index exists
        }
        try {
            $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_patient_id FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL");
            $success[] = '✓ Added FK fk_users_patient_id to patients(id)';
        } catch (PDOException $e) {
            $errors[] = '⚠ Could not add foreign key automatically: ' . $e->getMessage();
        }
    }
} catch (PDOException $e) {
    $errors[] = '✗ Error inspecting users table: ' . $e->getMessage();
}

echo '<hr>';
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