<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

// Redirect to unified login page
redirect('/hms/public/login.php');
