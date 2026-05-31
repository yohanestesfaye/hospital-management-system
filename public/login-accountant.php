<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$account_type = 'Accountant';
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
			redirect('/hms/public/dashboard-accountant.php');
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
			<div class="card shadow-sm border-dark">
				<div class="card-body p-4">
					<div class="text-center mb-4">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-calculator text-dark" viewBox="0 0 16 16">
							<path d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h8ZM4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H4Z"/>
							<path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5v-2ZM4 6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V6Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V6Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V6Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V6Zm-6 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V8Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V8Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V8Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5V8Zm-6 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1Zm-6 2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5Z"/>
						</svg>
					</div>
					<h1 class="h4 mb-4 text-center">Accountant Login Panel</h1>
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
						<button type="submit" class="btn btn-dark w-100 mb-2">Sign in</button>
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

