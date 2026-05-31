<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
if ($id) {
	$stmt = $db->prepare('DELETE FROM appointments WHERE id = ?');
	$stmt->execute([$id]);
}
redirect('/hms/public/appointments/index.php');


