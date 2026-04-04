<?php
// Replicate inquiries.php logic exactly
require_once __DIR__ . '/includes/connect.php';
echo '<pre>';

$search       = '';
$status_filter= '';
$sort_by      = 'i.id';
$sort_dir     = 'DESC';
$page         = 1;
$limit        = 25;
$offset       = 0;

$where = ["(i.form_type = 'Inquiry' OR i.form_type IS NULL)"];
$params = [];
$where_sql = implode(' AND ', $where);

echo "WHERE SQL: $where_sql\n";
echo "PARAMS: " . json_encode($params) . "\n\n";

$sql = "SELECT i.*, f.faculty_name FROM admission_inquiries i LEFT JOIN faculties f ON i.faculty_id = f.id WHERE $where_sql ORDER BY $sort_by $sort_dir LIMIT $limit OFFSET $offset";
echo "SQL: $sql\n\n";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo "Row count: " . count($rows) . "\n";
if (!empty($rows)) {
    echo "Keys in first row: " . implode(', ', array_keys($rows[0])) . "\n";
    echo "status = " . var_export($rows[0]['status'] ?? 'MISSING', true) . "\n";
    echo "student_first_name = " . var_export($rows[0]['student_first_name'] ?? 'MISSING', true) . "\n";
    echo "form_type = " . var_export($rows[0]['form_type'] ?? 'MISSING', true) . "\n";
}

echo "\n\nFull first row:\n";
print_r($rows[0] ?? []);
echo '</pre>';
