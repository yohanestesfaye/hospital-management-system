<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$allowed_roles = ['Nurse','Receptionist','Pharmacist','Laboratorist'];

$id = (int)get('id');
if ($id) {
    // Ensure we only delete allowed staff roles
    $stmt = $db->prepare('SELECT account_type FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user && in_array($user['account_type'], $allowed_roles, true)) {
        $del = $db->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
    }
}
redirect('/hms/public/staff/index.php');