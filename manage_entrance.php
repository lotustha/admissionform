<?php
// manage_entrance.php
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

$msg = '';

// Handle Add/Edit Entrance Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $schedule_id = !empty($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : null;
    $class_name = trim($_POST['class_name']);
    $faculty_id = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
    $exam_date = $_POST['exam_date'];
    $exam_time = trim($_POST['exam_time']);
    $venue = trim($_POST['venue']);
    $total_capacity = (int)$_POST['total_capacity'];
    
    if (!empty($class_name) && !empty($exam_date) && !empty($exam_time) && !empty($venue) && $total_capacity > 0) {
        if ($schedule_id) {
            $stmt = $pdo->prepare("UPDATE entrance_schedules SET class_name=?, faculty_id=?, exam_date=?, exam_time=?, venue=?, total_capacity=? WHERE id=?");
            $stmt->execute([$class_name, $faculty_id, $exam_date, $exam_time, $venue, $total_capacity, $schedule_id]);
            $msg = "Entrance schedule updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO entrance_schedules (class_name, faculty_id, exam_date, exam_time, venue, total_capacity) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$class_name, $faculty_id, $exam_date, $exam_time, $venue, $total_capacity]);
            $msg = "Entrance schedule added successfully.";
        }
    } else {
        $msg = "Please fill in all required fields properly.";
    }
}

// Handle Delete Entrance Schedule
if (isset($_GET['delete_schedule'])) {
    $id = (int)$_GET['delete_schedule'];
    $stmt = $pdo->prepare("DELETE FROM entrance_schedules WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_entrance.php?msg=deleted");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Schedule deleted successfully.";
}

