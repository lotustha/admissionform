<?php
// print_result_list.php — Printable Exam Results List
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$school_address = $settings['address'] ?? '';
$logo = $settings['logo_path'] ?? '';
$pass_pct = (float)($settings['result_pass_percentage'] ?? 40);

// Filters
$filter_class = $_GET['class'] ?? '';
$filter_status = $_GET['result_filter'] ?? 'all'; // Default to all for print if not specified
$hide_marks = isset($_GET['hide_marks']) && $_GET['hide_marks'] === '1';

// Build query
$where = "i.form_type = 'Admission' AND i.payment_status = 'Paid'";
$params = [];

if ($filter_class) {
    $where .= " AND i.applied_class = ?";
    $params[] = $filter_class;
}
if ($filter_status === 'unpublished') {
    $where .= " AND (i.result_status = 'Pending' OR i.result_status IS NULL)";
} elseif ($filter_status === 'published') {
    $where .= " AND i.result_status != 'Pending' AND i.result_published_at IS NOT NULL";
}

$sql = "SELECT i.*, f.faculty_name, e.exam_date 
        FROM admission_inquiries i 
        LEFT JOIN faculties f ON i.faculty_id = f.id 
        LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
        WHERE {$where} 
        ORDER BY i.applied_class, i.entrance_roll_no";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Date and filters text
$filter_text = [];
if ($filter_class) $filter_text[] = "Class: " . $filter_class;
if ($filter_status !== 'all') $filter_text[] = "Status: " . ucfirst($filter_status);
$filter_str = !empty($filter_text) ? implode(" | ", $filter_text) : "All Data";

