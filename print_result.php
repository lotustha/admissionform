<?php
// print_result.php — Printable Entrance Exam Result Card
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

// Students can only view their own result
if ($is_student && !$is_admin && $id !== (int)$_SESSION['student_id']) {
    die("Unauthorized access. You can only view your own result.");
}

// Fetch student data
$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                       FROM admission_inquiries i 
                       LEFT JOIN faculties f ON i.faculty_id = f.id 
                       LEFT JOIN entrance_schedules e ON i.schedule_id = e.id 
                       WHERE i.id = ? AND i.form_type = 'Admission'");
$stmt->execute([$id]);
$inq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inq) {
    die("Application not found.");
}

if (($inq['payment_status'] ?? 'Pending') !== 'Paid') {
    die("Result not available. Payment has not been recorded yet.");
}

$rs_status = $inq['result_status'] ?? 'Pending';
$rs_published = !empty($inq['result_published_at']);

if (!$rs_published || $rs_status === 'Pending') {
    die("Result has not been published yet.");
}

$rs_marks = $inq['marks_obtained'] ?? 0;
$rs_total = (float)($inq['total_marks'] ?? 100);
$rs_percentage = ($rs_total > 0 && $rs_marks !== null) ? round(((float)$rs_marks / $rs_total) * 100, 1) : 0;
$rs_remarks = $inq['result_remarks'] ?? '';

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$school_address = $settings['address'] ?? '';
$school_phone = $settings['contact_phone'] ?? '';
$school_email = $settings['org_email'] ?? '';
$logo = $settings['logo_path'] ?? '';

// Determine badge based on status
$status_label = $rs_status;
$color_scheme = [];
if ($rs_status === 'Pass') {
    $status_label = 'PASSED';
    $color_scheme = ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-700', 'badge_bg' => 'bg-emerald-600', 'marks_text' => 'text-emerald-600', 'icon' => '🎉'];
} elseif ($rs_status === 'Fail') {
    $status_label = 'NOT SELECTED';
    $color_scheme = ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'text' => 'text-red-700', 'badge_bg' => 'bg-red-500', 'marks_text' => 'text-red-600', 'icon' => '📋'];
} else {
    $status_label = 'WAITLISTED';
    $color_scheme = ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'badge_bg' => 'bg-amber-500', 'marks_text' => 'text-amber-600', 'icon' => '⏳'];
}

