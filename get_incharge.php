<?php
// get_incharge.php — Public endpoint: returns incharge info for a faculty/class
require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : 0;
$class_name = isset($_GET['class_name']) ? trim($_GET['class_name']) : '';

if ($faculty_id > 0) {
    $stmt = $pdo->prepare("SELECT incharge_name, incharge_title, incharge_whatsapp, incharge_photo_path FROM faculties WHERE id = ?");
    $stmt->execute([$faculty_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // Fallback: search by class name
    echo json_encode(['success' => false, 'message' => 'No faculty ID provided.']);
    exit;
}

if ($data && !empty($data['incharge_name'])) {
    echo json_encode([
        'success'       => true,
        'incharge_name' => $data['incharge_name'],
        'incharge_title'=> $data['incharge_title'] ?? '',
        'whatsapp'      => $data['incharge_whatsapp'] ?? '',
        'photo'         => $data['incharge_photo_path'] ?? '',
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No incharge assigned for this class.']);
}
