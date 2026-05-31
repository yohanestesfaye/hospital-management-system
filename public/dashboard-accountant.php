<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth();
require_account_type('Accountant');
require_once __DIR__ . '/../config.php';
$db = get_db_connection();

// Ensure all required tables exist
try { 
    $db->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT NULL,
        patient_id INT NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'Unpaid',
        source VARCHAR(30) NULL,
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        paid_date DATETIME NULL,
        receipt_number VARCHAR(50) NULL,
        payment_method VARCHAR(30) NULL,
        processed_by INT NULL,
        INDEX(patient_id), INDEX(invoice_number), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

try { 
    $db->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NULL,
        patient_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('Cash','Card','Mobile Money','Insurance','Bank Transfer') NOT NULL,
        receipt_number VARCHAR(50) NULL,
        processed_by INT NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(invoice_id), INDEX(patient_id), INDEX(processed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

try { 
    $db->exec("CREATE TABLE IF NOT EXISTS insurance_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        patient_id INT NOT NULL,
        insurance_provider VARCHAR(100) NULL,
        claim_number VARCHAR(50) NULL,
        status ENUM('Pending','Submitted','Approved','Rejected','Paid') DEFAULT 'Pending',
        submitted_date DATETIME NULL,
        approved_date DATETIME NULL,
        amount DECIMAL(10,2) NOT NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(invoice_id), INDEX(patient_id), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); 
} catch (Throwable $e) {}

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

// Calculate KPIs
$today = date('Y-m-d');
$today_start = $today . ' 00:00:00';
$today_end = $today . ' 23:59:59';

// Today's Total Revenue - Sum of all revenue sources (Pharmacy + Lab + Doctor + Other)
$today_revenue = 0;
try {
    // Calculate total revenue from all sources:
    // 1. Pharmacy revenue (from dispensed prescriptions)
    // 2. Lab revenue (from lab bills/invoices)
    // 3. Doctor/OPD revenue (from doctor bills/invoices)
    // 4. Other revenue (from other bills/invoices)
    // 5. Payments collected today (for any source)
    
    // Get pharmacy revenue (from invoices with prescription_id or source='Pharmacy')
    $pharm_rev = 0;
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
        FROM invoices
        WHERE prescription_id IS NOT NULL
        AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $pharm_rev = (float)$stmt->fetch()['total'];
    
    if ($pharm_rev == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Pharmacy'
            AND prescription_id IS NULL
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $pharm_rev = (float)$stmt->fetch()['total'];
    }
    
    // Get lab revenue (from bills/invoices with source='Lab')
    $lab_rev_calc = 0;
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE b.source = 'Lab'
        AND DATE(b.issued_date) = CURDATE()");
    $stmt->execute();
    $lab_rev_calc = (float)$stmt->fetch()['total'];
    
    if ($lab_rev_calc == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Lab'
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $lab_rev_calc = (float)$stmt->fetch()['total'];
    }
    
    // Get doctor revenue (from bills/invoices with source='Doctor')
    $doctor_rev_calc = 0;
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE b.source = 'Doctor'
        AND DATE(b.issued_date) = CURDATE()");
    $stmt->execute();
    $doctor_rev_calc = (float)$stmt->fetch()['total'];
    
    if ($doctor_rev_calc == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Doctor'
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $doctor_rev_calc = (float)$stmt->fetch()['total'];
    }
    
    // Get other revenue (from bills/invoices with other sources or no source)
    $other_rev = 0;
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE (b.source NOT IN ('Pharmacy','Lab','Doctor') OR b.source IS NULL)
        AND (i.source NOT IN ('Pharmacy','Lab','Doctor') OR i.source IS NULL)
        AND i.prescription_id IS NULL
        AND DATE(COALESCE(b.issued_date, i.created_at)) = CURDATE()");
    $stmt->execute();
    $other_rev = (float)$stmt->fetch()['total'];
    
    // Sum all revenue sources (this will be recalculated after individual revenue calculations)
    // We'll recalculate at the end using the actual calculated values
    $today_revenue = $pharm_rev + $lab_rev_calc + $doctor_rev_calc + $other_rev;
} catch (Throwable $e) {
    // Fallback to payments if calculation fails
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $today_revenue = (float)$stmt->fetch()['total'];
    } catch (Throwable $e2) {}
}

// Pending Payments
$pending_payments = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) AS c FROM invoices WHERE status = 'Unpaid'");
    $pending_payments = (int)$stmt->fetch()['c'];
} catch (Throwable $e) {}

