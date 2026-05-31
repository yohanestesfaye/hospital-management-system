<?php
/**
 * Fix Receptionist Account Script
 * This script will:
 * 1. Check if Receptionist is in the account_type ENUM
 * 2. Add Receptionist to ENUM if needed
 * 3. Create the receptionist account if it doesn't exist
 * Access: http://localhost/hms/public/setup/fix_receptionist.php
 */

require_once __DIR__ . '/../../config.php';

$db = get_db_connection();
$errors = [];
$success = [];
$warnings = [];

echo "<h2>HMS - Fix Receptionist Account</h2>";
echo "<p>This script will check and fix the receptionist account setup.</p>";
echo "<hr>";

// Step 1: Check if account_type column supports Receptionist
try {
	$stmt = $db->query("SHOW COLUMNS FROM users WHERE Field = 'account_type'");
	$column_info = $stmt->fetch();
	
	if ($column_info) {
		$enum_values = $column_info['Type'];
		if (strpos($enum_values, 'Receptionist') === false) {
			// Need to modify the ENUM to include Receptionist
			$success[] = "Found account_type column, but Receptionist is missing from ENUM";
			$warnings[] = "Attempting to add Receptionist to account_type ENUM...";
			
			try {
				$db->exec("ALTER TABLE users 
					MODIFY COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') 
					NOT NULL DEFAULT 'Admin'");
				$success[] = "✓ Successfully added Receptionist to account_type ENUM";
			} catch (PDOException $e) {
				$errors[] = "✗ Failed to modify account_type ENUM: " . $e->getMessage();
				$errors[] = "You may need to run this SQL manually in phpMyAdmin:";
				$errors[] = "ALTER TABLE users MODIFY COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') NOT NULL DEFAULT 'Admin';";
			}
		} else {
			$success[] = "✓ account_type ENUM already includes Receptionist";
		}
	} else {
		$errors[] = "✗ account_type column not found. Please run migrate_database.php first.";
	}
} catch (PDOException $e) {
	$errors[] = "✗ Error checking database: " . $e->getMessage();
}

// Step 2: Check if receptionist account exists
if (empty($errors)) {
	try {
		$stmt = $db->prepare("SELECT id, username, name, account_type FROM users WHERE username = 'receptionist'");
		$stmt->execute();
		$existing = $stmt->fetch();
		
		if ($existing) {
			if ($existing['account_type'] === 'Receptionist') {
				$success[] = "✓ Receptionist account already exists (ID: " . $existing['id'] . ")";
			} else {
				// Update existing account to Receptionist type
				try {
					$update_stmt = $db->prepare("UPDATE users SET account_type = 'Receptionist' WHERE username = 'receptionist'");
					$update_stmt->execute();
					$success[] = "✓ Updated existing receptionist account to Receptionist type";
				} catch (PDOException $e) {
					$errors[] = "✗ Failed to update receptionist account: " . $e->getMessage();
				}
			}
		} else {
			// Create receptionist account
			$warnings[] = "Receptionist account not found. Creating new account...";
			try {
				$password_hash = password_hash('Receptionist@123', PASSWORD_BCRYPT);
				$stmt = $db->prepare("INSERT INTO users (username, name, password_hash, account_type) VALUES (?, ?, ?, ?)");
				$stmt->execute(['receptionist', 'Receptionist Lisa Anderson', $password_hash, 'Receptionist']);
				$success[] = "✓ Created receptionist account successfully";
				$success[] = "Username: <strong>receptionist</strong>";
				$success[] = "Password: <strong>Receptionist@123</strong>";
			} catch (PDOException $e) {
				$errors[] = "✗ Failed to create receptionist account: " . $e->getMessage();
			}
		}
	} catch (PDOException $e) {
		$errors[] = "✗ Error checking for receptionist account: " . $e->getMessage();
	}
}

// Step 3: Check and add patient_location column to appointments table
if (empty($errors)) {
	try {
		$stmt = $db->query("SHOW COLUMNS FROM appointments LIKE 'patient_location'");
		$column_exists = $stmt->fetch();
		
		if (!$column_exists) {
			$warnings[] = "patient_location column not found in appointments table. Adding it...";
			try {
				$db->exec("ALTER TABLE appointments 
					ADD COLUMN patient_location ENUM('Waiting','With Doctor') DEFAULT 'Waiting' 
					AFTER status");
				$success[] = "✓ Added patient_location column to appointments table";
				
				// Set default for existing appointments
				try {
					$db->exec("UPDATE appointments SET patient_location = 'Waiting' WHERE patient_location IS NULL");
					$success[] = "✓ Set default 'Waiting' for existing appointments";
				} catch (PDOException $e) {
					$warnings[] = "⚠ Could not update existing appointments: " . $e->getMessage();
				}
			} catch (PDOException $e) {
				$errors[] = "✗ Failed to add patient_location column: " . $e->getMessage();
				$errors[] = "Please run: http://localhost/hms/public/setup/migrate_patient_location.php";
			}
		} else {
			$success[] = "✓ patient_location column already exists in appointments table";
		}
	} catch (PDOException $e) {
		$errors[] = "✗ Error checking patient_location column: " . $e->getMessage();
	}
}

// Step 4: Verify the account works
if (empty($errors)) {
	try {
		$stmt = $db->prepare("SELECT id, username, name, account_type FROM users WHERE username = 'receptionist' AND account_type = 'Receptionist'");
		$stmt->execute();
		$verify = $stmt->fetch();
		
		if ($verify) {
			$success[] = "✓ Verification: Receptionist account is properly configured";
			$success[] = "You can now login with:";
			$success[] = "&nbsp;&nbsp;Username: <strong>receptionist</strong>";
			$success[] = "&nbsp;&nbsp;Password: <strong>Receptionist@123</strong>";
			$success[] = "&nbsp;&nbsp;Account Type: <strong>Receptionist</strong>";
		} else {
			$errors[] = "✗ Verification failed: Account exists but type mismatch";
		}
	} catch (PDOException $e) {
		$errors[] = "✗ Error during verification: " . $e->getMessage();
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

if (!empty($warnings)) {
	echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
	echo "<h3 style='color: #856404; margin-top: 0;'>Warnings:</h3>";
	echo "<ul>";
	foreach ($warnings as $msg) {
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

echo "<hr>";
echo "<p><a href='/hms/public/login.php'>← Go to Login Page</a> | <a href='/hms/public/index.php'>Back to Home</a></p>";

