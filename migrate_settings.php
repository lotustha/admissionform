<?php
// migrate_settings.php — ONE-TIME script to migrate school_settings → app_settings
require 'includes/connect.php';

$log = [];

try {
    // 1. Fetch all data from school_settings
    $old = $pdo->query("SELECT * FROM school_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$old) throw new Exception("school_settings is empty or doesn't exist.");

    // 2. Ensure app_settings has all needed keys by upserting each one
    $mapping = [
        'school_name'     => $old['school_name']     ?? '',
        'address'         => $old['address']         ?? '',
        'contact_person'  => $old['contact_person']  ?? '',
        'contact_phone'   => $old['contact_phone']   ?? '',
        'logo_path'       => $old['logo_path']        ?? '',
        'form_title'      => $old['form_title']       ?? 'Admission Inquiry Form',
        'gemini_api_keys' => $old['gemini_api_keys']  ?? '',
    ];

    // Make sure app_settings table exists with correct schema
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
        `key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `value` TEXT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $log[] = "✅ `app_settings` table verified.";

    $upsert = $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");

    foreach ($mapping as $k => $v) {
        $upsert->execute([$k, $v]);
        $log[] = "✅ Copied: <b>$k</b> = " . htmlspecialchars(substr((string)$v, 0, 60));
    }

    // 3. Drop school_settings
    $pdo->exec("DROP TABLE IF EXISTS `school_settings`");
    $log[] = "✅ Dropped `school_settings` table.";
    $log[] = "<br><b>✅ Migration complete!</b>";

} catch (Exception $e) {
    $log[] = "❌ Error: " . $e->getMessage();
}

echo "<!DOCTYPE html><html><head><title>Migration</title><script src='https://cdn.tailwindcss.com'></script></head>
<body class='bg-gray-100 p-8 text-sm font-mono'>
<div class='max-w-xl mx-auto bg-white p-6 rounded-xl shadow border'>
<h1 class='font-bold text-xl mb-4 text-gray-800'>school_settings → app_settings Migration</h1>";
foreach ($log as $l) echo "<p class='mb-1'>$l</p>";
echo "<div class='mt-5 pt-4 border-t flex gap-3'>
<a href='dashboard.php' class='bg-emerald-600 text-white px-4 py-2 rounded text-sm hover:bg-emerald-700'>→ Dashboard</a>
<a href='manage_settings.php' class='bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700'>→ Settings</a>
</div></div></body></html>";
?>
