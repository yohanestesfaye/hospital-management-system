<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lang.php';
?><!doctype html>
<html lang="<?php echo get_current_lang(); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo sanitize($env['app_name']); ?></title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
	<link href="/hms/public/assets/css/styles.css" rel="stylesheet">
</head>
<body>
	<div class="min-vh-100 d-flex flex-column">

