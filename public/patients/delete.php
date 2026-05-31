<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
if ($id) {
	$stmt = $db->prepare('DELETE FROM patients WHERE id = ?');
	$stmt->execute([$id]);
}
redirect('/hms/public/patients/index.php');