// Insurance Pending Claims
$pending_insurance = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) AS c FROM insurance_claims WHERE status IN ('Pending', 'Submitted')");
    $pending_insurance = (int)$stmt->fetch()['c'];
} catch (Throwable $e) {}

// Total Invoices Today
$invoices_today = 0;
try {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM invoices WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $invoices_today = (int)$stmt->fetch()['c'];
} catch (Throwable $e) {}

// Pharmacy Revenue Today - includes all bills/invoices from dispensed prescriptions (paid and unpaid)
$pharmacy_revenue = 0;
try {
    // Primary method: Get from invoices with prescription_id (most direct link to dispensed prescriptions)
    // This is the most reliable since invoices are created when prescriptions are dispensed
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
        FROM invoices
        WHERE prescription_id IS NOT NULL
        AND DATE(created_at) = CURDATE()");
    $stmt->execute();
    $pharmacy_revenue = (float)$stmt->fetch()['total'];
    
    // If no invoices with prescription_id, check invoices with source='Pharmacy'
    if ($pharmacy_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Pharmacy'
            AND prescription_id IS NULL
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $pharmacy_revenue = (float)$stmt->fetch()['total'];
    }
    
    // If still 0, check bills (in case invoice wasn't created)
    if ($pharmacy_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM bills
            WHERE source = 'Pharmacy'
            AND DATE(issued_date) = CURDATE()
            AND NOT EXISTS (SELECT 1 FROM invoices WHERE bill_id = bills.id)");
        $stmt->execute();
        $pharmacy_revenue = (float)$stmt->fetch()['total'];
    }
    
    // Ultimate fallback: Direct from prescriptions dispensed today
    if ($pharmacy_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(
            CASE 
                WHEN total_price > 0 THEN total_price
                WHEN unit_price > 0 AND quantity > 0 THEN unit_price * quantity
                ELSE 0
            END
        ), 0) AS total
        FROM prescriptions
        WHERE dispensed = 1
        AND DATE(dispensed_at) = CURDATE()");
        $stmt->execute();
        $pharmacy_revenue = (float)$stmt->fetch()['total'];
    }
} catch (Throwable $e) {
    // Ultimate fallback: Direct prescription query
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(
            CASE 
                WHEN total_price > 0 THEN total_price
                WHEN unit_price > 0 AND quantity > 0 THEN unit_price * quantity
                ELSE 0
            END
        ), 0) AS total
        FROM prescriptions
        WHERE dispensed = 1
        AND DATE(dispensed_at) = CURDATE()");
        $stmt->execute();
        $pharmacy_revenue = (float)$stmt->fetch()['total'];
    } catch (Throwable $e2) {}
}

// Lab Revenue Today - includes all lab bills/invoices created today
$lab_revenue = 0;
try {
    // Get from bills with source='Lab'
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE b.source = 'Lab'
        AND DATE(b.issued_date) = CURDATE()");
    $stmt->execute();
    $lab_revenue = (float)$stmt->fetch()['total'];
    
    // Fallback: Get from invoices with source='Lab'
    if ($lab_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Lab'
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $lab_revenue = (float)$stmt->fetch()['total'];
    }
} catch (Throwable $e) {}

// Doctor/OPD Revenue Today - includes all doctor bills/invoices created today
$doctor_revenue = 0;
try {
    // Get from bills with source='Doctor'
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE b.source = 'Doctor'
        AND DATE(b.issued_date) = CURDATE()");
    $stmt->execute();
    $doctor_revenue = (float)$stmt->fetch()['total'];
    
    // Fallback: Get from invoices with source='Doctor'
    if ($doctor_revenue == 0) {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE source = 'Doctor'
            AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $doctor_revenue = (float)$stmt->fetch()['total'];
    }
} catch (Throwable $e) {}

// Recalculate Today's Total Revenue using the actual calculated values
// This ensures we're summing the same values shown in individual KPI cards
$today_revenue = $pharmacy_revenue + $lab_revenue + $doctor_revenue;

