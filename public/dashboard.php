<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_once __DIR__ . '/../config.php';

// Redirect to appropriate dashboard based on account type
$account_type = current_account_type();
if ($account_type) {
	$dashboard_map = [
		'Admin' => 'dashboard-admin.php',
		'Doctor' => 'dashboard-doctor.php',
		'Patient' => 'dashboard-patient.php',
		'Nurse' => 'dashboard-nurse.php',
		'Pharmacist' => 'dashboard-pharmacist.php',
		'Laboratorist' => 'dashboard-laboratorist.php',
		'Accountant' => 'dashboard-accountant.php',
		'Receptionist' => 'dashboard-receptionist.php',
	];
	
	if (isset($dashboard_map[$account_type])) {
		redirect('/hms/public/' . $dashboard_map[$account_type]);
	}
}

// Fallback to admin dashboard if account type not found
redirect('/hms/public/dashboard-admin.php');


