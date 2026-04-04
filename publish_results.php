<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Academic Staff'])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

$settings = getSchoolSettings($pdo);
$pass_pct = (float)($settings['result_pass_percentage'] ?? 40);

// Handle bulk publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_publish'])) {
    $ids = $_POST['student_ids'] ?? [];
    $marks_list = $_POST['marks'] ?? [];
    $totals_list = $_POST['totals'] ?? [];
    $statuses = $_POST['statuses'] ?? [];
    $remarks_list = $_POST['remarks'] ?? [];
    $send_emails = isset($_POST['send_emails']);

    $published = 0;
    foreach ($ids as $sid) {
        $m = (float)($marks_list[$sid] ?? 0);
        $t = (float)($totals_list[$sid] ?? 100);
        $s = $statuses[$sid] ?? '';
        $r = trim($remarks_list[$sid] ?? '');
        
        if ($m <= 0 || $t <= 0 || empty($s)) continue;

        $upd = $pdo->prepare("UPDATE admission_inquiries SET marks_obtained=?, total_marks=?, result_status=?, result_remarks=?, result_published_at=NOW(), result_published_by=? WHERE id=?");
        $upd->execute([$m, $t, $s, $r ?: null, $_SESSION['admin_id'], $sid]);
        
        if ($send_emails) {
            sendResultEmail($pdo, $sid);
        }
        $published++;
    }

    header("Location: publish_results.php?msg=published&count=" . $published);
    exit;
}

// Filters
$filter_class = $_GET['class'] ?? '';
$filter_status = $_GET['result_filter'] ?? 'unpublished';

// Get classes list
$classes = $pdo->query("SELECT DISTINCT applied_class FROM admission_inquiries WHERE form_type='Admission' ORDER BY applied_class")->fetchAll(PDO::FETCH_COLUMN);

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

$sql = "SELECT i.*, f.faculty_name 
        FROM admission_inquiries i 
        LEFT JOIN faculties f ON i.faculty_id = f.id 
        WHERE {$where} 
        ORDER BY i.applied_class, i.entrance_roll_no";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN result_status='Pass' AND result_published_at IS NOT NULL THEN 1 ELSE 0 END) as passed,
    SUM(CASE WHEN result_status='Fail' AND result_published_at IS NOT NULL THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN result_status='Waitlisted' AND result_published_at IS NOT NULL THEN 1 ELSE 0 END) as waitlisted,
    SUM(CASE WHEN result_published_at IS NOT NULL THEN 1 ELSE 0 END) as total_published
    FROM admission_inquiries 
    WHERE form_type='Admission' AND payment_status='Paid'";
$stats = $pdo->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Results - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50">

<?php include __DIR__ . '/includes/admin_sidebar.php'; ?>

