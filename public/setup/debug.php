<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/plain');
echo "HMS Debug\n";
try {
	$db = get_db_connection();
	echo "DB: connected to '" . $GLOBALS['env']['db_name'] . "'\n";
	$tables = $db->query("SHOW TABLES")->fetchAll();
	echo "Tables:" . (count($tables)) . "\n";
	$hasUsers = false;
	foreach ($tables as $t) {
		$tbl = array_values($t)[0];
		echo " - $tbl\n";
		if ($tbl === 'users') { $hasUsers = true; }
	}
	if ($hasUsers) {
		$row = $db->query("SELECT COUNT(*) AS c FROM users")->fetch();
		echo "users count: " . (int)$row['c'] . "\n";
		$adm = $db->query("SELECT id, username, name FROM users WHERE username='admin' LIMIT 1")->fetch();
		echo $adm ? "admin user: present\n" : "admin user: NOT FOUND\n";
	} else {
		echo "users table: NOT FOUND\n";
	}
} catch (Throwable $e) {
	http_response_code(500);
	echo 'Error: ' . $e->getMessage();
}


