<?php
// manage_sessions.php
session_start();
require_once __DIR__ . '/includes/connect.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if (!in_array($admin_role, ['Super Admin', 'Academic Staff'])) {
    header("Location: dashboard.php");
    exit;
}

$msg = ''; $msg_type = 'success';

// Create new session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $label = trim($_POST['session_label']);
    $sy = trim($_POST['start_year']);
    $ey = trim($_POST['end_year']);
    if ($label && $sy && $ey) {
        $pdo->prepare("INSERT INTO academic_sessions (session_label, start_year, end_year, is_active, admission_open, inquiry_open) VALUES (?,?,?,0,1,1)")
            ->execute([$label, $sy, $ey]);
        $msg = "Session '$label' created.";
    }
}

// Activate a session
if (isset($_GET['activate'])) {
    $sid = (int)$_GET['activate'];
    $pdo->exec("UPDATE academic_sessions SET is_active = 0");
    $pdo->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?")->execute([$sid]);
    $msg = "Session activated.";
}

// Toggle open/close
if (isset($_GET['toggle'])) {
    [$field, $sid] = explode('_', $_GET['toggle'] . '_0');
    $sid = (int)$_GET['toggle_id'];
    $field = $_GET['toggle_field'];
    if (in_array($field, ['admission_open', 'inquiry_open'])) {
        $cur = $pdo->prepare("SELECT $field FROM academic_sessions WHERE id = ?");
        $cur->execute([$sid]);
        $val = $cur->fetchColumn();
        $pdo->prepare("UPDATE academic_sessions SET $field = ? WHERE id = ?")
            ->execute([$val ? 0 : 1, $sid]);
        $msg = "Updated successfully.";
    }
}

// Handle toggle via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_session'])) {
    $sid   = (int)$_POST['session_id'];
    $field = $_POST['toggle_field'];
    if (in_array($field, ['admission_open', 'inquiry_open', 'is_active'])) {
        if ($field === 'is_active') {
            $pdo->exec("UPDATE academic_sessions SET is_active = 0");
            $pdo->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?")->execute([$sid]);
            $msg = "Active session changed.";
        } else {
            $cur = $pdo->prepare("SELECT `$field` FROM academic_sessions WHERE id=?");
            $cur->execute([$sid]);
            $v = $cur->fetchColumn();
            $pdo->prepare("UPDATE academic_sessions SET `$field` = ? WHERE id = ?")->execute([$v ? 0 : 1, $sid]);
            $msg = "Toggle updated.";
        }
    }
}

// Delete session
if (isset($_GET['delete_session'])) {
    $sid = (int)$_GET['delete_session'];
    $actv = $pdo->prepare("SELECT is_active FROM academic_sessions WHERE id=?");
    $actv->execute([$sid]);
    if ($actv->fetchColumn()) {
        $msg = "Cannot delete the active session.";
        $msg_type = 'error';
    } else {
        $pdo->prepare("DELETE FROM academic_sessions WHERE id=?")->execute([$sid]);
        $msg = "Session deleted.";
    }
}

$sessions = $pdo->query("SELECT *, (SELECT COUNT(*) FROM admission_inquiries i WHERE i.session_id = academic_sessions.id) as total_submissions FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50">
<?php include 'includes/admin_sidebar.php'; ?>

<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Academic Sessions</h1>
        <p class="text-gray-500 text-sm mt-1">Manage academic years and control whether admission or inquiry forms are open to the public.</p>
    </div>

    <?php if ($msg): ?>
    <div class="mb-5 p-3 rounded-lg border text-sm font-medium <?php echo $msg_type==='error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Create New Session -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-gray-50 border-b border-gray-100">
                    <h2 class="font-bold text-gray-800">Create New Session</h2>
                </div>
                <form method="POST" class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Session Label</label>
                        <input type="text" name="session_label" required placeholder="e.g. 2083-2084 BS" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Start Year (BS)</label>
                            <input type="text" name="start_year" required placeholder="2083" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">End Year (BS)</label>
                            <input type="text" name="end_year" required placeholder="2084" class="w-full border border-gray-300 rounded-lg p-2.5 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>
                    <button type="submit" name="create_session" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-lg transition-colors text-sm">
                        + Create Session
                    </button>
                </form>
            </div>

            <div class="mt-4 bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-700">
                <p class="font-bold mb-1">How it works</p>
                <ul class="space-y-1 text-xs">
                    <li>🟢 <b>Active session</b> — the public forms submit under this session</li>
                    <li>🔒 <b>Closing Admission/Inquiry</b> — shows "closed" message on the public form</li>
                    <li>📊 Stats are tracked per session</li>
                </ul>
            </div>
        </div>

        <!-- Sessions List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-5 bg-gray-50 border-b border-gray-100">
                    <h2 class="font-bold text-gray-800">All Sessions</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if (empty($sessions)): ?>
                    <div class="p-8 text-center text-gray-400 text-sm">No sessions found. Create one to get started.</div>
                    <?php endif; ?>
                    <?php foreach ($sessions as $sess): ?>
                    <div class="p-5 hover:bg-gray-50/50 transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-bold text-gray-900"><?php echo htmlspecialchars($sess['session_label']); ?></span>
                                    <?php if ($sess['is_active']): ?>
                                    <span class="bg-emerald-100 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">Active</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($sess['start_year']); ?> BS → <?php echo htmlspecialchars($sess['end_year']); ?> BS &nbsp;·&nbsp; <?php echo $sess['total_submissions']; ?> submissions</p>

                                <div class="flex gap-3 mt-3">
                                    <!-- Admission toggle -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="session_id" value="<?php echo $sess['id']; ?>">
                                        <input type="hidden" name="toggle_field" value="admission_open">
                                        <button type="submit" name="toggle_session" class="text-xs px-3 py-1.5 rounded-lg border font-semibold transition <?php echo $sess['admission_open'] ? 'bg-blue-50 border-blue-200 text-blue-700 hover:bg-blue-100' : 'bg-gray-100 border-gray-200 text-gray-500 hover:bg-gray-200'; ?>">
                                            <?php echo $sess['admission_open'] ? '🟢 Admission: Open' : '🔴 Admission: Closed'; ?>
                                        </button>
                                    </form>
                                    <!-- Inquiry toggle -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="session_id" value="<?php echo $sess['id']; ?>">
                                        <input type="hidden" name="toggle_field" value="inquiry_open">
                                        <button type="submit" name="toggle_session" class="text-xs px-3 py-1.5 rounded-lg border font-semibold transition <?php echo $sess['inquiry_open'] ? 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100' : 'bg-gray-100 border-gray-200 text-gray-500 hover:bg-gray-200'; ?>">
                                            <?php echo $sess['inquiry_open'] ? '🟢 Inquiry: Open' : '🔴 Inquiry: Closed'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 shrink-0">
                                <?php if (!$sess['is_active']): ?>
                                <form method="POST" onsubmit="return confirm('Set this session as active? The public form will use this session for new submissions.')">
                                    <input type="hidden" name="session_id" value="<?php echo $sess['id']; ?>">
                                    <input type="hidden" name="toggle_field" value="is_active">
                                    <button type="submit" name="toggle_session" class="text-xs px-3 py-1.5 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-semibold w-full">Set Active</button>
                                </form>
                                <a href="?delete_session=<?php echo $sess['id']; ?>" onclick="return confirm('Delete this session?')" class="text-xs px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded-lg hover:bg-red-100 transition font-semibold text-center">Delete</a>
                                <?php else: ?>
                                <span class="text-[11px] text-emerald-600 font-semibold text-center px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-200">Current Session</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
