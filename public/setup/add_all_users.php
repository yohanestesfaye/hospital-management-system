<?php
/**
 * Setup script to add default user accounts for all actor types
 * Run this once to create all default accounts
 * Access: http://localhost/hms/public/setup/add_all_users.php
 */

require_once __DIR__ . '/../../config.php';

$db = get_db_connection();

// Define all default users
$default_users = [
	[
		'username' => 'admin',
		'name' => 'Administrator',
		'password' => 'Admin@123',
		'account_type' => 'Admin'
	],
	[
		'username' => 'doctor',
		'name' => 'Dr. John Smith',
		'password' => 'Doctor@123',
		'account_type' => 'Doctor'
	],
	[
		'username' => 'patient',
		'name' => 'John Patient',
		'password' => ' ',
		'account_type' => 'Patient'
	],
	[
		'username' => 'nurse',
		'name' => 'Nurse Jane Doe',
		'password' => 'Nurse@123',
		'account_type' => 'Nurse'
	],
	[
		'username' => 'pharmacist',
		'name' => 'Pharmacist Bob Wilson',
		'password' => 'Pharmacist@123',
		'account_type' => 'Pharmacist'
	],
	[
		'username' => 'laboratorist',
		'name' => 'Lab Tech Sarah Johnson',
		'password' => 'Laboratorist@123',
		'account_type' => 'Laboratorist'
	],
	[
		'username' => 'accountant',
		'name' => 'Accountant Mike Brown',
		'password' => 'Accountant@123',
		'account_type' => 'Accountant'
	],
	[
		'username' => 'receptionist',
		'name' => 'Receptionist Lisa Anderson',
		'password' => 'Receptionist@123',
		'account_type' => 'Receptionist'
	]
];

$created = [];
$skipped = [];
$errors = [];

echo "<h2>HMS - Create All Default User Accounts</h2>";
echo "<p>This script will create default accounts for all actor types.</p>";
echo "<hr>";

foreach ($default_users as $user) {
	// Check if user already exists
	$stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND account_type = ?');
	$stmt->execute([$user['username'], $user['account_type']]);
	$existing = $stmt->fetch();
	
	if ($existing) {
		$skipped[] = $user;
		continue;
	}
	
	// Create password hash
	$password_hash = password_hash($user['password'], PASSWORD_BCRYPT);
	
	// Insert user
	try {
		$stmt = $db->prepare('INSERT INTO users (username, name, password_hash, account_type) VALUES (?, ?, ?, ?)');
		$stmt->execute([$user['username'], $user['name'], $password_hash, $user['account_type']]);
		$created[] = $user;
	} catch (PDOException $e) {
		$errors[] = [
			'user' => $user,
			'error' => $e->getMessage()
		];
	}
}

// Display results
if (!empty($created)) {
	echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #155724; margin-top: 0;'>✓ Accounts Created:</h3>";
	echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
	echo "<tr style='background: #c3e6cb;'><th>Username</th><th>Password</th><th>Name</th><th>Account Type</th></tr>";
	foreach ($created as $user) {
		echo "<tr>";
		echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
		echo "<td>" . htmlspecialchars($user['password']) . "</td>";
		echo "<td>" . htmlspecialchars($user['name']) . "</td>";
		echo "<td>" . htmlspecialchars($user['account_type']) . "</td>";
		echo "</tr>";
	}
	echo "</table>";
	echo "</div>";
}

if (!empty($skipped)) {
	echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #856404; margin-top: 0;'>⚠ Accounts Already Exist (Skipped):</h3>";
	echo "<ul>";
	foreach ($skipped as $user) {
		echo "<li><strong>" . htmlspecialchars($user['username']) . "</strong> (" . htmlspecialchars($user['account_type']) . ")</li>";
	}
	echo "</ul>";
	echo "</div>";
}

if (!empty($errors)) {
	echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #721c24; margin-top: 0;'>✗ Errors:</h3>";
	echo "<ul>";
	foreach ($errors as $error) {
		echo "<li><strong>" . htmlspecialchars($error['user']['username']) . "</strong>: " . htmlspecialchars($error['error']) . "</li>";
	}
	echo "</ul>";
	echo "</div>";
}

// Summary table
echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 20px 0;'>";
echo "<h3 style='color: #0c5460; margin-top: 0;'>📋 All Login Credentials:</h3>";
echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #bee5eb;'><th>Account Type</th><th>Username</th><th>Password</th><th>Login Page</th></tr>";
foreach ($default_users as $user) {
	$login_page = 'login-' . strtolower($user['account_type']) . '.php';
	echo "<tr>";
	echo "<td><strong>" . htmlspecialchars($user['account_type']) . "</strong></td>";
	echo "<td>" . htmlspecialchars($user['username']) . "</td>";
	echo "<td>" . htmlspecialchars($user['password']) . "</td>";
	echo "<td><a href='/hms/public/" . htmlspecialchars($login_page) . "'>Login</a></td>";
	echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "<hr>";
echo "<p><a href='/hms/public/index.php'>← Back to Login Selection</a></p>";

