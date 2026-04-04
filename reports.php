<?php
// reports.php - Dedicated Analytics & Reporting Engine
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Cashier', 'Viewer'])) {
    header("Location: dashboard.php");
    exit;
}

// Date Range Filtering
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

// Format for SQL (Inclusivity on the end date by pushing to end of day)
$sql_start = $start_date . ' 00:00:00';
$sql_end   = $end_date . ' 23:59:59';

// ---------------------------------------------------------
// CSV EXPORT LOGIC
// ---------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("
        SELECT 
            entrance_roll_no, student_first_name, student_last_name, 
            student_email, student_phone, gender, address_province,
            applied_class, form_type, payment_status, payment_amount,
            status as application_status, submission_date
        FROM admission_inquiries 
        WHERE submission_date BETWEEN ? AND ?
        ORDER BY submission_date ASC
    ");
    $stmt->execute([$sql_start, $sql_end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=admission_report_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Roll No', 'First Name', 'Last Name', 'Email', 'Phone', 'Gender', 'Province', 'Applied Class', 'Form Type', 'Payment Status', 'Amount Paid', 'Application Status', 'Submission Date'));
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ---------------------------------------------------------
// DATA FETCHING FOR CHARTS & KPIs
// ---------------------------------------------------------

// 1. High-Level KPIs
$kpi_stmt = $pdo->prepare("
    SELECT 
        COUNT(id) as total_applicants,
        SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as total_paid,
        SUM(CASE WHEN payment_status = 'Paid' THEN payment_amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN status = 'Admitted' THEN 1 ELSE 0 END) as total_admitted
    FROM admission_inquiries
    WHERE submission_date BETWEEN ? AND ? AND form_type = 'Admission'
");
$kpi_stmt->execute([$sql_start, $sql_end]);
$kpis = $kpi_stmt->fetch(PDO::FETCH_ASSOC);

// 2. Revenue Over Time (Daily)
$rev_stmt = $pdo->prepare("
    SELECT DATE(payment_date) as pay_date, SUM(payment_amount) as daily_revenue
    FROM admission_inquiries
    WHERE payment_status = 'Paid' AND payment_date BETWEEN ? AND ?
    GROUP BY DATE(payment_date)
    ORDER BY pay_date ASC
");
$rev_stmt->execute([$sql_start, $sql_end]);
$revenue_data = $rev_stmt->fetchAll(PDO::FETCH_ASSOC);

$dates_json = [];
$revenues_json = [];
foreach($revenue_data as $rd) {
    if (!$rd['pay_date']) continue;
    $dates_json[] = date('M d', strtotime($rd['pay_date']));
    $revenues_json[] = (float)$rd['daily_revenue'];
}

// 3. Admission Funnel
$funnel_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM admission_inquiries
    WHERE form_type = 'Admission' AND submission_date BETWEEN ? AND ?
    GROUP BY status
");
$funnel_stmt->execute([$sql_start, $sql_end]);
$funnel_raw = $funnel_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Funnel logic: Total -> Paid -> Approved -> Admitted
// Since Approved usually comes after Paid in our flow, and Admitted after Exam Passed.
$f_total = $kpis['total_applicants'];
$f_paid = $kpis['total_paid'];
$f_approved = ($funnel_raw['Approved'] ?? 0) + ($funnel_raw['Admitted'] ?? 0); // Admitted implies it was approved
$f_admitted = $funnel_raw['Admitted'] ?? 0;

$funnel_labels = ['Total Applied', 'Fee Paid', 'Approved', 'Fully Admitted'];
$funnel_values = [(int)$f_total, (int)$f_paid, (int)$f_approved, (int)$f_admitted];

// 4. Demographics (Gender)
$gender_stmt = $pdo->prepare("
    SELECT gender, COUNT(*) as count
    FROM admission_inquiries
    WHERE submission_date BETWEEN ? AND ? AND form_type = 'Admission' AND gender IS NOT NULL AND gender != ''
    GROUP BY gender
");
$gender_stmt->execute([$sql_start, $sql_end]);
$gender_data = $gender_stmt->fetchAll(PDO::FETCH_ASSOC);

$gender_labels = [];
$gender_values = [];
foreach($gender_data as $gd) {
    $gender_labels[] = $gd['gender'];
    $gender_values[] = (int)$gd['count'];
}

// Fetch class breakdown for a bonus pie chart
$class_stmt = $pdo->prepare("
    SELECT applied_class, COUNT(*) as count
    FROM admission_inquiries
    WHERE submission_date BETWEEN ? AND ? AND form_type = 'Admission'
    GROUP BY applied_class
");
$class_stmt->execute([$sql_start, $sql_end]);
$class_data = $class_stmt->fetchAll(PDO::FETCH_ASSOC);

$class_labels = [];
$class_values = [];
foreach($class_data as $cd) {
    $class_labels[] = $cd['applied_class'] ?: 'Unknown';
    $class_values[] = (int)$cd['count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; bg-[#f8fafc]; }</style>
</head>
<body class="bg-slate-50 text-slate-800">
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="max-w-7xl mx-auto pb-10">
        
        <!-- Header & Filters -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 mb-8 mt-4">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 flex items-center gap-3">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Analytics Engine
                </h1>
                <p class="text-sm font-medium text-slate-500 mt-2">Analyze financial and demographic metrics across your application cycles.</p>
            </div>
            
            <form method="GET" class="bg-white p-2 flex flex-col sm:flex-row items-center gap-2 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center gap-2 px-3">
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest shrink-0">Date Range:</div>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-amber-500 font-semibold cursor-pointer text-slate-700">
                    <span class="text-slate-400 font-bold">-</span>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-amber-500 font-semibold cursor-pointer text-slate-700">
                </div>
                <button type="submit" class="w-full sm:w-auto bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm px-6 py-2.5 rounded-xl transition shadow-md">Apply</button>
            </form>
        </div>

        <div class="mb-6 flex justify-end">
            <a href="reports.php?export=csv&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="inline-flex items-center gap-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 font-bold text-sm px-5 py-2.5 rounded-xl transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Export CSV Report
            </a>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-150 transition duration-500 ease-in-out z-0"></div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1">Total Applications</p>
                    <h3 class="text-4xl font-black text-slate-900"><?php echo number_format((int)$kpis['total_applicants']); ?></h3>
                </div>
            </div>
            
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-teal-50 rounded-full group-hover:scale-150 transition duration-500 ease-in-out z-0"></div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-teal-600 uppercase tracking-widest mb-1">Total Fee Paid</p>
                    <h3 class="text-4xl font-black text-teal-900"><?php echo number_format((int)$kpis['total_paid']); ?></h3>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-slate-900 to-slate-800 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                <div class="relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Gross Revenue</p>
                    <h3 class="text-4xl font-black text-emerald-400">Rs. <?php echo number_format((float)$kpis['total_revenue']); ?></h3>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-24 h-24 bg-indigo-50 rounded-full group-hover:scale-150 transition duration-500 ease-in-out z-0"></div>
                <div class="relative z-10">
                    <p class="text-xs font-bold text-indigo-600 uppercase tracking-widest mb-1">Total Admitted</p>
                    <h3 class="text-4xl font-black text-indigo-900"><?php echo number_format((int)$kpis['total_admitted']); ?></h3>
                </div>
            </div>
        </div>

        <!-- Charts Area -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Revenue Line Chart -->
            <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-slate-900">Revenue Generation Trend</h2>
                    <span class="bg-emerald-50 text-emerald-600 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">Daily</span>
                </div>
                <div class="relative h-72 w-full">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Enrollment Funnel -->
            <div class="lg:col-span-1 bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-900 mb-6">Enrollment Conversion Funnel</h2>
                <div class="relative h-72 w-full flex items-center justify-center">
                    <canvas id="funnelChart"></canvas>
                </div>
            </div>
            
            <!-- Gender Demographics -->
            <div class="lg:col-span-1 bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-900 mb-6">Gender Breakdown</h2>
                <div class="relative h-64 w-full flex items-center justify-center">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>

            <!-- Class Breakdown -->
            <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <h2 class="text-lg font-bold text-slate-900 mb-6">Applications by Class</h2>
                <div class="relative h-64 w-full flex items-center justify-center">
                    <canvas id="classChart"></canvas>
                </div>
            </div>

        </div>

    </div>
    </div></main></div> <!-- Closing tags for sidebar -->

<script>
// Chart default configurations
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b'; // slate-500

// 1. Revenue Chart
const revCtx = document.getElementById('revenueChart').getContext('2d');

// Create a gradient for the line chart fill
const gradient = revCtx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)'); // emerald-500
gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

new Chart(revCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates_json); ?>,
        datasets: [{
            label: 'Revenue (Rs.)',
            data: <?php echo json_encode($revenues_json); ?>,
            borderColor: '#10b981', // emerald-500
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#10b981',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: '#f1f5f9' }, border: { display: false }, beginAtZero: true }
        }
    }
});

// 2. Enrollment Funnel Chart (using Bar chart to simulate funnel)
const funnelCtx = document.getElementById('funnelChart').getContext('2d');
new Chart(funnelCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($funnel_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($funnel_values); ?>,
            backgroundColor: [
                '#3b82f6', // blue-500
                '#8b5cf6', // violet-500
                '#f59e0b', // amber-500
                '#10b981'  // emerald-500
            ],
            borderRadius: 8,
            barThickness: 40
        }]
    },
    options: {
        indexAxis: 'y', // Horizontal bars look like a funnel
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { display: false, beginAtZero: true },
            y: { grid: { display: false }, border: { display: false } }
        }
    }
});

// 3. Gender Demographics Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
new Chart(genderCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($gender_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($gender_values); ?>,
            backgroundColor: ['#6366f1', '#ec4899', '#f43f5e', '#14b8a6'], // indigo, pink, rose, teal
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%',
        plugins: {
            legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 20 } }
        }
    }
});

// 4. Class Breakdown Chart
const classCtx = document.getElementById('classChart').getContext('2d');
new Chart(classCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($class_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($class_values); ?>,
            backgroundColor: '#0f172a', // slate-900
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { grid: { color: '#f1f5f9' }, border: { display: false }, beginAtZero: true }
        }
    }
});
</script>
</body>
</html>
