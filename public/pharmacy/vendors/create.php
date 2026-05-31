<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../../config.php';
$db = get_db_connection();

try { $db->query('SELECT 1 FROM pharm_vendors LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS pharm_vendors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        phone VARCHAR(50) NULL,
        email VARCHAR(150) NULL,
        address VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=(string)post('name');
    $phone=(string)post('phone');
    $email=(string)post('email');
    $address=(string)post('address');
    if(trim($name)===''){ $errors[]='Vendor name is required'; }
    if(!$errors){
        $stmt=$db->prepare('INSERT INTO pharm_vendors(name,phone,email,address) VALUES (?,?,?,?)');
        $stmt->execute([$name,$phone,$email,$address]);
        redirect('/hms/public/pharmacy/vendors/index.php');
    }
}

include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Add Vendor</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/pharmacy/vendors/index.php"><i class="bi bi-list"></i> Vendors</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label form-required">Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-primary">Save</button><a class="btn btn-secondary" href="/hms/public/pharmacy/vendors/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>