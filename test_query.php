<?php
require 'includes/connect.php';
$where_sql = "(i.form_type = 'Inquiry' OR i.form_type IS NULL)";
$stmt = $pdo->prepare("SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id WHERE $where_sql LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "Keys in fetched row:\n";
    print_r(array_keys($row));
} else {
    echo "No rows found for inquiry.";
}
?>
