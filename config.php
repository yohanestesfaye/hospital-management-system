<?php
$env = [
	'db_host' => '127.0.0.1',
	'db_name' => 'hms',
	'db_user' => 'root',
	'db_pass' => '',
	'app_name' => 'Hospital Management System',
	'low_stock_threshold' => 5,
    'currency_code' => 'ETB',
    'currency_symbol' => 'ETB '
];

function get_db_connection() {
	global $env;
	static $pdo = null;
	if ($pdo !== null) {
		return $pdo;
	}
	$dsn = 'mysql:host=' . $env['db_host'] . ';dbname=' . $env['db_name'] . ';charset=utf8mb4';
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	try {
		$pdo = new PDO($dsn, $env['db_user'], $env['db_pass'], $options);
		return $pdo;
	} catch (PDOException $e) {
		http_response_code(500);
		echo 'Database connection failed.';
		exit;
	}
}

function sanitize($value) {
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
	header('Location: ' . $path);
	exit;
}

function post($key, $default = null) {
	return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function get($key, $default = null) {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function format_currency($amount) {
    global $env;
    return (string)$env['currency_symbol'] . number_format((float)$amount, 2);
}