// Filtering & Pagination Logic
$search = $_GET['search'] ?? '';
$faculty_filter = $_GET['faculty_filter'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(e.class_name LIKE ? OR e.venue LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
}
if ($faculty_filter !== '') {
    $where[] = "e.faculty_id = ?";
    $params[] = $faculty_filter;
}

$where_sql = implode(' AND ', $where);

$offset = ($page - 1) * $limit;
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM entrance_schedules e WHERE $where_sql");
$count_stmt->execute($params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch Data
$faculties = $pdo->query("SELECT * FROM faculties WHERE requires_entrance = 1 ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$schedules_stmt = $pdo->prepare("SELECT e.*, f.faculty_name FROM entrance_schedules e LEFT JOIN faculties f ON e.faculty_id = f.id WHERE $where_sql ORDER BY e.exam_date ASC, e.exam_time ASC LIMIT $limit OFFSET $offset");
$schedules_stmt->execute($params);
$schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch open classes from class_seats
$classes_stmt = $pdo->query("SELECT * FROM class_seats WHERE is_open = 1 ORDER BY id ASC");
$open_classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

function build_url($updates) {
    $qs = array_merge($_GET, $updates);
    unset($qs['msg'], $qs['delete_schedule']);
    return '?' . http_build_query($qs);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Entrance Exams - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manage Entrance Exams</h2>
            <p class="text-gray-500 text-sm mt-1">Add available exam time slots for applicants to choose from.</p>
        </div>
        
        <?php if($msg): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline font-medium"><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Add Forms Column -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Add/Edit Schedule Form -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
                    <h3 class="font-bold text-lg text-gray-800 mb-4 border-b pb-2" id="form-title">Add Time Slot</h3>
                    <form method="POST" id="schedule-form">
                        <input type="hidden" name="schedule_id" id="schedule_id" value="">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                            <select name="class_name" required class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm bg-white">
                                <option value="">Select Class</option>
                                <?php foreach($open_classes as $cls): ?>
                                    <option value="<?php echo htmlspecialchars($cls['class_name']); ?>"><?php echo htmlspecialchars($cls['class_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Faculty (Optional)</label>
                            <select name="faculty_id" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm bg-white">
                                <option value="">-- No Specific Faculty --</option>
                                <?php foreach($faculties as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Only showing faculties that require an entrance exam.</p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Date</label>
                            <input type="date" name="exam_date" required class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Exam Time</label>
                            <input type="time" name="exam_time" required class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                            <input type="text" name="venue" required placeholder="e.g. Hall A" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Capacity</label>
                            <input type="number" name="total_capacity" id="total_capacity" required placeholder="e.g. 50" min="1" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="save_schedule" id="submit-btn" class="w-full bg-emerald-600 text-white font-medium py-2 rounded hover:bg-emerald-700 transition duration-150 text-sm">Add Schedule</button>
                            <button type="button" id="cancel-btn" class="hidden w-1/3 bg-gray-200 text-gray-700 font-medium py-2 rounded hover:bg-gray-300 transition duration-150 text-sm" onclick="resetForm()">Cancel</button>
                        </div>
                    </form>
                </div>

            </div>
            
            <!-- List Column -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-xl border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-gray-800">Available Schedules</h3>
                        <div class="text-sm text-gray-500">Showing <?php echo $total_records; ?> records</div>
                    </div>
                    
                    <!-- Search & Filter Bar -->
                    <div class="p-4 border-b border-gray-200 bg-white">
                        <form method="GET" class="flex flex-wrap gap-4 items-end">
                            <div class="flex-1 min-w-[150px]">
                                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Search Keywords</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Class, Venue..." class="w-full text-sm border-gray-300 rounded focus:ring-emerald-500 py-2 px-3 border outline-none">
                            </div>
                            <div class="w-48">
                                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Faculty</label>
                                <select name="faculty_filter" class="w-full text-sm border-gray-300 rounded focus:ring-emerald-500 py-2 px-3 border outline-none bg-white">
                                    <option value="">All Faculties</option>
                                    <?php foreach($faculties as $fac): ?>
                                    <option value="<?php echo $fac['id']; ?>" <?php if($faculty_filter == $fac['id']) echo 'selected'; ?>><?php echo htmlspecialchars($fac['faculty_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm font-medium h-[38px] transition">Filter</button>
                                <a href="manage_entrance.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium h-[38px] inline-flex items-center ml-2 transition">Clear</a>
                            </div>
                        </form>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($schedules)): ?>
                            <p class="text-gray-500 text-sm text-center py-6">No entrance schedules have been added yet.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Class / Faculty</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Date & Time</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">Venue</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 uppercase tracking-wider text-xs">Capacity</th>
                                            <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wider text-xs">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach($schedules as $s): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($s['class_name']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($s['faculty_name'] ?? 'N/A'); ?></div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-gray-900"><?php echo htmlspecialchars(date('M d, Y', strtotime($s['exam_date']))); ?></div>
                                                <div class="text-xs text-emerald-600 font-semibold"><?php echo htmlspecialchars(date('h:i A', strtotime($s['exam_time']))); ?></div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                <?php echo htmlspecialchars($s['venue']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center font-medium">
                                                <?php 
                                                    // Count booked slots if needed
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admission_inquiries WHERE schedule_id = ?");
                                                    $stmt->execute([$s['id']]);
                                                    $booked = $stmt->fetchColumn();
                                                ?>
                                                <span class="text-gray-800"><?php echo $booked; ?> / <?php echo htmlspecialchars($s['total_capacity']); ?></span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right font-medium flex items-center justify-end">
                                                <a href="exam_attendance.php?id=<?php echo $s['id']; ?>" class="text-blue-600 hover:text-blue-800 text-xs bg-blue-50 hover:bg-blue-100 px-2 py-1.5 rounded transition shadow-sm mr-2 border border-blue-200">View Students</a>
                                                <button type="button" onclick='editSchedule(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)' class="text-amber-600 hover:text-amber-800 text-xs bg-amber-50 hover:bg-amber-100 px-2 py-1.5 rounded transition shadow-sm mr-2 border border-amber-200">Edit</button>
                                                <a href="?delete_schedule=<?php echo $s['id']; ?>" onclick="return confirm('Are you sure you want to delete this schedule?');" class="text-red-500 hover:text-red-700 text-xs bg-red-50 hover:bg-red-100 px-2 py-1.5 rounded transition shadow-sm border border-red-200">Delete</a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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

        </div>
    </div>
        </div>
    </main>
</div>
    
    <script>
        function editSchedule(data) {
            document.getElementById('form-title').textContent = 'Edit Time Slot';
            document.getElementById('submit-btn').textContent = 'Update Schedule';
            document.getElementById('cancel-btn').classList.remove('hidden');
            document.getElementById('submit-btn').classList.remove('w-full');
            document.getElementById('submit-btn').classList.add('w-2/3');
            
            document.getElementById('schedule_id').value = data.id;
            document.querySelector('[name="class_name"]').value = data.class_name;
            document.querySelector('[name="faculty_id"]').value = data.faculty_id || '';
            document.querySelector('[name="exam_date"]').value = data.exam_date;
            document.querySelector('[name="exam_time"]').value = data.exam_time;
            document.querySelector('[name="venue"]').value = data.venue;
            document.getElementById('total_capacity').value = data.total_capacity;
            
            // Scroll to form
            document.getElementById('schedule-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        function resetForm() {
            document.getElementById('form-title').textContent = 'Add Time Slot';
            document.getElementById('submit-btn').textContent = 'Add Schedule';
            document.getElementById('cancel-btn').classList.add('hidden');
            document.getElementById('submit-btn').classList.remove('w-2/3');
            document.getElementById('submit-btn').classList.add('w-full');
            
            document.getElementById('schedule_id').value = '';
            document.getElementById('schedule-form').reset();
        }
    </script>
</body>
</html>
