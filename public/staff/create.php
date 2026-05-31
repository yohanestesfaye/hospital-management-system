<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$allowed_roles = ['Nurse','Receptionist','Pharmacist','Laboratorist'];

try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'license_number'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN license_number VARCHAR(100) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'certification'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN certification VARCHAR(150) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'shift'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN shift VARCHAR(50) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'desk_number'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN desk_number VARCHAR(50) NULL"); } } catch (Throwable $e) {}
try { if (!$db->query("SHOW COLUMNS FROM users LIKE 'specialty_area'" )->fetch()) { $db->exec("ALTER TABLE users ADD COLUMN specialty_area VARCHAR(150) NULL"); } } catch (Throwable $e) {}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)post('username'));
    $name = trim((string)post('name'));
    $password = (string)post('password');
    $account_type = (string)post('account_type');
    $license_number = trim((string)post('license_number')) ?: null;
    $certification = trim((string)post('certification')) ?: null;
    $shift = trim((string)post('shift')) ?: null;
    $desk_number = trim((string)post('desk_number')) ?: null;
    $specialty_area = trim((string)post('specialty_area')) ?: null;

    if ($username === '') { $errors[] = 'Username is required'; }
    if ($name === '') { $errors[] = 'Name is required'; }
    if ($password === '') { $errors[] = 'Password is required'; }
    if (!in_array($account_type, $allowed_roles, true)) { $errors[] = 'Invalid role selected'; }

    // Unique username check
    if (!$errors) {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ((int)$stmt->fetch()['c'] > 0) {
            $errors[] = 'Username already exists';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users(username, name, password_hash, account_type, license_number, certification, shift, desk_number, specialty_area) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$username, $name, $hash, $account_type, $license_number, $certification, $shift, $desk_number, $specialty_area]);
        redirect('/hms/public/staff/index.php');
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center">
            <h1 class="h6 mb-0">Add Staff</h1>
            <a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/staff/index.php"><i class="bi bi-list"></i> Staff List</a>
        </div>
        <div class="card-body">
    <?php if ($errors) { ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e) { ?><li><?php echo sanitize($e); ?></li><?php } ?>
            </ul>
        </div>
    <?php } ?>
    <form method="post" autocomplete="off">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label form-required">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col-md-5">
                <label class="form-label form-required">Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-3">
                <label class="form-label form-required">Role</label>
                <select name="account_type" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($allowed_roles as $role) { ?>
                        <option value="<?php echo sanitize($role); ?>"><?php echo sanitize($role); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label form-required">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6 role-field d-none" data-role="Pharmacist">
                <label class="form-label">License Number</label>
                <input type="text" name="license_number" class="form-control">
            </div>
            <div class="col-md-6 role-field d-none" data-role="Nurse">
                <label class="form-label">Certification</label>
                <input type="text" name="certification" class="form-control">
            </div>
            <div class="col-md-6 role-field d-none" data-role="Nurse">
                <label class="form-label">Shift</label>
                <input type="text" name="shift" class="form-control" placeholder="Day/Night">
            </div>
            <div class="col-md-6 role-field d-none" data-role="Receptionist">
                <label class="form-label">Desk Number</label>
                <input type="text" name="desk_number" class="form-control">
            </div>
            <div class="col-md-6 role-field d-none" data-role="Laboratorist">
                <label class="form-label">Specialty Area</label>
                <input type="text" name="specialty_area" class="form-control" placeholder="Hematology, Biochemistry, etc.">
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Save</button>
                <a class="btn btn-secondary" href="/hms/public/staff/index.php">Cancel</a>
            </div>
        </div>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        var select = document.querySelector('select[name=account_type]');
        var fields = document.querySelectorAll('.role-field');
        function update(){
            var role = select.value;
            fields.forEach(function(el){ el.classList.toggle('d-none', el.getAttribute('data-role')!==role); });
        }
        select.addEventListener('change', update); update();
    });
    </script>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>