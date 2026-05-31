<?php
/**
 * Database Migration Script
 * This script will update your existing HMS database to add the account_type column
 * Access: http://localhost/hms/public/setup/migrate_database.php
 */

require_once __DIR__ . '/../../config.php';

$db = get_db_connection();
$errors = [];
$success = [];

echo "<h2>HMS Database Migration</h2>";
echo "<p>This script will update your database to add the account_type column.</p>";
echo "<hr>";

// Check if account_type column exists
try {
	$stmt = $db->query("SHOW COLUMNS FROM users LIKE 'account_type'");
	$column_exists = $stmt->fetch();
	
	if ($column_exists) {
		$success[] = "✓ account_type column already exists in users table";
	} else {
		// Add the column
		try {
			$db->exec("ALTER TABLE users 
				ADD COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') 
				NOT NULL DEFAULT 'Admin' 
				AFTER password_hash");
			$success[] = "✓ Added account_type column to users table";
		} catch (PDOException $e) {
			$errors[] = "✗ Failed to add account_type column: " . $e->getMessage();
		}
	}
} catch (PDOException $e) {
	$errors[] = "✗ Error checking database: " . $e->getMessage();
}

// Update existing users
if (empty($errors)) {
	try {
		$stmt = $db->prepare("UPDATE users SET account_type = 'Admin' WHERE username = 'admin'");
		$stmt->execute();
		$success[] = "✓ Updated admin user account_type";
	} catch (PDOException $e) {
		$errors[] = "✗ Error updating admin user: " . $e->getMessage();
	}
}

// Display results
if (!empty($success)) {
	echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #155724; margin-top: 0;'>Success:</h3>";
	echo "<ul>";
	foreach ($success as $msg) {
		echo "<li>" . htmlspecialchars($msg) . "</li>";
	}
	echo "</ul>";
	echo "</div>";
}

if (!empty($errors)) {
	echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #721c24; margin-top: 0;'>Errors:</h3>";
	echo "<ul>";
	foreach ($errors as $msg) {
		echo "<li>" . htmlspecialchars($msg) . "</li>";
	}
	echo "</ul>";
	echo "</div>";
}

if (empty($errors)) {
	echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #0c5460; margin-top: 0;'>Next Steps:</h3>";
	echo "<ol>";
	echo "<li><a href='/hms/public/setup/add_doctor.php'>Create Doctor Account</a></li>";
	echo "<li><a href='/hms/public/index.php'>Go to Login Page</a></li>";
	echo "</ol>";
	echo "</div>";
}

echo "<hr>";
echo "<p><a href='/hms/public/index.php'>← Back to Home</a></p>";

