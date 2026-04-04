<?php
// student_dashboard.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: status_check.php");
    exit;
}
$student_id = $_SESSION['student_id'];

// Block disabled accounts
try {
    $chk = $pdo->prepare("SELECT status FROM admission_inquiries WHERE id=?");
    $chk->execute([$student_id]);
    $chk_status = $chk->fetchColumn();
    if ($chk_status === 'Disabled') {
        session_destroy();
        header("Location: status_check.php?error=disabled");
        exit;
    }
} catch(Exception $e) {}

// Handle document uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_docs'])) {
    $upload_dir = 'uploads/documents/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $updates = [];
    $params = [];
    
    $files_to_handle = [
        'pp_photo' => 'pp_photo_path',
        'marksheet' => 'document_path',
        'birth_cert' => 'birth_cert_path'
    ];
    
    foreach ($files_to_handle as $input_name => $db_column) {
        if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$input_name]['name'], PATHINFO_EXTENSION);
            $filename = strtolower($input_name) . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$input_name]['tmp_name'], $path)) {
                $updates[] = "$db_column = ?";
                $params[] = $path;
            }
        }
    }
    
    if (!empty($updates)) {
        $params[] = $student_id;
        $sql = "UPDATE admission_inquiries SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
        header("Location: student_dashboard.php?tab=documents&msg=uploaded");
        exit;
    }
}

// Handle profile edits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    
    // Check if status is locked
    $stmt_check = $pdo->prepare("SELECT status FROM admission_inquiries WHERE id = ?");
    $stmt_check->execute([$student_id]);
    $current_status = $stmt_check->fetchColumn();
    
    if ($current_status !== 'Approved' && $current_status !== 'Admitted') {
        $upd_sql = "UPDATE admission_inquiries SET 
            student_first_name = ?, student_last_name = ?, student_email = ?, dob_bs = ?, dob_ad = ?, gender = ?,
            address_province = ?, address_district = ?, address_municipality = ?, address_ward_village = ?,
            father_name = ?, father_occupation = ?, father_contact = ?,
            mother_name = ?, mother_occupation = ?, mother_contact = ?,
            local_guardian_name = ?, guardian_contact = ?, guardian_relation = ?,
            previous_school_name = ?, previous_board = ?, gpa_or_percentage = ?, see_symbol_no = ?
            WHERE id = ?";
            
        $pd_params = [
            $_POST['student_first_name'], $_POST['student_last_name'], $_POST['student_email'], $_POST['dob_bs'], $_POST['dob_ad'], $_POST['gender'],
            $_POST['address_province'], $_POST['address_district'], $_POST['address_municipality'], $_POST['address_ward_village'],
            $_POST['father_name'], $_POST['father_occupation'], $_POST['father_contact'],
            $_POST['mother_name'], $_POST['mother_occupation'], $_POST['mother_contact'],
            $_POST['local_guardian_name'], $_POST['guardian_contact'], $_POST['guardian_relation'],
            $_POST['previous_school_name'], $_POST['previous_board'], $_POST['gpa_or_percentage'], $_POST['see_symbol_no'],
            $student_id
        ];
        
        $pdo->prepare($upd_sql)->execute($pd_params);
        header("Location: student_dashboard.php?tab=edit_profile&msg=profile_updated");
        exit;
    }
}

