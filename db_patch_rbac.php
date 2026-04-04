<?php
require_once __DIR__ . '/includes/connect.php';

try {
    $pdo->exec("ALTER TABLE admins ADD COLUMN role ENUM('Super Admin', 'Academic Staff', 'Cashier', 'Viewer') DEFAULT 'Super Admin'");
    echo "Added role column.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
