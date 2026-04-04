<?php
require 'includes/connect.php';
$stmt = $pdo->query("SELECT * FROM admission_inquiries LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "KEYS: " . implode(", ", array_keys($row));
} else {
    echo "NO ROWS FOUND";
}
?>