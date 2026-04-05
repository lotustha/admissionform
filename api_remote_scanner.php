<?php
// api_remote_scanner.php - Handles push/poll for mobile-to-PC scanner link
session_start();
require_once __DIR__ . '/includes/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'create') {
    $token = bin2hex(random_bytes(8)); // 16 char token
    $stmt = $pdo->prepare("INSERT INTO remote_scanner_sessions (session_token) VALUES (?)");
    $stmt->execute([$token]);
    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

if ($action === 'push') {
    $token = $_POST['token'] ?? '';
    $payload = $_POST['payload'] ?? '';
    if (!$token || !$payload) {
        echo json_encode(['error' => 'Missing data']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE remote_scanner_sessions SET last_payload = ? WHERE session_token = ?");
    $updated = $stmt->execute([$payload, $token]) && $stmt->rowCount() > 0;
    
    echo json_encode(['success' => $updated]);
    exit;
}

if ($action === 'poll') {
    $token = $_GET['token'] ?? '';
    $last_time = (int)($_GET['last_time'] ?? 0);
    
    if (!$token) {
        echo json_encode(['error' => 'Missing token']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT last_payload, UNIX_TIMESTAMP(updated_at) as updated_ts FROM remote_scanner_sessions WHERE session_token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['last_payload']) {
        $ts = (int)$row['updated_ts'];
        if ($ts > $last_time) {
            echo json_encode(['success' => true, 'has_data' => true, 'payload' => $row['last_payload'], 'updated_ts' => $ts]);
            exit;
        }
    }
    
    echo json_encode(['success' => true, 'has_data' => false]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
