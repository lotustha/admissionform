<?php
require_once __DIR__ . '/includes/connect.php';

echo "Checking Dotenv...\n";
if (class_exists('Dotenv\Dotenv')) {
    echo "SUCCESS: Dotenv\Dotenv found.\n";
} else {
    echo "FAILURE: Dotenv\Dotenv NOT found.\n";
}

echo "Checking Dompdf...\n";
if (class_exists('Dompdf\Dompdf')) {
    echo "SUCCESS: Dompdf\Dompdf found.\n";
} else {
    echo "FAILURE: Dompdf\Dompdf NOT found.\n";
}
?>
