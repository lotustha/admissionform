<?php
// applications.php — Full admission applications list
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = $_POST['status'] ?? '';
    $inquiry_id = $_POST['inquiry_id'] ?? '';
    if ($status && $inquiry_id) {
        $old_stmt = $pdo->prepare("SELECT status, form_type FROM admission_inquiries WHERE id=?");
        $old_stmt->execute([$inquiry_id]);
        $oldRow = $old_stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE admission_inquiries SET status=? WHERE id=?")
            ->execute([$status, $inquiry_id]);
            
        // Trigger automated Admit Card email on transition to "Approved"
        if ($status === 'Approved' && isset($oldRow['status']) && $oldRow['status'] !== 'Approved' && $oldRow['form_type'] === 'Admission') {
            sendApprovalEmail($pdo, $inquiry_id);
        }
    }
    header("Location: applications.php?msg=updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry'])) {
    $inquiry_id = $_POST['inquiry_id'] ?? '';
    if ($inquiry_id) {
        $stmt_check = $pdo->prepare("SELECT pp_photo_path, document_path, birth_cert_path FROM admission_inquiries WHERE id = ?");
        $stmt_check->execute([$inquiry_id]);
        $del_record = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($del_record) {
            $files_to_delete = ['pp_photo_path', 'document_path', 'birth_cert_path'];
            foreach ($files_to_delete as $file_col) {
                if (!empty($del_record[$file_col]) && file_exists(__DIR__ . '/' . $del_record[$file_col])) {
                    @unlink(__DIR__ . '/' . $del_record[$file_col]);
                }
            }
            $pdo->prepare("DELETE FROM admission_inquiries WHERE id=?")->execute([$inquiry_id]);
        }
    }
    header("Location: applications.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_pay'])) {
    $inquiry_id = $_POST['inquiry_id'] ?? '';
    if ($inquiry_id) {
        $settings = getSchoolSettings($pdo);
        $application_fee = (float)($settings['application_fee'] ?? 500);
        $pay_method = $_POST['payment_method'] ?? 'Cash';
        $pay_ref = !empty($_POST['payment_reference']) ? trim($_POST['payment_reference']) : 'AUTO-OFFLINE-' . strtoupper(uniqid());

        $upd = $pdo->prepare("UPDATE admission_inquiries SET 
                                payment_status = 'Paid', 
                                payment_amount = ?, 
                                payment_method = ?, 
                                payment_reference = ?, 
                                payment_date = NOW(),
                                status = IF(status = 'Pending', 'Approved', status)
                               WHERE id = ?");
        if ($upd->execute([$application_fee, $pay_method, $pay_ref, $inquiry_id])) {
            sendPaymentConfirmationEmail($pdo, $inquiry_id);
            header("Location: applications.php?msg=paid");
            exit;
        }
    }
}

$search         = trim($_GET['search'] ?? '');
$status_filter  = $_GET['status_filter'] ?? '';
$class_filter   = $_GET['class_filter'] ?? '';
$session_filter = $_GET['session_filter'] ?? '';
$sort_by        = $_GET['sort_by'] ?? 'i.id';
$sort_dir       = (strtolower($_GET['sort_dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = max(5, (int)($_GET['limit'] ?? 25));

$allowed_sorts = ['i.id','i.entrance_roll_no','i.student_first_name','i.applied_class','i.status','i.submission_date'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'i.id';
}

$where = ["i.form_type = 'Admission'"]; 
$params = [];

if ($search !== '') {
    $where[] = "(i.entrance_roll_no LIKE ? OR i.student_first_name LIKE ? OR i.student_last_name LIKE ? OR i.father_contact LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($status_filter !== '') {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
}
if ($class_filter !== '') {
    $where[] = "i.applied_class = ?";
    $params[] = $class_filter;
}
if ($session_filter !== '') {
    $where[] = "i.session_id = ?";
    $params[] = $session_filter;
}

$where_sql = implode(' AND ', $where);
$offset    = ($page - 1) * $limit;

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=applications_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    $headers = ['ID', 'Form Type', 'Roll No', 'Status', 'First Name', 'Last Name', 'Email', 'DOB BS', 'DOB AD', 'Gender', 'Province', 'District', 'Municipality', 'Ward/Village', 'Father Name', 'Father Occupation', 'Father Contact', 'Mother Name', 'Mother Occupation', 'Mother Contact', 'Local Guardian Name', 'Guardian Contact', 'Guardian Relation', 'Applied Class', 'Faculty', 'Optional Sub 1', 'Optional Sub 2', 'Prev School Name', 'Prev Board', 'GPA/Percentage', 'SEE Symbol No', 'Payment Status', 'Payment Amount', 'Payment Method', 'Payment Reference', 'Submission Date'];
    fputcsv($out, $headers, ",", "\"", "\\");
    $exp = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id=f.id WHERE $where_sql ORDER BY $sort_by $sort_dir");
    $exp->execute($params);
    while ($r = $exp->fetch(PDO::FETCH_ASSOC)) {
        $row = [
            $r['id'], $r['form_type'] ?? 'Admission', $r['entrance_roll_no'], $r['status'], $r['student_first_name'], $r['student_last_name'], $r['student_email'], $r['dob_bs'], $r['dob_ad'], $r['gender'], $r['address_province'], $r['address_district'], $r['address_municipality'], $r['address_ward_village'], $r['father_name'], $r['father_occupation'], $r['father_contact'], $r['mother_name'], $r['mother_occupation'], $r['mother_contact'], $r['local_guardian_name'], $r['guardian_contact'], $r['guardian_relation'], $r['applied_class'], $r['faculty_name'] ?? 'N/A', $r['optional_subject_1'], $r['optional_subject_2'], $r['previous_school_name'], $r['previous_board'], $r['gpa_or_percentage'], $r['see_symbol_no'], $r['payment_status'], $r['payment_amount'], $r['payment_method'], $r['payment_reference'], $r['submission_date']
        ];
        fputcsv($out, $row, ",", "\"", "\\");
    }
    fclose($out);
    exit;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM admission_inquiries i WHERE $where_sql");
$total->execute($params); 
$total_records = (int)$total->fetchColumn();
$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

$stmt = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id=f.id WHERE $where_sql ORDER BY $sort_by $sort_dir LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_sessions = $pdo->query("SELECT id, session_label FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_classes  = $pdo->query("SELECT DISTINCT applied_class FROM admission_inquiries WHERE applied_class IS NOT NULL ORDER BY applied_class")->fetchAll(PDO::FETCH_COLUMN);

function ap_url($u) { return '?'.http_build_query(array_merge($_GET, $u)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Full Admissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-[1400px] mx-auto p-4 sm:p-6 lg:p-10">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Full Admissions</h1>
            <p class="text-sm font-medium text-gray-500 mt-2">Manage and review all submitted student applications.</p>
        </div>
        <div class="flex gap-3">
            <a href="admin_add_application.php" class="inline-flex items-center gap-2 bg-emerald-600 border border-emerald-700 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-md hover:shadow-lg transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                New Manual Entry
            </a>
            <a href="<?php echo ap_url(['export'=>'csv']); ?>" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm hover:shadow transition">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm font-medium">Status updated successfully.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm font-medium">Application deleted successfully.</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'paid'): ?>
        <div class="mb-4 p-3 bg-blue-50 border border-blue-200 text-blue-700 rounded-lg text-sm font-medium">Payment recorded and confirmation email sent.</div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="GET" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 mb-8 flex flex-wrap gap-4 items-end">
        <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
        <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sort_dir === 'ASC' ? 'asc' : 'desc'); ?>">

        <div class="flex-1 min-w-[200px]">
            <label for="search" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Search</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, roll no, phone..." class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 transition shadow-sm">
        </div>
        <div class="w-40">
            <label for="status_filter" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Status</label>
            <select id="status_filter" name="status_filter" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-sm">
                <option value="">All Statuses</option>
                <?php foreach(['Pending','Approved','Rejected','Admitted'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php if($status_filter === $s) echo 'selected'; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-48">
            <label for="class_filter" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Class / Grade</label>
            <select id="class_filter" name="class_filter" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-sm">
                <option value="">All Classes</option>
                <?php foreach($all_classes as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php if($class_filter === $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($all_sessions)): ?>
        <div class="w-48">
            <label for="session_filter" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Academic Session</label>
            <select id="session_filter" name="session_filter" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-sm">
                <option value="">All Sessions</option>
                <?php foreach($all_sessions as $sess): ?>
                <option value="<?php echo $sess['id']; ?>" <?php if($session_filter === (string)$sess['id']) echo 'selected'; ?>><?php echo htmlspecialchars($sess['session_label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="w-28">
            <label for="limit" class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Rows</label>
            <select id="limit" name="limit" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-sm">
                <?php foreach([10, 25, 50, 100] as $l): ?>
                <option value="<?php echo $l; ?>" <?php if($limit === $l) echo 'selected'; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
            <button type="submit" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm hover:shadow transition">Filter</button>
            <a href="applications.php" class="flex-1 sm:flex-none text-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm transition">Clear</a>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-10">
        <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
            <span class="text-sm font-bold text-gray-600">Total Records Found: <span class="text-emerald-600"><?php echo number_format($total_records); ?></span></span>
        </div>
        <div class="overflow-x-auto relative min-h-[400px]">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-widest sticky top-0 z-10 font-bold border-b border-gray-100 shadow-[0_1px_2px_rgba(0,0,0,0.02)]">
                    <tr>
                        <th class="px-5 py-4 text-left"><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by'=>'i.entrance_roll_no','sort_dir'=>($sort_by==='i.entrance_roll_no' && $sort_dir==='ASC')?'desc':'asc'])); ?>" class="flex items-center gap-1 hover:text-emerald-600">Roll No <?php if($sort_by==='i.entrance_roll_no') echo $sort_dir==='ASC'?'↑':'↓'; ?></a></th>
                        <th class="px-5 py-4 text-left"><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by'=>'i.student_first_name','sort_dir'=>($sort_by==='i.student_first_name' && $sort_dir==='ASC')?'desc':'asc'])); ?>" class="flex items-center gap-1 hover:text-emerald-600">Student <?php if($sort_by==='i.student_first_name') echo $sort_dir==='ASC'?'↑':'↓'; ?></a></th>
                        <th class="px-5 py-4 text-left"><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by'=>'i.applied_class','sort_dir'=>($sort_by==='i.applied_class' && $sort_dir==='ASC')?'desc':'asc'])); ?>" class="flex items-center gap-1 hover:text-emerald-600">Class <?php if($sort_by==='i.applied_class') echo $sort_dir==='ASC'?'↑':'↓'; ?></a></th>
                        <th class="px-5 py-4 text-left">Contact</th>
                        <th class="px-5 py-4 text-left"><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by'=>'i.submission_date','sort_dir'=>($sort_by==='i.submission_date' && $sort_dir==='ASC')?'desc':'asc'])); ?>" class="flex items-center gap-1 hover:text-emerald-600">Date <?php if($sort_by==='i.submission_date') echo $sort_dir==='ASC'?'↑':'↓'; ?></a></th>
                        <th class="px-5 py-4 text-left"><a href="?<?php echo http_build_query(array_merge($_GET, ['sort_by'=>'i.status','sort_dir'=>($sort_by==='i.status' && $sort_dir==='ASC')?'desc':'asc'])); ?>" class="flex items-center gap-1 hover:text-emerald-600">Status <?php if($sort_by==='i.status') echo $sort_dir==='ASC'?'↑':'↓'; ?></a></th>
                        <th class="px-5 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-500 font-medium">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            No full admission applications found matching criteria.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r):
                            $status = $r['status'] ?? 'Pending';
                            $badges = [
                                'Pending' => 'bg-yellow-100 text-yellow-800',
                                'Approved'=> 'bg-blue-100 text-blue-800',
                                'Rejected'=> 'bg-red-100 text-red-800',
                                'Admitted'=> 'bg-emerald-100 text-emerald-800'
                            ];
                            $bc = $badges[$status] ?? 'bg-gray-100 text-gray-600';
                            
                            $fname = $r['student_first_name'] ?? '';
                            $lname = $r['student_last_name'] ?? '';
                            $fullname = trim("$fname $lname");
                            
                            $gender = $r['gender'] ?? '-';
                            $dob = $r['dob_bs'] ?? '-';
                            $class = $r['applied_class'] ?? '-';
                            $faculty_name = $r['faculty_name'] ?? '';
                            $contact = !empty($r['father_contact']) ? $r['father_contact'] : ($r['guardian_contact'] ?? '-');
                            $sub_date = !empty($r['submission_date']) ? date('M d, Y', strtotime($r['submission_date'])) : '-';
                            $roll_no = $r['entrance_roll_no'] ?? '-';
                            $id = $r['id'] ?? '';
                        ?>
                        <tr class="hover:bg-emerald-50/20 transition-all group">
                            <td class="px-5 py-4 font-mono text-sm text-emerald-700 font-bold"><?php echo htmlspecialchars($roll_no); ?></td>
                            <td class="px-5 py-4">
                                <?php if ($id): ?>
                                    <a href="view_application.php?id=<?php echo urlencode($id); ?>" class="font-bold text-gray-900 group-hover:text-emerald-600 text-sm transition-colors block">
                                        <?php echo htmlspecialchars($fullname ?: 'Unknown'); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="font-bold text-gray-900 text-sm block"><?php echo htmlspecialchars($fullname ?: 'Unknown'); ?></span>
                                <?php endif; ?>
                                <div class="text-[11px] text-slate-500 font-medium mt-0.5 tracking-wide">
                                    <?php echo htmlspecialchars($gender); ?> &middot; DOB: <?php echo htmlspecialchars($dob); ?>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-gray-900 font-bold text-sm bg-slate-100 inline-block px-2.5 py-0.5 rounded-md border border-slate-200"><?php echo htmlspecialchars($class); ?></div>
                                <?php if($faculty_name): ?>
                                    <div class="text-xs text-slate-500 mt-1 font-semibold"><?php echo htmlspecialchars($faculty_name); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-gray-600 text-sm font-medium">
                                <?php echo htmlspecialchars($contact); ?>
                            </td>
                            <td class="px-5 py-4 text-gray-500 text-sm font-medium">
                                <?php echo htmlspecialchars($sub_date); ?>
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-3 py-1.5 rounded-full text-xs font-bold border border-current <?php echo str_replace('bg-', 'bg-', $bc); ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <?php if ($id): ?>
                                <form method="POST" class="flex gap-2 items-center justify-end">
                                    <input type="hidden" name="inquiry_id" value="<?php echo htmlspecialchars($id); ?>">
                                    
                                    <?php if (($r['payment_status'] ?? 'Pending') !== 'Paid'): ?>
                                    <button type="button" onclick="openPaymentModal(<?php echo $id; ?>)" class="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-xs hover:bg-blue-600 hover:text-white shadow-sm transition font-bold flex items-center gap-1" title="Collect Payment">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                        Pay
                                    </button>
                                    <?php endif; ?>

                                    <select name="status" class="text-xs border border-gray-300 rounded-lg px-2 py-1.5 bg-white outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 font-semibold shadow-sm cursor-pointer">
                                        <?php foreach(['Pending','Approved','Rejected','Admitted'] as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php if($status === $s) echo 'selected'; ?>><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-emerald-600 shadow-sm transition font-bold">Update</button>
                                    <button type="submit" name="delete_inquiry" onclick="return confirm('Are you sure you want to completely delete this application and its files? This cannot be undone.');" class="bg-red-50 text-red-600 px-3 py-1.5 rounded-lg text-xs hover:bg-red-600 hover:text-white shadow-sm transition font-bold" title="Delete Application">Delete</button>
                                </form>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 font-medium">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="text-sm font-bold text-gray-500">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="flex gap-1.5">
                <?php if ($page > 1): ?>
                    <a href="<?php echo ap_url(['page' => $page - 1]); ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-100 transition shadow-sm">Previous</a>
                <?php endif; ?>
                
                <div class="hidden sm:flex gap-1.5">
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?php echo ap_url(['page' => $p]); ?>" class="px-4 py-2 border rounded-lg text-sm font-bold transition shadow-sm <?php echo $p === $page ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo ap_url(['page' => $page + 1]); ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-100 transition shadow-sm">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- closing tags for admin sidebar if it wraps content -->
</div></main></div> <!-- wrapper -->

<!-- Payment Modal -->
<div id="paymentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="closePaymentModal()"></div>
    <div class="relative bg-slate-800 rounded-3xl w-full max-w-[400px] overflow-hidden shadow-2xl border border-white/10 animate-[scaleIn_0.2s_ease-out]">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg leading-tight">Collect Payment</h3>
                        <p class="text-white/50 text-xs">Mark application as paid</p>
                    </div>
                </div>
                <button type="button" onclick="closePaymentModal()" class="text-white/40 hover:text-white bg-white/5 hover:bg-white/10 rounded-full p-2 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" id="modalPaymentForm">
                <input type="hidden" name="quick_pay" value="1">
                <input type="hidden" name="inquiry_id" id="modal_inquiry_id" value="">
                
                <?php 
                if(!isset($settings)){ $settings = getSchoolSettings($pdo); }
                $fee = number_format((float)($settings['application_fee'] ?? 500), 2);
                ?>
                <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-4 mb-5 flex justify-between items-center">
                    <div>
                        <div class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Amount to Collect</div>
                        <div class="text-2xl font-black text-white">Rs. <?php echo $fee; ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>

                <p class="text-white/50 text-[10px] font-bold uppercase tracking-wider mb-2">Select Payment Method</p>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <?php 
                    $methods = ['Cash' => '💵', 'eSewa' => '📱', 'Khalti' => '📲', 'Bank' => '🏦', 'Cheque' => '📝', 'Other' => '📋'];
                    $first = true;
                    foreach ($methods as $mKey => $mIcon): 
                        $mVal = ($mKey === 'Bank') ? 'Bank Deposit' : $mKey;
                    ?>
                    <label class="cursor-pointer group">
                        <input type="radio" name="payment_method" value="<?php echo $mVal; ?>" class="hidden peer" <?php echo $first ? 'checked' : ''; ?>>
                        <div class="bg-white/5 hover:bg-white/10 peer-checked:bg-emerald-500 peer-checked:text-white text-white/70 text-center py-2.5 rounded-xl transition-all border border-white/5 peer-checked:border-emerald-400 peer-checked:shadow-[0_0_15px_rgba(16,185,129,0.3)]">
                            <span class="text-lg block mb-0.5 group-hover:scale-110 peer-checked:scale-110 transition-transform"><?php echo $mIcon; ?></span>
                            <span class="text-[10px] font-bold block"><?php echo $mKey; ?></span>
                        </div>
                    </label>
                    <?php $first = false; endforeach; ?>
                </div>

                <div class="mb-5">
                    <input type="text" name="payment_reference" placeholder="Reference No. (optional)" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-white/40 focus:bg-white/10 focus:border-white/20 outline-none transition font-medium focus:ring-2 focus:ring-emerald-500/20">
                </div>

                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-white font-black py-3 px-4 rounded-xl shadow-[0_4px_20px_rgba(16,185,129,0.3)] hover:shadow-[0_4px_25px_rgba(16,185,129,0.4)] transition-all text-sm flex items-center justify-center gap-2 hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Confirm Payment & Send Admit Card
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    @keyframes scaleIn {
        from { opacity: 0; transform: scale(0.95) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>
<script>
    function openPaymentModal(id) {
        document.getElementById('modal_inquiry_id').value = id;
        document.getElementById('paymentModal').classList.remove('hidden');
    }
    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
        document.getElementById('modal_inquiry_id').value = '';
    }
</script>
</body>
</html>
