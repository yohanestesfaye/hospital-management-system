<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Ensure table exists
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_name VARCHAR(100) NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id INT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id), INDEX(action), INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

// Filters
$search = get('search', '');
$action_filter = get('action', '');
$date_filter = get('date_filter', 'today');

$where = [];
$params = [];

if ($search) {
    $where[] = "(user_name LIKE ? OR details LIKE ? OR action LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($action_filter) {
    $where[] = "action = ?";
    $params[] = $action_filter;
}

if ($date_filter === 'today') {
    $where[] = "DATE(created_at) = CURDATE()";
} elseif ($date_filter === 'week') {
    $where[] = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($date_filter === 'month') {
    $where[] = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$limit = (int)get('limit', 50);
if (!in_array($limit, [25, 50, 100, 200], true)) {
    $limit = 50;
}
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $limit;

// Count total
$count_sql = "SELECT COUNT(*) AS c FROM audit_logs $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total = (int)$stmt->fetch()['c'];

// Get logs
$logs = [];
$sql = "SELECT * FROM audit_logs $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$logs = $stmt->fetchAll();

$total_pages = max(1, (int)ceil($total / $limit));

// Get unique actions for filter
$actions = [];
try {
    $stmt = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

include __DIR__ . '/../../includes/accountant-header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h4 mb-0"><i class="bi bi-file-earmark-text"></i> Audit Logs</h2>
    </div>

    <!-- Filters -->
    <div class="card card-modern mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="User, Action, Details..." value="<?php echo sanitize($search); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act) { ?>
                            <option value="<?php echo sanitize($act); ?>" <?php echo $action_filter === $act ? 'selected' : ''; ?>>
                                <?php echo sanitize($act); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date Range</label>
                    <select name="date_filter" class="form-select">
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="/hms/public/audit/logs.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="card card-modern">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Audit Logs (<?php echo $total; ?> total)</h5>
            <select name="limit" class="form-select form-select-sm" style="width: auto;" onchange="window.location.href = updateURLParam('limit', this.value)">
                <?php foreach ([25, 50, 100, 200] as $n) { ?>
                    <option value="<?php echo $n; ?>" <?php echo $limit === $n ? 'selected' : ''; ?>>Show <?php echo $n; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-modern">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)) { ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No audit logs found</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><strong><?php echo sanitize($log['user_name']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo sanitize($log['action']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['entity_type'] && $log['entity_id']) { ?>
                                            <code><?php echo sanitize($log['entity_type']); ?> #<?php echo (int)$log['entity_id']; ?></code>
                                        <?php } else { ?>
                                            <span class="text-muted">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <small><?php echo sanitize($log['details'] ?? '-'); ?></small>
                                    </td>
                                    <td>
                                        <code class="small"><?php echo sanitize($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) { ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination mb-0 justify-content-center">
                            <?php for ($p = 1; $p <= $total_pages; $p++) { ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>">
                                        <?php echo $p; ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </nav>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
function updateURLParam(param, value) {
    const url = new URL(window.location);
    url.searchParams.set(param, value);
    return url.toString();
}
</script>

<?php include __DIR__ . '/../../includes/accountant-footer.php'; ?>

