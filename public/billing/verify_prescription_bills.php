<?php
/**
 * Verification script to check if prescription bills are being created
 * Access: http://localhost/hms/public/billing/verify_prescription_bills.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

echo "<h2>Prescription Bill Verification</h2>";
echo "<style>table{border-collapse:collapse;width:100%;margin:10px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background-color:#f2f2f2}</style>";

// Check recent dispensed prescriptions
echo "<h3>Recent Dispensed Prescriptions (Last 20)</h3>";
try {
    $stmt = $db->query("SELECT p.id, p.medication, p.total_price, p.dispensed_at, p.dispensed,
        CONCAT(pa.first_name, ' ', pa.last_name) AS patient_name,
        i.id AS invoice_id, i.invoice_number, i.amount AS invoice_amount, i.status AS invoice_status,
        b.id AS bill_id, b.amount AS bill_amount, b.status AS bill_status, b.source AS bill_source
        FROM prescriptions p
        JOIN patients pa ON p.patient_id = pa.id
        LEFT JOIN invoices i ON i.prescription_id = p.id
        LEFT JOIN bills b ON b.id = i.bill_id
        WHERE p.dispensed = 1
        ORDER BY p.dispensed_at DESC
        LIMIT 20");
    $results = $stmt->fetchAll();
    
    if (empty($results)) {
        echo "<p>No dispensed prescriptions found.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Prescription ID</th><th>Patient</th><th>Medication</th><th>Price</th><th>Bill ID</th><th>Bill Amount</th><th>Bill Status</th><th>Invoice ID</th><th>Invoice #</th><th>Invoice Status</th><th>Dispensed</th></tr>";
        foreach ($results as $r) {
            $has_bill = !empty($r['bill_id']);
            $has_invoice = !empty($r['invoice_id']);
            $row_color = ($has_bill && $has_invoice) ? '' : 'background-color:#ffcccc;';
            echo "<tr style='$row_color'>";
            echo "<td>" . (int)$r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['patient_name']) . "</td>";
            echo "<td>" . htmlspecialchars($r['medication']) . "</td>";
            echo "<td>" . format_currency((float)$r['total_price']) . "</td>";
            echo "<td>" . ($r['bill_id'] ? (int)$r['bill_id'] : '<span style="color:red">MISSING</span>') . "</td>";
            echo "<td>" . ($r['bill_amount'] ? format_currency((float)$r['bill_amount']) : '-') . "</td>";
            echo "<td>" . ($r['bill_status'] ? htmlspecialchars($r['bill_status']) : '-') . "</td>";
            echo "<td>" . ($r['invoice_id'] ? (int)$r['invoice_id'] : '<span style="color:red">MISSING</span>') . "</td>";
            echo "<td>" . ($r['invoice_number'] ? htmlspecialchars($r['invoice_number']) : '-') . "</td>";
            echo "<td>" . ($r['invoice_status'] ? htmlspecialchars($r['invoice_status']) : '-') . "</td>";
            echo "<td>" . htmlspecialchars($r['dispensed_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count missing bills
        $missing_bills = 0;
        $missing_invoices = 0;
        foreach ($results as $r) {
            if (empty($r['bill_id'])) $missing_bills++;
            if (empty($r['invoice_id'])) $missing_invoices++;
        }
        
        if ($missing_bills > 0 || $missing_invoices > 0) {
            echo "<div style='background:#fff3cd;padding:10px;margin:10px 0;border:1px solid #ffc107;'>";
            echo "<strong>⚠️ Issues Found:</strong><br>";
            if ($missing_bills > 0) {
                echo "• $missing_bills prescription(s) missing bills<br>";
            }
            if ($missing_invoices > 0) {
                echo "• $missing_invoices prescription(s) missing invoices<br>";
            }
            echo "</div>";
        } else {
            echo "<div style='background:#d4edda;padding:10px;margin:10px 0;border:1px solid #28a745;'>";
            echo "<strong>✓ All prescriptions have bills and invoices!</strong>";
            echo "</div>";
        }
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check bills table structure
echo "<h3>Bills Table Structure</h3>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM bills");
    $columns = $stmt->fetchAll();
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check invoices table structure
echo "<h3>Invoices Table Structure</h3>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM invoices");
    $columns = $stmt->fetchAll();
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='/hms/public/billing/prescriptions.php'>View Prescription Billing</a> | ";
echo "<a href='/hms/public/dashboard-accountant.php'>Accountant Dashboard</a></p>";
?>

