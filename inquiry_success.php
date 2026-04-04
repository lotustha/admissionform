<?php
// inquiry_success.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM admission_inquiries WHERE id = ? AND form_type = 'Inquiry'");
$stmt->execute([$id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    header("Location: index.php");
    exit;
}

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$ref_id = str_pad($inquiry['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry Received - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-emerald-50 min-h-screen p-4 md:p-8 flex items-center justify-center">

<div class="max-w-xl w-full mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-emerald-100 text-center relative pointer-events-auto p-8">
    
    <div class="w-20 h-20 mx-auto bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-6 ring-4 ring-emerald-50">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
    </div>

    <h2 class="text-3xl font-bold text-gray-800 mb-2">Inquiry Received!</h2>
    <p class="text-gray-600 text-sm mb-6">Thank you, <span class="font-bold text-gray-900"><?php echo htmlspecialchars($inquiry['student_first_name'] . ' ' . $inquiry['student_last_name']); ?></span>. We have successfully received your quick inquiry.</p>
    
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-5 mb-8 inline-block shadow-sm">
        <p class="text-xs text-gray-500 uppercase tracking-widest font-semibold mb-1">Your Reference Number</p>
        <p class="text-2xl font-mono font-bold text-emerald-700 tracking-wider">#INQ-<?php echo $ref_id; ?></p>
    </div>

    <p class="text-gray-600 mb-8 max-w-sm mx-auto">Our admissions team will review your details and contact you shortly at <strong><?php echo htmlspecialchars($inquiry['father_contact']); ?></strong> or via email if provided.</p>

    <div class="flex gap-4 justify-center">
        <a href="index.php" class="px-6 py-3 rounded-lg bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-600/30 w-full sm:w-auto">
            Return to Homepage
        </a>
    </div>

</div>

<!-- Chat Widget -->
<?php if (file_exists(__DIR__ . '/includes/chat_widget.php')) include 'includes/chat_widget.php'; ?>

</body>
</html>
