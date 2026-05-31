<?php
require_once __DIR__ . '/../../includes/auth.php';
require_auth();
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action', '');
    if ($action === 'update_name') {
        $new_name = trim((string)post('name'));
        if ($new_name !== '') {
            try {
                $stmt = $db->prepare('UPDATE users SET name = ? WHERE id = ?');
                $stmt->execute([$new_name, (int)$_SESSION['user_id']]);
                $_SESSION['user_name'] = $new_name;
                $notice = 'Profile updated successfully';
            } catch (Throwable $e) {
                $notice = 'Unable to update profile';
            }
        } else {
            $notice = 'Name cannot be empty';
        }
    }
}

$user = [
    'username' => isset($_SESSION['username']) ? $_SESSION['username'] : '',
    'name' => isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '',
    'account_type' => current_account_type(),
];

// Try to enrich with linked patient info if available and user is Patient
$patient = null;
if ($user['account_type'] === 'Patient') {
    try {
        $col = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch();
        if ($col) {
            $stmt = $db->prepare('SELECT u.patient_id, p.first_name, p.last_name, p.gender, p.dob FROM users u LEFT JOIN patients p ON p.id = u.patient_id WHERE u.id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $patient = $stmt->fetch();
        }
    } catch (PDOException $e) { /* ignore */ }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<style>
    .profile-hero { background: linear-gradient(135deg, #0ea5e9, #6366f1); color: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 8px 24px rgba(99,102,241,.2); }
    .avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,.2); display: flex; align-items: center; justify-content: center; font-size: 2rem; }
    .profile-card { border: none; border-radius: 16px; box-shadow: 0 6px 16px rgba(0,0,0,.06); }
    .profile-actions .btn { border-radius: 10px; }
</style>
<div class="container py-4">
    <div class="profile-hero d-flex align-items-center justify-content-between mb-4">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div>
                <div class="h4 mb-1"><?php echo sanitize($user['name']); ?></div>
                <div class="small">@<?php echo sanitize($user['username']); ?> • <?php echo sanitize($user['account_type']); ?></div>
            </div>
        </div>
        <div class="profile-actions">
            <a href="/hms/public/account/password.php" class="btn btn-light"><i class="bi bi-key"></i> Change Password</a>
        </div>
    </div>
    <?php if ($notice) { ?><div class="alert alert-info"><?php echo sanitize($notice); ?></div><?php } ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card profile-card">
                <div class="card-header bg-light">
                    <strong>Profile Information</strong>
                </div>
                <div class="card-body">
                    <div class="mb-3"><span class="text-muted">Full Name</span><div class="fw-semibold"><?php echo sanitize($user['name']); ?></div></div>
                    <div class="mb-3"><span class="text-muted">Username</span><div class="fw-semibold"><?php echo sanitize($user['username']); ?></div></div>
                    <div class="mb-2"><span class="text-muted">Role</span><div class="fw-semibold"><?php echo sanitize($user['account_type']); ?></div></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card profile-card">
                <div class="card-header bg-light">
                    <strong>Edit Display Name</strong>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="update_name">
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" value="<?php echo sanitize($user['name']); ?>" required>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                            <a class="btn btn-outline-secondary" href="/hms/public/account/profile.php"><i class="bi bi-x-circle"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php if ($patient) { ?>
        <div class="col-12">
            <div class="card profile-card">
                <div class="card-header bg-light">
                    <strong>Patient Details</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3"><span class="text-muted">Patient Name</span><div class="fw-semibold"><?php echo sanitize(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></div></div>
                        <div class="col-md-3"><span class="text-muted">Gender</span><div class="fw-semibold"><?php echo sanitize($patient['gender'] ?? ''); ?></div></div>
                        <div class="col-md-3"><span class="text-muted">Date of Birth</span><div class="fw-semibold"><?php echo sanitize($patient['dob'] ?? ''); ?></div></div>
                    </div>
                </div>
            </div>
        </div>
        <?php } elseif ($user['account_type'] === 'Patient') { ?>
        <div class="col-12">
            <div class="alert alert-info">Your account is not linked to a patient record yet. Please contact reception to link your profile.</div>
        </div>
        <?php } ?>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
