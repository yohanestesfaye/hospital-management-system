<?php
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Patient');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

$notifications_ready = true;
try { $db->query('SELECT 1 FROM notifications LIMIT 1'); } catch (PDOException $e) { $notifications_ready = false; }

$user_id = (int)$_SESSION['user_id'];
$notifs = [];
if ($notifications_ready) {
    $stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user_id]);
    $notifs = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">My Notifications</h1>
    </div>
    <?php if (!$notifications_ready) { ?>
        <div class="alert alert-info">Notifications module not initialized. Staff can run <a href="/hms/public/setup/migrate_add_notifications.php" class="alert-link">this migration</a>.</div>
    <?php } else { ?>
        <div class="list-group">
            <?php foreach ($notifs as $n) { ?>
                <div class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?php echo sanitize($n['title']); ?></div>
                        <div class="text-muted small"><?php echo sanitize($n['created_at']); ?></div>
                        <div><?php echo nl2br(sanitize($n['message'])); ?></div>
                    </div>
                    <?php if ((int)$n['is_read'] === 0) { ?><span class="badge bg-primary rounded-pill">new</span><?php } ?>
                </div>
            <?php } ?>
            <?php if (!$notifs) { ?>
                <div class="list-group-item text-muted">No notifications</div>
            <?php } ?>
        </div>
    <?php } ?>
    
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>