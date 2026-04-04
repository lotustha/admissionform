<?php
// manage_academics.php
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

// Handle Add Faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_faculty'])) {
    $faculty_name = trim($_POST['faculty_name']);
    $requires_entrance = isset($_POST['requires_entrance']) ? 1 : 0;
    $incharge_name = trim($_POST['incharge_name'] ?? '');
    $incharge_title = trim($_POST['incharge_title'] ?? '');
    $incharge_whatsapp = trim($_POST['incharge_whatsapp'] ?? '');
    $incharge_photo_path = '';
    
    if (!empty($_FILES['incharge_photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['incharge_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $path = 'uploads/incharge/' . uniqid('inc_') . '.' . $ext;
            if (move_uploaded_file($_FILES['incharge_photo']['tmp_name'], __DIR__ . '/' . $path)) {
                $incharge_photo_path = $path;
            }
        }
    }
    
    if (!empty($faculty_name)) {
        $stmt = $pdo->prepare("INSERT INTO faculties (faculty_name, requires_entrance, incharge_name, incharge_title, incharge_whatsapp, incharge_photo_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$faculty_name, $requires_entrance, $incharge_name, $incharge_title, $incharge_whatsapp, $incharge_photo_path]);
        $msg = "Faculty added successfully.";
    }
}

// Handle Update Incharge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_incharge'])) {
    $fid = (int)$_POST['faculty_id'];
    $incharge_name = trim($_POST['incharge_name'] ?? '');
    $incharge_title = trim($_POST['incharge_title'] ?? '');
    $incharge_whatsapp = trim($_POST['incharge_whatsapp'] ?? '');
    
    // Fetch old photo
    $old = $pdo->prepare("SELECT incharge_photo_path FROM faculties WHERE id=?");
    $old->execute([$fid]);
    $incharge_photo_path = $old->fetchColumn();
    
    if (!empty($_FILES['incharge_photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['incharge_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $path = 'uploads/incharge/' . uniqid('inc_') . '.' . $ext;
            if (move_uploaded_file($_FILES['incharge_photo']['tmp_name'], __DIR__ . '/' . $path)) {
                $incharge_photo_path = $path;
            }
        }
    }
    
    $pdo->prepare("UPDATE faculties SET incharge_name=?, incharge_title=?, incharge_whatsapp=?, incharge_photo_path=? WHERE id=?"
    )->execute([$incharge_name, $incharge_title, $incharge_whatsapp, $incharge_photo_path, $fid]);
    $msg = "Incharge updated successfully.";
}

// Handle Delete Faculty
if (isset($_GET['delete_faculty'])) {
    $id = (int)$_GET['delete_faculty'];
    $stmt = $pdo->prepare("DELETE FROM faculties WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_academics.php?msg=deleted");
    exit;
}

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
    $faculty_id = (int)$_POST['faculty_id'];
    $subject_name = trim($_POST['subject_name']);
    
    if (!empty($subject_name) && $faculty_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO faculty_subjects (faculty_id, subject_name) VALUES (?, ?)");
        $stmt->execute([$faculty_id, $subject_name]);
        $msg = "Subject added successfully.";
    }
}

// Handle Delete Subject
if (isset($_GET['delete_subject'])) {
    $id = (int)$_GET['delete_subject'];
    $stmt = $pdo->prepare("DELETE FROM faculty_subjects WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_academics.php?msg=deleted");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Item deleted successfully.";
}

