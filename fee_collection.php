<?php
// fee_collection.php — Exam Fee Collection Center
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Cashier'])) {
    header("Location: dashboard.php");
    exit;
}

$settings = getSchoolSettings($pdo);
$msg = '';

// Handle Mark as Paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $pay_id  = (int)$_POST['pay_id'];
    $amount  = (float)$_POST['amount'];
    $ref     = trim($_POST['reference'] ?? '');
    $method  = trim($_POST['payment_method'] ?? 'Cash');

    if ($pay_id > 0 && $amount > 0) {
        $pdo->prepare("UPDATE admission_inquiries SET payment_status='Paid', payment_amount=?, payment_reference=?, payment_method=?, payment_date=NOW() WHERE id=?")
            ->execute([$amount, $ref, $method, $pay_id]);
        
        // Send Admit Card + Receipt email to student & institute
        sendPaymentConfirmationEmail($pdo, $pay_id);
        
        $msg = 'paid';
    }
}

// Handle Disable / Enable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_disable'])) {
    $tog_id = (int)$_POST['tog_id'];
    // We use status='Disabled' as the disabled flag
    $row = $pdo->prepare("SELECT status FROM admission_inquiries WHERE id=?");
    $row->execute([$tog_id]);
    $cur = $row->fetchColumn();
    $new_status = ($cur === 'Disabled') ? 'Pending' : 'Disabled';
    $pdo->prepare("UPDATE admission_inquiries SET status=? WHERE id=?")->execute([$new_status, $tog_id]);
    $msg = ($new_status === 'Disabled') ? 'disabled' : 'enabled';
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_record'])) {
    $del_id = (int)$_POST['del_id'];
    if ($del_id > 0) {
        $pdo->prepare("DELETE FROM admission_inquiries WHERE id=?")->execute([$del_id]);
        $msg = 'deleted';
    }
}

// Handle search
$search = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'unpaid'; // 'unpaid' | 'paid' | 'all'

$where_clauses = ["form_type = 'Admission'"];
$params = [];

if ($filter === 'unpaid') {
    $where_clauses[] = "(payment_status IS NULL OR payment_status = '' OR payment_status = 'Unpaid' OR payment_status = 'Pending')";
} elseif ($filter === 'paid') {
    $where_clauses[] = "payment_status = 'Paid'";
}

if (!empty($search)) {
    $where_clauses[] = "(student_first_name LIKE ? OR student_last_name LIKE ? OR entrance_roll_no LIKE ? OR student_email LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s);
}

$where_sql = implode(' AND ', $where_clauses);

