<?php
// exam_attendance.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Academic Staff'])) {
    header("Location: dashboard.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_entrance.php");
    exit;
}

$schedule_id = (int)$_GET['id'];

// AJAX Handler for QR Scanning & Quick Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan_attendance') {
    $roll = $_POST['roll'] ?? '';
    $status = $_POST['attendance_status'] ?? 'Present'; // 'Present' or 'Absent' or 'Pending'
    
    // Safety check verify roll belongs to this schedule
    $stmt = $pdo->prepare("SELECT id FROM admission_inquiries WHERE entrance_roll_no = ? AND schedule_id = ?");
    $stmt->execute([$roll, $schedule_id]);
    $inq = $stmt->fetch();
    
    if ($inq) {
        $pdo->prepare("UPDATE admission_inquiries SET attendance_status = ? WHERE id = ?")
            ->execute([$status, $inq['id']]);
        echo json_encode(['success' => true, 'status' => $status, 'message' => "Marked $status for Roll $roll"]);
    } else {
        echo json_encode(['success' => false, 'message' => "Roll $roll not found in this schedule."]);
    }
    exit;
}

// Get Schedule Details
$sched_stmt = $pdo->prepare("SELECT e.*, f.faculty_name FROM entrance_schedules e LEFT JOIN faculties f ON e.faculty_id = f.id WHERE e.id = ?");
$sched_stmt->execute([$schedule_id]);
$schedule = $sched_stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    header("Location: manage_entrance.php");
    exit;
}

// Filtering & Pagination Logic
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'i.entrance_roll_no';
$sort_dir = isset($_GET['sort_dir']) && $_GET['sort_dir'] === 'desc' ? 'DESC' : 'ASC';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;

$allowed_sorts = ['i.id', 'i.entrance_roll_no', 'i.student_first_name', 'i.status'];
if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'i.entrance_roll_no';

$where = ["i.schedule_id = ?"];
$params = [$schedule_id];

if ($search !== '') {
    $where[] = "(i.entrance_roll_no LIKE ? OR i.student_first_name LIKE ? OR i.student_last_name LIKE ? OR i.father_contact LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$where_sql = implode(' AND ', $where);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_exam_' . $schedule_id . '_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Entrance Roll No', 'First Name', 'Last Name', 'Gender', 'Phone', 'Status']);
    
    $export_stmt = $pdo->prepare("SELECT entrance_roll_no, student_first_name, student_last_name, gender, father_contact, guardian_contact, status FROM admission_inquiries i WHERE $where_sql ORDER BY $sort_by $sort_dir");
    $export_stmt->execute($params);
    
    while ($row = $export_stmt->fetch(PDO::FETCH_ASSOC)) {
        $phone = $row['father_contact'] ?: $row['guardian_contact'];
        fputcsv($output, [$row['entrance_roll_no'], $row['student_first_name'], $row['student_last_name'], $row['gender'], $phone, $row['status']]);
    }
    fclose($output);
    exit;
}

$offset = ($page - 1) * $limit;
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM admission_inquiries i WHERE $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

