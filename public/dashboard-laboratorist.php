<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_any_account_type(['Laboratorist','LabManager']);
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

include __DIR__ . '/../includes/laboratorist-header.php';

// Stats
try { $patients_total = (int)$db->query('SELECT COUNT(*) AS c FROM patients')->fetch()['c']; } catch (Throwable $e) { $patients_total = 0; }
try { $appointments_today = (int)$db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE(appointment_date) = CURDATE()" )->fetch()['c']; } catch (Throwable $e) { $appointments_today = 0; }
try { $pending_count = (int)$db->query("SELECT COUNT(*) AS c FROM lab_results WHERE COALESCE(status,'Pending')='Pending'")->fetch()['c']; } catch (Throwable $e) { $pending_count = 0; }
try { $completed_today = (int)$db->query("SELECT COUNT(*) AS c FROM lab_results WHERE COALESCE(status,'Pending')='Completed' AND DATE(COALESCE(result_date, created_at))=CURDATE()")->fetch()['c']; } catch (Throwable $e) { $completed_today = 0; }
try { $urgent_count = (int)$db->query("SELECT COUNT(*) AS c FROM lab_results WHERE COALESCE(priority,'Routine')='Urgent' AND COALESCE(status,'Pending')='Pending'" )->fetch()['c']; } catch (Throwable $e) { $urgent_count = 0; }

// Pending items
$pending_items = [];
try {
    $pending_items = $db->query("SELECT lr.id, lr.patient_id, lr.test_name, COALESCE(lr.priority,'Routine') AS priority, COALESCE(lr.status,'Pending') AS status, CONCAT(p.first_name,' ',p.last_name) AS patient_name, u.name AS doctor_name, lr.created_at FROM lab_results lr JOIN patients p ON p.id = lr.patient_id LEFT JOIN users u ON u.id = lr.requested_by WHERE COALESCE(lr.status,'Pending') = 'Pending' ORDER BY (COALESCE(lr.priority,'Routine') = 'Urgent') DESC, lr.created_at ASC LIMIT 12")->fetchAll();
} catch (Throwable $e) { $pending_items = []; }
?>
<div class="container-fluid">
    <?php $role_label = current_account_type()==='LabManager' ? 'Lab Manager' : 'Laboratorist'; ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Laboratory Dashboard</h1>
        <span class="badge bg-secondary"><?php echo $role_label; ?></span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-secondary"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Total Patients</div>
                        <div class="h3 mb-0"><?php echo $patients_total; ?></div>
                    </div>
                    <div class="display-6 text-secondary">🔬</div>
                </div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-info"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Today's Appointments</div>
                        <div class="h3 mb-0"><?php echo $appointments_today; ?></div>
                    </div>
                    <div class="display-6 text-info">📅</div>
                </div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-warning"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Pending Lab Tests</div>
                        <div class="h3 mb-0"><?php echo $pending_count; ?></div>
                        <div class="small text-muted mt-1">Urgent: <?php echo $urgent_count; ?></div>
                    </div>
                    <div class="display-6 text-warning">🧪</div>
                </div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-success"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small">Completed Tests Today</div>
                        <div class="h3 mb-0"><?php echo $completed_today; ?></div>
                    </div>
                    <div class="display-6 text-success">✅</div>
                </div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Pending Lab Orders</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr>
                                <th style="width:60px">#</th>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Priority</th>
                                <th>Ordering Doctor</th>
                                <th>Status</th>
                                <th style="width:220px">Options</th>
                            </tr></thead>
                            <tbody>
                                <?php $i=1; foreach($pending_items as $row){ ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><a href="/hms/public/patients/show.php?id=<?php echo (int)$row['patient_id']; ?>"><?php echo sanitize($row['patient_name']); ?></a></td>
                                    <td><?php echo sanitize($row['test_name']); ?></td>
                                    <td><span class="badge bg-<?php echo ($row['priority']==='Urgent')?'danger':'secondary'; ?>"><?php echo sanitize($row['priority']); ?></span></td>
                                    <td><?php echo sanitize($row['doctor_name'] ?? '—'); ?></td>
                                    <td><span class="badge bg-warning">Pending</span></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="/hms/public/lab_results/create.php?patient_id=<?php echo (int)$row['patient_id']; ?>&test_name=<?php echo urlencode($row['test_name']); ?>">Add Result</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="/hms/public/lab_results/index.php">View Orders</a>
                                    </td>
                                </tr>
                                <?php } ?>
                                <?php if(!$pending_items){ ?><tr><td colspan="7" class="text-center text-muted">No pending orders</td></tr><?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="/hms/public/lab_results/index.php" class="list-group-item list-group-item-action">🧪 Lab Orders & Results</a>
                        <a href="/hms/public/lab_results/create.php" class="list-group-item list-group-item-action">➕ Add Result</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/admin-footer.php'; ?>