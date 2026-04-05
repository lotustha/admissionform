<?php
// manage_settings.php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? 'Super Admin';
if ($admin_role !== 'Super Admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = '';
$msg_type = 'success';

// -- Handle POST Actions --

// 1. Save Organization Settings → app_settings key-value
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_org'])) {
    $school_name     = trim($_POST['org_name'] ?? '');
    $address         = trim($_POST['org_address'] ?? '');
    $contact_phone   = trim($_POST['org_phone'] ?? '');
    $contact_person  = trim($_POST['org_contact_person'] ?? '');
    $org_email       = trim($_POST['org_email'] ?? '');
    $form_title      = trim($_POST['form_title'] ?? 'Admission Inquiry Form');

    // Handle logo upload
    $logo_path_update = '';
    if (!empty($_FILES['org_logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['org_logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
            $uploadDir = __DIR__ . '/assets/uploads/logos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['org_logo']['tmp_name'], $uploadDir . $filename)) {
                $logo_path_update = 'assets/uploads/logos/' . $filename;
            }
        }
    }

    $upsert = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

    $upsert->execute(['school_name',    $school_name]);
    $upsert->execute(['address',        $address]);
    $upsert->execute(['contact_phone',  $contact_phone]);
    $upsert->execute(['contact_person', $contact_person]);
    $upsert->execute(['org_email',      $org_email]);
    $upsert->execute(['form_title',     $form_title]);
    
    $announcement_text = trim($_POST['announcement_text'] ?? '');
    $upsert->execute(['announcement_text', $announcement_text]);

    if ($logo_path_update) {
        $upsert->execute(['logo_path', $logo_path_update]);
    }
    $msg = "Organization settings saved successfully!";
}

// 1.5 Save Fees & Payments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fees'])) {
    $fee = trim($_POST['application_fee'] ?? '0');
    // Basic validation to ensure it's a number
    $fee = preg_replace('/[^0-9.]/', '', $fee);
    if ($fee === '') $fee = '0';
    
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('application_fee', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$fee]);

    // Save invoice settings
    $inv_prefix = strtoupper(trim($_POST['invoice_prefix'] ?? 'INV'));
    $inv_start = max(1, (int)($_POST['invoice_start_number'] ?? 1));
    
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('invoice_prefix', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$inv_prefix]);
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('invoice_start_number', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([(string)$inv_start]);

    // Save result settings
    $pass_pct = trim($_POST['result_pass_percentage'] ?? '40');
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('result_pass_percentage', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$pass_pct]);

    // Save unpaid admit card setting
    $allow_unpaid = isset($_POST['allow_unpaid_admit_card']) ? '1' : '0';
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('allow_unpaid_admit_card', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$allow_unpaid]);

    $msg = "Fees & Academic settings saved successfully!";
}


// 2. Save Account Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_account'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $current_pass = $_POST['current_password'] ?? '';

    // Verify current password using password_hash column
    $s = $pdo->prepare("SELECT password_hash FROM admins WHERE id = ?");
    $s->execute([$_SESSION['admin_id']]);
    $admin_row = $s->fetch();

    if (!$admin_row || !password_verify($current_pass, $admin_row['password_hash'])) {
        $msg = "Current password is incorrect.";
        $msg_type = 'error';
    } else {
        $updates = [];
        $params  = [];

        if (!empty($new_username) && $new_username !== $_SESSION['admin_username']) {
            $updates[] = "username = ?";
            $params[] = $new_username;
        }
        if (!empty($new_pass)) {
            if ($new_pass !== $confirm_pass) {
                $msg = "New passwords do not match.";
                $msg_type = 'error';
                goto render;
            }
            $updates[] = "password_hash = ?";
            $params[] = password_hash($new_pass, PASSWORD_BCRYPT);
        }

        if (!empty($updates)) {
            $params[] = $_SESSION['admin_id'];
            $pdo->prepare("UPDATE admins SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
            if (!empty($new_username)) $_SESSION['admin_username'] = $new_username;
            $msg = "Account updated successfully!";
        } else {
            $msg = "No changes were made.";
        }
    }
}

