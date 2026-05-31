<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../../config.php';
$db = get_db_connection();

try { $db->query('SELECT 1 FROM pharm_categories LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS pharm_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        description TEXT NULL,
        vendor_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(vendor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$vendors = [];
try { $vendors = $db->query('SELECT id, name FROM pharm_vendors ORDER BY name')->fetchAll(); } catch (Throwable $e) { $vendors = []; }

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=(string)post('name');
    $vendor_id=(int)post('vendor_id');
    $description=(string)post('description');
    if(trim($name)===''){ $errors[]='Category name is required'; }
    if(!$errors){
        $stmt=$db->prepare('INSERT INTO pharm_categories(name,description,vendor_id) VALUES (?,?,?)');
        $stmt->execute([$name,$description,$vendor_id ?: null]);
        redirect('/hms/public/pharmacy/categories/index.php');
    }
}

include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Add Pharm Category</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/pharmacy/categories/index.php"><i class="bi bi-list"></i> Categories</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label form-required">Pharmaceutical Category Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Pharmaceutical Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select vendor</option>
                            <?php foreach($vendors as $v){ ?><option value="<?php echo (int)$v['id']; ?>"><?php echo sanitize($v['name']); ?></option><?php } ?>
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Pharmaceutical Category Description</label><textarea name="description" rows="4" class="form-control"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Add Category</button><a class="btn btn-secondary" href="/hms/public/pharmacy/categories/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>