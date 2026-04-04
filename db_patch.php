<?php
require_once __DIR__ . '/includes/connect.php';

try {
    $pdo->exec("ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS is_open BOOLEAN DEFAULT 1 AFTER total_seats;");
    $pdo->exec("ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS contact_person VARCHAR(150) NULL AFTER is_open;");
    $pdo->exec("ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) NULL AFTER contact_person;");
    echo "Successfully updated class_seats schema.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
