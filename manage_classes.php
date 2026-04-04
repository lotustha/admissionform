<?php
// manage_classes.php
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

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $class_name = trim($_POST['class_name'] ?? '');
    $total_seats = (int)($_POST['total_seats'] ?? 0);
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $contact_person = trim($_POST['contact_person'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    
    if (!empty($class_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO class_seats (class_name, total_seats, is_open, contact_person, whatsapp_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$class_name, $total_seats, $is_open, $contact_person, $whatsapp_number]);
            $msg = "Class added successfully.";
        } catch (PDOException $e) {
            $msg = "Error: " . $e->getMessage();
        }
    }
}

// Handle Update Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class'])) {
    $id = (int)$_POST['class_id'];
    $total_seats = (int)($_POST['total_seats'] ?? 0);
    $is_open = isset($_POST['is_open']) ? 1 : 0;
    $contact_person = trim($_POST['contact_person'] ?? '');
    $whatsapp_number = trim($_POST['whatsapp_number'] ?? '');
    
    $stmt = $pdo->prepare("UPDATE class_seats SET total_seats=?, is_open=?, contact_person=?, whatsapp_number=? WHERE id=?");
    $stmt->execute([$total_seats, $is_open, $contact_person, $whatsapp_number, $id]);
    $msg = "Class updated successfully.";
}

// Handle Delete Class
if (isset($_GET['delete_class'])) {
    $id = (int)$_GET['delete_class'];
    $stmt = $pdo->prepare("DELETE FROM class_seats WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_classes.php?msg=deleted");
    exit;
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = "Class deleted successfully.";
}

// If class seats is empty, populate with default classes.
$count = $pdo->query("SELECT COUNT(*) FROM class_seats")->fetchColumn();
if ($count == 0) {
    $default_classes = ['ECD', 'Class 1', 'Class 2', 'Class 3', 'Class 4', 'Class 5', 'Class 6', 'Class 7', 'Class 8', 'Class 9', 'Class 11'];
    $stmt = $pdo->prepare("INSERT INTO class_seats (class_name, is_open) VALUES (?, 1)");
    foreach ($default_classes as $c) {
        $stmt->execute([$c]);
    }
}

$classes = $pdo->query("SELECT * FROM class_seats ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>
        
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manage Classes &amp; Seats</h2>
            <p class="text-gray-500 text-sm mt-1">Control which classes are available for admission and set specific contact persons.</p>
        </div>
        
        <?php if($msg): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline font-medium"><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Add Form Column -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Add Class Form -->
                <div class="bg-white p-6 rounded-xl shadow border border-gray-100">
                    <h3 class="font-bold text-lg text-gray-800 mb-4 border-b pb-2">Add New Class</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Class Name</label>
                            <input type="text" name="class_name" required placeholder="e.g. Class 10" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Seats (0 = Unlimited)</label>
                            <input type="number" name="total_seats" value="0" min="0" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none text-sm">
                        </div>
                        <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="is_open" checked class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Open for Admissions/Inquiries</span>
                            </label>
                        </div>
                        <div class="border-t border-dashed border-gray-200 pt-4 mt-4 mb-3">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Class Contact Detials (Optional)</p>
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Contact Person Name</label>
                                <input type="text" name="contact_person" placeholder="e.g. Ms. Sita" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 outline-none text-sm">
                            </div>
                            <div class="mb-4">
                                <label class="block text-xs font-medium text-gray-600 mb-1">WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" placeholder="e.g. 9812345678" class="w-full rounded border-gray-300 border p-2 focus:ring-emerald-500 outline-none text-sm">
                            </div>
                        </div>
                        <button type="submit" name="add_class" class="w-full bg-emerald-600 text-white font-medium py-2 rounded hover:bg-emerald-700 transition duration-150 text-sm">Add Class</button>
                    </form>
                </div>
            </div>
            
            <!-- List Column -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-xl border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                        <h3 class="font-bold text-lg text-gray-800">Current Classes</h3>
                    </div>
                    
                    <div class="p-0 overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-100/50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-200">
                                    <th class="p-4 font-semibold">Class Name</th>
                                    <th class="p-4 font-semibold">Status</th>
                                    <th class="p-4 font-semibold">Seats</th>
                                    <th class="p-4 font-semibold">Contact Person</th>
                                    <th class="p-4 font-semibold text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm divide-y divide-gray-100">
                                <?php foreach($classes as $c): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <form method="POST">
                                            <input type="hidden" name="class_id" value="<?php echo $c['id']; ?>">
                                            <td class="p-4 font-medium text-gray-900"><?php echo htmlspecialchars($c['class_name']); ?></td>
                                            <td class="p-4">
                                                <label class="inline-flex items-center cursor-pointer">
                                                  <input type="checkbox" name="is_open" class="sr-only peer" <?php echo $c['is_open'] ? 'checked' : ''; ?>>
                                                  <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                                </label>
                                            </td>
                                            <td class="p-4">
                                                <input type="number" name="total_seats" value="<?php echo $c['total_seats']; ?>" class="w-16 p-1 border rounded text-xs">
                                            </td>
                                            <td class="p-4">
                                                <input type="text" name="contact_person" value="<?php echo htmlspecialchars($c['contact_person'] ?? ''); ?>" placeholder="Name" class="w-full p-1 border rounded mb-1 text-xs">
                                                <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($c['whatsapp_number'] ?? ''); ?>" placeholder="WhatsApp" class="w-full p-1 border rounded text-xs">
                                            </td>
                                            <td class="p-4 text-right">
                                                <button type="submit" name="update_class" class="text-white bg-indigo-500 hover:bg-indigo-600 px-3 py-1.5 rounded font-medium text-xs mb-1 w-full text-center">Update</button>
                                                <a href="?delete_class=<?php echo $c['id']; ?>" onclick="return confirm('Delete this class?');" class="block text-red-500 border border-red-200 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded font-medium text-xs text-center w-full">Delete</a>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
