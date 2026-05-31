<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$account_type = 'Admin';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = post('username');
	$password = post('password');
	if ($username && $password) {
		$db = get_db_connection();
		$stmt = $db->prepare('SELECT id, name, password_hash, account_type FROM users WHERE username = ? AND account_type = ? LIMIT 1');
		$stmt->execute([$username, $account_type]);
		$user = $stmt->fetch();
		if ($user && password_verify($password, $user['password_hash'])) {
			$_SESSION['user_id'] = (int)$user['id'];
			$_SESSION['user_name'] = $user['name'];
			$_SESSION['account_type'] = $user['account_type'];
			redirect('/hms/public/dashboard-admin.php');
		} else {
			$error = 'Invalid credentials or account type mismatch';
		}
	} else {
		$error = 'Please enter username and password';
	}
}
include __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-5">
			<div class="card shadow-sm border-primary">
				<div class="card-body p-4">
					<div class="text-center mb-4">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-shield-check text-primary" viewBox="0 0 16 16">
							<path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157 1.813 7.9 4.189 9.102.391.196.824.292 1.27.292s.879-.096 1.27-.292c2.376-1.202 4.743-4.945 4.189-9.102a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
							<path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
						</svg>
					</div>
					<h1 class="h4 mb-4 text-center">Admin Login Panel</h1>
					<?php if ($error) { ?>
						<div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
					<?php } ?>
					<form method="post" autocomplete="off">
						<div class="mb-3">
							<label class="form-label form-required">Username</label>
							<input type="text" class="form-control" name="username" required autofocus>
						</div>
						<div class="mb-3">
							<label class="form-label form-required">Password</label>
							<input type="password" class="form-control" name="password" required>
						</div>
						<button type="submit" class="btn btn-primary w-100 mb-2">Sign in</button>
						<div class="text-center">
							<a href="index.php" class="text-muted small">← Back to Login Selection</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>

