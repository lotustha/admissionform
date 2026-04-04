<?php
// login.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_rate_limit('login', 5, 60)) {
        $error = "Too many login attempts. Please wait 1 minute.";
    } elseif (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please refresh the page.";
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'Super Admin';
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-emerald-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-emerald-100">
        <div class="bg-emerald-600 p-6 text-white text-center">
            <h1 class="text-2xl font-bold">Admin Portal</h1>
            <p class="text-emerald-100 text-sm mt-1">Sign in to manage admissions</p>
        </div>
        <div class="p-8">
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm rounded">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full rounded-lg border-gray-300 border p-3 focus:ring-2 focus:ring-emerald-500 shadow-sm outline-none">
                </div>
                <button type="submit" class="w-full bg-emerald-600 text-white font-bold py-3 px-4 rounded-lg hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-600/30">
                    Login securely
                </button>
            </form>
        </div>
    </div>
</body>
</html>
