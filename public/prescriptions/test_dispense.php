<?php
/**
 * Test script to verify dispense functionality
 * Access: http://localhost/hms/public/prescriptions/test_dispense.php
 */
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

echo "<h2>Prescription Dispense Test</h2>";

// Check if tables exist
$tables = ['prescriptions', 'bills', 'invoices', 'pharmaceuticals'];
foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) AS c FROM $table");
        $count = $stmt->fetch()['c'];
        echo "<p>✓ Table '$table' exists (records: $count)</p>";
    } catch (Throwable $e) {
        echo "<p>✗ Table '$table' missing: " . $e->getMessage() . "</p>";
    }
}

// Check prescription columns
$columns = ['quantity', 'unit_price', 'total_price', 'dispensed', 'dispensed_at', 'dispensed_by'];
foreach ($columns as $col) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM prescriptions LIKE '$col'");
        if ($stmt->fetch()) {
            echo "<p>✓ Column 'prescriptions.$col' exists</p>";
        } else {
            echo "<p>✗ Column 'prescriptions.$col' missing</p>";
        }
    } catch (Throwable $e) {
        echo "<p>✗ Error checking column '$col': " . $e->getMessage() . "</p>";
    }
}

// Check invoice columns
$inv_columns = ['prescription_id'];
foreach ($inv_columns as $col) {
    try {
        $stmt = $db->query("SHOW COLUMNS FROM invoices LIKE '$col'");
        if ($stmt->fetch()) {
            echo "<p>✓ Column 'invoices.$col' exists</p>";
        } else {
            echo "<p>✗ Column 'invoices.$col' missing</p>";
        }
    } catch (Throwable $e) {
        echo "<p>✗ Error checking column '$col': " . $e->getMessage() . "</p>";
    }
}

// Show recent dispensed prescriptions with bills
echo "<h3>Recent Dispensed Prescriptions with Bills</h3>";
try {
    $stmt = $db->query("SELECT p.id, p.medication, p.total_price, p.dispensed_at, 
        i.invoice_number, i.amount AS invoice_amount, i.status AS invoice_status,
        CONCAT(pa.first_name, ' ', pa.last_name) AS patient_name
        FROM prescriptions p
        JOIN patients pa ON p.patient_id = pa.id
        LEFT JOIN invoices i ON i.prescription_id = p.id
        WHERE p.dispensed = 1
        ORDER BY p.dispensed_at DESC
        LIMIT 10");
    $results = $stmt->fetchAll();
    
    if (empty($results)) {
        echo "<p>No dispensed prescriptions found.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Patient</th><th>Medication</th><th>Price</th><th>Invoice</th><th>Status</th><th>Dispensed</th></tr>";
        foreach ($results as $r) {
            echo "<tr>";
            echo "<td>" . (int)$r['id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['patient_name']) . "</td>";
            echo "<td>" . htmlspecialchars($r['medication']) . "</td>";
            echo "<td>" . format_currency((float)$r['total_price']) . "</td>";
            echo "<td>" . ($r['invoice_number'] ? htmlspecialchars($r['invoice_number']) : 'No Invoice') . "</td>";
            echo "<td>" . ($r['invoice_status'] ? htmlspecialchars($r['invoice_status']) : 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($r['dispensed_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='/hms/public/dashboard-pharmacist.php'>Back to Pharmacist Dashboard</a></p>";
?>

