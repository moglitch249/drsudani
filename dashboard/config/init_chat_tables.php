<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

try {
    $db = db();
    
    // Create chat_sessions table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_sessions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_token VARCHAR(64) UNIQUE NOT NULL,
        customer_name VARCHAR(100),
        customer_email VARCHAR(100),
        agent_id INT UNSIGNED NULL,
        status ENUM('waiting', 'active', 'closed') DEFAULT 'waiting',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Create chat_messages table
    $db->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        session_id INT UNSIGNED NOT NULL,
        sender_type ENUM('customer', 'agent') NOT NULL,
        sender_id INT UNSIGNED NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Chat tables created successfully.\n";

} catch (PDOException $e) {
    echo "Error creating chat tables: " . $e->getMessage() . "\n";
}
