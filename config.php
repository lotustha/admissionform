<?php
// includes/connect.php

// Define database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_admission_2083');
define('DB_USER', 'root'); // Change this to your live database username later
define('DB_PASS', '');     // Change this to your live database password later

try {
    // Set DSN (Data Source Name)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

    // Create a PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    // Set PDO attributes for error handling and data fetching
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    // If connection fails, log the error and stop execution
    // In production, avoid echoing exact database errors to the user
    error_log("Connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}
?>