<?php
// includes/connect.php

// Check if config exists
if (!file_exists(__DIR__ . '/../.env')) {
    // Determine base URL to redirect to setup.php
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

    // If this file is accessed directly, move one directory up
    if (basename($_SERVER['PHP_SELF']) === 'connect.php') {
        $uri = rtrim(dirname($uri), '/\\');
    }

    header("Location: $protocol://$host$uri/setup.php");
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Backwards compatibility layer for legacy code
if (!defined('DB_HOST')) define('DB_HOST', $_ENV['DB_HOST']);
if (!defined('DB_NAME')) define('DB_NAME', $_ENV['DB_NAME']);
if (!defined('DB_USER')) define('DB_USER', $_ENV['DB_USER']);
if (!defined('DB_PASS')) define('DB_PASS', $_ENV['DB_PASS']);

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    // Set attributes for error handling and fetch mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Auto-run schema migrations on every request
    if (!defined('MIGRATION_RAN')) {
        define('MIGRATION_RAN', true);
        require_once __DIR__ . '/../migration.php';
        run_migrations($pdo);
    }

} catch (PDOException $e) {
    // If database doesn't exist (Error 1049), redirect to setup
    if ($e->getCode() == 1049) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $uri = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        if (basename($_SERVER['PHP_SELF']) === 'connect.php') {
            $uri = rtrim(dirname($uri), '/\\');
        }
        header("Location: $protocol://$host$uri/setup.php");
        exit;
    }

    die("Database Connection failed: " . $e->getMessage());
}
?>