// Add other revenue (bills/invoices with other sources or no source)
try {
    $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
        FROM bills b
        LEFT JOIN invoices i ON i.bill_id = b.id
        WHERE (b.source NOT IN ('Pharmacy','Lab','Doctor') OR b.source IS NULL)
        AND (i.source NOT IN ('Pharmacy','Lab','Doctor') OR i.source IS NULL)
        AND (i.prescription_id IS NULL OR i.prescription_id = 0)
        AND DATE(COALESCE(b.issued_date, i.created_at)) = CURDATE()");
    $stmt->execute();
    $other_revenue = (float)$stmt->fetch()['total'];
    $today_revenue += $other_revenue;
} catch (Throwable $e) {}

// If still 0, fallback to payments collected (for backward compatibility)
if ($today_revenue == 0) {
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $today_revenue = (float)$stmt->fetch()['total'];
    } catch (Throwable $e) {}
}

// Recent transactions
$recent_transactions = [];
try {
    $stmt = $db->prepare("SELECT p.*, i.invoice_number, CONCAT(pat.first_name, ' ', pat.last_name) AS patient_name,
        u.name AS processed_by_name
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        JOIN patients pat ON p.patient_id = pat.id
        LEFT JOIN users u ON p.processed_by = u.id
        ORDER BY p.created_at DESC LIMIT 10");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll();
} catch (Throwable $e) {}

// Revenue chart data (last 7 days) - by source
$chart_data = [];
$chart_data_by_source = ['Pharmacy' => [], 'Lab' => [], 'Doctor' => [], 'Other' => []];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $total_revenue = 0;
    $pharmacy_rev = 0;
    $lab_rev = 0;
    $doctor_rev = 0;
    $other_rev = 0;
    
    try {
        // Total revenue - Sum of all revenue sources for the date
        $pharm_rev_date = 0;
        $lab_rev_date = 0;
        $doctor_rev_date = 0;
        $other_rev_date = 0;
        
        // Pharmacy revenue
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE prescription_id IS NOT NULL
            AND DATE(created_at) = ?");
        $stmt->execute([$date]);
        $pharm_rev_date = (float)$stmt->fetch()['total'];
        
        if ($pharm_rev_date == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
                FROM invoices
                WHERE source = 'Pharmacy'
                AND DATE(created_at) = ?");
            $stmt->execute([$date]);
            $pharm_rev_date = (float)$stmt->fetch()['total'];
        }
        
        // Lab revenue
        $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
            FROM bills b
            LEFT JOIN invoices i ON i.bill_id = b.id
            WHERE b.source = 'Lab'
            AND DATE(b.issued_date) = ?");
        $stmt->execute([$date]);
        $lab_rev_date = (float)$stmt->fetch()['total'];
        
        if ($lab_rev_date == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
                FROM invoices
                WHERE source = 'Lab'
                AND DATE(created_at) = ?");
            $stmt->execute([$date]);
            $lab_rev_date = (float)$stmt->fetch()['total'];
        }
        
        // Doctor revenue
        $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
            FROM bills b
            LEFT JOIN invoices i ON i.bill_id = b.id
            WHERE b.source = 'Doctor'
            AND DATE(b.issued_date) = ?");
        $stmt->execute([$date]);
        $doctor_rev_date = (float)$stmt->fetch()['total'];
        
        if ($doctor_rev_date == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
                FROM invoices
                WHERE source = 'Doctor'
                AND DATE(created_at) = ?");
            $stmt->execute([$date]);
            $doctor_rev_date = (float)$stmt->fetch()['total'];
        }
        
        // Other revenue
        $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(i.amount, b.amount)), 0) AS total
            FROM bills b
            LEFT JOIN invoices i ON i.bill_id = b.id
            WHERE (b.source NOT IN ('Pharmacy','Lab','Doctor') OR b.source IS NULL)
            AND (i.source NOT IN ('Pharmacy','Lab','Doctor') OR i.source IS NULL)
            AND (i.prescription_id IS NULL OR i.prescription_id = 0)
            AND DATE(COALESCE(b.issued_date, i.created_at)) = ?");
        $stmt->execute([$date]);
        $other_rev_date = (float)$stmt->fetch()['total'];
        
        // Total revenue = sum of all sources
        $total_revenue = $pharm_rev_date + $lab_rev_date + $doctor_rev_date + $other_rev_date;
        
        // Fallback to payments if no revenue found
        if ($total_revenue == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
            $total_revenue = (float)$stmt->fetch()['total'];
        }
        
        // Revenue by source
        // Pharmacy: Get from invoices with prescription_id (most direct)
        $pharmacy_rev = 0;
        
        // Primary: From invoices with prescription_id
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE prescription_id IS NOT NULL
            AND DATE(created_at) = ?");
        $stmt->execute([$date]);
        $pharmacy_rev = (float)$stmt->fetch()['total'];
        
        // Fallback 1: From invoices with source='Pharmacy'
        if ($pharmacy_rev == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
                FROM invoices
                WHERE source = 'Pharmacy'
                AND DATE(created_at) = ?");
            $stmt->execute([$date]);
            $pharmacy_rev = (float)$stmt->fetch()['total'];
        }
        
        // Fallback 2: From bills
        if ($pharmacy_rev == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) AS total
                FROM bills
                WHERE source = 'Pharmacy' AND DATE(issued_date) = ?");
            $stmt->execute([$date]);
            $pharmacy_rev = (float)$stmt->fetch()['total'];
        }
        
        // Fallback 3: Direct from prescriptions
        if ($pharmacy_rev == 0) {
            $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(total_price, quantity * unit_price)), 0) AS total
                FROM prescriptions
                WHERE dispensed = 1
                AND DATE(dispensed_at) = ?");
            $stmt->execute([$date]);
            $pharmacy_rev = (float)$stmt->fetch()['total'];
        }
        
        // Lab, Doctor, Other: Get from payments collected
        $stmt = $db->prepare("SELECT 
            COALESCE(SUM(CASE WHEN i.source = 'Lab' OR b.source = 'Lab' THEN p.amount ELSE 0 END), 0) AS lab,
            COALESCE(SUM(CASE WHEN i.source = 'Doctor' OR b.source = 'Doctor' THEN p.amount ELSE 0 END), 0) AS doctor,
            COALESCE(SUM(CASE WHEN (i.source NOT IN ('Pharmacy','Lab','Doctor') OR i.source IS NULL) 
                AND (b.source NOT IN ('Pharmacy','Lab','Doctor') OR b.source IS NULL) 
                THEN p.amount ELSE 0 END), 0) AS other
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN bills b ON i.bill_id = b.id
            WHERE DATE(p.created_at) = ?");
        $stmt->execute([$date]);
        $source_data = $stmt->fetch();
        $lab_rev = (float)($source_data['lab'] ?? 0);
        $doctor_rev = (float)($source_data['doctor'] ?? 0);
        $other_rev = (float)($source_data['other'] ?? 0);
    } catch (Throwable $e) {}
    
    $chart_data[] = ['date' => date('M d', strtotime($date)), 'revenue' => $total_revenue];
    $chart_data_by_source['Pharmacy'][] = $pharmacy_rev;
    $chart_data_by_source['Lab'][] = $lab_rev;
    $chart_data_by_source['Doctor'][] = $doctor_rev;
    $chart_data_by_source['Other'][] = $other_rev;
}