// 3. Save AI Key → app_settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai'])) {
    $key = trim($_POST['gemini_api_keys'] ?? '');
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('gemini_api_keys', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$key]);
    $msg = "Gemini API key saved!";
}

// -- Read current values from app_settings --
render:
$settings    = getSchoolSettings($pdo);
$org_name    = $settings['school_name']    ?? '';
$org_address = $settings['address']        ?? '';
$org_phone   = $settings['contact_phone']  ?? '';
$org_contact = $settings['contact_person'] ?? '';
$org_email   = $settings['org_email']      ?? '';
$form_title  = $settings['form_title']     ?? 'Admission Inquiry Form';
$org_logo    = $settings['logo_path']      ?? '';
$gemini_keys = $settings['gemini_api_keys'] ?? '';
$application_fee = $settings['application_fee'] ?? '500';
$result_pass_percentage = $settings['result_pass_percentage'] ?? '40';

$active_tab = $_GET['tab'] ?? 'org';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo htmlspecialchars($org_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>

    <div class="max-w-4xl mx-auto">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
            <p class="text-gray-500 text-sm mt-1">Manage your organization profile, admin account, and AI configuration.</p>
        </div>

        <?php if ($msg): ?>
        <div class="mb-6 p-4 rounded-xl border <?php echo $msg_type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-800'; ?> text-sm font-semibold shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?php echo htmlspecialchars($msg); ?>
        </div>
        <?php endif; ?>

        <!-- Tab Navigation (Pill Layout) -->
        <div class="flex flex-wrap gap-2 mb-8 bg-white p-2 rounded-xl shadow-sm border border-gray-100">
            <?php
            $tabs = ['org' => '🏫 Organization', 'fees' => '💳 Fees & Academics', 'account' => '👤 Admin Account', 'ai' => '🤖 AI Configuration'];
            foreach ($tabs as $t_key => $t_label):
                $is_active = $active_tab === $t_key;
            ?>
            <a href="?tab=<?php echo $t_key; ?>" class="px-5 py-2.5 text-sm font-semibold rounded-lg transition-all <?php echo $is_active ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'; ?>">
                <?php echo $t_label; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- TAB: Organization -->
        <?php if ($active_tab === 'org'): ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900 text-lg">Organization Profile</h2>
                    <p class="text-sm text-gray-500 mt-1">This information is displayed on public pages and used by the AI chatbot.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">School / Organization Name <span class="text-red-500">*</span></label>
                            <input type="text" name="org_name" value="<?php echo htmlspecialchars($org_name); ?>" required class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Form Title</label>
                            <input type="text" name="form_title" value="<?php echo htmlspecialchars($form_title); ?>" placeholder="Admission Inquiry Form" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Contact Person / Principal</label>
                            <input type="text" name="org_contact_person" value="<?php echo htmlspecialchars($org_contact); ?>" placeholder="e.g. Mr. Rajan Sharma" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Contact Phone</label>
                            <input type="text" name="org_phone" value="<?php echo htmlspecialchars($org_phone); ?>" placeholder="e.g. 01-456789" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Notification Email</label>
                        <input type="email" name="org_email" value="<?php echo htmlspecialchars($org_email); ?>" placeholder="e.g. info@school.edu.np" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                        <p class="text-xs text-gray-400 mt-1.5">Form submissions will be sent to this email address.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Address</label>
                        <input type="text" name="org_address" value="<?php echo htmlspecialchars($org_address); ?>" placeholder="e.g. Kathmandu, Nepal" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Student Dashboard Announcement</label>
                        <textarea name="announcement_text" placeholder="e.g. Entrance halls changed to Block B. Results will be published on April 10." class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition h-20"><?php echo htmlspecialchars($settings['announcement_text'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-400 mt-1.5">This text will be displayed prominently on all student dashboards. Leave blank to disable.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">School Logo</label>
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 flex-shrink-0 rounded-xl border-2 <?php echo $org_logo ? 'border-indigo-200' : 'border-dashed border-gray-300'; ?> overflow-hidden bg-gray-50 flex items-center justify-center shadow-sm">
                                <?php if ($org_logo): ?>
                                    <img src="<?php echo htmlspecialchars($org_logo); ?>" class="w-full h-full object-contain p-1" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <input type="file" name="org_logo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:font-semibold hover:file:bg-indigo-100 cursor-pointer border border-gray-300 rounded-lg p-1.5 transition-colors">
                                <p class="text-xs text-gray-400 mt-1.5">PNG, JPG, or SVG. Appears in the public form header and admin panel.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_org" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition-colors shadow-sm">Save Organization Info</button>
                </div>
            </div>
        </form>

        <!-- TAB: Fees & Payments -->
        <?php elseif ($active_tab === 'fees'): ?>
        <form method="POST">
            <div class="bg-white rounded-2xl shadow-sm border border-indigo-200 overflow-hidden outline outline-4 outline-indigo-50">
                <div class="p-6 border-b border-gray-100 bg-indigo-50/50">
                    <h2 class="font-bold text-gray-900 text-lg flex items-center gap-2">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Fees & Academic Settings
                    </h2>
                    <p class="text-sm text-gray-600 mt-1 ml-8">Configure the mandatory admission application fee and invoice numbering.</p>
                </div>
                <div class="p-8 space-y-8">
                    <!-- Fee Amount -->
                    <div class="max-w-md">
                        <label class="block text-sm font-bold text-gray-800 mb-2">Application/Entrance Exam Fee Amount (Rs.)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-gray-500 font-bold">Rs.</span>
                            <input type="text" name="application_fee" value="<?php echo htmlspecialchars($application_fee); ?>" placeholder="e.g. 500" class="w-full border-2 border-slate-300 rounded-xl py-3 pl-12 pr-4 text-lg font-bold shadow-sm focus:ring-4 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition">
                        </div>
                        <p class="text-sm text-gray-500 mt-2 flex items-start gap-2">
                            <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Enter the amount required. <strong>Set to 0</strong> to make all applications completely free.</span>
                        </p>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Invoice Settings -->
                    <div>
                        <h3 class="font-bold text-gray-800 text-base mb-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Invoice / Receipt Numbering
                        </h3>
                        <p class="text-xs text-gray-500 mb-4 ml-7">Configure how payment receipt invoice numbers are generated (e.g., INV001, REC100).</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-lg">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV'); ?>" placeholder="INV" maxlength="10" class="w-full border border-gray-300 rounded-lg p-3 text-sm font-mono font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition uppercase">
                                <p class="text-xs text-gray-400 mt-1">e.g., INV, REC, BILL</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Starting Number</label>
                                <input type="number" name="invoice_start_number" value="<?php echo htmlspecialchars($settings['invoice_start_number'] ?? '1'); ?>" min="1" placeholder="1" class="w-full border border-gray-300 rounded-lg p-3 text-sm font-mono font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                <p class="text-xs text-gray-400 mt-1">Next new invoice starts from this number</p>
                            </div>
                        </div>
                        <div class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-3 max-w-lg">
                            <p class="text-xs text-slate-600 font-medium">
                                <strong>Preview:</strong> 
                                <span class="font-mono bg-white px-2 py-0.5 rounded border text-indigo-700 font-bold"><?php echo htmlspecialchars(($settings['invoice_prefix'] ?? 'INV') . str_pad($settings['invoice_start_number'] ?? '1', 3, '0', STR_PAD_LEFT)); ?></span>
                                → Next invoices will auto-increment from this number.
                            </p>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Result Settings -->
                    <div>
                        <h3 class="font-bold text-gray-800 text-base mb-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            Exam Result Settings
                        </h3>
                        <p class="text-xs text-gray-500 mb-4 ml-7">Configure the threshold for automatic pass status in entrance exam results.</p>
                        <div class="max-w-xs ml-7">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Pass Percentage (%)</label>
                            <input type="number" step="0.01" name="result_pass_percentage" value="<?php echo htmlspecialchars($result_pass_percentage); ?>" min="0" max="100" class="w-full border border-gray-300 rounded-lg p-3 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none transition">
                            <p class="text-xs text-gray-400 mt-1">Default is 40%.</p>
                        </div>
                    </div>

                    <hr class="border-gray-200">

                    <!-- Admit Card Settings -->
                    <div>
                        <h3 class="font-bold text-gray-800 text-base mb-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
                            Admit Card Generation
                        </h3>
                        <p class="text-xs text-gray-500 mb-4 ml-7">Configure whether students can download their admit card before paying the entrance fee.</p>
                        <div class="ml-7 bg-indigo-50/50 border border-indigo-100 rounded-xl p-4 flex items-start gap-3">
                            <div class="pt-0.5">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="allow_unpaid_admit_card" value="1" class="sr-only peer" <?php echo ($settings['allow_unpaid_admit_card'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-gray-800 cursor-pointer block">Allow Unpaid Admit Cards</label>
                                <p class="text-xs text-gray-500 mt-1">If enabled, admit cards will be generated and emailed to students immediately upon Application Approval, even if their payment status is 'Pending'.</p>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_fees" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-md hover:shadow-lg transition-all">Save Academic & Fee Settings</button>
                </div>
            </div>
        </form>

        <!-- TAB: Account -->
        <?php elseif ($active_tab === 'account'): ?>
        <form method="POST">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900 text-lg">Admin Account</h2>
                    <p class="text-sm text-gray-500 mt-1">Change your login credentials. Current password is required to make changes.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-700 flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Signed in as: <strong class="text-blue-900"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></span>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Current Password <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" required class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Enter current password to verify">
                    </div>
                    <hr class="border-gray-100 my-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">New Username</label>
                        <input type="text" name="new_username" value="<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                        <p class="text-xs text-gray-400 mt-1.5">Leave unchanged to only update the password.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">New Password</label>
                            <input type="password" name="new_password" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Leave blank to keep current">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none" placeholder="Repeat new password">
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_account" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition-colors shadow-sm">Update Account</button>
                </div>
            </div>
        </form>

        <!-- TAB: AI Configuration -->
        <?php elseif ($active_tab === 'ai'): ?>
        <form method="POST">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="font-bold text-gray-900 text-lg">AI Configuration</h2>
                    <p class="text-sm text-gray-500 mt-1">Manage the Gemini API key used by the chatbot and OCR features.</p>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Gemini API Key(s)</label>
                        <div class="relative">
                            <textarea id="api-key-input" name="gemini_api_keys" rows="3" placeholder="AIzaSy..." class="w-full border border-gray-300 rounded-lg p-3 pr-12 text-sm focus:ring-2 focus:ring-indigo-500 outline-none font-mono"><?php echo htmlspecialchars($gemini_keys); ?></textarea>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">You can add multiple keys separated by commas. The system will randomly rotate between them to avoid rate limits. Get a free key from <a href="https://aistudio.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline font-medium">Google AI Studio</a>.</p>
                    </div>
                    <?php if (!empty(trim($gemini_keys))): ?>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-sm text-emerald-800 flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        API key is configured. The AI chatbot is active.
                    </div>
                    <?php else: ?>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800 flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        No API key detected. Add one to enable the AI chatbot.
                    </div>
                    <?php endif; ?>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-500 font-medium">
                        <strong>Current Model:</strong> <span class="font-mono text-slate-700 bg-white px-1 border rounded hidden sm:inline">gemini-3-flash-preview</span> Used for chatbot responses and OCR document scanning.
                    </div>
                </div>
                <div class="p-6 bg-gray-50 border-t border-gray-100 flex justify-end">
                    <button type="submit" name="save_ai" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-6 rounded-lg transition-colors shadow-sm">Save API Key</button>
                </div>
            </div>
        </form>
        <?php endif; ?>

    </div>
    </div>
    </main>
</div>
</body>
</html>