try {
    $stmt = $pdo->prepare("SELECT * FROM admission_inquiries WHERE $where_sql ORDER BY submission_date DESC LIMIT 60");
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (Exception $e) { $students = []; }

// Revenue summary
try {
    $total_collected = $pdo->query("SELECT COALESCE(SUM(payment_amount),0) FROM admission_inquiries WHERE payment_status='Paid'")->fetchColumn();
    $total_paid_count = $pdo->query("SELECT COUNT(*) FROM admission_inquiries WHERE payment_status='Paid' AND form_type='Admission'")->fetchColumn();
    $total_unpaid_count = $pdo->query("SELECT COUNT(*) FROM admission_inquiries WHERE form_type='Admission' AND (payment_status IS NULL OR payment_status='' OR payment_status='Unpaid' OR payment_status='Pending')")->fetchColumn();
} catch (Exception $e) {
    $total_collected = 0; $total_paid_count = 0; $total_unpaid_count = 0;
}

// Recent payments log
try {
    $recent_payments = $pdo->query("SELECT * FROM admission_inquiries WHERE payment_status='Paid' ORDER BY submission_date DESC LIMIT 8")->fetchAll();
} catch (Exception $e) { $recent_payments = []; }

$app_fee = (float)($settings['application_fee'] ?? 500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Fee Collection — <?php echo htmlspecialchars($settings['school_name'] ?? 'Admin'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 font-sans">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-6xl mx-auto">

    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <span class="w-9 h-9 rounded-xl bg-teal-600 text-white flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </span>
                Fee Collection Center
            </h1>
            <p class="text-sm text-gray-400 mt-1">Search applicants and record offline exam fee payments.</p>
        </div>
    </div>

    <?php if ($msg === 'paid'): ?>
    <div class="mb-5 flex items-center gap-3 bg-teal-50 border border-teal-200 text-teal-800 px-5 py-3 rounded-xl shadow-sm" id="success_banner">
        <svg class="w-5 h-5 text-teal-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="font-semibold">Payment recorded successfully!</span>
        <button onclick="document.getElementById('success_banner').remove()" class="ml-auto text-teal-500 hover:text-teal-700">&times;</button>
    </div>
    <script>setTimeout(()=>document.getElementById('success_banner')?.remove(), 4000)</script>
    <?php elseif ($msg === 'disabled'): ?>
    <div class="mb-5 flex items-center gap-3 bg-amber-50 border border-amber-200 text-amber-800 px-5 py-3 rounded-xl shadow-sm" id="success_banner">
        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
        <span class="font-semibold">Student disabled. They can no longer log in to their dashboard.</span>
        <button onclick="document.getElementById('success_banner').remove()" class="ml-auto text-amber-500 hover:text-amber-700">&times;</button>
    </div>
    <script>setTimeout(()=>document.getElementById('success_banner')?.remove(), 5000)</script>
    <?php elseif ($msg === 'enabled'): ?>
    <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3 rounded-xl shadow-sm" id="success_banner">
        <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="font-semibold">Student re-enabled successfully.</span>
        <button onclick="document.getElementById('success_banner').remove()" class="ml-auto text-emerald-500 hover:text-emerald-700">&times;</button>
    </div>
    <script>setTimeout(()=>document.getElementById('success_banner')?.remove(), 4000)</script>
    <?php elseif ($msg === 'deleted'): ?>
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-5 py-3 rounded-xl shadow-sm" id="success_banner">
        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        <span class="font-semibold">Record deleted permanently.</span>
        <button onclick="document.getElementById('success_banner').remove()" class="ml-auto text-red-500 hover:text-red-700">&times;</button>
    </div>
    <script>setTimeout(()=>document.getElementById('success_banner')?.remove(), 4000)</script>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-7">
        <div class="bg-gradient-to-br from-teal-500 to-emerald-600 rounded-2xl p-5 text-white shadow-md">
            <div class="text-xs font-bold uppercase tracking-widest opacity-75 mb-1">Total Collected</div>
            <div class="text-3xl font-black">Rs. <?php echo number_format($total_collected); ?></div>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-100 p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div class="text-2xl font-black text-gray-900"><?php echo number_format($total_paid_count); ?></div>
                <div class="text-xs text-gray-500 font-semibold">Fees Collected</div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-rose-100 p-5 shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div class="text-2xl font-black text-gray-900"><?php echo number_format($total_unpaid_count); ?></div>
                <div class="text-xs text-gray-500 font-semibold">Pending Payments</div>
            </div>
        </div>
    </div>

    <!-- Search + Filter Bar -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm mb-5 p-5">
        <form method="GET" class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, roll no or email..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none">
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php foreach (['unpaid' => 'Unpaid', 'paid' => 'Paid', 'all' => 'All'] as $f => $label): ?>
                <a href="?filter=<?php echo $f; ?>&q=<?php echo urlencode($search); ?>" class="px-4 py-2.5 rounded-xl text-xs font-bold transition <?php echo $filter === $f ? 'bg-teal-600 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                    <?php echo $label; ?>
                </a>
                <?php endforeach; ?>
                <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-700 transition">Search</button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Student Table -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900">Applicants</h2>
                    <span class="text-xs text-gray-400"><?php echo count($students); ?> result(s)</span>
                </div>
                <?php if (empty($students)): ?>
                <div class="p-10 text-center text-gray-400 text-sm">No applicants found matching your search.</div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-50">
                        <thead class="text-[11px] text-gray-400 uppercase tracking-wider bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left">Roll No.</th>
                                <th class="px-4 py-3 text-left">Class</th>
                                <th class="px-4 py-3 text-left">Fee Status</th>
                                <th class="px-4 py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($students as $s):
                                $is_paid = ($s['payment_status'] === 'Paid');
                                $is_disabled = ($s['status'] === 'Disabled');
                            ?>
                            <tr class="hover:bg-gray-50 transition group <?php echo $is_disabled ? 'opacity-60' : ''; ?>" id="row_<?php echo $s['id']; ?>">
                                <td class="px-5 py-3.5">
                                    <a href="view_application.php?id=<?php echo $s['id']; ?>" class="font-semibold text-emerald-700 hover:text-emerald-900 hover:underline text-sm transition">
                                        <?php echo htmlspecialchars($s['student_first_name'].' '.$s['student_last_name']); ?>
                                    </a>
                                    <div class="text-[11px] text-gray-400"><?php echo htmlspecialchars($s['student_email'] ?? ''); ?></div>
                                    <?php if ($is_disabled): ?>
                                        <span class="inline-block mt-0.5 text-[10px] bg-gray-200 text-gray-600 font-bold px-1.5 py-0.5 rounded">DISABLED</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5 font-mono font-bold text-emerald-700 text-sm"><?php echo htmlspecialchars($s['entrance_roll_no'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-3.5 text-gray-600 text-xs font-medium"><?php echo htmlspecialchars($s['applied_class'] ?? ''); ?></td>
                                <td class="px-4 py-3.5">
                                    <?php if ($is_paid): ?>
                                        <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 text-xs font-bold px-2.5 py-1 rounded-full">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                            Paid
                                        </span>
                                        <div class="text-[10px] text-gray-400 mt-0.5">Rs. <?php echo number_format($s['payment_amount']); ?></div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 bg-rose-100 text-rose-700 text-xs font-bold px-2.5 py-1 rounded-full">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                        <?php if (!$is_paid): ?>
                                        <button onclick="openModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['student_first_name'].' '.$s['student_last_name'])); ?>', <?php echo $app_fee; ?>)"
                                            title="Mark as Paid"
                                            class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                            Paid
                                        </button>
                                        <?php else: ?>
                                        <a href="print_receipt.php?id=<?php echo $s['id']; ?>" target="_blank"
                                            title="Print Receipt"
                                            class="bg-indigo-100 hover:bg-indigo-200 text-indigo-700 text-xs font-bold px-2.5 py-1.5 rounded-lg transition inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                            Receipt
                                        </a>
                                        <?php endif; ?>

                                        <!-- Disable / Enable -->
                                        <form method="POST" class="inline m-0">
                                            <input type="hidden" name="toggle_disable" value="1">
                                            <input type="hidden" name="tog_id" value="<?php echo $s['id']; ?>">
                                            <button type="submit"
                                                title="<?php echo $is_disabled ? 'Enable student' : 'Disable student'; ?>"
                                                class="text-xs font-bold px-2.5 py-1.5 rounded-lg transition <?php echo $is_disabled ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'; ?>">
                                                <?php echo $is_disabled ? 'Enable' : 'Disable'; ?>
                                            </button>
                                        </form>

                                        <!-- Delete -->
                                        <button onclick="confirmDelete(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['student_first_name'].' '.$s['student_last_name'])); ?>')"
                                            title="Delete record"
                                            class="bg-red-100 hover:bg-red-200 text-red-700 text-xs font-bold px-2.5 py-1.5 rounded-lg transition">
                                            Del
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments Log -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden sticky top-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900 text-sm">Recent Payments</h2>
                </div>
                <?php if (empty($recent_payments)): ?>
                <div class="p-6 text-center text-gray-400 text-xs">No payments recorded yet.</div>
                <?php else: ?>
                <ul class="divide-y divide-gray-50">
                    <?php foreach ($recent_payments as $rp): ?>
                    <li class="px-5 py-3.5 hover:bg-gray-50 transition">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <a href="view_application.php?id=<?php echo $rp['id']; ?>" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 hover:underline truncate block transition">
                                    <?php echo htmlspecialchars($rp['student_first_name'].' '.$rp['student_last_name']); ?>
                                </a>
                                <div class="text-[11px] text-gray-400 font-mono"><?php echo htmlspecialchars($rp['entrance_roll_no'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-black text-teal-700">Rs. <?php echo number_format($rp['payment_amount']); ?></div>
                                <div class="text-[10px] text-gray-400"><?php echo htmlspecialchars($rp['payment_method'] ?? 'Cash'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($rp['payment_reference'])): ?>
                        <div class="text-[10px] text-gray-400 mt-0.5 font-mono">Ref: <?php echo htmlspecialchars($rp['payment_reference']); ?></div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
</main></div>

<!-- Mark as Paid Modal -->
<div id="payModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-7 relative">
        <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        <h2 class="text-xl font-black text-gray-900 mb-1">Record Payment</h2>
        <p class="text-sm text-gray-500 mb-5" id="modal_student_name">Student Name</p>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="mark_paid" value="1">
            <input type="hidden" name="pay_id" id="modal_pay_id">

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Amount (Rs.)</label>
                <input type="number" step="0.01" name="amount" id="modal_amount" required
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none font-bold text-gray-900">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                <select name="payment_method" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-400 outline-none">
                    <option value="Cash">Cash</option>
                    <option value="Bank Deposit">Bank Deposit</option>
                    <option value="eSewa">eSewa</option>
                    <option value="Khalti">Khalti</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Transaction / Reference No. <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="reference" placeholder="e.g. TXN123456"
                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:border-teal-400 focus:ring-2 focus:ring-teal-100 outline-none">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal()" class="px-5 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl shadow-sm transition">
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-7 relative">
        <div class="w-14 h-14 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </div>
        <h2 class="text-lg font-black text-gray-900 text-center mb-1">Delete Record?</h2>
        <p class="text-sm text-gray-500 text-center mb-1">You are about to permanently delete:</p>
        <p class="text-sm font-bold text-gray-800 text-center mb-5" id="delete_student_name"></p>
        <p class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg p-3 mb-5 text-center">This action <strong>cannot be undone</strong>. All application data for this student will be permanently erased.</p>
        <form method="POST" class="flex justify-center gap-3">
            <input type="hidden" name="delete_record" value="1">
            <input type="hidden" name="del_id" id="delete_modal_id">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 text-sm font-semibold rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition">Cancel</button>
            <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl shadow-sm transition">Yes, Delete</button>
        </form>
    </div>
</div>

<script>
function openModal(id, name, amount) {
    document.getElementById('modal_pay_id').value = id;
    document.getElementById('modal_student_name').textContent = name;
    document.getElementById('modal_amount').value = amount;
    document.getElementById('payModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('payModal').classList.add('hidden');
}
document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function confirmDelete(id, name) {
    document.getElementById('delete_modal_id').value = id;
    document.getElementById('delete_student_name').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>
</body>
</html>