<div class="max-w-7xl mx-auto">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Publish Exam Results</h1>
            <p class="text-sm text-gray-500 mt-1">Enter marks and publish results for paid applicants</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="applications.php" class="text-sm font-semibold text-gray-600 hover:text-gray-900 bg-white border border-gray-200 px-4 py-2 rounded-lg hover:border-gray-300 transition">← Applications</a>
        </div>
    </div>

    <!-- Success Toast -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'published'): ?>
    <div class="flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3 rounded-xl shadow-sm mb-6" id="toast_pub">
        <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="font-semibold text-sm">Successfully published <?php echo (int)($_GET['count'] ?? 0); ?> result(s).</span>
        <button onclick="document.getElementById('toast_pub').remove()" class="ml-auto text-emerald-400 hover:text-emerald-600">&times;</button>
    </div>
    <script>setTimeout(() => document.getElementById('toast_pub')?.remove(), 5000)</script>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Paid</p>
            <p class="text-2xl font-black text-gray-900 mt-1"><?php echo $stats['total']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-emerald-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Passed</p>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?php echo $stats['passed']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-red-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-red-400 uppercase tracking-wider">Failed</p>
            <p class="text-2xl font-black text-red-600 mt-1"><?php echo $stats['failed']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-amber-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Waitlisted</p>
            <p class="text-2xl font-black text-amber-600 mt-1"><?php echo $stats['waitlisted']; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-indigo-100 p-4 shadow-sm">
            <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider">Published</p>
            <p class="text-2xl font-black text-indigo-600 mt-1"><?php echo $stats['total_published']; ?></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-100 rounded-xl p-4 mb-6 shadow-sm flex flex-col md:flex-row gap-3 items-end">
        <form method="GET" class="flex flex-wrap gap-3 items-end flex-1">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Class</label>
                <select name="class" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white font-semibold focus:border-indigo-400 outline-none">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" <?php if($filter_class === $c) echo 'selected'; ?>><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Status</label>
                <select name="result_filter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white font-semibold focus:border-indigo-400 outline-none">
                    <option value="unpublished" <?php if($filter_status==='unpublished') echo 'selected'; ?>>Unpublished</option>
                    <option value="published" <?php if($filter_status==='published') echo 'selected'; ?>>Published</option>
                    <option value="all" <?php if($filter_status==='all') echo 'selected'; ?>>All</option>
                </select>
            </div>
            <button type="submit" class="bg-gray-900 text-white font-bold px-5 py-2 rounded-lg text-sm hover:bg-gray-800 transition">Filter</button>
            
            <div class="flex items-center gap-3 ml-4 border-l border-gray-200 pl-4">
                <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-widest">Hide Marks in Print</span>
                <label class="relative inline-flex items-center cursor-pointer mt-0.5">
                    <input type="checkbox" id="hide_marks_chk" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-indigo-300 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                </label>
            </div>
            
            <a href="#" onclick="this.href='print_result_list.php?<?php echo http_build_query($_GET); ?>&hide_marks=' + (document.getElementById('hide_marks_chk').checked ? '1' : '0')" target="_blank" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold px-5 py-2 rounded-lg text-sm border border-indigo-200 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print List
            </a>
        </form>
        <div class="text-sm text-gray-400">Pass threshold: <strong class="text-gray-700"><?php echo $pass_pct; ?>%</strong></div>
    </div>

    <?php if (empty($students)): ?>
    <div class="bg-white border border-gray-100 rounded-xl p-12 text-center shadow-sm">
        <svg class="w-16 h-16 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
        <h3 class="font-bold text-gray-500 text-lg">No Applicants Found</h3>
        <p class="text-sm text-gray-400 mt-1">No paid applicants match your current filters.</p>
    </div>
    <?php else: ?>

    <!-- Bulk Form -->
    <form method="POST" id="bulkForm">
        <input type="hidden" name="bulk_publish" value="1">

        <div class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
            
            <!-- Table Header Actions -->
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Select All</span>
                    </label>
                    <span class="text-xs text-gray-400">| <?php echo count($students); ?> applicant(s)</span>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="send_emails" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-medium text-gray-600">Send emails</span>
                    </label>
                    <button type="submit" class="bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white font-bold px-5 py-2 rounded-lg text-sm shadow-md transition inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        Publish Selected
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="w-8 px-3 py-3"></th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Roll No</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Student Name</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Class</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-24">Marks</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-20">Total</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-20">%</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-32">Status</th>
                            <th class="px-3 py-3 text-left text-[10px] font-bold text-gray-400 uppercase tracking-wider">Remarks</th>
                            <th class="px-3 py-3 text-center text-[10px] font-bold text-gray-400 uppercase tracking-wider w-12">View</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($students as $s): 
                            $is_published = !empty($s['result_published_at']) && ($s['result_status'] ?? 'Pending') !== 'Pending';
                            $row_marks = $s['marks_obtained'] ?? '';
                            $row_total = $s['total_marks'] ?? 100;
                            $row_status = $s['result_status'] ?? 'Pending';
                            $row_remarks = $s['result_remarks'] ?? '';
                            $row_pct = ($row_total > 0 && $row_marks !== '' && $row_marks !== null) ? round(((float)$row_marks / (float)$row_total) * 100, 1) : '';
                            
                            $status_dot = '';
                            if ($is_published) {
                                if ($row_status === 'Pass') $status_dot = 'bg-emerald-400';
                                elseif ($row_status === 'Fail') $status_dot = 'bg-red-400';
                                else $status_dot = 'bg-amber-400';
                            }
                        ?>
                        <tr class="hover:bg-gray-50/50 transition <?php echo $is_published ? 'bg-gray-50/30' : ''; ?>" data-id="<?php echo $s['id']; ?>">
                            <td class="px-3 py-3 text-center">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" class="student-check rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php if(!$is_published) echo 'checked'; ?>>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <?php if ($status_dot): ?><div class="w-2 h-2 rounded-full <?php echo $status_dot; ?>"></div><?php endif; ?>
                                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($s['entrance_roll_no'] ?? 'N/A'); ?></span>
                                </div>
                            </td>
                            <td class="px-3 py-3 font-semibold text-gray-700"><?php echo htmlspecialchars($s['student_first_name'] . ' ' . $s['student_last_name']); ?></td>
                            <td class="px-3 py-3 text-gray-500">
                                <?php echo htmlspecialchars($s['applied_class']); ?>
                                <?php if($s['faculty_name']): ?><span class="text-gray-400"> — <?php echo htmlspecialchars($s['faculty_name']); ?></span><?php endif; ?>
                            </td>
                            <td class="px-3 py-3">
                                <input type="number" step="0.01" name="marks[<?php echo $s['id']; ?>]" value="<?php echo htmlspecialchars($row_marks); ?>" 
                                    class="marks-input w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center font-bold focus:border-indigo-400 focus:ring-1 focus:ring-indigo-100 outline-none" 
                                    placeholder="—" data-total-input="totals[<?php echo $s['id']; ?>]">
                            </td>
                            <td class="px-3 py-3">
                                <input type="number" step="0.01" name="totals[<?php echo $s['id']; ?>]" value="<?php echo htmlspecialchars($row_total); ?>" 
                                    class="total-input w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center font-bold focus:border-indigo-400 focus:ring-1 focus:ring-indigo-100 outline-none">
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="pct-display text-sm font-bold <?php echo ($row_pct !== '' && $row_pct >= $pass_pct) ? 'text-emerald-600' : 'text-red-500'; ?>"><?php echo $row_pct !== '' ? $row_pct . '%' : '—'; ?></span>
                            </td>
                            <td class="px-3 py-3">
                                <select name="statuses[<?php echo $s['id']; ?>]" class="status-select w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs font-bold focus:border-indigo-400 outline-none bg-white">
                                    <option value="Pass" <?php if($row_status==='Pass') echo 'selected'; ?>>Pass</option>
                                    <option value="Fail" <?php if($row_status==='Fail') echo 'selected'; ?>>Fail</option>
                                    <option value="Waitlisted" <?php if($row_status==='Waitlisted') echo 'selected'; ?>>Waitlisted</option>
                                </select>
                            </td>
                            <td class="px-3 py-3">
                                <input type="text" name="remarks[<?php echo $s['id']; ?>]" value="<?php echo htmlspecialchars($row_remarks); ?>" 
                                    class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:border-indigo-400 outline-none" placeholder="Optional">
                            </td>
                            <td class="px-3 py-3 text-center">
                                <a href="view_application.php?id=<?php echo $s['id']; ?>" target="_blank" class="text-indigo-500 hover:text-indigo-700 transition" title="View Details">
                                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    <?php endif; ?>

</div>

</div></main></div>

<script>
// Select all
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.student-check').forEach(cb => cb.checked = this.checked);
});

// Auto-calculate percentage and auto-set status on marks change
const passPct = <?php echo $pass_pct; ?>;

document.querySelectorAll('.marks-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        const totalInput = row.querySelector('.total-input');
        const pctSpan = row.querySelector('.pct-display');
        const statusSelect = row.querySelector('.status-select');
        
        const marks = parseFloat(this.value) || 0;
        const total = parseFloat(totalInput.value) || 100;
        
        if (marks > 0 && total > 0) {
            const pct = Math.round((marks / total) * 1000) / 10;
            pctSpan.textContent = pct + '%';
            pctSpan.className = 'pct-display text-sm font-bold ' + (pct >= passPct ? 'text-emerald-600' : 'text-red-500');
            
            // Auto-set status
            statusSelect.value = pct >= passPct ? 'Pass' : 'Fail';
        } else {
            pctSpan.textContent = '—';
            pctSpan.className = 'pct-display text-sm font-bold text-gray-300';
        }
    });
});

document.querySelectorAll('.total-input').forEach(input => {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        row.querySelector('.marks-input').dispatchEvent(new Event('input'));
    });
});
</script>

</body>
</html>
