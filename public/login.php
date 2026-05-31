<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username');
    $password = post('password');

    if ($username && $password) {
        $db = get_db_connection();
        $stmt = $db->prepare('SELECT id, name, password_hash, account_type FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['account_type'] = $user['account_type'];

            // Redirect to appropriate dashboard
            $dashboard_map = [
                'Admin' => 'dashboard-admin.php',
                'Doctor' => 'dashboard-doctor.php',
                'Patient' => 'dashboard-patient.php',
                'Nurse' => 'dashboard-nurse.php',
                'Pharmacist' => 'dashboard-pharmacist.php',
                'Laboratorist' => 'dashboard-laboratorist.php',
                'Accountant' => 'dashboard-accountant.php',
                'Receptionist' => 'dashboard-receptionist.php',
                'LabManager' => 'dashboard-laboratorist.php',
            ];

            $acct = $user['account_type'];
            $dashboard = isset($dashboard_map[$acct]) ? $dashboard_map[$acct] : 'dashboard.php';
            redirect('/hms/public/' . $dashboard);
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login - Hospital Management System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
	<link href="/hms/public/assets/css/styles.css" rel="stylesheet">
	<style>
	body {
		background: #f5f5f5;
		min-height: 100vh;
		display: flex;
		flex-direction: column;
	}
	.top-navbar {
		background-color: #2c3e50;
		color: white;
		padding: 12px 20px;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.top-navbar h5 {
		margin: 0;
		font-size: 1.1rem;
		font-weight: 500;
	}
	.top-navbar .dropdown-toggle {
		background: transparent;
		border: 1px solid rgba(255,255,255,0.3);
		color: white;
		padding: 6px 12px;
		font-size: 0.9rem;
	}
	.top-navbar .dropdown-toggle:hover {
		background: rgba(255,255,255,0.1);
		border-color: rgba(255,255,255,0.5);
	}
    .login-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        background: url('/hms/public/assets/img/bgg.jpg') center/cover no-repeat fixed;
        padding: 16px 12px;
        position: relative;
        overflow: hidden;
    }
    .login-wrapper::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(99,102,241,0.35), rgba(139,92,246,0.35));
    }
    .login-container {
        width: 100%;
        max-width: 420px;
        padding: 12px;
    }
    .login-panel {
        background: rgba(255,255,255,0.18);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(17,24,39,0.25);
        padding: 28px;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.30);
    }
	.login-panel::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 5px;
		background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
	}
    .login-header {
        text-align: center;
        margin-bottom: 18px;
    }
    .login-header h2 {
        color: #1f2937;
        font-weight: 600;
        margin-bottom: 6px;
        letter-spacing: 0.2px;
    }
    .login-header p {
        color: #666;
        font-size: 0.85rem;
    }
    .form-group {
        margin-bottom: 12px;
    }
    .form-group label {
        display: block;
        margin-bottom: 6px;
        color: #333;
        font-weight: 500;
        font-size: 0.9rem;
    }
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid rgba(255,255,255,0.35);
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s;
        background: rgba(255,255,255,0.6);
        color: #1f2937;
    }
	.form-control:focus {
		outline: none;
		border-color: #667eea;
		background: white;
		box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
	}
	.form-control select {
		cursor: pointer;
	}
    .btn-login {
        width: 100%;
        padding: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 8px;
    }
	.btn-login:hover {
		transform: translateY(-2px);
		box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
	}
	.btn-login:active {
		transform: translateY(0);
	}
    .alert-danger {
        background: rgba(255,0,0,0.06);
        border: 1px solid rgba(255,0,0,0.18);
        color: #9b1c1c;
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.9rem;
    }
    .forgot-password {
        text-align: center;
        margin-top: 12px;
    }
	.forgot-password a {
		color: #667eea;
		text-decoration: none;
		font-size: 0.9rem;
		transition: color 0.3s;
	}
	.forgot-password a:hover {
		color: #764ba2;
		text-decoration: underline;
	}
	.account-type-icon {
		display: inline-block;
		width: 20px;
		text-align: center;
		margin-right: 8px;
	}
    .footer-copyright {
        text-align: center;
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid #e0e0e0;
        color: #666;
        font-size: 0.85rem;
    }
	.footer-copyright .copyright-line {
		display: block;
		width: 100px;
		height: 2px;
		background: #28a745;
		margin: 10px auto;
	}
</style>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
	<div>
		<h5>Hospital Management System</h5>
	</div>

</nav>

<div class="login-wrapper">
<div class="login-container">
	<div class="login-panel">
		<div class="login-header">
			<h2>Login Panel</h2>
			<p>Hospital Management System</p>
		</div>
		
		<?php if ($error) { ?>
			<div class="alert-danger">
				<i class="bi bi-exclamation-triangle-fill"></i> <?php echo sanitize($error); ?>
			</div>
		<?php } ?>
		
		<form method="post" autocomplete="off" id="loginForm">
            
			
			<div class="form-group">
				<label for="username">
					<i class="bi bi-person account-type-icon"></i> Username
				</label>
				<input type="text" class="form-control" name="username" id="username" required autofocus placeholder="Enter your username">
			</div>
			
			<div class="form-group">
				<label for="password">
					<i class="bi bi-lock account-type-icon"></i> Password
				</label>
				<input type="password" class="form-control" name="password" id="password" required placeholder="Enter your password">
			</div>
			
			<button type="submit" class="btn-login">
				<i class="bi bi-box-arrow-in-right"></i> Login
			</button>
		</form>
		
		<div class="forgot-password">
			<a href="#">Forgot Password?</a>
		</div>
		
		<div class="footer-copyright">
			<div class="copyright-line"></div>
            © <?php echo date('Y'); ?> Hospital Management System - Developed by WENBY
		</div>
	</div>
</div>
</div>

<script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        if (!username || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
            return false;
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
