<?php
// includes/connect.php

// 1. Check if the app is installed
$config_file = __DIR__ . '/config.php';

if (!file_exists($config_file)) {
    // If we are already on setup.php, don't redirect (prevents infinite loops)
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header("Location: setup.php");
        exit;
    }
} else {
    // 2. Load the auto-generated config file
    require_once $config_file;

    // 3. Establish the secure PDO connection
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);

        // Ensure errors are thrown as exceptions for easier debugging
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch associative arrays by default
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Disable emulated prepares for strict type security against SQL injection
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    } catch (PDOException $e) {
        // Log the error securely
        error_log("Database Connection Error: " . $e->getMessage());

        // Show a generic message to the user
        die("<h3>Critical Error: Could not connect to the database.</h3><p>Please check your config.php settings.</p>");
    }
}
?>