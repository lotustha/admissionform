<?php
require 'includes/connect.php';

$stmt = $pdo->query("DESCRIBE admission_inquiries");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("DESCRIBE academic_sessions");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