$stmt = $pdo->prepare("SELECT i.* FROM admission_inquiries i WHERE $where_sql ORDER BY $sort_by $sort_dir LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

function build_url($updates) {
    $qs = array_merge($_GET, $updates);
    unset($qs['msg'], $qs['export']);
    return '?' . http_build_query($qs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Attendance - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 min-h-screen pb-10">
    
    <!-- Navbar -->
    <nav class="bg-emerald-700 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-6">
                    <span class="font-bold text-xl tracking-tight">Admission Control</span>
                    <div class="hidden md:flex space-x-4">
                        <a href="dashboard.php" class="text-emerald-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">Applications</a>
                        <a href="manage_academics.php" class="text-emerald-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">Academics</a>
                        <a href="manage_entrance.php" class="bg-emerald-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">Entrance Exams</a>
                    </div>
                </div>
                <div>
                    <span class="mr-4 text-emerald-200 text-sm hidden sm:inline">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    <a href="dashboard.php?logout=1" class="bg-emerald-800 hover:bg-emerald-900 px-3 py-2 rounded-md text-sm font-medium transition-colors border border-emerald-600">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="mb-6 flex gap-4 items-center justify-between">
            <div>
                <a href="manage_entrance.php" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium flex items-center mb-2">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    Back to Entrance Exams
                </a>
                <h2 class="text-2xl font-bold text-gray-800">Exam Attendance List</h2>
                <div class="text-gray-600 mt-2 text-sm flex gap-4">
                    <span><strong>Class:</strong> <?php echo htmlspecialchars($schedule['class_name'] . ($schedule['faculty_name'] ? ' ('.$schedule['faculty_name'].')' : '')); ?></span>
                    <span><strong>Date/Time:</strong> <?php echo htmlspecialchars(date('M d, Y', strtotime($schedule['exam_date'])) . ' at ' . date('h:i A', strtotime($schedule['exam_time']))); ?></span>
                    <span><strong>Venue:</strong> <?php echo htmlspecialchars($schedule['venue']); ?></span>
                </div>
            </div>
            
        </div>
        
        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="id" value="<?php echo $schedule_id; ?>">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Search Students</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, Roll No, Phone..." class="w-full text-sm border-gray-300 rounded focus:ring-emerald-500 py-2 px-3 border outline-none">
                </div>
                <div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm font-medium h-[38px] transition-colors">Filter</button>
                    <a href="?id=<?php echo $schedule_id; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium h-[38px] inline-flex items-center ml-2 transition-colors">Clear</a>
                </div>
                <div class="ml-auto flex items-center">
                    <a href="print_attendance.php?id=<?php echo $schedule_id; ?>" target="_blank" class="bg-indigo-600 border border-indigo-700 text-white hover:bg-indigo-700 px-4 py-2 rounded text-sm font-medium shadow-sm transition-colors flex items-center h-[38px] mr-2">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Sheet
                    </a>
                    <a href="<?php echo build_url(['export'=>'csv']); ?>" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded text-sm font-medium shadow-sm transition-colors flex items-center h-[38px] mr-2">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Export CSV
                    </a>
                    <div class="flex rounded shadow-sm h-[38px]">
                        <button type="button" onclick="openScanner()" class="bg-emerald-600 border border-emerald-700 text-white hover:bg-emerald-700 px-4 py-2 rounded-l text-sm font-bold transition-colors flex items-center shadow-md">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Scan QR (PC)
                        </button>
                        <button type="button" onclick="linkCompanion()" class="bg-emerald-700 border border-emerald-800 text-white hover:bg-emerald-800 px-3 py-2 rounded-r text-sm font-bold transition-colors flex items-center border-l-0 shadow-md tooltip" title="Use Phone as Scanner">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
            <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                <h3 class="font-semibold text-gray-800">Booked Students</h3>
                <div class="text-sm text-gray-500">Total: <?php echo $total_records; ?> / <?php echo htmlspecialchars($schedule['total_capacity']); ?> Capacity</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php 
                            $headers = [
                                'i.entrance_roll_no' => 'Roll No',
                                'i.student_first_name' => 'Student Name',
                                '_gender' => 'Gender / DOB',
                                '_contact' => 'Contact',
                                'i.attendance_status' => 'Attendance'
                            ];
                            foreach($headers as $col => $label): 
                                if($col === '_gender' || $col === '_contact'):
                                    echo "<th class='px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs'>$label</th>";
                                else:
                                    $next_dir = ($sort_by === $col && $sort_dir === 'ASC') ? 'desc' : 'asc';
                                    $icon = ($sort_by === $col) ? ($sort_dir === 'ASC' ? ' ↑' : ' ↓') : '';
                            ?>
                            <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                                <a href="<?php echo build_url(['sort_by' => $col, 'sort_dir' => $next_dir]); ?>" class="hover:text-emerald-700 transition flex items-center group">
                                    <?php echo $label; ?><span class="text-emerald-500 ml-1 group-hover:opacity-100"><?php echo $icon; ?></span>
                                </a>
                            </th>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <th class="px-6 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach($students as $st): ?>
                        <tr class="hover:bg-emerald-50 transition-colors cursor-pointer group" onclick='openModal(<?php echo json_encode($st, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                            <td class="px-6 py-4 whitespace-nowrap font-semibold text-emerald-600 group-hover:text-emerald-800 transition-colors">
                                <?php echo htmlspecialchars($st['entrance_roll_no']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($st['student_first_name'] . ' ' . $st['student_last_name']); ?></div>
                                <div class="text-[11px] text-emerald-600 mt-1 opacity-0 group-hover:opacity-100 transition-opacity">Click to view details</div >
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?php echo htmlspecialchars($st['gender']); ?> <br>
                                <span class="text-xs"><?php echo htmlspecialchars($st['dob_bs']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                <?php echo htmlspecialchars($st['father_contact'] ?: $st['guardian_contact']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status = $st['attendance_status'] ?? 'Pending';
                                $badgeClass = "bg-gray-100 text-gray-800";
                                if($status === 'Pending') $badgeClass = "bg-yellow-100 text-yellow-800";
                                if($status === 'Present') $badgeClass = "bg-emerald-100 text-emerald-800";
                                if($status === 'Absent') $badgeClass = "bg-red-100 text-red-800";
                                ?>
                                <span id="badge-<?php echo $st['id']; ?>" class="px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full <?php echo $badgeClass; ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="event.stopPropagation(); markAttendance('<?php echo htmlspecialchars($st['entrance_roll_no']); ?>', 'Present')" class="text-emerald-600 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-md transition-colors mr-2 border border-emerald-200">
                                    Present
                                </button>
                                <button onclick="event.stopPropagation(); markAttendance('<?php echo htmlspecialchars($st['entrance_roll_no']); ?>', 'Absent')" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1 rounded-md transition-colors border border-red-200">
                                    Absent
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($students)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-gray-500 text-sm">
                                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                No students have booked this slot yet.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <?php if($total_pages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div class="text-sm text-gray-500">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?>
                </div>
                <div class="flex gap-1">
                    <?php if($page > 1): ?>
                        <a href="<?php echo build_url(['page' => $page - 1]); ?>" class="px-3 py-1 bg-white border border-gray-300 text-gray-600 rounded text-sm hover:bg-gray-50 transition">Prev</a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for($p = $start_page; $p <= $end_page; $p++): 
                    ?>
                        <a href="<?php echo build_url(['page' => $p]); ?>" class="px-3 py-1 border <?php echo $p === $page ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'; ?> rounded text-sm transition">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="<?php echo build_url(['page' => $page + 1]); ?>" class="px-3 py-1 bg-white border border-gray-300 text-gray-600 rounded text-sm hover:bg-gray-50 transition">Next</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Student Details Modal -->
    <div id="student-modal" class="fixed inset-0 z-50 hidden bg-black/60 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-all scale-95 opacity-0" id="modal-box">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50 rounded-t-xl">
                <div>
                    <h3 class="text-xl font-bold text-gray-800" id="modal-name">Student Details</h3>
                    <p class="text-sm text-gray-500 mt-1">Application Information</p>
                </div>
                <button onclick="closeModal()" class="text-gray-400 hover:text-red-500 bg-gray-200 hover:bg-red-50 p-2 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto" id="modal-content">
                <!-- Content injected here via JS -->
            </div>
            
            <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-3 rounded-b-xl">
                <a href="#" id="modal-view-app-btn" target="_blank" class="px-4 py-2 bg-blue-50 text-blue-700 font-medium rounded border border-blue-200 hover:bg-blue-100 transition shadow-sm">Full Application View</a>
                <button onclick="closeModal()" class="px-5 py-2 bg-white text-gray-700 font-medium rounded border border-gray-300 hover:bg-gray-50 transition shadow-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div id="scanner-modal" class="fixed inset-0 z-50 hidden bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all scale-95 opacity-0" id="scanner-box">
            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                <h3 class="font-bold text-emerald-800">Scan Admit Card QR</h3>
                <button onclick="closeScanner()" class="text-gray-400 hover:text-red-500 bg-gray-200 p-2 rounded-full transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div class="p-4 bg-black relative">
                <div id="reader-error" class="hidden absolute inset-0 z-10 bg-black/90 flex flex-col items-center justify-center text-white p-4 text-center">
                    <svg class="w-10 h-10 text-red-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <p class="font-bold mb-1">Camera Access Blocked</p>
                    <p class="text-xs text-gray-400 mb-4">Live streaming requires an HTTPS connection. You can upload an image of the QR Code instead.</p>
                    <label class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-bold cursor-pointer transition text-sm">
                        Upload QR Image
                        <input type="file" id="qr-upload" accept="image/*" class="hidden" onchange="scanLocalImage(event)">
                    </label>
                </div>
                <div id="reader" class="w-full min-h-[300px]"></div>
                <div id="scan-result" class="absolute inset-x-4 bottom-4 bg-emerald-600/90 text-white p-3 rounded-lg text-center font-bold shadow-lg transform translate-y-20 transition-transform opacity-0">
                    Scanning...
                </div>
            </div>
            <div class="p-4 bg-gray-50 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Point camera at admit card OR
                </div>
                <label class="px-3 py-1.5 bg-indigo-50 text-indigo-700 font-medium rounded border border-indigo-200 hover:bg-indigo-100 transition shadow-sm text-xs cursor-pointer">
                    Upload Image
                    <input type="file" id="qr-upload-alt" accept="image/*" class="hidden" onchange="scanLocalImage(event)">
                </label>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('student-modal');
        const modalBox = document.getElementById('modal-box');
        
        function openModal(data) {
            document.getElementById('modal-name').textContent = (data.student_first_name + ' ' + data.student_last_name).toUpperCase();
            document.getElementById('modal-view-app-btn').href = `view_application.php?id=${data.id}`;
            
            const badgeMap = {
                'Pending': 'bg-yellow-100 text-yellow-800',
                'Approved': 'bg-blue-100 text-blue-800',
                'Admitted': 'bg-emerald-100 text-emerald-800',
                'Rejected': 'bg-red-100 text-red-800'
            };
            const badgeClass = badgeMap[data.status] || 'bg-gray-100 text-gray-800';

            let html = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8 mb-4">
                    <div class="bg-gray-50 p-3 rounded border border-gray-100">
                        <span class="text-xs text-gray-500 uppercase font-semibold block mb-1">Entrance Roll No</span>
                        <span class="text-lg font-bold text-gray-900">${data.entrance_roll_no}</span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded border border-gray-100">
                        <span class="text-xs text-gray-500 uppercase font-semibold block mb-1">Status</span>
                        <span class="px-2 py-1 inline-flex text-xs leading-4 font-semibold rounded-full ${badgeClass}">${data.status}</span>
                    </div>
                    
                    <div><span class="text-sm text-gray-500 block">DOB (BS)</span> <span class="font-medium text-gray-800">${data.dob_bs}</span></div>
                    <div><span class="text-sm text-gray-500 block">Gender</span> <span class="font-medium text-gray-800">${data.gender}</span></div>
                    <div><span class="text-sm text-gray-500 block">Email Address</span> <span class="font-medium text-gray-800">${data.student_email || 'Not Provided'}</span></div>
                    <div><span class="text-sm text-gray-500 block">Contact Num</span> <span class="font-medium text-gray-800">${data.father_contact || data.guardian_contact}</span></div>
                </div>
                
                <h4 class="font-bold text-emerald-800 border-b border-emerald-100 pb-2 mb-3 mt-6">Family Information</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><span class="text-sm text-gray-500 block">Father's Name</span> <span class="font-medium text-gray-800">${data.father_name}</span></div>
                    <div><span class="text-sm text-gray-500 block">Mother's Name</span> <span class="font-medium text-gray-800">${data.mother_name || 'N/A'}</span></div>
                    <div><span class="text-sm text-gray-500 block">Local Guardian</span> <span class="font-medium text-gray-800">${data.local_guardian_name || 'N/A'}</span></div>
                </div>
                
                <h4 class="font-bold text-emerald-800 border-b border-emerald-100 pb-2 mb-3 mt-6">Previous Academic</h4>
                <div class="grid grid-cols-1 gap-3">
                    <div><span class="text-sm text-gray-500 block">School Name</span> <span class="font-medium text-gray-800">${data.previous_school_name || 'N/A'}</span></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><span class="text-sm text-gray-500 block">GPA / Percentage</span> <span class="font-medium text-gray-800">${data.gpa_or_percentage || 'N/A'}</span></div>
                        <div><span class="text-sm text-gray-500 block">SEE Symbol No</span> <span class="font-medium text-gray-800">${data.see_symbol_no || 'N/A'}</span></div>
                    </div>
                </div>
            `;
            document.getElementById('modal-content').innerHTML = html;
            
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalBox.classList.remove('scale-95', 'opacity-0');
                modalBox.classList.add('scale-100', 'opacity-100');
            }, 10);
        }
        
        function closeModal() {
            modalBox.classList.remove('scale-100', 'opacity-100');
            modalBox.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 200);
        }

        // Attendance AJAX Marking
        async function markAttendance(roll, status) {
            try {
                const formData = new FormData();
                formData.append('action', 'scan_attendance');
                formData.append('roll', roll);
                formData.append('attendance_status', status);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                if (data.success) {
                    // Flash success or reload page
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                console.error(err);
                alert('Connection error while marking attendance.');
            }
        }
    </script>

    <!-- HTML5 QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const scannerModal = document.getElementById('scanner-modal');
        const scannerBox = document.getElementById('scanner-box');
        let html5QrcodeScanner = null;
        let isProcessingScan = false;

        function openScanner() {
            document.getElementById('reader-error').classList.add('hidden');
            scannerModal.classList.remove('hidden');
            setTimeout(() => {
                scannerBox.classList.remove('scale-95', 'opacity-0');
                scannerBox.classList.add('scale-100', 'opacity-100');
            }, 10);

            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5Qrcode("reader");
            }

            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
            html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure)
            .catch(err => {
                document.getElementById('reader-error').classList.remove('hidden');
                console.warn("Camera streaming not supported or permissions denied: " + err);
            });
        }

        function closeScanner() {
            if (html5QrcodeScanner && html5QrcodeScanner.isScanning) {
                html5QrcodeScanner.stop().catch(err => console.error(err));
            }
            scannerBox.classList.remove('scale-100', 'opacity-100');
            scannerBox.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                scannerModal.classList.add('hidden');
            }, 200);
        }

        function scanLocalImage(event) {
            const scanBanner = document.getElementById('scan-result');
            const file = event.target.files[0];
            if (!file) return;

            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5Qrcode("reader");
            }
            
            scanBanner.textContent = "Processing image...";
            scanBanner.className = 'absolute inset-x-4 bottom-4 bg-blue-600/90 text-white p-3 rounded-lg text-center font-bold shadow-lg transform translate-y-0 transition-transform opacity-100 z-50';

            html5QrcodeScanner.scanFile(file, true)
                .then(decodedText => {
                    onScanSuccess(decodedText, null);
                })
                .catch(err => {
                    scanBanner.textContent = "No valid QR code found in image.";
                    scanBanner.className = 'absolute inset-x-4 bottom-4 bg-red-600/90 text-white p-3 rounded-lg text-center font-bold shadow-lg transform translate-y-0 transition-transform opacity-100 z-50';
                    setTimeout(() => {
                        scanBanner.classList.add('translate-y-20', 'opacity-0');
                        scanBanner.classList.remove('translate-y-0', 'opacity-100');
                    }, 3000);
                });
            
            // reset file input
            event.target.value = '';
        }

        async function onScanSuccess(decodedText, decodedResult) {
            if (isProcessingScan) return;
            isProcessingScan = true;
            
            const scanBanner = document.getElementById('scan-result');
            
            try {
                // expecting JSON payload: {"action":"scan_attendance","id":1,"roll":"..."}
                const payload = JSON.parse(decodedText);
                if (payload.action === 'scan_attendance' && payload.roll) {
                    
                    scanBanner.textContent = `Marking ${payload.roll} Present...`;
                    scanBanner.className = 'absolute inset-x-4 bottom-4 bg-emerald-600/90 text-white p-3 rounded-lg text-center font-bold shadow-lg transform translate-y-0 transition-transform opacity-100';

                    const formData = new FormData();
                    formData.append('action', 'scan_attendance');
                    formData.append('roll', payload.roll);
                    formData.append('attendance_status', 'Present');

                    const response = await fetch(window.location.href, { method: 'POST', body: formData });
                    const result = await response.json();
                    
                    if (result.success) {
                        scanBanner.textContent = result.message;
                        html5QrcodeScanner.pause(true); // pause camera temporarily
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        throw new Error(result.message);
                    }
                } else {
                    throw new Error("Invalid format");
                }
            } catch (err) {
                console.error(err);
                scanBanner.textContent = "Invalid QR code or error: " + err.message;
                scanBanner.className = 'absolute inset-x-4 bottom-4 bg-red-600/90 text-white p-3 rounded-lg text-center font-bold shadow-lg transform translate-y-0 transition-transform opacity-100';
                
                setTimeout(() => {
                    scanBanner.classList.add('translate-y-20', 'opacity-0');
                    scanBanner.classList.remove('translate-y-0', 'opacity-100');
                    isProcessingScan = false;
                }, 2000);
            }
        }

        function onScanFailure(error) {
            // handle scan failure, usually better to ignore and keep scanning
        }
    </script>
    <script src="js/companion_link.js"></script>
    <script>
        function linkCompanion() {
            initCompanionScanner(function(decodedText) {
                // Reroute it right into the existing logic!
                onScanSuccess(decodedText, null);
            });
        }
    </script>
</body>
</html>