// Back URL logic
$back_url = "dashboard.php";
if ($is_admin) {
    $back_url = "view_application.php?id=" . $id;
} else if ($is_student) {
    $back_url = "student_dashboard.php?tab=result";
}
if (isset($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    if (strpos($ref, 'view_application.php') !== false || strpos($ref, 'student_dashboard.php') !== false) {
        $back_url = $ref;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - <?php echo htmlspecialchars($inq['entrance_roll_no']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        @media print {
            body { background-color: #ffffff; margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .action-bar { display: none !important; }
            .print-container { max-width: 100% !important; margin: 0 !important; box-shadow: none !important; padding: 0 !important; border: none !important; }
            @page { margin: 10mm; size: A4 portrait; }
        }
    </style>
</head>
<body class="py-8 px-4">

<!-- Action Bar -->
<div class="action-bar max-w-3xl mx-auto mb-6 bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-col sm:flex-row items-center justify-between gap-4">
    <a href="<?php echo htmlspecialchars($back_url); ?>" class="flex items-center gap-2 text-sm font-bold text-gray-600 hover:text-indigo-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Go Back
    </a>
    <div class="flex items-center gap-3">
        <button onclick="window.print()" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-5 py-2.5 rounded-lg transition text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print
        </button>
        <button onclick="downloadPDF()" class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-5 py-2.5 rounded-lg shadow-sm transition text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Download PDF
        </button>
    </div>
</div>

<!-- Print Container start -->
<div id="result-element" class="print-container max-w-3xl mx-auto bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden relative">
    
    <!-- Header -->
    <div class="p-8 border-b border-gray-100 bg-slate-50 flex items-center justify-between">
        <div class="flex items-center gap-5">
            <?php if ($logo): ?>
                <img src="<?php echo htmlspecialchars($logo); ?>" class="w-20 h-20 object-contain rounded-xl border-2 border-white shadow-sm bg-white p-1" onerror="this.style.display='none'">
            <?php else: ?>
                <div class="w-20 h-20 bg-indigo-100 rounded-xl flex items-center justify-center text-indigo-500 shadow-sm border-2 border-white">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-tight"><?php echo htmlspecialchars($school_name); ?></h1>
                <?php if ($school_address): ?><p class="text-sm text-slate-500 font-medium mt-1 uppercase tracking-wide"><?php echo htmlspecialchars($school_address); ?></p><?php endif; ?>
                <?php if ($school_phone || $school_email): ?>
                <p class="text-xs text-slate-400 mt-1">
                    <?php echo htmlspecialchars($school_phone); ?> <?php echo ($school_phone && $school_email) ? '•' : ''; ?> <?php echo htmlspecialchars($school_email); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <h2 class="text-sm font-black text-slate-400 uppercase tracking-widest">Entrance Result</h2>
            <div class="mt-2 text-xl font-black text-slate-800 underline decoration-slate-300 decoration-4 underline-offset-4">#<?php echo htmlspecialchars($inq['entrance_roll_no']); ?></div>
        </div>
    </div>

    <!-- Title Bar -->
    <div class="bg-indigo-900 text-indigo-50 px-8 py-3 flex items-center justify-between">
        <span class="font-bold tracking-widest text-xs uppercase">Student Result Card</span>
        <span class="font-medium text-xs">Published: <?php echo date('d M Y, h:i A', strtotime($inq['result_published_at'])); ?></span>
    </div>

    <!-- Body -->
    <div class="p-8">
        
        <!-- Student details -->
        <div class="grid grid-cols-2 gap-6 mb-8 bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
            <div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Student Name</div>
                <div class="font-black text-slate-800 text-lg"><?php echo htmlspecialchars($inq['student_first_name'] . ' ' . $inq['student_last_name']); ?></div>
            </div>
            <div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Applied For</div>
                <div class="font-bold text-slate-700 text-base">
                    <?php echo htmlspecialchars($inq['applied_class']); ?>
                    <?php if(!empty($inq['faculty_name'])) echo ' — ' . htmlspecialchars($inq['faculty_name']); ?>
                </div>
            </div>
        </div>

        <!-- Result Box -->
        <div class="<?php echo $color_scheme['bg']; ?> <?php echo $color_scheme['border']; ?> border-2 rounded-2xl p-8 text-center relative overflow-hidden mb-8">
            <div class="inline-block <?php echo $color_scheme['badge_bg']; ?> text-white font-black text-sm px-6 py-2 rounded-full uppercase tracking-widest mb-6 shadow-sm">
                <?php echo $status_label; ?>
            </div>
            
            <div class="flex items-center justify-center gap-3">
                <span class="<?php echo $color_scheme['marks_text']; ?> text-6xl font-black leading-none"><?php echo $rs_marks; ?></span>
                <span class="text-3xl text-gray-400 font-bold leading-none align-bottom">/ <?php echo $rs_total; ?></span>
            </div>
            
            <div class="mt-4 <?php echo $color_scheme['text']; ?> font-bold text-xl tracking-tight">
                Percentage: <?php echo $rs_percentage; ?>%
            </div>
        </div>

        <!-- Remarks -->
        <?php if (!empty($rs_remarks)): ?>
        <div class="bg-slate-50 border border-slate-200 border-dashed rounded-xl p-5 mb-8">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                Remarks
            </div>
            <p class="text-slate-700 font-medium text-sm leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($rs_remarks); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($rs_status === 'Pass'): ?>
        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded-r-xl">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                    <h3 class="text-sm font-bold text-indigo-900 mb-1">Next Steps for Enrollment</h3>
                    <p class="text-sm text-indigo-700 leading-relaxed font-medium">Please visit the admission office with all your original academic certificates and this result card to complete your enrollment procedure.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="mt-8 mx-8 mb-8">
        <div class="flex justify-between items-end border-t border-slate-200 pt-8">
            <div class="flex flex-col gap-4">
                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Generated</div>
                <div class="text-xs text-slate-600 font-medium"><?php echo date('d M Y, h:i A'); ?></div>
            </div>
            <div class="text-center">
                <div class="w-48 border-t-2 border-slate-300 pt-2 text-xs font-bold text-slate-500 uppercase tracking-widest mt-12">Authorized Signature</div>
            </div>
        </div>
    </div>
</div>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadPDF() {
        const element = document.getElementById('result-element');
        const opt = {
            margin:       0,
            filename:     'ExamResult_<?php echo htmlspecialchars($inq['entrance_roll_no']); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        document.querySelector('.action-bar').style.display = 'none';
        html2pdf().set(opt).from(element).save().then(() => {
            document.querySelector('.action-bar').style.display = '';
        });
    }
</script>

</body>
</html>
