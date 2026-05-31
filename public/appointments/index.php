<?php
require_once __DIR__ . '/../../includes/auth.php';
// Staff-only list. Exclude Patient role to protect privacy.
require_any_account_type(['Admin','Doctor','Nurse','Pharmacist','Laboratorist','Accountant','Receptionist']);
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

// Check if patient_location column exists
$column_exists = false;
try {
	$stmt = $db->query("SHOW COLUMNS FROM appointments LIKE 'patient_location'");
	$column_exists = $stmt->fetch() !== false;
} catch (PDOException $e) {
	$column_exists = false;
}

$is_doctor = current_account_type() === 'Doctor';
$doctor_id = null;
if ($is_doctor) { $doctor_id = resolve_doctor_id($db); }

$where = '';
$params = [];
if ($is_doctor && $doctor_id) { $where = 'WHERE a.doctor_id = ?'; $params[] = $doctor_id; }

$sql = "SELECT a.*, CONCAT(p.first_name,' ',p.last_name) AS patient_name,
        CONCAT(d.first_name,' ',d.last_name) AS doctor_name
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN doctors d ON d.id = a.doctor_id
        " . ($where ? $where : '') . "
        ORDER BY a.appointment_date DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/nav.php';
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Appointments</h1>
        <?php if (in_array(current_account_type(), ['Admin','Receptionist'])) { ?>
            <a class="btn btn-primary" href="/hms/public/appointments/create.php">Add Appointment</a>
        <?php } ?>
    </div>
	<div class="table-responsive">
		<table class="table table-striped align-middle">
			<thead>
				<tr>
					<th>Date & Time</th>
					<th>Patient</th>
					<th>Doctor</th>
					<th>Status</th>
					<th>Location</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($appointments as $a) { ?>
					<tr>
						<td><?php echo sanitize($a['appointment_date']); ?></td>
						<td><?php echo sanitize($a['patient_name']); ?></td>
						<td><?php echo sanitize($a['doctor_name']); ?></td>
						<td>
							<?php
								$badge = 'secondary';
								if ($a['status'] === 'Scheduled') { $badge = 'primary'; }
								elseif ($a['status'] === 'Completed') { $badge = 'success'; }
								elseif ($a['status'] === 'Cancelled') { $badge = 'secondary'; }
								elseif ($a['status'] === 'Pending') { $badge = 'warning'; }
							?>
							<span class="badge bg-<?php echo $badge; ?>"><?php echo sanitize($a['status']); ?></span>
						</td>
						<td>
							<?php if (isset($a['patient_location'])) { ?>
								<span class="badge bg-<?php echo $a['patient_location'] === 'With Doctor' ? 'success' : 'warning'; ?>">
									<?php echo sanitize($a['patient_location']); ?>
								</span>
							<?php } else { ?>
								<span class="badge bg-secondary">N/A</span>
							<?php } ?>
						</td>
                        <td class="table-actions">
                            <?php if (in_array(current_account_type(), ['Admin','Receptionist'])) { ?>
                                <a class="btn btn-sm btn-outline-primary" href="/hms/public/appointments/edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
                            <?php } ?>
                            <?php if (current_account_type() === 'Admin') { ?>
                                <?php if ($a['status'] === 'Pending') { ?>
                                    <a class="btn btn-sm btn-success" href="/hms/public/appointments/approve.php?id=<?php echo (int)$a['id']; ?>">Approve</a>
                                <?php } ?>
                                <a class="btn btn-sm btn-outline-danger" href="/hms/public/appointments/delete.php?id=<?php echo (int)$a['id']; ?>" onclick="return confirm('Delete this appointment?');">Delete</a>
                            <?php } ?>
                            <?php if (current_account_type() === 'Doctor') { ?>
                                <a class="btn btn-sm btn-success" href="/hms/public/appointments/evaluate.php?id=<?php echo (int)$a['id']; ?>">Evaluate</a>
                                <a class="btn btn-sm btn-outline-primary" href="/hms/public/appointments/edit.php?id=<?php echo (int)$a['id']; ?>">Update Status</a>
                            <?php } ?>
                        </td>
					</tr>
				<?php } ?>
				<?php if (!$appointments) { ?>
					<tr><td colspan="6" class="text-center text-muted">No appointments found</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

