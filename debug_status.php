<?php
require 'includes/connect.php';
$stmt = $pdo->query("SELECT * FROM admission_inquiries LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    if (array_key_exists('status', $row))
        echo "YES_STATUS_EXISTS\n";
    else
        echo "NO_STATUS_MISSING\n";
    echo "COL_COUNT: " . count($row) . "\n";
    print_r(array_keys($row));
} else {
    echo "NO_ROWS";
}
?>