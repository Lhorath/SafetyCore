<?php
/**
 * Global Configuration - includes/config.php
 *
 * This file contains the core configuration settings for the NorthPoint 360 application.
 * It defines immutable constants for database authentication and site-wide environment variables.
 * This file should be secured and excluded from public version control repositories in a real-world scenario.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// ============================================================================
// 1. ENVIRONMENT & DATABASE CONFIGURATION
// ============================================================================

// Load environment variables from project root .env (if present).
// This is a minimal, dependency-free loader intended for small projects.
$rootDir = dirname(__DIR__);
$envFile = $rootDir . DIRECTORY_SEPARATOR . '.env';

if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key !== '') {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

/**
 * Database Host
 * The hostname or IP address of the MySQL database server.
 */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');

/**
 * Database Name
 * The name of the specific database to select.
 */
define('DB_NAME', getenv('DB_NAME') ?: '');

/**
 * Database Username
 * The username used to authenticate with the database.
 */
define('DB_USER', getenv('DB_USER') ?: '');

/**
 * Database Password
 * The password associated with the database user.
 */
define('DB_PASS', getenv('DB_PASS') ?: '');


// ============================================================================
// 2. APPLICATION CONFIGURATION
// ============================================================================

/**
 * Site URL
 * The absolute base URL of the application, used for generating absolute links
 * (e.g., in emails or redirects) and referencing assets.
 * Ensure this includes the trailing slash.
 */
define('SITE_URL', 'http://dackdns.ddns.net/');

?>