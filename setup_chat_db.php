<?php
require_once __DIR__ . '/includes/connect.php';

echo "Running Database Updates...\n";

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS knowledge_base (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            content TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
    ");
    echo "knowledge_base created or exists.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_token VARCHAR(255) NOT NULL UNIQUE,
            inquiry_id INT NULL,
            status ENUM('bot', 'human_requested', 'human_active', 'resolved') DEFAULT 'bot',
            assigned_to INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (inquiry_id) REFERENCES admission_inquiries(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_to) REFERENCES admins(id) ON DELETE SET NULL
        );
    ");
    echo "chat_sessions created or exists.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            sender_type ENUM('user', 'bot', 'admin') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
        );
    ");
    echo "chat_messages created or exists.\n";
    
    echo "Done.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
