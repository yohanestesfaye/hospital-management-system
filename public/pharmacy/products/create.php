<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_account_type('Admin');
require_once __DIR__ . '/../../../config.php';
$db = get_db_connection();

try { $db->query('SELECT 1 FROM pharmaceuticals LIMIT 1'); } catch (PDOException $e) {
    $db->exec("CREATE TABLE IF NOT EXISTS pharmaceuticals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        sku VARCHAR(100) NULL,
        category_id INT NULL,
        vendor_id INT NULL,
        unit_price DECIMAL(10,2) DEFAULT 0,
        stock_qty INT DEFAULT 0,
        expiry_date DATE NULL,
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { if (!$db->query("SHOW COLUMNS FROM pharmaceuticals LIKE 'expiry_date'" )->fetch()) { $db->exec("ALTER TABLE pharmaceuticals ADD COLUMN expiry_date DATE NULL"); } } catch (Throwable $e) {}

$categories = [];$vendors=[];
try { $categories = $db->query('SELECT id, name FROM pharm_categories ORDER BY name')->fetchAll(); } catch (Throwable $e) {}
try { $vendors = $db->query('SELECT id, name FROM pharm_vendors ORDER BY name')->fetchAll(); } catch (Throwable $e) {}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=(string)post('name');
    $sku=(string)post('sku');
    $category_id=(int)post('category_id');
    $vendor_id=(int)post('vendor_id');
    $unit_price=(float)post('unit_price');
    $stock_qty=(int)post('stock_qty');
    $expiry_date=(string)post('expiry_date');
    $description=(string)post('description');
    if(trim($name)===''){ $errors[]='Name is required'; }
    if(!$errors){
        $stmt=$db->prepare('INSERT INTO pharmaceuticals(name,sku,category_id,vendor_id,unit_price,stock_qty,expiry_date,description) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$name,$sku,$category_id ?: null,$vendor_id ?: null,$unit_price,$stock_qty,$expiry_date ?: null,$description]);
        redirect('/hms/public/pharmacy/products/index.php');
    }
}

include __DIR__ . '/../../../includes/header.php';
include __DIR__ . '/../../../includes/nav.php';
?>
<div class="container py-4">
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex align-items-center"><h1 class="h6 mb-0">Add Pharmaceutical</h1><a class="btn btn-sm btn-outline-secondary ms-auto" href="/hms/public/pharmacy/products/index.php"><i class="bi bi-list"></i> Items</a></div>
        <div class="card-body">
            <?php if($errors){ ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e){ ?><li><?php echo sanitize($e); ?></li><?php } ?></ul></div><?php } ?>
            <form method="post" autocomplete="off">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label form-required">Name</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Category</label>
                        <select name="category_id" class="form-select"><option value="">Select category</option><?php foreach($categories as $c){ ?><option value="<?php echo (int)$c['id']; ?>"><?php echo sanitize($c['name']); ?></option><?php } ?></select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select"><option value="">Select vendor</option><?php foreach($vendors as $v){ ?><option value="<?php echo (int)$v['id']; ?>"><?php echo sanitize($v['name']); ?></option><?php } ?></select>
                    </div>
                    <div class="col-md-4"><label class="form-label">Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Unit Price</label><input type="number" step="0.01" min="0" name="unit_price" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Stock Qty</label><input type="number" step="1" min="0" name="stock_qty" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="4" class="form-control"></textarea></div>
                    <div class="col-12"><button class="btn btn-primary">Save</button><a class="btn btn-secondary" href="/hms/public/pharmacy/products/index.php">Cancel</a></div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
