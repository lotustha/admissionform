<?php
// manage_staff.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Only Super Admin can manage staff
$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if ($admin_role !== 'Super Admin') {
    die("<div style='padding:50px; font-family:sans-serif; text-align:center;'>
            <h2>Unauthorized Access</h2>
            <p>You do not have permission to manage staff roles.</p>
            <a href='dashboard.php'>Return to Dashboard</a>
         </div>");
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_staff'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'Academic Staff';
        
        if ($username && $password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, role) VALUES (?, ?, ?)");
                $stmt->execute([$username, $hash, $role]);
                $msg = "Staff account created successfully.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $err = "Username already exists.";
                } else {
                    $err = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $err = "Username and password are required.";
        }
    } elseif (isset($_POST['delete_staff'])) {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        // Prevent deleting oneself
        if ($staff_id === (int)$_SESSION['admin_id']) {
            $err = "You cannot delete your own account.";
        } else {
            // Ensure there is at least one super admin left if we are deleting a super admin
            $stmt = $pdo->prepare("SELECT role FROM admins WHERE id = ?");
            $stmt->execute([$staff_id]);
            $del_role = $stmt->fetchColumn();
            
            if ($del_role === 'Super Admin') {
                $count = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'Super Admin'")->fetchColumn();
                if ($count <= 1) {
                    $err = "Cannot delete the last Super Admin.";
                    $staff_id = 0; // block deletion
                }
            }
            
            if ($staff_id) {
                $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$staff_id]);
                $msg = "Staff account deleted successfully.";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $new_password = $_POST['new_password'] ?? '';
        if ($staff_id && $new_password) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$hash, $staff_id]);
            $msg = "Password updated successfully.";
        }
    } elseif (isset($_POST['change_role'])) {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        
        if ($staff_id === (int)$_SESSION['admin_id'] && $new_role !== 'Super Admin') {
            $err = "You cannot demote yourself from Super Admin.";
        } elseif ($staff_id && $new_role) {
            $pdo->prepare("UPDATE admins SET role = ? WHERE id = ?")->execute([$new_role, $staff_id]);
            $msg = "Role updated successfully.";
        }
    }
}

// Fetch all staff
$staff_list = $pdo->query("SELECT id, username, role, created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Staff & Permissions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-[#f8fafc]">

<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-5xl mx-auto py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Manage Staff</h1>
        <p class="text-sm font-medium text-gray-500 mt-1">Add users and configure Role-Based Access Control (RBAC).</p>
    </div>

    <?php if ($msg): ?>
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-bold shadow-sm"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm font-bold shadow-sm"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Staff Form -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-6">
                <h2 class="text-lg font-bold text-gray-800 mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    Add New Staff
                </h2>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Username</label>
                        <input type="text" name="username" required class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition font-medium">
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Password</label>
                        <input type="password" name="password" required class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition font-medium">
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-1.5">Role / Permission Level</label>
                        <select name="role" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-2.5 text-sm text-gray-800 focus:bg-white focus:border-blue-500 outline-none transition font-semibold cursor-pointer">
                            <option value="Super Admin">Super Admin (Full Access)</option>
                            <option value="Academic Staff" selected>Academic Staff (Admissions & Academics)</option>
                            <option value="Cashier">Cashier (Billing & Fee Collection Only)</option>
                            <option value="Viewer">Viewer (Read-Only Analytics)</option>
                        </select>
                    </div>
                    <button type="submit" name="add_staff" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg shadow-[0_4px_15px_rgba(37,99,235,0.2)] hover:shadow-[0_4px_20px_rgba(37,99,235,0.3)] transition transform hover:-translate-y-0.5 text-sm flex justify-center items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Create Account
                    </button>
                </form>
            </div>
        </div>

        <!-- Staff List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Current Staff Accounts
                    </h2>
                    <span class="bg-blue-50 text-blue-600 px-3 py-1 rounded-full text-xs font-bold"><?php echo count($staff_list); ?> Users</span>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($staff_list as $staff): 
                        $is_me = ($staff['id'] === $_SESSION['admin_id']);
                        $r = $staff['role'];
                        $role_bg = 'bg-gray-100 text-gray-600';
                        if ($r === 'Super Admin') $role_bg = 'bg-blue-100 text-blue-700 border border-blue-200';
                        if ($r === 'Academic Staff') $role_bg = 'bg-emerald-100 text-emerald-700 border border-emerald-200';
                        if ($r === 'Cashier') $role_bg = 'bg-teal-100 text-teal-700 border border-teal-200';
                        if ($r === 'Viewer') $role_bg = 'bg-amber-100 text-amber-700 border border-amber-200';
                    ?>
                    <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 <?php if($is_me) echo 'bg-blue-50/30'; ?>">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 border border-gray-300 flex items-center justify-center text-lg font-black text-gray-600 shadow-sm">
                                <?php echo strtoupper(substr($staff['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                                    <?php echo htmlspecialchars($staff['username']); ?>
                                    <?php if ($is_me): ?><span class="text-[10px] bg-blue-600 text-white px-2 py-0.5 rounded-full tracking-wider uppercase">You</span><?php endif; ?>
                                </h3>
                                <p class="text-xs text-gray-400 mt-0.5 font-medium">Added: <?php echo date('M d, Y', strtotime($staff['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <form method="POST" class="flex items-center gap-2">
                                <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                <select name="new_role" onchange="this.form.submit()" class="text-xs font-bold px-3 py-1.5 rounded-full cursor-pointer outline-none <?php echo $role_bg; ?>" <?php if($is_me && $staff['role']==='Super Admin') echo 'disabled'; ?>>
                                    <option value="Super Admin" <?php if($r==='Super Admin') echo 'selected'; ?>>Super Admin</option>
                                    <option value="Academic Staff" <?php if($r==='Academic Staff') echo 'selected'; ?>>Academic Staff</option>
                                    <option value="Cashier" <?php if($r==='Cashier') echo 'selected'; ?>>Cashier</option>
                                    <option value="Viewer" <?php if($r==='Viewer') echo 'selected'; ?>>Viewer</option>
                                </select>
                                <input type="hidden" name="change_role" value="1">
                            </form>
                            
                            <?php if (!$is_me): ?>
                            <button type="button" onclick="document.getElementById('pwd_<?php echo $staff['id']; ?>').classList.toggle('hidden')" class="text-gray-400 hover:text-blue-600 transition p-1.5 rounded-md hover:bg-blue-50" title="Reset Password">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                <button type="submit" name="delete_staff" onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($staff['username']); ?>?');" class="text-gray-400 hover:text-red-600 transition p-1.5 rounded-md hover:bg-red-50" title="Delete Account">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Hidden Password Reset Form -->
                    <div id="pwd_<?php echo $staff['id']; ?>" class="hidden px-6 py-4 bg-slate-50 border-t border-gray-100">
                        <form method="POST" class="flex gap-2 max-w-sm">
                            <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                            <input type="text" name="new_password" placeholder="New Password" required class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-blue-500 outline-none rounded-lg font-medium">
                            <button type="submit" name="change_password" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition shadow-sm">Save</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</div></main></div> <!-- Closing tags for sidebar -->
</body>
</html>
