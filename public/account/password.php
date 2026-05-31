<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

require_auth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$current = post('current_password');
	$new = post('new_password');
	$confirm = post('confirm_password');

	if (!$current || !$new || !$confirm) {
		$error = 'All fields are required';
	} elseif ($new !== $confirm) {
		$error = 'New password and confirm password do not match';
	} elseif (strlen($new) < 8) {
		$error = 'New password must be at least 8 characters';
	} else {
		$db = get_db_connection();
		$stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
		$stmt->execute([$_SESSION['user_id']]);
		$user = $stmt->fetch();
		if (!$user || !password_verify($current, $user['password_hash'])) {
			$error = 'Current password is incorrect';
		} else {
			$newHash = password_hash($new, PASSWORD_BCRYPT);
			$upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
			$upd->execute([$newHash, $_SESSION['user_id']]);
			$success = 'Password updated successfully';
		}
	}
}

include __DIR__ . '/../../includes/header.php';

?>
<?php $role = current_account_type(); $accent = '#0b7285'; $accentDark = '#075465'; if($role==='Admin'){ $accent='#28a745'; $accentDark='#1f7a34'; } elseif($role==='Nurse'){ $accent='#ffc107'; $accentDark='#e0a800'; } elseif($role==='Pharmacist'){ $accent='#0d6efd'; $accentDark='#0b5ed7'; } elseif($role==='Laboratorist'){ $accent='#6610f2'; $accentDark='#520dc2'; } elseif($role==='Accountant'){ $accent='#198754'; $accentDark='#157347'; } elseif($role==='Receptionist'){ $accent='#fd7e14'; $accentDark='#c75c0a'; } elseif($role==='Patient'){ $accent='#6f42c1'; $accentDark='#59329a'; } ?>
<style>
.cp-hero{background:linear-gradient(90deg, var(--accent), var(--accent-dark)); color:#fff; border-radius:16px; padding:24px; box-shadow:0 10px 20px rgba(0,0,0,.08)}
.cp-card{border:0; border-radius:16px; box-shadow:0 12px 24px rgba(0,0,0,.08)}
.cp-card-header{background:var(--accent); color:#fff; border-top-left-radius:16px; border-top-right-radius:16px; padding:14px 20px}
.btn-accent{background-color:var(--accent); border-color:var(--accent); color:#fff}
.btn-accent:hover{background-color:var(--accent-dark); border-color:var(--accent-dark); color:#fff}
.form-label{font-weight:600}
.badge-role{background:rgba(255,255,255,.2); color:#fff}
</style>
<div class="container py-5" style="--accent: <?php echo $accent; ?>; --accent-dark: <?php echo $accentDark; ?>;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="cp-hero mb-4 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="display-6">🔐</div>
                    <div>
                        <div class="h3 mb-1">Change Password</div>
                        <div class="small" style="opacity:.9">Keep your account secure</div>
                    </div>
                </div>
                <span class="badge badge-role"><?php echo sanitize($role ?: 'User'); ?></span>
            </div>
            <div class="card cp-card">
                <div class="cp-card-header">Security Settings</div>
                <div class="card-body p-4">
                    <?php if ($error) { ?>
                        <div class="alert alert-danger" role="alert"><?php echo sanitize($error); ?></div>
                    <?php } ?>
                    <?php if ($success) { ?>
                        <div class="alert alert-success" role="alert"><?php echo sanitize($success); ?></div>
                    <?php } ?>
                    <form method="post" autocomplete="off" novalidate>
                        <div class="mb-3">
                            <label class="form-label form-required">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="current_password" required id="current_password">
                                <button class="btn btn-outline-secondary" type="button" data-toggle="pw" data-target="#current_password"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label form-required">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                <input type="password" class="form-control" name="new_password" minlength="8" required id="new_password">
                                <button class="btn btn-outline-secondary" type="button" data-toggle="pw" data-target="#new_password"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text">Use at least 8 characters with uppercase, lowercase, number, and symbol.</div>
                            <div class="progress mt-2" style="height:6px">
                                <div class="progress-bar" id="pw_strength" role="progressbar" style="width:0%"></div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label form-required">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" name="confirm_password" minlength="8" required id="confirm_password">
                                <button class="btn btn-outline-secondary" type="button" data-toggle="pw" data-target="#confirm_password"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-accent">Update Password</button>
                            <a href="/hms/public/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-toggle="pw"]').forEach(function(btn){
  btn.addEventListener('click',function(){
    var target = document.querySelector(btn.getAttribute('data-target'));
    if(!target) return;
    target.type = target.type === 'password' ? 'text' : 'password';
    var icon = btn.querySelector('i');
    if(icon){ icon.className = target.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash'; }
  });
});
var np = document.getElementById('new_password');
var bar = document.getElementById('pw_strength');
function score(v){
  var s = 0;
  if(v.length>=8) s+=25;
  if(/[A-Z]/.test(v)) s+=20;
  if(/[a-z]/.test(v)) s+=20;
  if(/[0-9]/.test(v)) s+=20;
  if(/[^A-Za-z0-9]/.test(v)) s+=15;
  return Math.min(100,s);
}
if(np && bar){
  np.addEventListener('input',function(){
    var val = np.value || '';
    var p = score(val);
    bar.style.width = p+'%';
    bar.className = 'progress-bar '+(p<40?'bg-danger':(p<70?'bg-warning':'bg-success'));
  });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>



