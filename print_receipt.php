<?php
// print_receipt.php — Professional Payment Receipt / Invoice
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

// Allow access for admins OR logged-in students (students can only view their own)
$is_admin = isset($_SESSION['admin_id']);
$is_student = isset($_SESSION['student_id']);

if (!$is_admin && !$is_student) {
    die("Unauthorized access. Please log in.");
}

if (!isset($_GET['id'])) {
    die("Invalid Request. Application ID required.");
}

$id = (int)$_GET['id'];

// Students can only view their own receipt
if ($is_student && !$is_admin && $id !== (int)$_SESSION['student_id']) {
    die("Unauthorized access. You can only view your own receipt.");
}

// Fetch student data
$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                       FROM admission_inquiries i 
                       LEFT JOIN faculties f ON i.faculty_id = f.id 
                       LEFT JOIN entrance_schedules e ON i.schedule_id = e.id 
                       WHERE i.id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inq) {
    die("Application not found.");
}

if (($inq['payment_status'] ?? 'Pending') !== 'Paid') {
    die("No payment receipt available. Payment has not been recorded yet.");
}

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$school_address = $settings['address'] ?? '';
$school_phone = $settings['contact_phone'] ?? '';
$school_email = $settings['org_email'] ?? '';
$logo = $settings['logo_path'] ?? '';
$invoice_prefix = $settings['invoice_prefix'] ?? 'INV';
$invoice_start = (int)($settings['invoice_start_number'] ?? 1);

// Generate or retrieve invoice number
$invoice_number = $inq['invoice_number'] ?? null;

if (empty($invoice_number)) {
    // Find the current maximum invoice number in the DB
    $max_stmt = $pdo->query("SELECT MAX(CAST(REPLACE(invoice_number, '{$invoice_prefix}', '') AS UNSIGNED)) as max_num FROM admission_inquiries WHERE invoice_number IS NOT NULL AND invoice_number != ''");
    $max_row = $max_stmt->fetch(PDO::FETCH_ASSOC);
    $max_existing = (int)($max_row['max_num'] ?? 0);
    
    // New number = max of (highest existing, start_number - 1) + 1
    $next_num = max($max_existing, $invoice_start - 1) + 1;
    $invoice_number = $invoice_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    
    // Persist to DB
    $pdo->prepare("UPDATE admission_inquiries SET invoice_number = ? WHERE id = ?")->execute([$invoice_number, $id]);
}

// Payment date fallback
$payment_date = $inq['payment_date'] ?? $inq['submission_date'] ?? date('Y-m-d');
$payment_date_formatted = date('d M, Y', strtotime($payment_date));
$payment_time = date('h:i A', strtotime($payment_date));

