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
// 1. DATABASE CONFIGURATION
// ============================================================================

/**
 * Database Host
 * The hostname or IP address of the MySQL database server.
 */
define('DB_HOST', '127.0.0.1');

/**
 * Database Name
 * The name of the specific database to select.
 */
define('DB_NAME', 'u971098166_safetysite');

/**
 * Database Username
 * The username used to authenticate with the database.
 */
define('DB_USER', 'safety');

/**
 * Database Password
 * The password associated with the database user.
 */
define('DB_PASS', '5I£8e:t0?3');


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