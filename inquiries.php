<?php
// inquiries.php — Inquiry form submissions list
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
// All roles can view inquiries (Super Admin, Academic Staff, Cashier, Viewer)


$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'i.id';
$sort_dir = (($_GET['sort_dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, (int)($_GET['limit'] ?? 25));

$allowed_sorts = ['i.id', 'i.student_first_name', 'i.applied_class', 'i.status', 'i.submission_date'];
if (!in_array($sort_by, $allowed_sorts))
    $sort_by = 'i.id';

$where = ["(i.form_type = 'Inquiry' OR i.form_type IS NULL)"]; // treat old rows without form_type as inquiry
$params = [];

if ($search !== '') {
    $where[] = "(i.student_first_name LIKE ? OR i.student_last_name LIKE ? OR i.father_contact LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($status_filter !== '') {
    $where[] = "i.status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where);
$offset = ($page - 1) * $limit;

if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=inquiries_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    $headers = ['ID', 'Form Type', 'Roll No', 'Status', 'First Name', 'Last Name', 'Email', 'DOB BS', 'DOB AD', 'Gender', 'Province', 'District', 'Municipality', 'Ward/Village', 'Father Name', 'Father Occupation', 'Father Contact', 'Mother Name', 'Mother Occupation', 'Mother Contact', 'Local Guardian Name', 'Guardian Contact', 'Guardian Relation', 'Applied Class', 'Faculty', 'Optional Sub 1', 'Optional Sub 2', 'Prev School Name', 'Prev Board', 'GPA/Percentage', 'SEE Symbol No', 'Payment Status', 'Payment Amount', 'Payment Method', 'Payment Reference', 'Submission Date'];
    fputcsv($out, $headers, ",", "\"", "\\");
    $exp = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id WHERE $where_sql ORDER BY $sort_by $sort_dir");
    $exp->execute($params);
    while ($r = $exp->fetch(PDO::FETCH_ASSOC)) {
        $row = [
            $r['id'], $r['form_type'] ?? 'Inquiry', $r['entrance_roll_no'], $r['status'], $r['student_first_name'], $r['student_last_name'], $r['student_email'], $r['dob_bs'], $r['dob_ad'], $r['gender'], $r['address_province'], $r['address_district'], $r['address_municipality'], $r['address_ward_village'], $r['father_name'], $r['father_occupation'], $r['father_contact'], $r['mother_name'], $r['mother_occupation'], $r['mother_contact'], $r['local_guardian_name'], $r['guardian_contact'], $r['guardian_relation'], $r['applied_class'], $r['faculty_name'] ?? 'N/A', $r['optional_subject_1'], $r['optional_subject_2'], $r['previous_school_name'], $r['previous_board'], $r['gpa_or_percentage'], $r['see_symbol_no'], $r['payment_status'], $r['payment_amount'], $r['payment_method'], $r['payment_reference'], $r['submission_date']
        ];
        fputcsv($out, $row, ",", "\"", "\\");
    }
    fclose($out);
    exit;
}

$total = $pdo->prepare("SELECT COUNT(*) FROM admission_inquiries i WHERE $where_sql");
$total->execute($params);
$total_records = $total->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id WHERE $where_sql ORDER BY $sort_by $sort_dir LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// DEBUG: Remove after fixing
// if (!empty($rows)) { echo '<pre>'; var_dump($rows[0]); echo '</pre>'; die(); }


function iq_url($updates)
{
    return '?' . http_build_query(array_merge($_GET, $updates));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inquiry Submissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-[1400px] mx-auto p-4 sm:p-6 lg:p-10">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Quick Inquiry Submissions</h1>
            <p class="text-sm font-medium text-gray-500 mt-2">Students who submitted a quick inquiry without the full admission form.</p>
        </div>
        <a href="<?php echo iq_url(['export' => 'csv']); ?>" class="inline-flex items-center gap-2 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm hover:shadow transition">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export CSV
        </a>
    </div>



    <!-- Filter Bar -->
    <form method="GET" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 mb-8 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, contact..." class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm transition">
        </div>
        <div class="w-40">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Status</label>
            <select name="status_filter" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 shadow-sm cursor-pointer transition">
                <option value="">All Statuses</option>
                <?php foreach (['Pending', 'Approved', 'Rejected', 'Admitted'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php if ($status_filter === $s) echo 'selected'; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-28">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Rows</label>
            <select name="limit" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 shadow-sm cursor-pointer transition">
                <?php foreach ([10, 25, 50, 100] as $l): ?>
                <option value="<?php echo $l; ?>" <?php if ($limit === $l) echo 'selected'; ?>><?php echo $l; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
            <button type="submit" class="flex-1 sm:flex-none bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm hover:shadow transition">Filter</button>
            <a href="inquiries.php" class="flex-1 sm:flex-none text-center bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm transition">Clear</a>
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
                        <th class="px-5 py-4 text-left">Student</th>
                        <th class="px-5 py-4 text-left">Class</th>
                        <th class="px-5 py-4 text-left">Contact</th>
                        <th class="px-5 py-4 text-left">Date</th>
                        <th class="px-5 py-4 text-left">Status</th>
                        <th class="px-5 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-16 text-center text-gray-400">
                            <div class="flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                <span class="text-gray-500 font-medium text-base">No Quick Inquiries Found</span>
                                <span class="text-xs text-gray-400 mt-1">There are no inquiries matching your criteria.</span>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
    $badge = ['Pending' => 'bg-yellow-100 text-yellow-800', 'Approved' => 'bg-blue-100 text-blue-800', 'Rejected' => 'bg-red-100 text-red-800', 'Admitted' => 'bg-emerald-100 text-emerald-800'];
    $bc = $badge[$r['status']] ?? 'bg-gray-100 text-gray-600';
?>
                    <tr class="hover:bg-emerald-50/20 transition-all group">
                        <td class="px-5 py-4">
                            <a href="view_application.php?id=<?php echo $r['id']; ?>" class="font-bold text-gray-900 group-hover:text-emerald-600 text-sm transition-colors block">
                                <?php echo htmlspecialchars($r['student_first_name'] . ' ' . $r['student_last_name']); ?>
                            </a>
                            <div class="text-[11px] text-slate-500 font-medium mt-0.5 tracking-wide"><?php echo htmlspecialchars($r['gender'] ?: 'Unspecified'); ?></div>
                        </td>
                        <td class="px-5 py-4 text-gray-900 font-bold text-sm">
                            <div class="bg-slate-100 inline-block px-2.5 py-0.5 rounded-md border border-slate-200"><?php echo htmlspecialchars($r['applied_class'] ?: 'N/A'); ?></div>
                        </td>
                        <td class="px-5 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($r['father_contact'] ?: $r['guardian_contact']); ?></td>
                        <td class="px-5 py-4 text-gray-500 text-sm font-medium"><?php echo date('M d, Y', strtotime($r['submission_date'])); ?></td>
                        <td class="px-5 py-4">
                            <span class="px-3 py-1.5 rounded-full text-xs font-bold border border-current <?php echo str_replace('bg-', 'bg-', $bc); ?>">
                                <?php echo $r['status']; ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="view_application.php?id=<?php echo $r['id']; ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-700 hover:text-emerald-700 hover:border-emerald-200 hover:bg-emerald-50 shadow-sm transition-all focus:ring-2 focus:ring-emerald-500 outline-none">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Review
                            </a>
                        </td>
                    </tr>
                    <?php
endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-5 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="text-sm font-bold text-gray-500">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="flex gap-1.5">
                <?php if ($page > 1): ?>
                    <a href="<?php echo iq_url(['page' => $page - 1]); ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-100 transition shadow-sm">Previous</a>
                <?php endif; ?>
                
                <div class="hidden sm:flex gap-1.5">
                    <?php for ($p = max(1, $page - 2); $p <= min($total_pages, $page + 2); $p++): ?>
                        <a href="<?php echo iq_url(['page' => $p]); ?>" class="px-4 py-2 border rounded-lg text-sm font-bold transition shadow-sm <?php echo $p === $page ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo iq_url(['page' => $page + 1]); ?>" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-100 transition shadow-sm">Next</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div></main></div>
</body>
</html>
