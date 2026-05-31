<?php
/**
 * Migration: Create notifications table for user notifications
 * Access: http://localhost/hms/public/setup/migrate_add_notifications.php
 */
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$success = [];
$errors = [];

echo '<h2>HMS - Migration: Create notifications table</h2>';

try {
    $db->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_notif_user_id (user_id),
        CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $success[] = '✓ notifications table is ready';
} catch (PDOException $e) {
    $errors[] = '✗ Failed to create notifications table: ' . $e->getMessage();
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