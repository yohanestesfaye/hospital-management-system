<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_any_account_type(['Admin','Pharmacist']);
require_once __DIR__ . '/../../../config.php';
$db = get_db_connection();
$is_admin = current_account_type() === 'Admin';

try {
    $db->exec("CREATE TABLE IF NOT EXISTS pharm_vendors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        phone VARCHAR(50) NULL,
        email VARCHAR(150) NULL,
        address VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { }

$q = get('q','');
$limit = (int)get('limit',10); if(!in_array($limit,[10,25,50,100],true)){ $limit=10; }
$page = max(1,(int)get('page',1)); $offset = ($page-1)*$limit;

$where = '';$params=[];
if ($q !== '') { $like = '%' . $q . '%'; $where = 'WHERE name LIKE ?'; $params = [$like]; }

$count_sql = 'SELECT COUNT(*) AS c FROM pharm_vendors ' . $where;
$stmt = $db->prepare($count_sql); $stmt->execute($params); $total = (int)$stmt->fetch()['c'];

$list_sql = 'SELECT * FROM pharm_vendors ' . ($where ? $where : '') . ' ORDER BY created_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($list_sql); $stmt->execute($params); $items = $stmt->fetchAll();

if (current_account_type()==='Pharmacist') {
    include __DIR__ . '/../../../includes/pharmacist-header.php';
} else {
    include __DIR__ . '/../../../includes/header.php';
}
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Pharmacy Vendors</h1>
        <div class="d-flex gap-2">
            <?php if ($is_admin) { ?>
                <a class="btn btn-primary" href="/hms/public/pharmacy/vendors/create.php">Add Vendor</a>
                <a class="btn btn-outline-secondary" href="/hms/public/dashboard-admin.php">Cancel</a>
            <?php } ?>
        </div>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light"><div class="d-flex align-items-center"><div class="me-auto fw-semibold">Vendors</div><?php if ($is_admin) { ?><a class="btn btn-sm btn-primary" href="/hms/public/pharmacy/vendors/create.php"><i class="bi bi-plus-lg"></i> Add</a><?php } ?></div></div>
        <div class="card-body">
            <form class="row g-2 mb-3" method="get">
                <div class="col-sm-9 col-md-7"><input type="text" class="form-control" name="q" placeholder="Search vendors" value="<?php echo sanitize($q); ?>"></div>
                <div class="col-sm-3 col-md-3"><select name="limit" class="form-select" onchange="this.form.submit()"><?php foreach([10,25,50,100] as $n){ ?><option value="<?php echo $n; ?>" <?php echo $limit===$n?'selected':''; ?>>Show <?php echo $n; ?> entries</option><?php } ?></select></div>
                <div class="col-sm-12 col-md-2"><button class="btn btn-outline-secondary w-100">Search</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-hover align-middle"><thead class="table-light"><tr>
                    <th style="width:60px">#</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th>
                </tr></thead><tbody>
                    <?php $i=$offset+1; foreach($items as $row){ ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo sanitize($row['name']); ?></td>
                            <td><?php echo sanitize($row['phone']); ?></td>
                            <td><?php echo sanitize($row['email']); ?></td>
                            <td><?php echo sanitize($row['address']); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if(!$items){ ?><tr><td colspan="5" class="text-center text-muted">No vendors found</td></tr><?php } ?>
                </tbody></table>
            </div>
            <?php $total_pages=max(1,(int)ceil($total/$limit)); ?>
            <nav><ul class="pagination"><?php for($p=1;$p<=$total_pages;$p++){ $active=$p===$page?'active':''; ?><li class="page-item <?php echo $active; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&limit=<?php echo (int)$limit; ?>&page=<?php echo (int)$p; ?>"><?php echo (int)$p; ?></a></li><?php } ?></ul></nav>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../../../includes/footer.php'; ?>
