<?php
/**
 * migration.php — Schema Version Manager
 *
 * This file is auto-included by includes/connect.php on every request.
 * It checks the current DB schema version and applies any missing
 * structural changes (new columns, tables) safely using IF NOT EXISTS.
 *
 * CURRENT VERSION: 1.9
 */

define('APP_VERSION', '1.9');

function run_migrations(PDO $pdo): void
{
    // Ensure the app_settings table exists (minimum bootstrap)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            `key` VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) { return; }

    // Read current schema version
    $row = $pdo->query("SELECT `value` FROM app_settings WHERE `key` = 'schema_version' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $current = (float)($row['value'] ?? 0);

    // ── Version 1.0 – base schema (setup.php handles this) ─────────────────
    // ── Version 1.1 – columns added across previous sessions ───────────────
    if ($current < 1.1) {
        $patches = [
            // class_seats additions
            "ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS is_open BOOLEAN DEFAULT 1 AFTER total_seats",
            "ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS contact_person VARCHAR(150) NULL AFTER is_open",
            "ALTER TABLE class_seats ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) NULL AFTER contact_person",
            // faculties additions
            "ALTER TABLE faculties ADD COLUMN IF NOT EXISTS incharge_name VARCHAR(200) DEFAULT NULL",
            "ALTER TABLE faculties ADD COLUMN IF NOT EXISTS incharge_whatsapp VARCHAR(30) DEFAULT NULL",
            "ALTER TABLE faculties ADD COLUMN IF NOT EXISTS incharge_photo_path VARCHAR(500) DEFAULT NULL",
            "ALTER TABLE faculties ADD COLUMN IF NOT EXISTS incharge_title VARCHAR(100) DEFAULT NULL",
            // admission_inquiries additions
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS student_email VARCHAR(150) NULL AFTER student_last_name",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS form_type ENUM('Admission','Inquiry') DEFAULT 'Admission' AFTER status",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS session_id INT NULL DEFAULT NULL AFTER form_type",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS birth_cert_path VARCHAR(255) NULL AFTER document_path",
            // entrance_schedules: ensure table exists
            "CREATE TABLE IF NOT EXISTS entrance_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                class_name VARCHAR(50) NOT NULL,
                faculty_id INT,
                exam_date DATE NOT NULL,
                exam_time VARCHAR(50) NOT NULL,
                venue VARCHAR(150) NOT NULL,
                total_capacity INT NOT NULL,
                FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON DELETE SET NULL
            )",
            // academic_sessions: ensure table exists
            "CREATE TABLE IF NOT EXISTS academic_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_label VARCHAR(100) NOT NULL,
                start_year VARCHAR(10) NOT NULL,
                end_year VARCHAR(10) NOT NULL,
                is_active TINYINT DEFAULT 0,
                admission_open TINYINT DEFAULT 1,
                inquiry_open TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            // knowledge_base: ensure table exists
            "CREATE TABLE IF NOT EXISTS knowledge_base (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(100) NOT NULL,
                content TEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            // chat tables: ensure they exist
            "CREATE TABLE IF NOT EXISTS chat_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_token VARCHAR(255) NOT NULL UNIQUE,
                inquiry_id INT NULL,
                status ENUM('bot','human_requested','human_active','resolved') DEFAULT 'bot',
                assigned_to INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (inquiry_id) REFERENCES admission_inquiries(id) ON DELETE SET NULL
            )",
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                sender_type ENUM('user','bot','admin') NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
            )",
        ];

        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip if already exists */ }
        }

        _set_schema_version($pdo, 1.1);
        $current = 1.1;
    }

    // ── Version 1.2 – future additions go here ─────────────────────────────
    if ($current < 1.2) {
        // Placeholder for future schema changes
        // e.g. "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS notes TEXT NULL"
        _set_schema_version($pdo, 1.2);
        $current = 1.2;
    }

    // ── Version 1.3 – Payment Fields ──────────────────────────────────────────
    if ($current < 1.3) {
        $patches = [
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS payment_status ENUM('Pending', 'Paid', 'Failed') DEFAULT 'Pending'",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS payment_amount DECIMAL(10,2) NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) NULL"
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        // Insert default application fee if missing
        try {
            $pdo->exec("INSERT IGNORE INTO app_settings (`key`, `value`) VALUES ('application_fee', '500')");
        } catch (PDOException $e) {}

        _set_schema_version($pdo, 1.3);
        $current = 1.3;
    }

    // ── Version 1.4 – Invoice / Billing System ────────────────────────────
    if ($current < 1.4) {
        $patches = [
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(20) NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS payment_date DATETIME NULL",
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        // Insert default invoice settings if missing
        try {
            $pdo->exec("INSERT IGNORE INTO app_settings (`key`, `value`) VALUES ('invoice_prefix', 'INV')");
            $pdo->exec("INSERT IGNORE INTO app_settings (`key`, `value`) VALUES ('invoice_start_number', '1')");
        } catch (PDOException $e) {}

        _set_schema_version($pdo, 1.4);
        $current = 1.4;
    }

    // ── Version 1.5 – Entrance Exam Result Publication ────────────────────
    if ($current < 1.5) {
        $patches = [
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS marks_obtained DECIMAL(5,2) NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS total_marks DECIMAL(5,2) DEFAULT 100",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS result_status ENUM('Pending','Pass','Fail','Waitlisted') DEFAULT 'Pending'",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS result_remarks TEXT NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS result_published_at DATETIME NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS result_published_by INT NULL",
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        // Default pass percentage
        try {
            $pdo->exec("INSERT IGNORE INTO app_settings (`key`, `value`) VALUES ('result_pass_percentage', '40')");
        } catch (PDOException $e) {}

        _set_schema_version($pdo, 1.5);
        $current = 1.5;
    }

    // ── Version 1.6 – Role Based Access Control (RBAC) ────────────────────
    if ($current < 1.6) {
        $patches = [
            "ALTER TABLE admins ADD COLUMN IF NOT EXISTS role ENUM('Super Admin', 'Academic Staff', 'Cashier', 'Viewer') DEFAULT 'Super Admin'"
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        _set_schema_version($pdo, 1.6);
        $current = 1.6;
    }

    // ── Version 1.7 – Attendance & Interviews ────────────────────────────────
    if ($current < 1.7) {
        $patches = [
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS attendance_status ENUM('Pending', 'Present', 'Absent') DEFAULT 'Pending'",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS interview_status ENUM('Pending', 'Scheduled', 'Selected', 'Rejected', 'Waitlisted') DEFAULT 'Pending'",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS interview_date DATE NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS interview_time VARCHAR(50) NULL",
            "ALTER TABLE admission_inquiries ADD COLUMN IF NOT EXISTS interview_remarks TEXT NULL"
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        _set_schema_version($pdo, 1.7);
        $current = 1.7;
    }

    // ── Version 1.8 – UI Enhancements ────────────────────────────────────────
    if ($current < 1.8) {
        _set_schema_version($pdo, 1.8);
        $current = 1.8;
    }

    // ── Version 1.9 – Mobile Scanner Companion ────────────────────────────────
    if ($current < 1.9) {
        $patches = [
            "CREATE TABLE IF NOT EXISTS remote_scanner_sessions (
                session_token VARCHAR(50) PRIMARY KEY,
                last_payload TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];
        foreach ($patches as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* skip */ }
        }

        _set_schema_version($pdo, 1.9);
        $current = 1.9;
    }
}

function _set_schema_version(PDO $pdo, float $version): void
{
    $pdo->prepare("INSERT INTO app_settings (`key`, `value`) VALUES ('schema_version', ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$version]);
}
