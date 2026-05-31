<?php
/**
 * Migration Script to Add patient_location Column
 * This script will add the patient_location column to the appointments table
 * Access: http://localhost/hms/public/setup/migrate_patient_location.php
 */

require_once __DIR__ . '/../../config.php';

$db = get_db_connection();
$errors = [];
$success = [];

echo "<h2>HMS - Add patient_location Column</h2>";
echo "<p>This script will add the patient_location column to the appointments table.</p>";
echo "<hr>";

// Check if patient_location column exists
try {
	$stmt = $db->query("SHOW COLUMNS FROM appointments LIKE 'patient_location'");
	$column_exists = $stmt->fetch();
	
	if ($column_exists) {
		$success[] = "✓ patient_location column already exists in appointments table";
	} else {
		// Add the column
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
				$errors[] = "⚠ Warning: Could not update existing appointments: " . $e->getMessage();
			}
		} catch (PDOException $e) {
			$errors[] = "✗ Failed to add patient_location column: " . $e->getMessage();
			$errors[] = "You may need to run this SQL manually in phpMyAdmin:";
			$errors[] = "ALTER TABLE appointments ADD COLUMN patient_location ENUM('Waiting','With Doctor') DEFAULT 'Waiting' AFTER status;";
		}
	}
} catch (PDOException $e) {
	$errors[] = "✗ Error checking database: " . $e->getMessage();
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
	echo "<div style='background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; border-radius: 5px; margin: 20px 0;'>";
	echo "<h3 style='color: #0c5460; margin-top: 0;'>Next Steps:</h3>";
	echo "<ol>";
	echo "<li><a href='/hms/public/dashboard-receptionist.php'>Go to Receptionist Dashboard</a></li>";
	echo "<li><a href='/hms/public/login.php'>Go to Login Page</a></li>";
	echo "</ol>";
	echo "</div>";
}

echo "<hr>";
echo "<p><a href='/hms/public/index.php'>← Back to Home</a></p>";

