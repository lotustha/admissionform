<?php
// admission_success.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM admission_inquiries WHERE id = ? AND form_type = 'Admission'");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) { header("Location: index.php"); exit; }

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$roll_no = $student['entrance_roll_no'] ?: 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admission Success - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-teal-100 min-h-screen p-4 md:p-8 flex items-center justify-center">
    <div class="max-w-xl w-full mx-auto bg-white rounded-3xl shadow-2xl overflow-hidden border border-emerald-100 text-center relative p-8">
        
        <div class="w-20 h-20 mx-auto bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6 ring-4 ring-emerald-50 shadow-inner">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        
        <h2 class="text-3xl font-extrabold text-gray-800 mb-2">Application Submitted!</h2>
        <p class="text-gray-600 text-sm mb-8">Congratulations, <span class="font-bold text-gray-900"><?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?></span>. Your admission application has been received successfully.</p>
        
        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-6 mb-8 inline-block shadow-sm">
            <p class="text-xs text-teal-600 uppercase tracking-widest font-bold mb-1">Your Registration No.</p>
            <p class="text-3xl font-mono font-black text-emerald-800 tracking-widest drop-shadow-sm"><?php echo htmlspecialchars($roll_no); ?></p>
        </div>

        <p class="text-gray-500 text-sm mb-6 max-w-sm mx-auto leading-relaxed">Your application has been received! You can now access your very own <strong>Student Dashboard</strong> to track your status, pay any outstanding fees, upload missing documents, and edit your profile!</p>

        <div class="flex flex-col gap-4 justify-center mt-4">
            <a href="student_dashboard.php" class="px-8 py-4 rounded-xl bg-emerald-600 text-white font-black hover:bg-emerald-700 transition shadow-xl shadow-emerald-600/30 flex items-center justify-center gap-3 text-lg group w-full sm:w-auto mx-auto border-2 border-emerald-500">
                Go to My Dashboard
                <svg class="w-6 h-6 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
            </a>
            <a href="index.php" class="text-sm text-gray-400 font-semibold hover:text-gray-600 transition mt-2">
                Return to Home Page
            </a>
        </div>
    </div>
</body>
</html>
