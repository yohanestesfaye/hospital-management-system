<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$account_type = 'Patient';
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
			redirect('/hms/public/dashboard-patient.php');
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
			<div class="card shadow-sm border-info">
				<div class="card-body p-4">
					<div class="text-center mb-4">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-person text-info" viewBox="0 0 16 16">
							<path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4Zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10Z"/>
						</svg>
					</div>
					<h1 class="h4 mb-4 text-center">Patient Login Panel</h1>
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
						<button type="submit" class="btn btn-info w-100 mb-2">Sign in</button>
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

