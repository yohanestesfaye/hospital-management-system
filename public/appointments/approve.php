<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
if ($id) {
    // Only approve if currently Pending
    $stmt = $db->prepare('UPDATE appointments SET status = "Scheduled" WHERE id = ? AND status = "Pending"');
    $stmt->execute([$id]);
}
redirect('/hms/public/appointments/index.php');