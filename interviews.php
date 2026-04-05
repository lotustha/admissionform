<?php
// interviews.php — Interview Management
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Academic Staff'])) {
    header("Location: dashboard.php");
    exit;
}

// Handle AJAX Update (Single)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_interview') {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['interview_status'] ?? 'Pending';
    $date = empty($_POST['interview_date']) ? null : $_POST['interview_date'];
    $time = empty($_POST['interview_time']) ? null : $_POST['interview_time'];
    $remarks = $_POST['interview_remarks'] ?? '';

    $stmt = $pdo->prepare("UPDATE admission_inquiries SET interview_status=?, interview_date=?, interview_time=?, interview_remarks=? WHERE id=?");
    if ($stmt->execute([$status, $date, $time, $remarks, $id])) {
        sendInterviewEmail($pdo, $id);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Handle AJAX Bulk Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update_interview') {
    $ids = explode(',', $_POST['ids'] ?? '');
    $status = $_POST['interview_status'] ?? 'Pending';
    $date = empty($_POST['interview_date']) ? null : $_POST['interview_date'];
    $time = empty($_POST['interview_time']) ? null : $_POST['interview_time'];
    $remarks = $_POST['interview_remarks'] ?? '';

    $successCount = 0;
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) continue;
        $stmt = $pdo->prepare("UPDATE admission_inquiries SET interview_status=?, interview_date=?, interview_time=?, interview_remarks=? WHERE id=?");
        if ($stmt->execute([$status, $date, $time, $remarks, $id])) {
            sendInterviewEmail($pdo, $id);
            $successCount++;
        }
    }
    echo json_encode(['success' => true, 'count' => $successCount]);
    exit;
}

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'i.id';
$sort_dir = (($_GET['sort_dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, (int)($_GET['limit'] ?? 25));

$allowed_sorts = ['i.id', 'i.student_first_name', 'i.applied_class', 'i.interview_status', 'i.interview_date'];
if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'i.id';

// Eligible for interview: Passed Exam
$where = ["form_type = 'Admission' AND result_status = 'Pass'"];
$params = [];

if ($search !== '') {
    $where[] = "(student_first_name LIKE ? OR student_last_name LIKE ? OR entrance_roll_no LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($status_filter !== '') {
    $where[] = "interview_status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where);
$offset = ($page - 1) * $limit;

$total = $pdo->prepare("SELECT COUNT(*) FROM admission_inquiries i WHERE $where_sql");
$total->execute($params);
$total_records = $total->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id WHERE $where_sql ORDER BY $sort_by $sort_dir LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function q_url($updates) { return '?' . http_build_query(array_merge($_GET, $updates)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 pb-10">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-[1400px] mx-auto p-4 sm:p-6 lg:p-10">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Interview Management</h1>
            <p class="text-sm font-medium text-gray-500 mt-2">Manage schedules and feedback for candidates who cleared the entrance exam.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 mb-8 flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or Roll No" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm transition">
        </div>
        <div class="w-40">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Status</label>
            <select name="status_filter" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm outline-none bg-white focus:ring-2 focus:ring-emerald-500 shadow-sm transition">
                <option value="">All Statuses</option>
                <?php foreach (['Pending', 'Scheduled', 'Selected', 'Rejected', 'Waitlisted'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php if ($status_filter === $s) echo 'selected'; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm transition">Filter</button>
            <a href="interviews.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm transition inline-flex items-center">Clear</a>
        </div>
    </form>

    <!-- Bulk Action Toolbar -->
    <div id="bulk-toolbar" class="hidden bg-indigo-50 border border-indigo-100 rounded-2xl shadow-sm p-4 mb-6 flex items-center justify-between transition-all">
        <div class="flex items-center gap-4">
            <span class="font-bold text-indigo-800 bg-white shadow-sm border border-indigo-200 py-1.5 px-3 rounded-lg"><span id="selected-count">0</span> Selected</span>
            <button type="button" onclick="openBulkModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-bold shadow transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Bulk Schedule & Email
            </button>
        </div>
        <button type="button" onclick="clearSelection()" class="text-indigo-500 hover:text-indigo-800 text-sm font-bold bg-white px-3 py-1.5 rounded border border-transparent hover:border-indigo-200 transition">Clear Selection</button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-10">
        <div class="px-5 py-4 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
            <span class="text-sm font-bold text-gray-600">Candidates Found: <span class="text-emerald-600"><?php echo number_format($total_records); ?></span></span>
        </div>
        <div class="overflow-x-auto min-h-[400px]">
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-widest font-bold border-b border-gray-100">
                    <tr>
                        <th class="px-5 py-4 w-12 text-center"><input type="checkbox" id="selectAll" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer"></th>
                        <th class="px-5 py-4 text-left">Roll No</th>
                        <th class="px-5 py-4 text-left">Candidate Name</th>
                        <th class="px-5 py-4 text-left">Class</th>
                        <th class="px-5 py-4 text-left">Interview Date/Time</th>
                        <th class="px-5 py-4 text-left">Status</th>
                        <th class="px-5 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-16 text-center text-gray-400">
                            <span class="text-gray-500 font-medium text-base">No candidates found for interview yet.</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $badge = ['Pending' => 'bg-yellow-100 text-yellow-800', 'Scheduled' => 'bg-indigo-100 text-indigo-800', 'Selected' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Waitlisted' => 'bg-orange-100 text-orange-800'];
                        $bc = $badge[$r['interview_status'] ?? 'Pending'] ?? 'bg-gray-100 text-gray-600';
                    ?>
                    <tr class="hover:bg-emerald-50/50 transition-colors cursor-pointer group row-item" onclick='openModal(<?php echo json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                        <td class="px-5 py-4 text-center" onclick="event.stopPropagation()"><input type="checkbox" class="row-checkbox rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 w-4 h-4 cursor-pointer" value="<?php echo $r['id']; ?>"></td>
                        <td class="px-5 py-4 whitespace-nowrap font-bold text-gray-800"><?php echo htmlspecialchars($r['entrance_roll_no'] ?? ''); ?></td>
                        <td class="px-5 py-4">
                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($r['student_first_name'] . ' ' . $r['student_last_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($r['student_email'] ?? $r['father_contact'] ?? ''); ?></div>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap font-medium text-gray-700">
                            <?php echo htmlspecialchars($r['applied_class'] . ($r['faculty_name'] ? " - {$r['faculty_name']}" : '')); ?>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <?php if (!empty($r['interview_date'])): ?>
                                <span class="block text-gray-900 font-medium"><?php echo htmlspecialchars($r['interview_date']); ?></span>
                                <span class="text-xs text-gray-500 font-medium"><?php echo htmlspecialchars($r['interview_time'] ?? ''); ?></span>
                            <?php else: ?>
                                <span class="text-gray-400 italic font-medium text-xs">Not Scheduled</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2.5 py-1 rounded-full font-bold text-[11px] uppercase tracking-wider <?php echo $bc; ?>">
                                <?php echo htmlspecialchars($r['interview_status'] ?? 'Pending'); ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 whitespace-nowrap text-right">
                            <button onclick="event.stopPropagation(); openModal(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>)" class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-md transition-colors font-semibold text-xs inline-flex items-center gap-1 border border-emerald-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                Manage
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
            <div class="text-sm text-gray-500 font-medium">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></div>
            <div class="flex gap-1">
                <?php if($page > 1): ?>
                    <a href="<?php echo q_url(['page' => $page - 1]); ?>" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-600 rounded-md text-sm font-medium hover:bg-gray-50 transition">Prev</a>
                <?php endif; ?>
                <?php if($page < $total_pages): ?>
                    <a href="<?php echo q_url(['page' => $page + 1]); ?>" class="px-3 py-1.5 bg-white border border-gray-300 text-gray-600 rounded-md text-sm font-medium hover:bg-gray-50 transition">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Interview Modal -->
<div id="interview-modal" class="fixed inset-0 z-50 hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col transform transition-all scale-95 opacity-0" id="modal-box">
        <form id="interview-form" onsubmit="submitInterview(event)">
            <input type="hidden" name="id" id="modal-id">
            <input type="hidden" name="action" value="update_interview">
            
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-2xl">
                <div>
                    <h3 class="text-xl font-bold text-gray-900" id="modal-title">Manage Interview</h3>
                    <p class="text-sm font-medium text-gray-500" id="modal-subtitle">Candidate: ...</p>
                </div>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 bg-white shadow-sm border border-gray-200 p-2 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-5 flex-1">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Schedule Date</label>
                        <input type="date" name="interview_date" id="modal-date" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-gray-50 focus:bg-white transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Schedule Time</label>
                        <input type="time" name="interview_time" id="modal-time" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-gray-50 focus:bg-white transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Final Status</label>
                    <select name="interview_status" id="modal-status" class="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500 text-sm font-medium shadow-sm transition cursor-pointer">
                        <option value="Pending">Pending (Not Scheduled)</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Waitlisted">Waitlisted / On Hold</option>
                        <option value="Selected">Selected (Admit Ready)</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Interview Remarks / Notes</label>
                    <textarea name="interview_remarks" id="modal-remarks" rows="3" class="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-gray-50 focus:bg-white transition placeholder-gray-400" placeholder="E.g., Strong communication skills, approved for scholarship..."></textarea>
                </div>
            </div>
            
            <div class="p-5 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <a href="#" id="modal-app-link" target="_blank" class="px-5 py-2.5 bg-white text-emerald-700 font-bold rounded-lg border border-emerald-200 hover:bg-emerald-50 transition shadow-sm text-sm">View Full Application</a>
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-white text-gray-700 font-bold rounded-lg border border-gray-300 hover:bg-gray-50 transition shadow-sm text-sm">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-md transition text-sm flex items-center">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Interview Modal -->
<div id="bulk-modal" class="fixed inset-0 z-50 hidden bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col transform transition-all scale-95 opacity-0" id="bulk-modal-box">
        <form id="bulk-interview-form" onsubmit="submitBulkInterview(event)">
            <input type="hidden" name="ids" id="bulk-modal-ids">
            <input type="hidden" name="action" value="bulk_update_interview">
            
            <div class="p-6 border-b border-indigo-100 flex justify-between items-center bg-indigo-50 rounded-t-2xl">
                <div>
                    <h3 class="text-xl font-bold text-indigo-900">Bulk Manage Interviews</h3>
                    <p class="text-sm font-medium text-indigo-700" id="bulk-modal-subtitle">0 Candidates Selected</p>
                </div>
                <button type="button" onclick="closeBulkModal()" class="text-indigo-400 hover:text-indigo-600 bg-white shadow-sm border border-indigo-200 p-2 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 space-y-5 flex-1">
                <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm mb-2 flex items-start gap-3">
                    <svg class="w-5 h-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <span>This will simultaneously update the status and immediately email all selected candidates.</span>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Schedule Date</label>
                        <input type="date" name="interview_date" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-indigo-500 text-sm bg-gray-50 focus:bg-white transition" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Schedule Time</label>
                        <input type="time" name="interview_time" class="w-full border border-gray-300 rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-indigo-500 text-sm bg-gray-50 focus:bg-white transition" required>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Final Status</label>
                    <select name="interview_status" class="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm font-medium shadow-sm transition cursor-pointer">
                        <option value="Scheduled">Scheduled</option>
                        <option value="Pending">Pending (Not Scheduled)</option>
                        <option value="Waitlisted">Waitlisted / On Hold</option>
                        <option value="Selected">Selected (Admit Ready)</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Common Remarks / Notes</label>
                    <textarea name="interview_remarks" rows="2" class="w-full border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm bg-gray-50 focus:bg-white transition placeholder-gray-400" placeholder="These remarks will apply to all selected attendees... (Optional)"></textarea>
                </div>
            </div>
            
            <div class="p-5 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" onclick="closeBulkModal()" class="px-5 py-2.5 bg-white text-gray-700 font-bold rounded-lg border border-gray-300 hover:bg-gray-50 transition shadow-sm text-sm">Cancel</button>
                <button type="submit" id="bulk-submit-btn" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-md transition text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Apply Bulk & Email
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('interview-modal');
    const modalBox = document.getElementById('modal-box');

    function openModal(data) {
        document.getElementById('modal-subtitle').textContent = `Candidate: ${data.student_first_name} ${data.student_last_name} (${data.entrance_roll_no})`;
        document.getElementById('modal-id').value = data.id;
        document.getElementById('modal-date').value = data.interview_date || '';
        document.getElementById('modal-time').value = data.interview_time || '';
        document.getElementById('modal-status').value = data.interview_status || 'Pending';
        document.getElementById('modal-remarks').value = data.interview_remarks || '';
        document.getElementById('modal-app-link').href = `view_application.php?id=${data.id}`;

        modal.classList.remove('hidden');
        setTimeout(() => {
            modalBox.classList.remove('scale-95', 'opacity-0');
            modalBox.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeModal() {
        modalBox.classList.remove('scale-100', 'opacity-100');
        modalBox.classList.add('scale-95', 'opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 200);
    }

    async function submitInterview(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving & Emailing...`;
        btn.disabled = true;

        const formData = new FormData(form);

        try {
            const res = await fetch('interviews.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = `Save Changes`; btn.disabled = false;
            }
        } catch(err) {
            alert('Failed to connect to server.');
            btn.innerHTML = `Save Changes`; btn.disabled = false;
        }
    }

    // Bulk Management
    const selectAll = document.getElementById('selectAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkToolbar = document.getElementById('bulk-toolbar');
    const selectedCountEl = document.getElementById('selected-count');

    function updateBulkToolbar() {
        const selected = document.querySelectorAll('.row-checkbox:checked').length;
        selectedCountEl.textContent = selected;
        if (selected > 0) {
            bulkToolbar.classList.remove('hidden');
        } else {
            bulkToolbar.classList.add('hidden');
            selectAll.checked = false;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', e => {
            rowCheckboxes.forEach(cb => cb.checked = e.target.checked);
            updateBulkToolbar();
        });
    }

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const allChecked = document.querySelectorAll('.row-checkbox:checked').length === rowCheckboxes.length;
            selectAll.checked = allChecked;
            updateBulkToolbar();
        });
    });

    function clearSelection() {
        rowCheckboxes.forEach(cb => cb.checked = false);
        selectAll.checked = false;
        updateBulkToolbar();
    }

    const bulkModal = document.getElementById('bulk-modal');
    const bulkModalBox = document.getElementById('bulk-modal-box');

    function openBulkModal() {
        const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;
        
        document.getElementById('bulk-modal-ids').value = selected.join(',');
        document.getElementById('bulk-modal-subtitle').textContent = selected.length + ' Candidates Selected';
        
        bulkModal.classList.remove('hidden');
        setTimeout(() => {
            bulkModalBox.classList.remove('scale-95', 'opacity-0');
            bulkModalBox.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeBulkModal() {
        bulkModalBox.classList.remove('scale-100', 'opacity-100');
        bulkModalBox.classList.add('scale-95', 'opacity-0');
        setTimeout(() => bulkModal.classList.add('hidden'), 200);
    }

    async function submitBulkInterview(e) {
        e.preventDefault();
        const form = e.target;
        const btn = document.getElementById('bulk-submit-btn');
        const ogText = btn.innerHTML;
        btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline ml-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing Emails...`;
        btn.disabled = true;

        const formData = new FormData(form);

        try {
            const res = await fetch('interviews.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
                btn.innerHTML = ogText; btn.disabled = false;
            }
        } catch(err) {
            alert('Failed to connect to server.');
            btn.innerHTML = ogText; btn.disabled = false;
        }
    }
</script>
</body>
</html>