// Handle application deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("Invalid security token.");
    }
    
    $stmt_check = $pdo->prepare("SELECT status, pp_photo_path, document_path, birth_cert_path FROM admission_inquiries WHERE id = ?");
    $stmt_check->execute([$student_id]);
    $del_record = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($del_record && !in_array($del_record['status'], ['Approved', 'Admitted'])) {
        // Delete uploaded files
        $files_to_delete = ['pp_photo_path', 'document_path', 'birth_cert_path'];
        foreach ($files_to_delete as $file_col) {
            if (!empty($del_record[$file_col]) && file_exists(__DIR__ . '/' . $del_record[$file_col])) {
                @unlink(__DIR__ . '/' . $del_record[$file_col]);
            }
        }
        
        // Delete the record
        $del_stmt = $pdo->prepare("DELETE FROM admission_inquiries WHERE id = ?");
        $del_stmt->execute([$student_id]);
        
        // Destroy session and redirect
        session_destroy();
        header("Location: index.php?msg=deleted");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT i.*, f.faculty_name, e.exam_date, e.exam_time, e.venue 
                       FROM admission_inquiries i 
                       LEFT JOIN entrance_schedules e ON i.schedule_id = e.id
                       LEFT JOIN faculties f ON i.faculty_id = f.id
                       WHERE i.id = ?");
$stmt->execute([$student_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    unset($_SESSION['student_id']);
    header("Location: status_check.php");
    exit;
}

$settings = getSchoolSettings($pdo);
$school_name = $settings['school_name'] ?? 'School Admission Portal';
$application_fee = (float)($settings['application_fee'] ?? 500);
$allow_unpaid_admit = $settings['allow_unpaid_admit_card'] ?? '0';

$tab = $_GET['tab'] ?? 'overview';

// Status styling
$badgeClasses = [
    'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'Approved' => 'bg-blue-100 text-blue-800 border-blue-200',
    'Admitted' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'Rejected' => 'bg-red-100 text-red-800 border-red-200'
];
$status_badge = $badgeClasses[$result['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';

$paymentClasses = [
    'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'Paid' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
    'Failed' => 'bg-red-100 text-red-800 border-red-200'
];
$pay_status = $result['payment_status'] ?? 'Pending';
$pay_badge = $paymentClasses[$pay_status] ?? 'bg-yellow-100 text-yellow-800 border-yellow-200';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-emerald-50 min-h-screen">

<!-- Top Navigation -->
<nav class="bg-white border-b border-gray-200 sticky top-0 z-50 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center font-bold text-lg border border-emerald-200">
                    <?php echo strtoupper(substr($result['student_first_name'], 0, 1)); ?>
                </div>
                <div>
                    <h1 class="font-bold text-gray-900 leading-tight"><?php echo htmlspecialchars($result['student_first_name'] . ' ' . $result['student_last_name']); ?></h1>
                    <p class="text-xs text-gray-500 font-medium">Applicant Dashboard</p>
                </div>
            </div>
            <a href="student_logout.php" class="text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-md transition-colors flex items-center gap-1.5 border border-red-200 bg-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                Logout
            </a>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col md:flex-row gap-6">

    <!-- Messages -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'uploaded'): ?>
    <div class="fixed top-20 right-8 z-50 bg-emerald-100 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded shadow-lg flex justify-between items-center w-80" id="toast">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span class="font-bold text-sm">Documents Uploaded</span>
        </div>
        <button onclick="document.getElementById('toast').remove()" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
    </div>
    <script>setTimeout(() => document.getElementById('toast')?.remove(), 4000);</script>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'profile_updated'): ?>
    <div class="fixed top-20 right-8 z-50 bg-emerald-100 border-l-4 border-emerald-500 text-emerald-800 p-4 rounded shadow-lg flex justify-between items-center w-80" id="toast_prof">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <span class="font-bold text-sm">Profile Updated</span>
        </div>
        <button onclick="document.getElementById('toast_prof').remove()" class="text-emerald-500 hover:text-emerald-700 font-bold">&times;</button>
    </div>
    <script>setTimeout(() => document.getElementById('toast_prof')?.remove(), 4000);</script>
    <?php endif; ?>

    <!-- Sidebar/Tabs -->
    <div class="w-full md:w-64 flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-emerald-100 overflow-hidden md:sticky md:top-24">
            <div class="p-5 border-b border-gray-100 bg-emerald-600/5">
                <div class="inline-block px-3 py-1 rounded-full border <?php echo $status_badge; ?> text-xs font-bold uppercase tracking-wide mb-2">
                    Application: <?php echo htmlspecialchars($result['status']); ?>
                </div>
                <div class="text-sm font-medium text-gray-600">Roll No: <span class="text-gray-900 font-bold"><?php echo htmlspecialchars($result['entrance_roll_no'] ?: 'N/A'); ?></span></div>
            </div>
            <div class="flex flex-row overflow-x-auto md:flex-col p-2 space-x-2 md:space-x-0 md:space-y-1">
                <a href="?tab=overview" class="whitespace-nowrap px-4 py-3 rounded-lg text-sm font-semibold transition-colors <?php echo $tab === 'overview' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Overview
                </a>
                <?php if ($result['form_type'] === 'Admission'): ?>
                <a href="?tab=payments" class="whitespace-nowrap px-4 py-3 rounded-lg text-sm font-semibold transition-colors <?php echo $tab === 'payments' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Fees & Payments
                </a>
                <a href="?tab=documents" class="whitespace-nowrap px-4 py-3 rounded-lg text-sm font-semibold transition-colors <?php echo $tab === 'documents' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Documents
                </a>
                <a href="?tab=edit_profile" class="whitespace-nowrap px-4 py-3 rounded-lg text-sm font-semibold transition-colors <?php echo $tab === 'edit_profile' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-50'; ?> flex items-center gap-2">
                    <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Edit App
                </a>
                <?php endif; ?>
                <?php if ($result['status'] !== 'Approved' && $result['status'] !== 'Admitted'): ?>
                <div class="md:border-t md:border-gray-100 md:mt-1 md:pt-1">
                    <a href="?tab=delete" class="whitespace-nowrap px-4 py-3 rounded-lg text-sm font-semibold transition-colors <?php echo $tab === 'delete' ? 'bg-red-50 text-red-700' : 'text-red-400 hover:bg-red-50 hover:text-red-600'; ?> flex items-center gap-2">
                        <svg class="w-5 h-5 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Delete
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 bg-white rounded-xl shadow-sm border border-emerald-100 p-6 md:p-8">
        
        <!-- OVERVIEW TAB -->
        <?php if ($tab === 'overview'): ?>
            <!-- EXAM RESULT DISPLAY -->
            <?php 
            if ($result['form_type'] === 'Admission' && !empty($result['result_published_at'])): 
                $rs_status = $result['result_status'] ?? 'Pending';
                $rs_marks = $result['marks_obtained'] ?? null;
                $rs_total = (float)($result['total_marks'] ?? 100);
                $rs_percentage = ($rs_total > 0 && $rs_marks !== null) ? round(((float)$rs_marks / $rs_total) * 100, 1) : 0;
                $rs_remarks = $result['result_remarks'] ?? '';
            ?>
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100 flex justify-between items-center">
                <span>Entrance Examination Result</span>
                <a href="print_result.php?id=<?php echo $result['id']; ?>" class="text-xs font-bold bg-violet-100 hover:bg-violet-200 text-violet-700 px-4 py-2 rounded-lg transition inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    View / Print
                </a>
            </h2>

            <?php if ($rs_status === 'Pass'): ?>
            <!-- PASSED -->
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-8 text-center relative overflow-hidden mb-8">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-400 via-teal-400 to-emerald-500"></div>
                <div class="text-5xl mb-4">🎉</div>
                <h2 class="text-2xl font-black text-emerald-800 mb-2">Congratulations!</h2>
                <p class="text-emerald-600 font-medium mb-6">You have passed the entrance examination</p>
                
                <div class="bg-white/80 rounded-2xl p-6 max-w-sm mx-auto mb-6 shadow-sm border border-emerald-100">
                    <div class="inline-block bg-emerald-600 text-white font-bold text-xs px-4 py-1.5 rounded-full uppercase tracking-wider mb-4">PASSED</div>
                    <div>
                        <span class="text-5xl font-black text-emerald-700"><?php echo htmlspecialchars($rs_marks); ?></span>
                        <span class="text-xl text-gray-400 font-bold">/ <?php echo htmlspecialchars($rs_total); ?></span>
                    </div>
                    <p class="text-lg font-bold text-emerald-600 mt-2"><?php echo $rs_percentage; ?>%</p>
                </div>

                <?php if (!empty($rs_remarks)): ?>
                <div class="bg-white/60 rounded-lg p-3 max-w-sm mx-auto mb-4 border border-emerald-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Remarks</p>
                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars($rs_remarks); ?></p>
                </div>
                <?php endif; ?>

                <div class="bg-emerald-100 border border-emerald-200 rounded-xl p-5 max-w-md mx-auto mt-4">
                    <h3 class="font-bold text-emerald-800 text-sm mb-2">✅ Next Step: Complete Enrollment</h3>
                    <p class="text-sm text-emerald-700 leading-relaxed">Please visit the school administration office with your original documents to complete the enrollment process.</p>
                </div>
            </div>

            <?php elseif ($rs_status === 'Fail'): ?>
            <!-- FAILED -->
            <div class="bg-gray-50 border border-gray-200 rounded-2xl p-8 text-center mb-8">
                <div class="text-4xl mb-4">📋</div>
                <h2 class="text-xl font-bold text-gray-700 mb-2">Entrance Exam Result</h2>
                
                <div class="bg-white rounded-2xl p-6 max-w-sm mx-auto mb-6 shadow-sm border border-gray-200 mt-4">
                    <div class="inline-block bg-red-500 text-white font-bold text-xs px-4 py-1.5 rounded-full uppercase tracking-wider mb-4">NOT SELECTED</div>
                    <div>
                        <span class="text-5xl font-black text-red-600"><?php echo htmlspecialchars($rs_marks); ?></span>
                        <span class="text-xl text-gray-400 font-bold">/ <?php echo htmlspecialchars($rs_total); ?></span>
                    </div>
                    <p class="text-lg font-bold text-red-500 mt-2"><?php echo $rs_percentage; ?>%</p>
                </div>

                <?php if (!empty($rs_remarks)): ?>
                <div class="bg-white rounded-lg p-3 max-w-sm mx-auto mb-4 border border-gray-200">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Remarks</p>
                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars($rs_remarks); ?></p>
                </div>
                <?php endif; ?>

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 max-w-md mx-auto mt-4">
                    <p class="text-sm text-blue-700 leading-relaxed">Thank you for participating. For any queries, please contact the school administration office.</p>
                </div>
            </div>

            <?php elseif ($rs_status === 'Waitlisted'): ?>
            <!-- WAITLISTED -->
            <div class="bg-amber-50/50 border border-amber-200 rounded-2xl p-8 text-center mb-8">
                <div class="text-4xl mb-4">⏳</div>
                <h2 class="text-xl font-bold text-amber-800 mb-2">You Are Waitlisted</h2>
                <p class="text-amber-600 font-medium mb-6">Your result is under review</p>
                
                <div class="bg-white rounded-2xl p-6 max-w-sm mx-auto mb-6 shadow-sm border border-amber-200">
                    <div class="inline-block bg-amber-500 text-white font-bold text-xs px-4 py-1.5 rounded-full uppercase tracking-wider mb-4">WAITLISTED</div>
                    <div>
                        <span class="text-5xl font-black text-amber-700"><?php echo htmlspecialchars($rs_marks); ?></span>
                        <span class="text-xl text-gray-400 font-bold">/ <?php echo htmlspecialchars($rs_total); ?></span>
                    </div>
                    <p class="text-lg font-bold text-amber-600 mt-2"><?php echo $rs_percentage; ?>%</p>
                </div>

                <?php if (!empty($rs_remarks)): ?>
                <div class="bg-white rounded-lg p-3 max-w-sm mx-auto mb-4 border border-amber-100">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Remarks</p>
                    <p class="text-sm text-gray-700"><?php echo htmlspecialchars($rs_remarks); ?></p>
                </div>
                <?php endif; ?>

                <div class="bg-amber-100 border border-amber-200 rounded-xl p-5 max-w-md mx-auto mt-4">
                    <p class="text-sm text-amber-800 leading-relaxed">You have been placed on our waiting list. If a seat becomes available, the school will contact you directly. Please keep your contact details up to date.</p>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100">Application Overview</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100">
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Applicant Details</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Name</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['student_first_name'] . ' ' . $result['student_last_name']); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Class</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['applied_class']); ?></span></div>
                        <?php if($result['faculty_name']): ?>
                            <div class="flex justify-between"><span class="text-gray-500">Faculty</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['faculty_name']); ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between"><span class="text-gray-500">DOB (BS)</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['dob_bs']); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Contact</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['father_contact'] ?: ($result['mother_contact'] ?: '-')); ?></span></div>
                    </div>
                </div>

                <?php if ($result['form_type'] === 'Admission' && $result['exam_date']): ?>
                <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                    <h3 class="text-xs font-bold text-blue-600 uppercase tracking-wider mb-4">Entrance Exam</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Date</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['exam_date']); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Time</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['exam_time']); ?></span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Venue</span><span class="font-semibold text-gray-900"><?php echo htmlspecialchars($result['venue']); ?></span></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($result['form_type'] === 'Inquiry'): ?>
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-5 text-center text-sm text-indigo-800 mb-8">
                    We have received your quick inquiry. Our administration team will review your details and contact you shortly.
                </div>
            <?php endif; ?>




        <!-- PAYMENTS TAB -->
        <?php elseif ($tab === 'payments'): ?>
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100">Fees & Payments</h2>
            
            <?php if ($application_fee <= 0): ?>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    No application fees are required for this admission session.
                </div>
            <?php else: ?>
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 max-w-xl">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <p class="text-sm font-semibold text-gray-500">Admission Fee</p>
                            <p class="text-3xl font-bold text-gray-900">Rs. <?php echo number_format($application_fee, 2); ?></p>
                        </div>
                        <div class="px-4 py-1.5 rounded-full border <?php echo $pay_badge; ?> text-sm font-bold uppercase tracking-wide">
                            <?php echo $pay_status; ?>
                        </div>
                    </div>

                    <?php if ($pay_status === 'Pending' || $pay_status === 'Failed'): ?>
                        <p class="text-sm text-gray-600 mb-6">Please complete your payment at the administration office to finalize your application.</p>
                        
                        <div class="flex items-center gap-3 bg-white p-4 rounded-xl border border-gray-100 shadow-sm text-sm text-gray-700 font-medium">
                            <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </div>
                            <div>
                                Please visit the school's finance or administration desk during working hours and provide your Application Roll Number.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-5 text-center">
                            <svg class="w-10 h-10 text-emerald-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <h4 class="font-bold text-emerald-800 text-lg">Payment Successful</h4>
                            <p class="text-sm text-emerald-600">Your application fee has been paid.</p>
                            <?php if (!empty($result['payment_reference'])): ?>
                                <p class="text-xs text-gray-400 mt-2 font-mono">Ref: <?php echo htmlspecialchars($result['payment_reference']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- DOCUMENTS TAB -->
        <?php elseif ($tab === 'documents'): ?>
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100">Documents & Downloads</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- Submitted Form -->
                <div class="border border-gray-200 rounded-xl p-5 hover:border-emerald-300 hover:shadow-md transition-all group bg-white">
                    <div class="w-12 h-12 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center mb-4 group-hover:bg-emerald-50 group-hover:text-emerald-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-1">Application Record</h3>
                    <p class="text-gray-500 text-sm mb-4 h-10">Download a copy of your submitted application form for your records.</p>
                    <a href="print_application.php?id=<?php echo urlencode($result['id']); ?>" target="_blank" class="block w-full text-center py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold rounded-lg transition-colors text-sm">Download PDF</a>
                </div>

                <?php 
                    $is_paid = ($result['payment_status'] ?? 'Pending') === 'Paid';
                    $has_admit_right = $is_paid || $allow_unpaid_admit === '1';
                    $admit_ready = (($result['status'] === 'Approved' || $result['status'] === 'Admitted') && $has_admit_right && !empty($result['exam_date']));
                ?>
                <!-- Admit Card -->
                <div class="border <?php echo $admit_ready ? 'border-emerald-200 bg-emerald-50/30 hover:border-emerald-400 hover:shadow-md' : 'border-gray-200 bg-gray-50 opacity-75'; ?> rounded-xl p-5 transition-all group">
                    <div class="w-12 h-12 <?php echo $admit_ready ? 'bg-emerald-100 text-emerald-600' : 'bg-gray-200 text-gray-400'; ?> rounded-full flex items-center justify-center mb-4 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-1">Admit Card</h3>
                    
                    <?php if ($admit_ready): ?>
                        <p class="text-gray-500 text-sm mb-4 h-10">Required for entrance examination hall entry. Please print and bring this.</p>
                        <a href="admit_card.php?roll=<?php echo urlencode($result['entrance_roll_no']); ?>&dob=<?php echo urlencode($result['dob_bs']); ?>" target="_blank" class="block w-full text-center py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-lg transition-colors shadow-sm text-sm">Download Admit Card</a>
                    <?php elseif (($result['status'] === 'Approved' || $result['status'] === 'Admitted') && !$has_admit_right): ?>
                        <p class="text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 p-3 rounded block mt-2">Your application is approved! Please complete your fee payment at the school office. Your admit card will be available after payment confirmation.</p>
                    <?php else: ?>
                        <p class="text-xs font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 p-2 rounded block mt-2">Not available yet. Awaiting admin approval and exam scheduling.</p>
                    <?php endif; ?>
                </div>

                <!-- Payment Receipt -->
                <?php $pay_status_val = $result['payment_status'] ?? 'Pending'; ?>
                <div class="border <?php echo $pay_status_val === 'Paid' ? 'border-indigo-200 bg-indigo-50/30 hover:border-indigo-400 hover:shadow-md' : 'border-gray-200 bg-gray-50 opacity-75'; ?> rounded-xl p-5 transition-all group">
                    <div class="w-12 h-12 <?php echo $pay_status_val === 'Paid' ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-200 text-gray-400'; ?> rounded-full flex items-center justify-center mb-4 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="font-bold text-gray-900 text-lg mb-1">Payment Receipt</h3>
                    
                    <?php if ($pay_status_val === 'Paid'): ?>
                        <p class="text-gray-500 text-sm mb-4 h-10">Download your official payment receipt with invoice number.</p>
                        <a href="print_receipt.php?id=<?php echo urlencode($result['id']); ?>" target="_blank" class="block w-full text-center py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg transition-colors shadow-sm text-sm">Download Receipt</a>
                    <?php else: ?>
                        <p class="text-xs font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 p-2 rounded block mt-2">Available after payment is confirmed by administration.</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Upload Forms -->
            <div class="mt-8 border border-emerald-100 bg-emerald-50/30 rounded-xl p-6 relative">
                <h3 class="font-bold text-gray-900 text-lg mb-1 flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Upload / Update Documents
                </h3>
                
                <?php if ($result['status'] === 'Approved' || $result['status'] === 'Admitted'): ?>
                    <p class="text-sm border border-yellow-200 bg-yellow-50 text-yellow-700 rounded-lg p-3 my-4">
                        Your application is currently <strong><?php echo $result['status']; ?></strong>. Document edits are locked.
                    </p>
                <?php else: ?>
                <p class="text-sm text-gray-500 mb-6">Upload required supporting documents here. If you have already uploaded a document, re-uploading will replace the old one.</p>
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    
                    <!-- File Inputs Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        
                        <!-- Profile Photo -->
                        <div class="bg-white p-4 border border-gray-200 rounded-lg shadow-sm hover:border-emerald-300 transition group cursor-pointer" onclick="document.getElementById('pp_photo').click()">
                            <label class="block text-sm font-bold text-gray-700 mb-2 group-hover:text-emerald-700 transition">Passport Photo</label>
                            <?php if (!empty($result['pp_photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($result['pp_photo_path']); ?>" class="w-16 h-16 object-cover rounded-md border border-gray-200 mb-3" alt="Photo">
                            <?php else: ?>
                                <div class="text-xs text-red-500 font-bold mb-2">Missing</div>
                            <?php endif; ?>
                            <input type="file" id="pp_photo" name="pp_photo" accept="image/*" class="w-full text-xs text-gray-500 file:cursor-pointer file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700 file:font-semibold hover:file:bg-gray-200 border border-gray-200 rounded p-1" onclick="event.stopPropagation()">
                        </div>
                        
                        <!-- Marksheet -->
                        <div class="bg-white p-4 border border-gray-200 rounded-lg shadow-sm hover:border-emerald-300 transition group cursor-pointer" onclick="document.getElementById('marksheet').click()">
                            <label class="block text-sm font-bold text-gray-700 mb-2 group-hover:text-emerald-700 transition">Marksheet / Gradesheet</label>
                            <?php if (!empty($result['document_path'])): ?>
                                <div class="text-xs text-emerald-600 font-bold flex items-center gap-1 mb-3"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Existing Upload Found</div>
                            <?php else: ?>
                                <div class="text-xs text-red-500 font-bold mb-3">Missing</div>
                            <?php endif; ?>
                            <input type="file" id="marksheet" name="marksheet" accept="image/*,.pdf" class="w-full text-xs text-gray-500 file:cursor-pointer file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700 file:font-semibold hover:file:bg-gray-200 border border-gray-200 rounded p-1" onclick="event.stopPropagation()">
                        </div>
                        
                        <!-- Birth Certificate -->
                        <div class="bg-white p-4 border border-gray-200 rounded-lg shadow-sm hover:border-emerald-300 transition group cursor-pointer" onclick="document.getElementById('birth_cert').click()">
                            <label class="block text-sm font-bold text-gray-700 mb-2 group-hover:text-emerald-700 transition">Birth Certificate</label>
                            <?php if (!empty($result['birth_cert_path'])): ?>
                                <div class="text-xs text-emerald-600 font-bold flex items-center gap-1 mb-3"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Existing Upload Found</div>
                            <?php else: ?>
                                <div class="text-xs text-red-500 font-bold mb-3">Missing</div>
                            <?php endif; ?>
                            <input type="file" id="birth_cert" name="birth_cert" accept="image/*,.pdf" class="w-full text-xs text-gray-500 file:cursor-pointer file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-gray-100 file:text-gray-700 file:font-semibold hover:file:bg-gray-200 border border-gray-200 rounded p-1" onclick="event.stopPropagation()">
                        </div>
                        
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" name="upload_docs" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-sm transition inline-flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                            Save Documents
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>

        <!-- EDIT PROFILE TAB -->
        <?php elseif ($tab === 'edit_profile'): ?>
            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100">Edit Application Information</h2>
            
            <?php if ($result['status'] === 'Approved' || $result['status'] === 'Admitted'): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-5 rounded font-medium">
                    Your application status is <strong><?php echo $result['status']; ?></strong>. Application changes are locked and cannot be edited.
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-8">
                    
                    <!-- Section: Personal Information -->
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                        <h3 class="font-bold text-gray-700 mb-4 border-b border-gray-200 pb-2">Personal Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">First Name</label><input type="text" name="student_first_name" required class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['student_first_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Last Name</label><input type="text" name="student_last_name" required class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['student_last_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Email</label><input type="email" name="student_email" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['student_email']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Gender</label>
                                <select name="gender" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm">
                                    <option value="Male" <?php if($result['gender']==='Male') echo 'selected';?>>Male</option>
                                    <option value="Female" <?php if($result['gender']==='Female') echo 'selected';?>>Female</option>
                                    <option value="Other" <?php if($result['gender']==='Other') echo 'selected';?>>Other</option>
                                </select>
                            </div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">DOB (BS)</label><input type="text" name="dob_bs" placeholder="YYYY-MM-DD" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['dob_bs']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">DOB (AD)</label><input type="date" name="dob_ad" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['dob_ad']??''); ?>"></div>
                        </div>
                    </div>

                    <!-- Section: Address -->
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                        <h3 class="font-bold text-gray-700 mb-4 border-b border-gray-200 pb-2">Address</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Province</label><input type="text" name="address_province" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['address_province']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">District</label><input type="text" name="address_district" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['address_district']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Municipality/VDC</label><input type="text" name="address_municipality" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['address_municipality']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Ward</label><input type="text" name="address_ward_village" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['address_ward_village']??''); ?>"></div>
                        </div>
                    </div>

                    <!-- Section: Parents / Guardian -->
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                        <h3 class="font-bold text-gray-700 mb-4 border-b border-gray-200 pb-2">Parents & Guardian</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Father's Name</label><input type="text" name="father_name" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['father_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Father's Job</label><input type="text" name="father_occupation" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['father_occupation']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Father's Contact</label><input type="text" name="father_contact" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['father_contact']??''); ?>"></div>

                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Mother's Name</label><input type="text" name="mother_name" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['mother_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Mother's Job</label><input type="text" name="mother_occupation" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['mother_occupation']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Mother's Contact</label><input type="text" name="mother_contact" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['mother_contact']??''); ?>"></div>

                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Local Guardian</label><input type="text" name="local_guardian_name" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['local_guardian_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Guardian Relation</label><input type="text" name="guardian_relation" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['guardian_relation']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Guardian Contact</label><input type="text" name="guardian_contact" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['guardian_contact']??''); ?>"></div>
                        </div>
                    </div>

                    <!-- Section: Previous School -->
                    <div class="bg-gray-50 border border-gray-100 rounded-xl p-5">
                        <h3 class="font-bold text-gray-700 mb-4 border-b border-gray-200 pb-2">Academic History</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Previous School Name</label><input type="text" name="previous_school_name" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['previous_school_name']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">Board</label><input type="text" name="previous_board" placeholder="e.g. NEB" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['previous_board']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">GPA / Percentage</label><input type="text" name="gpa_or_percentage" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['gpa_or_percentage']??''); ?>"></div>
                            <div><label class="block text-sm font-semibold text-gray-600 mb-1">SEE / Final Symbol No.</label><input type="text" name="see_symbol_no" class="w-full border-gray-300 rounded-lg p-2.5 text-sm shadow-sm" value="<?php echo htmlspecialchars($result['see_symbol_no']??''); ?>"></div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-gray-100 pt-5">
                        <button type="submit" name="update_profile" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-sm hover:shadow-md transition">
                            Save Changes
                        </button>
                    </div>

                </form>
            <?php endif; ?>

        <?php elseif ($tab === 'delete'): ?>
            <h2 class="text-xl font-bold text-red-700 mb-6 pb-2 border-b border-red-100">Delete Application</h2>
            
            <?php if ($result['status'] === 'Approved' || $result['status'] === 'Admitted'): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 text-yellow-800 p-5 rounded font-medium">
                    Your application has been <strong><?php echo $result['status']; ?></strong>. It cannot be deleted.
                </div>
            <?php else: ?>
                <div class="max-w-lg mx-auto">
                    <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                        <div class="w-16 h-16 bg-red-100 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-red-800 mb-2">Are you sure you want to delete your application?</h3>
                        <p class="text-sm text-red-600 mb-6">This action is <strong>permanent and cannot be undone</strong>. All your application data, uploaded documents, and records will be permanently removed from our system.</p>
                        
                        <div class="bg-white border border-red-100 rounded-lg p-4 mb-6 text-left text-sm">
                            <p class="font-semibold text-gray-700 mb-2">The following will be deleted:</p>
                            <ul class="text-gray-600 space-y-1">
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Your application form data</li>
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Uploaded documents (photo, marksheet, certificates)</li>
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Entrance roll number & exam slot reservation</li>
                                <li class="flex items-center gap-2"><svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg> Payment records (if any)</li>
                            </ul>
                        </div>

                        <button type="button" onclick="document.getElementById('delete-confirm-modal').classList.remove('hidden')" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition-colors inline-flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            I want to delete my application
                        </button>
                        <div class="mt-3">
                            <a href="?tab=overview" class="text-sm text-gray-500 hover:text-gray-700 font-medium">← Cancel and go back</a>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal -->
                <div id="delete-confirm-modal" class="hidden fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4">
                    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 text-center">
                        <div class="w-14 h-14 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Final Confirmation</h3>
                        <p class="text-sm text-gray-500 mb-6">Type <strong class="text-red-600">DELETE</strong> below to confirm.</p>
                        <input type="text" id="delete-confirm-input" placeholder="Type DELETE to confirm" class="w-full border-2 border-gray-200 rounded-lg p-3 text-center font-bold text-lg focus:border-red-400 focus:ring-2 focus:ring-red-200 outline-none mb-4" autocomplete="off">
                        <div class="flex gap-3">
                            <button type="button" onclick="document.getElementById('delete-confirm-modal').classList.add('hidden'); document.getElementById('delete-confirm-input').value='';" class="flex-1 py-3 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors">Cancel</button>
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                                <button type="submit" name="delete_application" id="delete-confirm-btn" disabled class="w-full py-3 bg-red-300 text-white font-bold rounded-lg cursor-not-allowed transition-colors">Delete Forever</button>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                document.getElementById('delete-confirm-input').addEventListener('input', function() {
                    const btn = document.getElementById('delete-confirm-btn');
                    if (this.value === 'DELETE') {
                        btn.disabled = false;
                        btn.classList.remove('bg-red-300', 'cursor-not-allowed');
                        btn.classList.add('bg-red-600', 'hover:bg-red-700', 'cursor-pointer');
                    } else {
                        btn.disabled = true;
                        btn.classList.add('bg-red-300', 'cursor-not-allowed');
                        btn.classList.remove('bg-red-600', 'hover:bg-red-700', 'cursor-pointer');
                    }
                });
                </script>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
