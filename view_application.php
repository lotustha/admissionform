<?php
// view_application.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Invalid Request.");
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id LEFT JOIN entrance_schedules e ON i.schedule_id = e.id WHERE i.id = ?");
$stmt->execute([$id]);
$inq = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inq) {
    die("Application not found.");
}

$settings = getSchoolSettings($pdo);
$application_fee = (float)($settings['application_fee'] ?? 500);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid_inline'])) {
    $pay_method = trim($_POST['payment_method'] ?? 'Cash');
    $pay_ref    = trim($_POST['payment_reference'] ?? '');

    $upd = $pdo->prepare("UPDATE admission_inquiries SET 
                            payment_status = 'Paid', 
                            payment_amount = ?, 
                            payment_method = ?, 
                            payment_reference = ?, 
                            payment_date = NOW(),
                            status = IF(status = 'Pending', 'Approved', status)
                           WHERE id = ?");
    $upd->execute([$application_fee, $pay_method, !empty($pay_ref) ? $pay_ref : null, $id]);
    
    // Send Admit Card + Receipt email to student & institute
    sendPaymentConfirmationEmail($pdo, $id);
    
    header("Location: view_application.php?id=" . $id . "&msg=payment_updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revert_payment'])) {
    $pdo->prepare("UPDATE admission_inquiries SET payment_status = 'Pending', payment_amount = NULL, payment_method = NULL, payment_reference = NULL, payment_date = NULL WHERE id = ?")->execute([$id]);
    header("Location: view_application.php?id=" . $id . "&msg=payment_updated");
    exit;
}

// Handle result publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_result'])) {
    $marks = (float)($_POST['marks_obtained'] ?? 0);
    $total = (float)($_POST['total_marks'] ?? 100);
    $r_status = $_POST['result_status'] ?? 'Pending';
    $r_remarks = trim($_POST['result_remarks'] ?? '');
    $send_email = isset($_POST['send_email']);

    $upd = $pdo->prepare("UPDATE admission_inquiries SET marks_obtained=?, total_marks=?, result_status=?, result_remarks=?, result_published_at=NOW(), result_published_by=? WHERE id=?");
    $upd->execute([$marks, $total, $r_status, $r_remarks ?: null, $_SESSION['admin_id'], $id]);

    if ($send_email && $r_status !== 'Pending') {
        sendResultEmail($pdo, $id);
    }

    header("Location: view_application.php?id=" . $id . "&msg=result_published");
    exit;
}

// Handle result unpublish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unpublish_result'])) {
    $pdo->prepare("UPDATE admission_inquiries SET marks_obtained=NULL, total_marks=100, result_status='Pending', result_remarks=NULL, result_published_at=NULL, result_published_by=NULL WHERE id=?")->execute([$id]);
    header("Location: view_application.php?id=" . $id . "&msg=result_unpublished");
    exit;
}