$student_name = htmlspecialchars(strtoupper($inq['student_first_name'] . ' ' . $inq['student_last_name']));
$roll_no = htmlspecialchars($inq['entrance_roll_no'] ?? 'N/A');
$applied_class = htmlspecialchars($inq['applied_class'] ?? '');
$faculty = $inq['faculty_name'] ? ' - ' . htmlspecialchars($inq['faculty_name']) : '';
$amount = (float)($inq['payment_amount'] ?? 0);
$method = htmlspecialchars($inq['payment_method'] ?? 'Cash');
$reference = htmlspecialchars($inq['payment_reference'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo $invoice_number; ?> - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #1e293b;
            background: #e2e8f0;
            padding: 30px;
        }

        .receipt-container {
            max-width: 210mm;
            background: #fff;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.12);
            border-radius: 2px;
        }

        /* Decorative top border */
        .top-accent {
            height: 6px;
            background: linear-gradient(90deg, #059669, #10b981, #34d399, #10b981, #059669);
        }

        /* Header */
        .receipt-header {
            padding: 15px 25px 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f1f5f9;
        }
        .org-info {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .org-logo {
            width: 65px;
            height: 65px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            padding: 2px;
            background: #fff;
        }
        .org-details h1 {
            font-size: 20px;
            font-weight: 800;
            color: #064e3b;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .org-details p {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
            font-weight: 500;
        }

        .invoice-badge {
            text-align: right;
        }
        .invoice-badge .label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .invoice-badge .number {
            font-family: 'JetBrains Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            color: #059669;
            margin-top: 2px;
        }
        .invoice-badge .status {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 20px;
            margin-top: 6px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Title bar */
        .receipt-title {
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            color: white;
            text-align: center;
            padding: 14px 40px;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        /* Content */
        .receipt-body {
            padding: 15px 25px;
        }

        /* Two column info */
        .info-columns {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .info-col {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 15px;
        }
        .info-col h3 {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12.5px;
        }
        .info-row .label { color: #64748b; font-weight: 500; }
        .info-row .value { color: #0f172a; font-weight: 700; text-align: right; }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .items-table thead th {
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .items-table thead th:last-child,
        .items-table thead th:nth-child(3) {
            text-align: right;
        }
        .items-table tbody td {
            padding: 10px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .items-table tbody td:last-child,
        .items-table tbody td:nth-child(3) {
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
        }
        .items-table tbody td:first-child {
            font-weight: 600;
            color: #0f172a;
        }
        .item-desc {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 400;
            margin-top: 2px;
        }

        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 0;
        }
        .totals-box {
            width: 280px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 14px;
            font-size: 12.5px;
            border-bottom: 1px solid #f1f5f9;
        }
        .total-row .label { color: #64748b; font-weight: 500; }
        .total-row .value { font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #0f172a; }
        .total-row.grand {
            background: linear-gradient(135deg, #065f46 0%, #059669 100%);
            border-bottom: none;
            padding: 10px 14px;
        }
        .total-row.grand .label { color: white; font-weight: 700; font-size: 13px; }
        .total-row.grand .value { color: white; font-weight: 800; font-size: 16px; }

        /* Payment info */
        .payment-info {
            margin-top: 15px;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 10px;
            padding: 12px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .payment-icon {
            width: 42px;
            height: 42px;
            background: #059669;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .payment-icon svg { width: 22px; height: 22px; color: white; }
        .payment-details { flex: 1; }
        .payment-details .title {
            font-size: 13px;
            font-weight: 700;
            color: #065f46;
        }
        .payment-details .meta {
            font-size: 11px;
            color: #047857;
            margin-top: 2px;
        }

        /* Footer */
        .receipt-footer {
            padding: 15px 25px 15px;
            border-top: 2px solid #f1f5f9;
            margin-top: 15px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            padding-top: 10px;
        }
        .sig-block {
            text-align: center;
            width: 200px;
        }
        .sig-line {
            border-top: 2px solid #1e293b;
            padding-top: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }
        .sig-subtitle {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .note-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 11px;
            color: #92400e;
            margin-top: 20px;
            line-height: 1.5;
        }
        .note-box strong { color: #78350f; }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 100px;
            color: rgba(5, 150, 105, 0.04);
            font-weight: 900;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
            letter-spacing: 10px;
        }

        .receipt-content { position: relative; z-index: 1; }

        .generated-info {
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 20px;
            font-weight: 500;
        }

        /* Action buttons (not printed) */
        .action-bar {
            max-width: 210mm;
            margin: 0 auto 20px;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            text-decoration: none;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: #059669; color: white; box-shadow: 0 4px 15px rgba(5,150,105,0.3); }
        .btn-primary:hover { background: #047857; }
        .btn-dark { background: #1e293b; color: white; box-shadow: 0 4px 15px rgba(30,41,59,0.3); }
        .btn-dark:hover { background: #0f172a; }
        .btn-outline { background: white; color: #475569; border: 2px solid #e2e8f0; }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

        @media print {
            body { background: none; padding: 0; }
            .receipt-container { box-shadow: none; margin: 0; width: 100%; border-radius: 0; padding: 0 !important; }
            .action-bar { display: none !important; }
            .top-accent, .receipt-title { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .payment-info { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row.grand { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .invoice-badge .status { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .items-table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            html, body { min-height: 100%; overflow: visible; }
        }
    </style>
</head>
<body>

<!-- Action Buttons -->
<div class="action-bar">
    <button onclick="window.print()" class="btn btn-primary">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        Print Receipt
    </button>
    <button onclick="downloadPDF()" class="btn btn-dark">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        Save as PDF
    </button>
    <?php 
        $back_url = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $back_url = $_SERVER['HTTP_REFERER'];
        } elseif ($is_admin) {
            $back_url = 'view_application.php?id=' . $id;
        } else {
            $back_url = 'student_dashboard.php?tab=documents';
        }
    ?>
    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-outline">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Go Back
    </a>
</div>

<!-- Receipt -->
<div class="receipt-container" id="receipt-element">
    <div class="watermark">PAID</div>

    <div class="receipt-content">
        <div class="top-accent"></div>

        <!-- Header -->
        <div class="receipt-header">
            <div class="org-info">
                <?php if ($logo): ?>
                    <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="org-logo">
                <?php endif; ?>
                <div class="org-details">
                    <h1><?php echo htmlspecialchars($school_name); ?></h1>
                    <?php if ($school_address): ?>
                        <p><?php echo htmlspecialchars($school_address); ?></p>
                    <?php endif; ?>
                    <?php if ($school_phone): ?>
                        <p>Tel: <?php echo htmlspecialchars($school_phone); ?><?php echo $school_email ? ' | ' . htmlspecialchars($school_email) : ''; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="invoice-badge">
                <div class="label">Invoice Number</div>
                <div class="number"><?php echo htmlspecialchars($invoice_number); ?></div>
                <div class="status">✓ Paid</div>
            </div>
        </div>

        <!-- Title -->
        <div class="receipt-title">Payment Receipt</div>

        <div class="receipt-body">
            <!-- Student & Payment Info -->
            <div class="info-columns">
                <div class="info-col">
                    <h3>Billed To</h3>
                    <div class="info-row"><span class="label">Student Name</span><span class="value"><?php echo $student_name; ?></span></div>
                    <div class="info-row"><span class="label">Roll Number</span><span class="value"><?php echo $roll_no; ?></span></div>
                    <div class="info-row"><span class="label">Applied For</span><span class="value"><?php echo $applied_class . $faculty; ?></span></div>
                    <?php if (!empty($inq['father_name'])): ?>
                    <div class="info-row"><span class="label">Guardian</span><span class="value"><?php echo htmlspecialchars($inq['father_name']); ?></span></div>
                    <?php endif; ?>
                    <?php if (!empty($inq['father_contact'])): ?>
                    <div class="info-row"><span class="label">Contact</span><span class="value"><?php echo htmlspecialchars($inq['father_contact']); ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="info-col">
                    <h3>Payment Details</h3>
                    <div class="info-row"><span class="label">Invoice No.</span><span class="value" style="color:#059669;"><?php echo htmlspecialchars($invoice_number); ?></span></div>
                    <div class="info-row"><span class="label">Payment Date</span><span class="value"><?php echo $payment_date_formatted; ?></span></div>
                    <div class="info-row"><span class="label">Payment Method</span><span class="value"><?php echo $method; ?></span></div>
                    <?php if ($reference): ?>
                    <div class="info-row"><span class="label">Reference No.</span><span class="value" style="font-family:'JetBrains Mono',monospace; font-size:11px;"><?php echo $reference; ?></span></div>
                    <?php endif; ?>
                    <div class="info-row"><span class="label">Status</span><span class="value" style="color:#059669;">✓ Confirmed</span></div>
                </div>
            </div>

            <!-- Itemized Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:5%;">#</th>
                        <th style="width:55%;">Description</th>
                        <th style="width:20%;">Rate</th>
                        <th style="width:20%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>
                            Admission / Entrance Examination Fee
                            <div class="item-desc">For <?php echo $applied_class . $faculty; ?> — Academic Session 2083 BS</div>
                        </td>
                        <td>Rs. <?php echo number_format($amount, 2); ?></td>
                        <td>Rs. <?php echo number_format($amount, 2); ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="totals-section">
                <div class="totals-box">
                    <div class="total-row">
                        <span class="label">Subtotal</span>
                        <span class="value">Rs. <?php echo number_format($amount, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="label">Discount</span>
                        <span class="value">Rs. 0.00</span>
                    </div>
                    <div class="total-row grand">
                        <span class="label">Total Paid</span>
                        <span class="value">Rs. <?php echo number_format($amount, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Confirmation -->
            <div class="payment-info">
                <div class="payment-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div class="payment-details">
                    <div class="title">Payment Received Successfully</div>
                    <div class="meta">
                        Amount of Rs. <?php echo number_format($amount, 2); ?> received via <?php echo $method; ?> on <?php echo $payment_date_formatted; ?>
                        <?php echo $reference ? " (Ref: {$reference})" : ''; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="receipt-footer">
            <div class="note-box">
                <strong>Note:</strong> This is a computer-generated receipt and is valid without a physical signature. 
                Please retain this receipt for your records. In case of any discrepancy, kindly contact the administration office within 7 days of issue.
            </div>

            <div class="signatures">
                <div class="sig-block">
                    <div class="sig-line">Received By</div>
                    <div class="sig-subtitle">Administration Office</div>
                </div>
                <div class="sig-block">
                    <div class="sig-line">Authorized Signatory</div>
                    <div class="sig-subtitle"><?php echo htmlspecialchars($school_name); ?></div>
                </div>
            </div>

            <div class="generated-info">
                Invoice <?php echo htmlspecialchars($invoice_number); ?> • Generated on <?php echo date('Y-m-d H:i:s'); ?> • <?php echo htmlspecialchars($school_name); ?>
            </div>
        </div>
    </div>
</div>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadPDF() {
        const element = document.getElementById('receipt-element');
        const opt = {
            margin:       0,
            filename:     'Receipt_<?php echo $invoice_number; ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        // Temporarily hide action bar
        document.querySelector('.action-bar').style.display = 'none';
        html2pdf().set(opt).from(element).save().then(() => {
            document.querySelector('.action-bar').style.display = '';
        });
    }
</script>

</body>
</html>
