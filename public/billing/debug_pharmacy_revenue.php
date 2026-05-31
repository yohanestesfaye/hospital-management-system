<?php
/**
 * Debug script to check pharmacy revenue calculation
 * Access: http://localhost/hms/public/billing/debug_pharmacy_revenue.php
 */
require_once __DIR__ . '/../../includes/auth.php';
require_account_type('Accountant');
require_once __DIR__ . '/../../config.php';
$db = get_db_connection();

echo "<h2>Pharmacy Revenue Debug</h2>";
echo "<style>table{border-collapse:collapse;width:100%;margin:10px 0}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background-color:#f2f2f2}</style>";

$today = date('Y-m-d');
echo "<p><strong>Today's Date:</strong> $today</p>";

// Check bills created today
echo "<h3>1. Bills with source='Pharmacy' created today</h3>";
try {
    $stmt = $db->prepare("SELECT id, patient_id, amount, source, issued_date, DATE(issued_date) AS bill_date
        FROM bills
        WHERE source = 'Pharmacy'
        AND DATE(issued_date) = CURDATE()
        ORDER BY id DESC");
    $stmt->execute();
    $bills = $stmt->fetchAll();
    
    if (empty($bills)) {
        echo "<p style='color:orange;'>No pharmacy bills found for today.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Patient ID</th><th>Amount</th><th>Source</th><th>Issued Date</th><th>Date Only</th></tr>";
        $total = 0;
        foreach ($bills as $b) {
            $total += (float)$b['amount'];
            echo "<tr>";
            echo "<td>" . (int)$b['id'] . "</td>";
            echo "<td>" . (int)$b['patient_id'] . "</td>";
            echo "<td>" . format_currency((float)$b['amount']) . "</td>";
            echo "<td>" . htmlspecialchars($b['source']) . "</td>";
            echo "<td>" . htmlspecialchars($b['issued_date']) . "</td>";
            echo "<td>" . htmlspecialchars($b['bill_date']) . "</td>";
            echo "</tr>";
        }
        echo "<tr><td colspan='2'><strong>Total</strong></td><td><strong>" . format_currency($total) . "</strong></td><td colspan='3'></td></tr>";
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check invoices created today
echo "<h3>2. Invoices with source='Pharmacy' or prescription_id created today</h3>";
try {
    $stmt = $db->prepare("SELECT id, bill_id, patient_id, amount, source, prescription_id, created_at, DATE(created_at) AS invoice_date
        FROM invoices
        WHERE (source = 'Pharmacy' OR prescription_id IS NOT NULL)
        AND DATE(created_at) = CURDATE()
        ORDER BY id DESC");
    $stmt->execute();
    $invoices = $stmt->fetchAll();
    
    if (empty($invoices)) {
        echo "<p style='color:orange;'>No pharmacy invoices found for today.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Bill ID</th><th>Patient ID</th><th>Amount</th><th>Source</th><th>Prescription ID</th><th>Created At</th></tr>";
        $total = 0;
        foreach ($invoices as $inv) {
            $total += (float)$inv['amount'];
            echo "<tr>";
            echo "<td>" . (int)$inv['id'] . "</td>";
            echo "<td>" . ($inv['bill_id'] ? (int)$inv['bill_id'] : 'NULL') . "</td>";
            echo "<td>" . (int)$inv['patient_id'] . "</td>";
            echo "<td>" . format_currency((float)$inv['amount']) . "</td>";
            echo "<td>" . htmlspecialchars($inv['source'] ?? 'NULL') . "</td>";
            echo "<td>" . ($inv['prescription_id'] ? (int)$inv['prescription_id'] : 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($inv['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "<tr><td colspan='3'><strong>Total</strong></td><td><strong>" . format_currency($total) . "</strong></td><td colspan='3'></td></tr>";
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check prescriptions dispensed today
echo "<h3>3. Prescriptions dispensed today</h3>";
try {
    $stmt = $db->prepare("SELECT id, patient_id, medication, quantity, unit_price, total_price, dispensed_at, DATE(dispensed_at) AS dispensed_date
        FROM prescriptions
        WHERE dispensed = 1
        AND DATE(dispensed_at) = CURDATE()
        ORDER BY id DESC");
    $stmt->execute();
    $prescriptions = $stmt->fetchAll();
    
    if (empty($prescriptions)) {
        echo "<p style='color:orange;'>No prescriptions dispensed today.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Patient ID</th><th>Medication</th><th>Qty</th><th>Unit Price</th><th>Total Price</th><th>Dispensed At</th></tr>";
        $total = 0;
        foreach ($prescriptions as $prx) {
            $total += (float)$prx['total_price'];
            echo "<tr>";
            echo "<td>" . (int)$prx['id'] . "</td>";
            echo "<td>" . (int)$prx['patient_id'] . "</td>";
            echo "<td>" . htmlspecialchars($prx['medication']) . "</td>";
            echo "<td>" . (int)$prx['quantity'] . "</td>";
            echo "<td>" . format_currency((float)$prx['unit_price']) . "</td>";
            echo "<td>" . format_currency((float)$prx['total_price']) . "</td>";
            echo "<td>" . htmlspecialchars($prx['dispensed_at']) . "</td>";
            echo "</tr>";
        }
        echo "<tr><td colspan='5'><strong>Total</strong></td><td><strong>" . format_currency($total) . "</strong></td><td></td></tr>";
        echo "</table>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Calculate revenue using the same logic as dashboard
echo "<h3>4. Calculated Pharmacy Revenue (Using Dashboard Logic)</h3>";
$calculated_revenue = 0;
try {
    // Method 1: From bills
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
        FROM bills
        WHERE source = 'Pharmacy'
        AND DATE(issued_date) = CURDATE()");
    $stmt->execute();
    $bill_revenue = (float)$stmt->fetch()['total'];
    echo "<p><strong>From Bills:</strong> " . format_currency($bill_revenue) . "</p>";
    
    // Method 2: From invoices
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
        FROM invoices
        WHERE (source = 'Pharmacy' OR prescription_id IS NOT NULL)
        AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $invoice_revenue = (float)$stmt->fetch()['total'];
    echo "<p><strong>From Invoices:</strong> " . format_currency($invoice_revenue) . "</p>";
    
    $calculated_revenue = max($bill_revenue, $invoice_revenue);
    
    // Method 3: From prescriptions
    if ($calculated_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(total_price), 0) AS total
            FROM prescriptions
            WHERE dispensed = 1
            AND DATE(dispensed_at) = CURDATE()
            AND total_price > 0");
        $stmt->execute();
        $prescription_revenue = (float)$stmt->fetch()['total'];
        echo "<p><strong>From Prescriptions (fallback):</strong> " . format_currency($prescription_revenue) . "</p>";
        $calculated_revenue = $prescription_revenue;
    }
    
    echo "<p style='font-size:1.2em;color:green;'><strong>Final Calculated Revenue: " . format_currency($calculated_revenue) . "</strong></p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='/hms/public/dashboard-accountant.php'>Back to Dashboard</a></p>";
?>