// Status badge styles
$statusStyles = [
    'Pending'  => 'bg-amber-100 text-amber-800 border-amber-200',
    'Approved' => 'bg-blue-100 text-blue-800 border-blue-200',
    'Admitted' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'Rejected' => 'bg-red-100 text-red-800 border-red-200',
    'Disabled' => 'bg-gray-200 text-gray-600 border-gray-300',
];
$payStyles = [
    'Pending'  => 'bg-amber-100 text-amber-800 border-amber-200',
    'Paid'     => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'Failed'   => 'bg-red-100 text-red-800 border-red-200',
];
$status = $inq['status'] ?? 'Pending';
$statusBadge = $statusStyles[$status] ?? $statusStyles['Pending'];
$payStatus = $inq['payment_status'] ?? 'Pending';
$payBadge = $payStyles[$payStatus] ?? $payStyles['Pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application #<?php echo htmlspecialchars($inq['entrance_roll_no'] ?? $inq['id']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); }
        @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
        .shimmer-bg { background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.4) 50%, rgba(255,255,255,0) 100%); background-size: 200% 100%; animation: shimmer 2s ease-in-out infinite; }
        @keyframes pulse-dot { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.5; } }
        .pulse-dot { animation: pulse-dot 2s ease-in-out infinite; }
        @keyframes checkmark-pop { 0% { transform: scale(0) rotate(-45deg); opacity: 0; } 60% { transform: scale(1.2) rotate(0deg); } 100% { transform: scale(1) rotate(0deg); opacity: 1; } }
        .checkmark-pop { animation: checkmark-pop 0.5s ease forwards; }
        .method-chip { transition: all 0.2s ease; }
        .method-chip:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.08); }
        .method-chip.active { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,0.2); }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="max-w-5xl mx-auto py-6 px-4 space-y-6">

        <!-- Success Toast -->
        <?php if (isset($_GET['msg']) && in_array($_GET['msg'], ['payment_updated','result_published','result_unpublished'])): ?>
        <?php
            $toast_msgs = ['payment_updated'=>'Payment details updated successfully.', 'result_published'=>'Result published successfully.', 'result_unpublished'=>'Result has been unpublished.'];
            $toast_text = $toast_msgs[$_GET['msg']] ?? 'Done.';
        ?>
        <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3 rounded-xl shadow-sm" id="toast_msg">
            <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="font-semibold text-sm"><?php echo $toast_text; ?></span>
            <button onclick="document.getElementById('toast_msg').remove()" class="ml-auto text-emerald-400 hover:text-emerald-600">&times;</button>
        </div>
        <script>setTimeout(() => document.getElementById('toast_msg')?.remove(), 4000)</script>
        <?php endif; ?>

        <!-- Header Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600 px-6 py-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-white tracking-tight">Application Details</h1>
                        <p class="text-emerald-200 text-sm mt-1 font-medium">
                            <?php if ($inq['form_type'] === 'Admission'): ?>
                                Roll No: <span class="text-white font-bold"><?php echo htmlspecialchars($inq['entrance_roll_no'] ?? 'N/A'); ?></span>
                            <?php else: ?>
                                Inquiry Request
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'); ?>" class="bg-white/15 hover:bg-white/25 text-white px-4 py-2 rounded-lg transition backdrop-blur-sm border border-white/20">← Back</a>
                        <?php if ($inq['form_type'] === 'Admission'): ?>
                            <?php if ($payStatus === 'Paid'): ?>
                                <a href="print_admit_card.php?id=<?php echo $inq['id']; ?>" target="_blank" class="bg-white text-emerald-800 hover:bg-gray-100 px-4 py-2 rounded-lg shadow-sm transition font-bold">Admit Card</a>
                            <?php else: ?>
                                <button disabled title="Fee payment required to unlock Admit Card" class="bg-white/50 text-emerald-800/40 px-4 py-2 rounded-lg shadow-sm cursor-not-allowed border border-emerald-100/50 font-bold">Admit Card 🔒</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="edit_application.php?id=<?php echo $inq['id']; ?>" class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            Edit
                        </a>
                        <a href="print_application.php?id=<?php echo $inq['id']; ?>" target="_blank" class="bg-emerald-800 hover:bg-emerald-900 text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Form
                        </a>
                        <?php if ($payStatus === 'Paid'): ?>
                        <a href="print_receipt.php?id=<?php echo $inq['id']; ?>" target="_blank" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Receipt
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Row -->
            <div class="p-6 sm:p-8">
                <div class="flex flex-col-reverse sm:flex-row justify-between items-start gap-6 mb-8">
                    <div class="flex-1">
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                            <?php echo htmlspecialchars($inq['student_first_name'] . ' ' . $inq['student_last_name']); ?>
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full border <?php echo $statusBadge; ?>">
                                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60"></span>
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full border <?php echo $payBadge; ?>">
                                <?php echo $payStatus === 'Paid' ? '✓' : '○'; ?> Fee: <?php echo $payStatus; ?>
                            </span>
                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-100">
                                <?php echo htmlspecialchars($inq['applied_class']); ?><?php echo $inq['faculty_name'] ? ' — ' . htmlspecialchars($inq['faculty_name']) : ''; ?>
                            </span>
                        </div>
                    </div>
                    <!-- Profile Photo -->
                    <div class="w-28 h-28 flex-shrink-0 rounded-2xl bg-gray-100 border-2 border-gray-200 overflow-hidden shadow-md">
                        <?php if (!empty($inq['pp_photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($inq['pp_photo_path']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full text-gray-400 text-xs font-medium">No Photo</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Grid: Contact + Payment -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-8">
                    <!-- Contacts -->
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-5">
                        <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            Contact Information
                        </h3>
                        <div class="space-y-3">
                            <?php if(!empty($inq['student_email'])): ?>
                            <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-100 shadow-sm">
                                <div class="w-9 h-9 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Email</p>
                                    <a href="mailto:<?php echo htmlspecialchars($inq['student_email']); ?>" class="text-sm font-bold text-blue-700 hover:underline truncate block"><?php echo htmlspecialchars($inq['student_email']); ?></a>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-100 shadow-sm">
                                <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Primary Contact (<?php echo htmlspecialchars($inq['father_contact'] ? 'Father' : 'Mother'); ?>)</p>
                                    <a href="tel:<?php echo htmlspecialchars($inq['father_contact'] ?: $inq['mother_contact']); ?>" class="text-sm font-bold text-emerald-700 hover:underline"><?php echo htmlspecialchars($inq['father_contact'] ?: $inq['mother_contact']); ?></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Management -->
                    <div class="relative overflow-hidden rounded-2xl border <?php echo $payStatus === 'Paid' ? 'border-emerald-200' : 'border-amber-200 shadow-[0_0_15px_rgba(245,158,11,0.2)]'; ?>">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 <?php echo $payStatus === 'Paid' ? 'bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500' : 'bg-gradient-to-br from-amber-500 via-orange-500 to-red-500'; ?>">
                            <div class="absolute inset-0 opacity-10" style="background-image:url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg fill=%22none%22 fill-rule=%22evenodd%22%3E%3Cg fill=%22%23ffffff%22 fill-opacity=%220.4%22%3E%3Cpath d=%22M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z%22/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')"></div>
                            
                            <!-- Shimmer Animation Base for Both States -->
                            <div class="absolute top-0 right-0 w-32 h-32 shimmer-bg rounded-full -translate-y-8 translate-x-8 opacity-75"></div>
                        </div>

                        <div class="relative z-10 p-5">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg <?php echo $payStatus === 'Paid' ? 'bg-white/20' : 'bg-white/10'; ?> flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    </div>
                                    <span class="text-[10px] font-bold text-white/70 uppercase tracking-widest">Admission Fee</span>
                                </div>
                                <?php if ($payStatus === 'Paid'): ?>
                                <div class="flex items-center gap-1.5 bg-white/20 backdrop-blur-sm px-3 py-1 rounded-full">
                                    <div class="w-2 h-2 rounded-full bg-emerald-200 checkmark-pop"></div>
                                    <span class="text-[10px] font-bold text-white uppercase tracking-wider">Collected</span>
                                </div>
                                <?php else: ?>
                                <div class="flex items-center gap-1.5 bg-amber-500/30 backdrop-blur-sm px-3 py-1 rounded-full">
                                    <div class="w-2 h-2 rounded-full bg-amber-300 pulse-dot"></div>
                                    <span class="text-[10px] font-bold text-amber-200 uppercase tracking-wider">Unpaid</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Amount Display -->
                            <div class="text-center mb-4">
                                <p class="text-white/50 text-[10px] font-bold uppercase tracking-wider mb-1">Amount</p>
                                <p class="text-3xl font-black text-white tracking-tight">Rs. <?php echo number_format($application_fee, 0); ?></p>
                                <?php if ($payStatus === 'Paid' && !empty($inq['payment_date'])): ?>
                                <p class="text-white/50 text-[11px] mt-1 font-medium">Paid on <?php echo date('d M Y, h:i A', strtotime($inq['payment_date'])); ?></p>
                                <?php endif; ?>
                            </div>

                            <?php if ($payStatus === 'Paid'): ?>
                            <!-- Paid State -->
                            <div class="bg-white/15 backdrop-blur-sm rounded-xl p-4 space-y-2.5">
                                <div class="flex justify-between text-sm">
                                    <span class="text-white/60 font-medium">Method</span>
                                    <span class="text-white font-bold"><?php echo htmlspecialchars($inq['payment_method'] ?? 'Cash'); ?></span>
                                </div>
                                <?php if (!empty($inq['payment_reference'])): ?>
                                <div class="flex justify-between text-sm">
                                    <span class="text-white/60 font-medium">Reference</span>
                                    <span class="text-white font-bold font-mono text-xs"><?php echo htmlspecialchars($inq['payment_reference']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="flex gap-2 mt-4">
                                <a href="print_receipt.php?id=<?php echo $inq['id']; ?>" target="_blank" class="flex-1 bg-white text-emerald-700 text-center font-bold py-2.5 px-4 rounded-xl shadow-sm hover:shadow-md transition-all text-sm flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    Print Receipt
                                </a>
                                <form method="POST" class="shrink-0">
                                    <input type="hidden" name="revert_payment" value="1">
                                    <button type="submit" onclick="return confirm('Revert payment to Unpaid? This will clear all payment data.')" class="bg-white/15 hover:bg-white/25 text-white/80 hover:text-white font-semibold py-2.5 px-3 rounded-xl transition-all text-xs border border-white/10" title="Undo Payment">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                    </button>
                                </form>
                            </div>

                            <?php else: ?>
                            <!-- Unpaid State — Collect Payment -->
                            <form method="POST" id="paymentForm">
                                <input type="hidden" name="mark_paid_inline" value="1">
                                
                                <!-- Payment Method Selection -->
                                <p class="text-white/50 text-[10px] font-bold uppercase tracking-wider mb-2">Select Payment Method</p>
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <?php 
                                    $methods = [
                                        'Cash' => '💵',
                                        'eSewa' => '📱',
                                        'Khalti' => '📲',
                                        'Bank' => '🏦',
                                        'Cheque' => '📝',
                                        'Other' => '📋'
                                    ];
                                    $first = true;
                                    foreach ($methods as $mKey => $mIcon): 
                                        $mVal = ($mKey === 'Bank') ? 'Bank Deposit' : $mKey;
                                    ?>
                                    <label class="method-chip cursor-pointer">
                                        <input type="radio" name="payment_method" value="<?php echo $mVal; ?>" class="hidden peer" <?php echo $first ? 'checked' : ''; ?>>
                                        <div class="bg-white/10 hover:bg-white/20 peer-checked:bg-white peer-checked:text-slate-800 text-white text-center py-2.5 rounded-xl transition-all border border-white/10 peer-checked:border-white peer-checked:shadow-lg">
                                            <span class="text-lg block mb-0.5"><?php echo $mIcon; ?></span>
                                            <span class="text-[10px] font-bold block"><?php echo $mKey; ?></span>
                                        </div>
                                    </label>
                                    <?php $first = false; endforeach; ?>
                                </div>

                                <!-- Reference Field -->
                                <div class="mb-4">
                                    <input type="text" name="payment_reference" placeholder="Reference No. (optional)" class="w-full bg-white/10 border border-white/15 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/40 focus:bg-white/20 focus:border-white/30 outline-none transition font-medium">
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" class="w-full bg-white text-slate-800 font-black py-3 px-4 rounded-xl shadow-lg hover:shadow-xl transition-all text-sm flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98]">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Confirm Payment — Rs. <?php echo number_format($application_fee, 0); ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 text-sm">
                    <!-- Personal -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            Personal Information
                        </h3>
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Gender</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['gender']); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">DOB (BS)</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['dob_bs']); ?></b></div>
                            <?php if($inq['dob_ad']): ?>
                            <div class="flex justify-between py-1.5"><span class="text-gray-500">DOB (AD)</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['dob_ad']); ?></b></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Address
                        </h3>
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Province</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['address_province']); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">District</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['address_district']); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Municipality</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['address_municipality']); ?></b></div>
                            <div class="flex justify-between py-1.5"><span class="text-gray-500">Ward/Village</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['address_ward_village']); ?></b></div>
                        </div>
                    </div>

                    <!-- Family -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Family Details
                        </h3>
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Father</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['father_name'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Father Contact</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['father_contact'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Mother</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['mother_name'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Mother Contact</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['mother_contact'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Local Guardian</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['local_guardian_name'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5"><span class="text-gray-500">Guardian Relation</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['guardian_relation'] ?? 'N/A'); ?></b></div>
                        </div>
                    </div>

                    <!-- Academic -->
                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                        <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            Academic Background
                        </h3>
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">Previous School</span><b class="text-gray-900 truncate max-w-[180px]" title="<?php echo htmlspecialchars($inq['previous_school_name'] ?? 'N/A'); ?>"><?php echo htmlspecialchars($inq['previous_school_name'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">GPA / %</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['gpa_or_percentage'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5 border-b border-dashed border-gray-100"><span class="text-gray-500">SEE Symbol</span><b class="text-gray-900"><?php echo htmlspecialchars($inq['see_symbol_no'] ?? 'N/A'); ?></b></div>
                            <div class="flex justify-between py-1.5"><span class="text-gray-500">Optional Subjects</span><b class="text-gray-900 truncate max-w-[180px]" title="<?php echo htmlspecialchars(trim(($inq['optional_subject_1'] ?? '') . ', ' . ($inq['optional_subject_2'] ?? ''), ', ')); ?>"><?php echo htmlspecialchars(trim(($inq['optional_subject_1'] ?? '') . ', ' . ($inq['optional_subject_2'] ?? ''), ', ')) ?: 'N/A'; ?></b></div>
                        </div>
                    </div>
                </div>

                <!-- Entrance Exam Info (if applicable) -->
                <?php if ($inq['form_type'] === 'Admission' && $inq['exam_date']): ?>
                <div class="mt-5 bg-blue-50 border border-blue-100 rounded-2xl p-5">
                    <h3 class="text-[11px] font-bold text-blue-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Entrance Examination Schedule
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-xl p-4 border border-blue-100 text-center shadow-sm">
                            <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Date</p>
                            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars(date('d M, Y (l)', strtotime($inq['exam_date']))); ?></p>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-blue-100 text-center shadow-sm">
                            <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Time</p>
                            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars(date('h:i A', strtotime($inq['exam_time']))); ?></p>
                        </div>
                        <div class="bg-white rounded-xl p-4 border border-blue-100 text-center shadow-sm">
                            <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Venue</p>
                            <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($inq['venue']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Entrance Exam Result Section -->
                <?php if ($inq['form_type'] === 'Admission' && $payStatus === 'Paid'): ?>
                <?php
                    $r_status = $inq['result_status'] ?? 'Pending';
                    $r_marks = $inq['marks_obtained'] ?? null;
                    $r_total = (float)($inq['total_marks'] ?? 100);
                    $r_published = !empty($inq['result_published_at']);
                    $r_percentage = ($r_total > 0 && $r_marks !== null) ? round(((float)$r_marks / $r_total) * 100, 1) : 0;
                    $r_remarks = $inq['result_remarks'] ?? '';
                    
                    $r_badge_styles = [
                        'Pass' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'Fail' => 'bg-red-100 text-red-800 border-red-200',
                        'Waitlisted' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'Pending' => 'bg-gray-100 text-gray-600 border-gray-200',
                    ];
                    $r_badge = $r_badge_styles[$r_status] ?? $r_badge_styles['Pending'];
                    $pass_pct = (float)($settings['result_pass_percentage'] ?? 40);
                ?>
                <div class="mt-5 bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
                    <div class="px-5 py-4 bg-gradient-to-r from-violet-600 to-indigo-600 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            <span class="text-[11px] font-bold text-white/80 uppercase tracking-widest">Entrance Exam Result</span>
                        </div>
                        <?php if ($r_published): ?>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full border <?php echo $r_badge; ?>"><?php echo htmlspecialchars($r_status); ?></span>
                        <?php else: ?>
                        <span class="text-[10px] font-bold text-white/60 uppercase">Not Published</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <?php if ($r_published): ?>
                        <!-- Published Result Display -->
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Marks</p>
                                <p class="text-2xl font-black text-gray-900"><?php echo htmlspecialchars($r_marks); ?> <span class="text-sm text-gray-400 font-bold">/ <?php echo htmlspecialchars($r_total); ?></span></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Percentage</p>
                                <p class="text-2xl font-black <?php echo $r_status === 'Pass' ? 'text-emerald-600' : ($r_status === 'Fail' ? 'text-red-600' : 'text-amber-600'); ?>"><?php echo $r_percentage; ?>%</p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-4 text-center border border-gray-100">
                                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Published</p>
                                <p class="text-sm font-bold text-gray-700"><?php echo date('d M Y', strtotime($inq['result_published_at'])); ?></p>
                            </div>
                        </div>
                        <?php if (!empty($r_remarks)): ?>
                        <div class="bg-slate-50 rounded-lg p-3 mb-4 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Remarks</p>
                            <p class="text-sm text-slate-700"><?php echo htmlspecialchars($r_remarks); ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="flex gap-2">
                            <button type="button" onclick="document.getElementById('editResultForm').classList.toggle('hidden')" class="text-xs font-bold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 px-4 py-2 rounded-lg transition">Edit Result</button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="unpublish_result" value="1">
                                <button type="submit" onclick="return confirm('Unpublish this result? Student will no longer see it.')" class="text-xs font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 px-4 py-2 rounded-lg transition">Unpublish</button>
                            </form>
                        </div>
                        <!-- Hidden edit form -->
                        <div id="editResultForm" class="hidden mt-4 pt-4 border-t border-gray-100">
                        <?php else: ?>
                        <!-- Publish Form -->
                        <div>
                        <?php endif; ?>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="publish_result" value="1">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Marks Obtained</label>
                                        <input type="number" step="0.01" name="marks_obtained" value="<?php echo htmlspecialchars($r_marks ?? ''); ?>" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none font-bold" placeholder="e.g. 72">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total Marks</label>
                                        <input type="number" step="0.01" name="total_marks" value="<?php echo htmlspecialchars($r_total); ?>" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none font-bold" placeholder="e.g. 100">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Result Status</label>
                                    <select name="result_status" required class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-400 outline-none font-bold bg-white">
                                        <?php foreach(['Pass','Fail','Waitlisted'] as $rs): ?>
                                        <option value="<?php echo $rs; ?>" <?php if($r_status === $rs) echo 'selected'; ?>><?php echo $rs; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Remarks <span class="text-gray-400 font-normal">(optional)</span></label>
                                    <textarea name="result_remarks" rows="2" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="e.g. Excellent performance"><?php echo htmlspecialchars($r_remarks); ?></textarea>
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" name="send_email" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="text-sm font-medium text-gray-600">Send result email to student</span>
                                    </label>
                                </div>
                                <button type="submit" class="w-full bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-bold py-3 px-4 rounded-xl shadow-md transition text-sm">
                                    <?php echo $r_published ? 'Update Result' : 'Publish Result'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Document Scans -->
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-[11px] font-bold text-gray-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Uploaded Documents
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- PP Photo -->
                        <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                            <?php if (!empty($inq['pp_photo_path'])): ?>
                                <a href="<?php echo htmlspecialchars($inq['pp_photo_path']); ?>" target="_blank" class="block hover:opacity-80 transition">
                                    <img src="<?php echo htmlspecialchars($inq['pp_photo_path']); ?>" class="w-20 h-20 object-cover rounded-lg mx-auto mb-2 border border-gray-200">
                                    <span class="text-xs font-bold text-emerald-700">PP Photo ✓</span>
                                </a>
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gray-100 rounded-lg mx-auto mb-2 flex items-center justify-center text-gray-300"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>
                                <span class="text-xs text-gray-400 font-medium">No Photo</span>
                            <?php endif; ?>
                        </div>
                        <!-- Marksheet -->
                        <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                            <?php if (!empty($inq['document_path'])): ?>
                                <a href="<?php echo htmlspecialchars($inq['document_path']); ?>" target="_blank" class="block hover:opacity-80 transition">
                                    <div class="w-20 h-20 bg-emerald-50 rounded-lg mx-auto mb-2 flex items-center justify-center text-emerald-500"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                                    <span class="text-xs font-bold text-emerald-700">Marksheet ✓</span>
                                </a>
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gray-100 rounded-lg mx-auto mb-2 flex items-center justify-center text-gray-300"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                                <span class="text-xs text-gray-400 font-medium">Not Uploaded</span>
                            <?php endif; ?>
                        </div>
                        <!-- Birth Certificate -->
                        <div class="bg-white border border-gray-100 rounded-xl p-4 text-center shadow-sm">
                            <?php if (!empty($inq['birth_cert_path'])): ?>
                                <a href="<?php echo htmlspecialchars($inq['birth_cert_path']); ?>" target="_blank" class="block hover:opacity-80 transition">
                                    <div class="w-20 h-20 bg-emerald-50 rounded-lg mx-auto mb-2 flex items-center justify-center text-emerald-500"><svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                                    <span class="text-xs font-bold text-emerald-700">Birth Cert ✓</span>
                                </a>
                            <?php else: ?>
                                <div class="w-20 h-20 bg-gray-100 rounded-lg mx-auto mb-2 flex items-center justify-center text-gray-300"><svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                                <span class="text-xs text-gray-400 font-medium">Not Uploaded</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-6 pt-4 border-t border-gray-100 text-center">
                    <p class="text-xs text-gray-400">Application submitted on <?php echo htmlspecialchars($inq['submission_date']); ?> • ID: #<?php echo str_pad($inq['id'], 5, '0', STR_PAD_LEFT); ?></p>
                </div>
            </div>
        </div>

    </div>
    </div>
    </main>
</div>
</body>
</html>
