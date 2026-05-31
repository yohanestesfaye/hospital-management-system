<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$id = (int)get('id');
if ($id) {
    try {
        $db->exec("ALTER TABLE bills ADD COLUMN approved_by INT NULL");
        $stmt = $db->prepare('UPDATE bills SET status = "Approved", approved_by = ? WHERE id = ? AND status = "Unpaid"');
        $stmt->execute([(int)($_SESSION['user_id'] ?? 0), $id]);
    } catch (Throwable $e) {}
}
redirect('/hms/public/billing/index.php');

