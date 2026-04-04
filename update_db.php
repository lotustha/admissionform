<?php
// update_db.php — Run this ONCE to apply DB migrations
require_once __DIR__ . '/includes/connect.php';

$errors = [];
$success = [];

// 1. Create academic_sessions table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_label VARCHAR(100) NOT NULL,
        start_year VARCHAR(10) NOT NULL,
        end_year VARCHAR(10) NOT NULL,
        is_active TINYINT DEFAULT 0,
        admission_open TINYINT DEFAULT 1,
        inquiry_open TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $success[] = "✅ Table `academic_sessions` created (or already exists).";

    // Seed a default session if none exists
    $count = $pdo->query("SELECT COUNT(*) FROM academic_sessions")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO academic_sessions (session_label, start_year, end_year, is_active, admission_open, inquiry_open) VALUES ('2082-2083 BS', '2082', '2083', 1, 1, 1)");
        $success[] = "✅ Default session '2082-2083 BS' seeded and set as active.";
    }
} catch (PDOException $e) {
    $errors[] = "❌ academic_sessions: " . $e->getMessage();
}

// 2. Add session_id to admission_inquiries
$cols_to_add = [
    "session_id"      => "ALTER TABLE admission_inquiries ADD COLUMN `session_id` INT NULL DEFAULT NULL",
    "form_type"       => "ALTER TABLE admission_inquiries ADD COLUMN `form_type` ENUM('Admission','Inquiry') DEFAULT 'Admission' AFTER status",
    "birth_cert_path" => "ALTER TABLE admission_inquiries ADD COLUMN `birth_cert_path` VARCHAR(255) NULL",
    "student_email"   => "ALTER TABLE admission_inquiries ADD COLUMN `student_email` VARCHAR(150) NULL",
];
foreach ($cols_to_add as $col => $sql) {
    try {
        $pdo->exec($sql);
        $success[] = "✅ Column `$col` added to `admission_inquiries`.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $success[] = "⚠️  Column `$col` already exists — skipped.";
        } else {
            $errors[] = "❌ Column `$col`: " . $e->getMessage();
        }
    }
}

// 3. Add incharge columns to faculties
$facCols = [
    "incharge_name"       => "ALTER TABLE faculties ADD COLUMN `incharge_name` VARCHAR(200) DEFAULT NULL",
    "incharge_whatsapp"   => "ALTER TABLE faculties ADD COLUMN `incharge_whatsapp` VARCHAR(30) DEFAULT NULL",
    "incharge_photo_path" => "ALTER TABLE faculties ADD COLUMN `incharge_photo_path` VARCHAR(500) DEFAULT NULL",
    "incharge_title"      => "ALTER TABLE faculties ADD COLUMN `incharge_title` VARCHAR(100) DEFAULT NULL",
];
foreach ($facCols as $col => $sql) {
    try {
        $pdo->exec($sql);
        $success[] = "✅ Column `$col` added to `faculties`.";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $success[] = "⚠️  Column `$col` (faculties) already exists — skipped.";
        } else {
            $errors[] = "❌ Column `$col` (faculties): " . $e->getMessage();
        }
    }
}

// 3.5. Add role column to admins
try {
    $pdo->exec("ALTER TABLE admins ADD COLUMN `role` ENUM('Super Admin', 'Academic Staff', 'Cashier', 'Viewer') DEFAULT 'Super Admin'");
    $success[] = "✅ Column `role` added to `admins`.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        $success[] = "⚠️  Column `role` (admins) already exists — skipped.";
    } else {
        $errors[] = "❌ Column `role` (admins): " . $e->getMessage();
    }
}

// 4. Create directories
foreach ([__DIR__.'/uploads/incharge', __DIR__.'/assets/uploads/logos', __DIR__.'/assets/uploads/documents'] as $dir) {
    if (!is_dir($dir)) { mkdir($dir, 0775, true); $success[] = "✅ Created directory: " . basename($dir); }
    else { $success[] = "⚠️  Directory already exists: " . basename($dir); }
}

// 5. Verify app_settings exists and has data
try {
    $c = $pdo->query("SELECT COUNT(*) FROM app_settings")->fetchColumn();
    $success[] = "✅ `app_settings` table OK — $c key(s) configured.";
} catch (PDOException $e) {
    $errors[] = "❌ `app_settings` missing. Run setup.php or migrate_settings.php first.";
}

echo "<!DOCTYPE html><html><head><title>DB Migration</title><script src='https://cdn.tailwindcss.com'></script></head>
<body class='bg-gray-100 p-8 font-mono text-sm'>
<div class='max-w-2xl mx-auto bg-white p-6 rounded-xl shadow border'>
<h1 class='text-xl font-bold mb-1 text-gray-800'>Database Migration</h1>
<p class='text-xs text-gray-400 mb-5'>Run once after upgrading the system.</p>";
foreach ($success as $s) { echo "<p class='mb-1 text-green-700'>$s</p>"; }
foreach ($errors as $e) { echo "<p class='mb-1 text-red-700'>$e</p>"; }
$ok = empty($errors);
echo "<p class='mt-4 font-bold " . ($ok ? 'text-emerald-600' : 'text-red-600') . "'>" . ($ok ? '✅ All done! No errors.' : '⚠️ Some errors occurred. Check above.') . "</p>
<div class='mt-5 pt-4 border-t flex flex-wrap gap-3'>
    <a href='dashboard.php' class='bg-emerald-600 text-white px-4 py-2 rounded text-sm hover:bg-emerald-700'>→ Dashboard</a>
    <a href='manage_sessions.php' class='bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700'>→ Sessions</a>
    <a href='manage_settings.php' class='bg-gray-600 text-white px-4 py-2 rounded text-sm hover:bg-gray-700'>→ Settings</a>
</div></div></body></html>";
?>
