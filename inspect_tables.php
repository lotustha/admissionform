<?php
require 'includes/connect.php';

// Show both table structures
foreach (['app_settings', 'school_settings'] as $tbl) {
    try {
        $cols = $pdo->query("DESCRIBE `$tbl`")->fetchAll(PDO::FETCH_ASSOC);
        echo "=== $tbl ===\n";
        foreach ($cols as $c) echo "  {$c['Field']} ({$c['Type']}) default={$c['Default']}\n";
        $row = $pdo->query("SELECT * FROM `$tbl` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        echo "  DATA: " . json_encode($row) . "\n\n";
    } catch (Exception $e) {
        echo "=== $tbl === NOT FOUND: " . $e->getMessage() . "\n\n";
    }
}
?>
