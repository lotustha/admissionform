<?php
// chat_sync_endpoint.php
require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$session_token = $_GET['session_token'] ?? '';
$last_message_id = (int)($_GET['last_message_id'] ?? 0);

if (empty($session_token)) {
    echo json_encode(['success' => false, 'message' => 'Missing session token.']);
    exit;
}

// Get Session
$stmt = $pdo->prepare("SELECT id, status FROM chat_sessions WHERE session_token = ?");
$stmt->execute([$session_token]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode(['success' => true, 'messages' => [], 'status' => 'bot']);
    exit;
}

$session_id = $session['id'];

// Get New Messages
$stmt = $pdo->prepare("
    SELECT id, sender_type, message, created_at 
    FROM chat_messages 
    WHERE session_id = ? AND id > ? 
    ORDER BY id ASC
");
$stmt->execute([$session_id, $last_message_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'status' => $session['status'],
    'messages' => $messages
]);
