<?php
// admit_card.php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$logo = $settings['logo_path'] ?? '';

$id = $_GET['id'] ?? '';
$roll = $_GET['roll'] ?? '';
$dob = $_GET['dob'] ?? '';

if (empty($id) && (empty($roll) || empty($dob))) {
    die("Invalid access. Application ID or Roll number and DOB are required.");
}

if (!empty($id)) {
    $stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                           FROM admission_inquiries i 
                           LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                           LEFT JOIN faculties f ON i.faculty_id = f.id
                           WHERE i.id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                           FROM admission_inquiries i 
                           LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                           LEFT JOIN faculties f ON i.faculty_id = f.id
                           WHERE i.entrance_roll_no = ? AND i.dob_bs = ?");
    $stmt->execute([$roll, $dob]);
}

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Application not found or invalid credentials provided.");
}

// Redirect if pending/rejected
if ($student['status'] === 'Pending' || $student['status'] === 'Rejected') {
    header("Location: status_check.php");
    exit;
}

if (!$student['exam_date']) {
    die("No entrance exam scheduled for this application.");
}

$photo_src = !empty($student['pp_photo_path']) ? $student['pp_photo_path'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admit Card - <?php echo htmlspecialchars($student['student_first_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
        @media print {
            body { background-color: white !important; margin: 0; padding: 0 !important; display: block; align-items: flex-start; justify-content: flex-start; }
            @page { margin: 0; size: A4 portrait; }
            .no-print { display: none !important; }
            .admit-card-container {
                box-shadow: none !important;
                margin: 0.5cm auto !important;
                width: 100% !important;
                max-width: 19cm !important;
                border: 2px solid #000 !important;
                border-radius: 0 !important;
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            .print-exact { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
        .bg-pattern {
            background-image: radial-gradient(#10b981 1px, transparent 1px);
            background-size: 20px 20px;
            opacity: 0.05;
        }
    </style>
</head>
<body class="py-10 px-4 min-h-screen flex flex-col items-center">

    <!-- Action Buttons -->
    <div class="no-print flex flex-col sm:flex-row gap-4 mb-8 w-full max-w-4xl justify-center">
        <button onclick="window.print()" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-bold font-outfit shadow-lg shadow-emerald-500/30 transition-all active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Admit Card
        </button>
        <button onclick="downloadPDF()" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl font-bold font-outfit shadow-lg transition-all active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Save as PDF
        </button>
    </div>

    <!-- Main Admit Card -->
    <div id="admit-card-element" class="admit-card-container w-full max-w-[800px] bg-white rounded-2xl shadow-2xl overflow-hidden relative border border-gray-200">
        <!-- Background Pattern -->
        <div class="absolute inset-0 bg-pattern pointer-events-none z-0"></div>
        
        <div class="relative z-10">
            <!-- Header -->
            <div class="bg-emerald-700 text-white p-6 print-exact flex justify-between items-center border-b-[6px] border-emerald-500">
                <div class="w-20">
                    <?php if ($logo): ?>
                        <img src="<?php echo htmlspecialchars($logo); ?>" alt="Logo" class="max-h-16 rounded bg-white p-1 shadow-sm object-contain">
                    <?php endif; ?>
                </div>
                <div class="text-center flex-1 px-4 text-white">
                    <h1 class="text-2xl font-outfit font-extrabold uppercase tracking-widest leading-tight"><?php echo htmlspecialchars($school_name); ?></h1>
                    <p class="text-sm font-medium tracking-wide opacity-90 mt-1 uppercase text-emerald-100">Entrance Examination &middot; <?php echo date('Y'); ?></p>
                </div>
                <div class="w-20 text-right">
                    <p class="text-[10px] uppercase font-bold text-emerald-200">Ref ID</p>
                    <p class="text-sm font-bold font-mono">#<?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>

            <div class="bg-gray-50 py-3 text-center print-exact border-b border-gray-200">
                <h2 class="text-xl font-outfit font-black text-gray-800 tracking-[0.2em] uppercase">Admit Card</h2>
            </div>

            <!-- Body -->
            <div class="p-8 flex flex-col md:flex-row gap-8 items-start">
                
                <!-- Left: Photo & Sign -->
                <div class="w-full md:w-32 flex flex-col items-center flex-shrink-0 gap-4">
                    <div class="w-28 h-32 border-2 border-dashed border-gray-300 rounded overflow-hidden bg-gray-50 flex items-center justify-center p-1">
                        <?php if ($photo_src): ?>
                            <img src="<?php echo htmlspecialchars($photo_src); ?>" alt="Photo" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-xs text-gray-400 font-medium">Passport Size<br>Photo</span>
                        <?php endif; ?>
                    </div>
                    <div class="w-28 h-12 border border-gray-300 rounded flex items-end justify-center pb-1 relative bg-white">
                        <span class="absolute inset-0 flex-col items-center justify-center flex text-gray-100 opacity-20 pointer-events-none overflow-hidden"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg></span>
                        <span class="text-[9px] text-gray-400 uppercase font-bold uppercase relative z-10">Candidate Signature</span>
                    </div>
                </div>

                <!-- Right: Details -->
                <div class="flex-1 w-full grid grid-cols-1 sm:grid-cols-2 gap-y-5 gap-x-6">
                    <div class="col-span-1 sm:col-span-2">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Applicant Name</p>
                        <p class="text-lg font-bold text-gray-900 border-b border-gray-200 pb-1 mb-1"><?php echo htmlspecialchars(strtoupper($student['student_first_name'] . ' ' . $student['student_last_name'])); ?></p>
                    </div>

                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Roll Number</p>
                        <p class="text-2xl font-mono font-black text-emerald-700 bg-emerald-50 rounded px-2 py-1 inline-block -ml-2 border border-emerald-100"><?php echo htmlspecialchars($student['entrance_roll_no']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Applied For</p>
                        <p class="text-base font-bold text-gray-800 pt-1"><?php echo htmlspecialchars($student['applied_class'] . ($student['faculty_name'] ? ' - ' . $student['faculty_name'] : '')); ?></p>
                    </div>

                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Date of Birth (BS)</p>
                        <p class="text-sm font-semibold text-gray-800 border-b border-gray-200 pb-1"><?php echo htmlspecialchars($student['dob_bs']); ?></p>
                    </div>

                    <div>
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Gender</p>
                        <p class="text-sm font-semibold text-gray-800 border-b border-gray-200 pb-1"><?php echo htmlspecialchars($student['gender']); ?></p>
                    </div>

                    <div class="col-span-1 sm:col-span-2">
                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider mb-1">Father's Name & Contact</p>
                        <p class="text-sm font-medium text-gray-800 border-b border-gray-200 pb-1"><?php echo htmlspecialchars($student['father_name']); ?> &bull; <?php echo htmlspecialchars($student['father_contact']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Exam Details Banner -->
            <div class="mx-8 mb-6 bg-emerald-50 print-exact rounded-xl border border-emerald-200 flex flex-col sm:flex-row shadow-sm divide-y sm:divide-y-0 sm:divide-x divide-emerald-200">
                <div class="p-4 flex-1 text-center">
                    <p class="text-xs font-bold text-emerald-800 uppercase tracking-widest mb-1">Date</p>
                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars(date('d M Y (l)', strtotime($student['exam_date']))); ?></p>
                </div>
                <div class="p-4 flex-1 text-center">
                    <p class="text-xs font-bold text-emerald-800 uppercase tracking-widest mb-1">Time</p>
                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars(date('h:i A', strtotime($student['exam_time']))); ?></p>
                </div>
                <div class="p-4 flex-1 sm:flex-[1.5] text-center">
                    <p class="text-xs font-bold text-emerald-800 uppercase tracking-widest mb-1">Venue</p>
                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($student['venue']); ?></p>
                </div>
            </div>

            <!-- Rules -->
            <div class="mx-8 mb-8 pt-6 border-t border-gray-200">
                <h4 class="font-outfit font-bold text-gray-800 mb-2 uppercase text-sm">Important Instructions</h4>
                <ul class="text-[11px] text-gray-600 list-disc pl-4 space-y-1 font-medium leading-relaxed">
                    <li>This Admit Card is mandatory. Bring it along with a valid Photo ID to the examination center.</li>
                    <li>Candidates must report to the examination center at least 30 minutes before commencement.</li>
                    <li>Electronic devices (mobile phones, smartwatches, calculators) are strictly prohibited inside the hall.</li>
                    <li>Use only a Black or Blue pen. Pencils are not allowed on the standard answer sheets.</li>
                    <li>Candidates arriving 15 minutes after the exam begins will not be permitted to enter.</li>
                </ul>
            </div>

            <!-- Signatures -->
            <div class="mx-8 mb-8 pt-4 flex justify-between items-end">
                <div>
                    <p class="text-xs font-mono text-gray-400">Generated: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                <div class="text-center">
                    <div class="w-48 border-b-2 border-gray-800 mb-2"></div>
                    <p class="text-xs font-bold tracking-wide uppercase text-gray-800">Authorized Signatory</p>
                </div>
            </div>

        </div>
    </div>

<!-- HTML2PDF Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    function downloadPDF() {
        const element = document.getElementById('admit-card-element');
        const opt = {
            margin:       0.2,
            filename:     'Admit_Card_<?php echo htmlspecialchars($student['entrance_roll_no']); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
</body>
</html>
