<?php
/**
 * Setup script to add a doctor user account
 * Run this once to create a doctor account
 * Access: http://localhost/hms/public/setup/add_doctor.php
 */

require_once __DIR__ . '/../../config.php';

// Check if doctor already exists
$db = get_db_connection();
$stmt = $db->prepare('SELECT id FROM users WHERE username = ? AND account_type = ?');
$stmt->execute(['doctor', 'Doctor']);
$existing = $stmt->fetch();

if ($existing) {
	echo "Doctor account already exists!<br>";
	echo "Username: doctor<br>";
	echo "Password: Doctor@123<br>";
	exit;
}

// Create doctor account
$password = 'Doctor@123';
$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
	$stmt = $db->prepare('INSERT INTO users (username, name, password_hash, account_type) VALUES (?, ?, ?, ?)');
	$stmt->execute(['doctor', 'Dr. John Smith', $password_hash, 'Doctor']);
	
	echo "<h2>Doctor Account Created Successfully!</h2>";
	echo "<p><strong>Username:</strong> doctor</p>";
	echo "<p><strong>Password:</strong> Doctor@123</p>";
	echo "<p><strong>Name:</strong> Dr. John Smith</p>";
	echo "<p><strong>Account Type:</strong> Doctor</p>";
	echo "<hr>";
	echo "<p><a href='/hms/public/login-doctor.php'>Go to Doctor Login</a></p>";
} catch (PDOException $e) {
	echo "<h2>Error creating doctor account</h2>";
	echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

