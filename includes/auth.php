<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function require_auth() {
	if (!isset($_SESSION['user_id'])) {
		header('Location: /hms/public/index.php');
		exit;
	}
}

function require_account_type($account_type) {
    require_auth();
    if (!isset($_SESSION['account_type']) || $_SESSION['account_type'] !== $account_type) {
        header('Location: /hms/public/dashboard.php');
        exit;
    }
}

// Allow access if the current user account type is in the allowed list
function require_any_account_type(array $account_types) {
    require_auth();
    $current = isset($_SESSION['account_type']) ? $_SESSION['account_type'] : null;
    if ($current === null || !in_array($current, $account_types, true)) {
        header('Location: /hms/public/dashboard.php');
        exit;
    }
}

function current_user_name() {
	return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
}

function current_account_type() {
    return isset($_SESSION['account_type']) ? $_SESSION['account_type'] : null;
}

function current_user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function resolve_patient_id($db) {
    $pid = null;
    try {
        $col = $db->query("SHOW COLUMNS FROM users LIKE 'patient_id'")->fetch();
        if ($col) {
            $st = $db->prepare('SELECT patient_id FROM users WHERE id = ?');
            $st->execute([$_SESSION['user_id']]);
            $row = $st->fetch();
            if ($row && $row['patient_id']) { $pid = (int)$row['patient_id']; }
        }
    } catch (PDOException $e) { }
    return $pid;
}

function resolve_doctor_id($db) {
    $did = null;
    $has_col = false;
    try { $has_col = $db->query("SHOW COLUMNS FROM users LIKE 'doctor_id'")->fetch() !== false; } catch (PDOException $e) { $has_col = false; }
    if ($has_col) {
        try {
            $st = $db->prepare('SELECT doctor_id FROM users WHERE id = ?');
            $st->execute([$_SESSION['user_id']]);
            $row = $st->fetch();
            if ($row && $row['doctor_id']) { $did = (int)$row['doctor_id']; }
        } catch (PDOException $e) { }
    }
    if (!$did) {
        $name = isset($_SESSION['user_name']) ? trim((string)$_SESSION['user_name']) : '';
        if ($name !== '') {
            $normalized = preg_replace('/^Dr\.\s*/i', '', $name);
            $st2 = $db->prepare('SELECT id FROM doctors WHERE CONCAT(first_name, " ", last_name) = ? LIMIT 1');
            $st2->execute([$normalized]);
            $r = $st2->fetch();
            if ($r) {
                $did = (int)$r['id'];
                if ($has_col) {
                    try {
                        $up = $db->prepare('UPDATE users SET doctor_id = ? WHERE id = ?');
                        $up->execute([$did, $_SESSION['user_id']]);
                    } catch (Throwable $e) { }
                }
            }
        }
    }
    return $did;
}