// Fetch Data
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Academics - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manage Academics (+2 Faculties)</h2>
            <p class="text-gray-500 text-sm mt-1">Add faculties and their optional subjects to populate the admission form dynamically.</p>
        </div>
        
        <?php if($msg): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline font-medium"><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Add Forms Column -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Add Faculty Form -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
                    <h3 class="font-bold text-lg text-gray-800 mb-4 border-b pb-2">Add New Faculty / Class</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Faculty / Class Name</label>
                            <input type="text" name="faculty_name" required placeholder="e.g. Science" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="requires_entrance" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Requires Entrance Exam</span>
                            </label>
                        </div>
                        <div class="border-t border-dashed border-gray-200 pt-4 mt-4 mb-3">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Class Incharge Details</p>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Incharge Name</label>
                                <input type="text" name="incharge_name" placeholder="e.g. Mr. Raj Kumar" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 outline-none text-sm">
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Title / Role</label>
                                <input type="text" name="incharge_title" placeholder="e.g. Science Department Head" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 outline-none text-sm">
                            </div>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">WhatsApp Number</label>
                                <input type="text" name="incharge_whatsapp" placeholder="e.g. 9801234567" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 outline-none text-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Incharge Photo</label>
                                <input type="file" name="incharge_photo" accept="image/*" class="w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100 cursor-pointer border border-gray-300 rounded p-1">
                            </div>
                        </div>
                        <button type="submit" name="add_faculty" class="w-full bg-emerald-600 text-white font-medium py-2 rounded hover:bg-emerald-700 transition duration-150 text-sm">Add Faculty</button>
                    </form>
                </div>

                <!-- Add Subject Form -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
                    <h3 class="font-bold text-lg text-gray-800 mb-4 border-b pb-2">Add Optional Subject</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Faculty</label>
                            <select name="faculty_id" required class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm bg-white">
                                <option value="">-- Choose Faculty --</option>
                                <?php foreach($faculties as $f): ?>
                                    <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['faculty_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Subject Name</label>
                            <input type="text" name="subject_name" required placeholder="e.g. Computer Science" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <button type="submit" name="add_subject" class="w-full bg-indigo-600 text-white font-medium py-2 rounded hover:bg-indigo-700 transition duration-150 text-sm">Add Subject</button>
                    </form>
                </div>

            </div>
            
            <!-- List Column -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-xl border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-gray-800">Current Faculties & Subjects</h3>
                    </div>
                    
                    <div class="p-6">
                        <?php if (empty($faculties)): ?>
                            <p class="text-gray-500 text-sm text-center py-6">No faculties have been added yet.</p>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach($faculties as $f): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 relative group hover:border-emerald-300 transition-colors">
                                        <div class="flex justify-between items-start mb-3">
                                            <div>
                                                <h4 class="text-lg font-bold text-emerald-800 flex items-center gap-2">
                                                    <?php echo htmlspecialchars($f['faculty_name']); ?>
                                                    <?php if($f['requires_entrance']): ?>
                                                        <span class="bg-red-100 text-red-800 text-[10px] uppercase font-bold px-2 py-0.5 rounded-full tracking-wide">Entrance Req.</span>
                                                    <?php endif; ?>
                                                </h4>
                                            </div>
                                            <a href="?delete_faculty=<?php echo $f['id']; ?>" onclick="return confirm('Are you sure? This will also delete all subjects linked to this faculty.');" class="text-red-500 hover:text-red-700 text-sm font-medium">Delete</a>
                                        </div>
                                        
                                        <!-- Fetch and display subjects for this faculty -->
                                        <?php
                                            $stmt_sub = $pdo->prepare("SELECT * FROM faculty_subjects WHERE faculty_id = ?");
                                            $stmt_sub->execute([$f['id']]);
                                            $subjects = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
                                        ?>
                                        
                                        <div class="bg-gray-50 rounded p-3">
                                            <p class="text-xs text-gray-500 font-semibold uppercase mb-2">Optional Subjects:</p>
                                            <?php if(empty($subjects)): ?>
                                                <p class="text-sm text-gray-400 italic">None added.</p>
                                            <?php else: ?>
                                                <div class="flex flex-wrap gap-2">
                                                    <?php foreach($subjects as $sub): ?>
                                                        <span class="inline-flex items-center bg-white border border-gray-200 shadow-sm text-gray-700 text-sm px-3 py-1 rounded">
                                                            <?php echo htmlspecialchars($sub['subject_name']); ?>
                                                            <a href="?delete_subject=<?php echo $sub['id']; ?>" onclick="return confirm('Remove subject?');" class="ml-2 text-gray-400 hover:text-red-500">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                            </a>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
        </div>
    </main>
</div>
</body>
</html>