$unique_dates = [];
foreach ($students as $s) {
    if (!empty($s['exam_date'])) {
        $fmt = date('M d, Y', strtotime($s['exam_date']));
        if (!in_array($fmt, $unique_dates)) $unique_dates[] = $fmt;
    }
}
$exam_dt_str = empty($unique_dates) ? 'N/A' : implode(" & ", $unique_dates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Results List - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        @media print {
            body { background-color: #ffffff; margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .action-bar { display: none !important; }
            .print-container { max-width: 100% !important; margin: 0 !important; box-shadow: none !important; padding: 0 !important; border: none !important; }
            @page { margin: 15mm; size: A4 portrait; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
        }
    </style>
</head>
<body class="py-8 px-4">

<!-- Action Bar -->
<div class="action-bar max-w-5xl mx-auto mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex items-center justify-between">
    <a href="publish_results.php" class="text-sm font-bold text-gray-600 hover:text-indigo-600 transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Publisher
    </a>
    <button onclick="window.print()" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-2.5 rounded-lg shadow-sm transition text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        Print List
    </button>
</div>

<!-- Print Container -->
<div class="print-container max-w-5xl mx-auto bg-white rounded-xl shadow-lg border border-gray-200 p-8">
    
    <!-- Header -->
    <div class="text-center mb-8 border-b border-gray-200 pb-6">
        <?php if ($logo): ?>
            <img src="<?php echo htmlspecialchars($logo); ?>" class="h-16 mx-auto mb-3 object-contain" alt="Logo">
        <?php endif; ?>
        <h1 class="text-2xl font-black text-gray-900 leading-tight uppercase tracking-wider"><?php echo htmlspecialchars($school_name); ?></h1>
        <?php if ($school_address): ?><p class="text-sm text-gray-500 font-medium mt-1"><?php echo htmlspecialchars($school_address); ?></p><?php endif; ?>
        
        <h2 class="text-lg font-bold text-indigo-700 mt-4 uppercase tracking-widest border border-indigo-200 bg-indigo-50 inline-block px-4 py-1.5 rounded-full">Entrance Examination Results List</h2>
        <div class="text-sm font-bold text-indigo-900 mt-3">
            Entrance Date: <span class="font-black"><?php echo htmlspecialchars($exam_dt_str); ?></span>
        </div>
        <div class="text-sm text-gray-500 font-medium mt-1">
            Filtering: <span class="text-gray-900"><?php echo htmlspecialchars($filter_str); ?></span>
        </div>
    </div>

    <!-- Data Table -->
    <?php if (empty($students)): ?>
        <div class="text-center py-12 text-gray-400 font-medium">No records found for the selected criteria.</div>
    <?php else: ?>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-100 border border-gray-300">
                    <th class="border border-gray-300 px-3 py-2 text-center text-[10px] font-bold text-gray-600 uppercase w-12">S.N.</th>
                    <th class="border border-gray-300 px-3 py-2 text-left text-[10px] font-bold text-gray-600 uppercase">Roll No</th>
                    <th class="border border-gray-300 px-3 py-2 text-left text-[10px] font-bold text-gray-600 uppercase">Student Name</th>
                    <th class="border border-gray-300 px-3 py-2 text-left text-[10px] font-bold text-gray-600 uppercase">Class/Faculty</th>

                    <?php if (!$hide_marks): ?>
                    <th class="border border-gray-300 px-3 py-2 text-center text-[10px] font-bold text-gray-600 uppercase w-16">Marks</th>
                    <th class="border border-gray-300 px-3 py-2 text-center text-[10px] font-bold text-gray-600 uppercase w-16">%</th>
                    <?php endif; ?>
                    <th class="border border-gray-300 px-3 py-2 text-center text-[10px] font-bold text-gray-600 uppercase w-24">Status</th>
                    <th class="border border-gray-300 px-3 py-2 text-left text-[10px] font-bold text-gray-600 uppercase">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sn = 1;
                foreach ($students as $s): 
                    $row_marks = $s['marks_obtained'] ?? '';
                    $row_total = $s['total_marks'] ?? 100;
                    $row_status = $s['result_status'] ?? 'Pending';
                    $row_remarks = $s['result_remarks'] ?? '';
                    $row_pct = ($row_total > 0 && $row_marks !== '' && $row_marks !== null) ? round(((float)$row_marks / (float)$row_total) * 100, 1) : '';
                    $exam_date = $s['exam_date'] ? date('M d, Y', strtotime($s['exam_date'])) : '-';
                    
                    $is_published = !empty($s['result_published_at']) && $row_status !== 'Pending';
                    
                    $status_class = '';
                    if ($is_published) {
                        if ($row_status === 'Pass') $status_class = 'text-green-600 font-bold';
                        elseif ($row_status === 'Fail') $status_class = 'text-red-600 font-bold';
                        else $status_class = 'text-orange-600 font-bold';
                    } else {
                        $status_class = 'text-gray-400 italic';
                        $row_status = 'N/A';
                    }
                ?>
                <tr class="border-b border-gray-200">
                    <td class="border border-gray-300 px-3 py-2 text-center text-gray-500 text-xs"><?php echo $sn++; ?></td>
                    <td class="border border-gray-300 px-3 py-2 font-bold text-gray-900"><?php echo htmlspecialchars($s['entrance_roll_no'] ?? '-'); ?></td>
                    <td class="border border-gray-300 px-3 py-2 font-medium text-gray-800"><?php echo htmlspecialchars($s['student_first_name'] . ' ' . $s['student_last_name']); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-xs text-gray-600">
                        <?php echo htmlspecialchars($s['applied_class']); ?>
                        <?php if($s['faculty_name']): ?><br><span class="text-[10px] text-gray-400"><?php echo htmlspecialchars($s['faculty_name']); ?></span><?php endif; ?>
                    </td>

                    <?php if (!$hide_marks): ?>
                    <td class="border border-gray-300 px-3 py-2 text-center font-bold text-gray-800"><?php echo htmlspecialchars($row_marks); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-center font-bold text-gray-800"><?php echo $row_pct !== '' ? $row_pct : '-'; ?></td>
                    <?php endif; ?>
                    <td class="border border-gray-300 px-3 py-2 text-center <?php echo $status_class; ?>"><?php echo strtoupper($row_status); ?></td>
                    <td class="border border-gray-300 px-3 py-2 text-xs text-gray-600"><?php echo htmlspecialchars($row_remarks); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Signature Area -->
    <div class="mt-16 flex justify-between">
        <div class="text-center w-48">
            <div class="border-t border-gray-400 pt-2 text-[10px] font-bold text-gray-600 uppercase tracking-widest">Prepared By</div>
        </div>
        <div class="text-center w-48">
            <div class="border-t border-gray-400 pt-2 text-[10px] font-bold text-gray-600 uppercase tracking-widest">Authorized Signatory</div>
        </div>
    </div>
    
    <div class="mt-8 text-center text-[10px] text-gray-400 border-t border-gray-100 pt-4">
        Generated on <?php echo date('Y-m-d H:i:s'); ?>
    </div>

</div>

</body>
</html>
