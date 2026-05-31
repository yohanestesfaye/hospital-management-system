<?php
/**
 * Language Switcher
 * Handles language preference changes
 */
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config.php';

$lang = isset($_GET['lang']) ? trim((string)$_GET['lang']) : 'en';
set_language($lang);

// Redirect back to referrer or dashboard
$referer = $_SERVER['HTTP_REFERER'] ?? '/hms/public/dashboard.php';
redirect($referer);

