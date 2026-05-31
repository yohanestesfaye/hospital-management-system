<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$account_type = 'Nurse';
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
			redirect('/hms/public/dashboard-nurse.php');
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
			<div class="card shadow-sm border-warning">
				<div class="card-body p-4">
					<div class="text-center mb-4">
						<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-heart text-warning" viewBox="0 0 16 16">
							<path d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053c-.523 1.023-.641 2.5.314 4.385.92 1.815 2.834 3.989 6.286 6.357 3.452-2.368 5.365-4.542 6.286-6.357.955-1.886.838-3.362.314-4.385C13.486.878 10.4.28 8.717 2.01L8 2.748zM8 15C-7.333 4.868 3.279-3.04 7.824 1.143c.06.055.119.112.176.171a3.12 3.12 0 0 1 .176-.17C12.72-3.042 23.333 4.867 8 15z"/>
						</svg>
					</div>
					<h1 class="h4 mb-4 text-center">Nurse Login Panel</h1>
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
						<button type="submit" class="btn btn-warning w-100 mb-2">Sign in</button>
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

