<?php
/**
 * Database Connection Handler - includes/db.php
 *
 * This file is responsible for establishing a secure and persistent connection 
 * to the MySQL database using the credentials defined in config.php.
 * It initializes the mysqli object, sets the character encoding to UTF-8MB4 
 * for full Unicode support, and implements basic error handling to prevent 
 * sensitive information leakage during connection failures.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// Include the configuration file which contains the database credentials.
// require_once ensures the file is included only one time, even if requested again.
require_once 'config.php';

// This variable will hold our database connection object.
$conn = null;

try {
    // Create a new mysqli connection object using the constants from config.php.
    // The object instantiation initiates the connection to the database server.
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // Check if there was a connection error at the network or authentication level.
    if ($conn->connect_error) {
        // Log the specific error internally for system administrators.
        error_log("Database Connection Failed: " . $conn->connect_error);
        
        // Stop execution and show a generic, user-friendly message.
        // Never output $conn->connect_error directly to the screen in production.
        die("System Error: Database connection failed. Please contact technical support.");
    }

    // Set the character set to utf8mb4.
    // This is crucial for supporting full Unicode characters (including emojis)
    // and preventing SQL injection vectors related to character encoding.
    if (!$conn->set_charset("utf8mb4")) {
        // Log the error if the charset cannot be set, but don't necessarily kill the script
        // unless strict encoding is required for the application logic.
        error_log("Error loading character set utf8mb4: " . $conn->error);
    }

} catch (Exception $e) {
    // Catch any other fatal exceptions that might occur during the connection process.
    // This acts as a safety net to prevent stack traces from being exposed to the user.
    error_log("Critical Database Exception: " . $e->getMessage());
    die("A critical system error occurred. Please try again later.");
}

// If the script reaches this point, the $conn object is successfully connected,
// configured with the correct charset, and ready for use in subsequent queries.
?>