include __DIR__ . '/../includes/accountant-header.php';
?>

<div class="container-fluid">
    <!-- KPI Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-1">Today's Revenue</div>
                            <div class="h4 mb-0 fw-bold text-success"><?php echo format_currency($today_revenue); ?></div>
                        </div>
                        <div class="kpi-icon bg-success bg-opacity-10 text-success">
                            💵
                        </div>
                    </div>
                    <small class="text-muted">Total payments received today</small>
                </div>
            </div>
	</div>
	
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
				<div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
						<div>
                            <div class="text-muted small mb-1">Pending Payments</div>
                            <div class="h4 mb-0 fw-bold text-warning"><?php echo $pending_payments; ?></div>
                        </div>
                        <div class="kpi-icon bg-warning bg-opacity-10 text-warning">
                            🧾
                        </div>
						</div>
                    <small class="text-muted">Unpaid invoices</small>
					</div>
            </div>
        </div>
        
        
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
				<div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
						<div>
                            <div class="text-muted small mb-1">Invoices Today</div>
                            <div class="h4 mb-0 fw-bold text-primary"><?php echo $invoices_today; ?></div>
                        </div>
                        <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                            📄
                        </div>
						</div>
                    <small class="text-muted">Generated today</small>
					</div>
				</div>
			</div>
        
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
				<div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
						<div>
                            <div class="text-muted small mb-1">Pharmacy Revenue</div>
                            <div class="h4 mb-0 fw-bold" style="color: #8b5cf6;"><?php echo format_currency($pharmacy_revenue); ?></div>
                        </div>
                        <div class="kpi-icon bg-opacity-10 text-white" style="background-color: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                            💊
                        </div>
						</div>
                    <small class="text-muted">Today's pharmacy income</small>
					</div>
				</div>
        </div>
        
        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-1">Lab Revenue</div>
                            <div class="h4 mb-0 fw-bold" style="color: #ec4899;"><?php echo format_currency($lab_revenue); ?></div>
                        </div>
                        <div class="kpi-icon bg-opacity-10 text-white" style="background-color: rgba(236, 72, 153, 0.1); color: #ec4899;">
                            🔬
                        </div>
                    </div>
                    <small class="text-muted">Today's lab income</small>
			</div>
		</div>
	</div>

        <div class="col-xl-2 col-md-4 col-sm-6">
            <div class="card kpi-card border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <div class="text-muted small mb-1">Doctor/OPD Revenue</div>
                            <div class="h4 mb-0 fw-bold" style="color: #0d9488;"><?php echo format_currency($doctor_revenue); ?></div>
                        </div>
                        <div class="kpi-icon bg-opacity-10 text-white" style="background-color: rgba(13, 148, 136, 0.1); color: #0d9488;">
                            👨‍⚕️
                        </div>
				</div>
                    <small class="text-muted">Today's OPD income</small>
					</div>
				</div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Revenue Trend (Last 7 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
			</div>
		</div>
	</div>

        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
				</div>
				<div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/hms/public/payments/collect.php" class="btn btn-primary btn-modern">
                            <i class="bi bi-cash-coin"></i> Collect Payment
                        </a>
                        <a href="/hms/public/billing/new.php" class="btn btn-outline-primary btn-modern">
                            <i class="bi bi-receipt"></i> Generate Bill
                        </a>
                        <a href="/hms/public/reports/financial.php" class="btn btn-outline-success btn-modern">
                            <i class="bi bi-file-earmark-pdf"></i> Generate Report
                        </a>
                    </div>
				</div>
			</div>
		</div>
    </div>

    <!-- Recent Transactions -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card card-modern">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Transactions</h5>
                    <a href="/hms/public/payments/history.php" class="btn btn-sm btn-outline-primary">View All</a>
				</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Invoice #</th>
                                    <th>Patient</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Processed By</th>
                                    <th>Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_transactions)) { ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No recent transactions</td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($recent_transactions as $txn) { ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($txn['created_at'])); ?></td>
                                            <td><code><?php echo sanitize($txn['invoice_number'] ?? 'N/A'); ?></code></td>
                                            <td><?php echo sanitize($txn['patient_name']); ?></td>
                                            <td class="fw-bold text-success"><?php echo number_format((float)$txn['amount'], 2); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo sanitize($txn['payment_method']); ?></span></td>
                                            <td><?php echo sanitize($txn['processed_by_name'] ?? 'System'); ?></td>
                                            <td>
                                                <?php if ($txn['receipt_number']) { ?>
                                                    <code class="small"><?php echo sanitize($txn['receipt_number']); ?></code>
                                                <?php } else { ?>
                                                    <span class="text-muted">-</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
				</div>
			</div>
		</div>
	</div>
</div>
</div>

<script>
// Revenue Chart with breakdown by source
const ctx = document.getElementById('revenueChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($chart_data, 'date')); ?>,
            datasets: [
                {
                    label: 'Pharmacy',
                    data: <?php echo json_encode($chart_data_by_source['Pharmacy']); ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Lab',
                    data: <?php echo json_encode($chart_data_by_source['Lab']); ?>,
                    borderColor: '#ec4899',
                    backgroundColor: 'rgba(236, 72, 153, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Doctor/OPD',
                    data: <?php echo json_encode($chart_data_by_source['Doctor']); ?>,
                    borderColor: '#0d9488',
                    backgroundColor: 'rgba(13, 148, 136, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Other',
                    data: <?php echo json_encode($chart_data_by_source['Other']); ?>,
                    borderColor: '#6b7280',
                    backgroundColor: 'rgba(107, 114, 128, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    stacked: false,
                    ticks: {
                        callback: function(value) {
                            return 'ETB ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/accountant-footer.php'; ?>
