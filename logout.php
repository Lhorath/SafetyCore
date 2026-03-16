<?php
/**
 * Logout Script (logout.php)
 *
 * This script handles the user logout process. It is a functional script,
 * not a display page. Its sole purpose is to securely destroy the user's
 * session and then redirect them to the public homepage.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// --- 1. Start the session ---
// We must start the session to be able to access and modify the current session data.
session_start();

// --- 2. Unset all session variables ---
// Overwriting the $_SESSION array with an empty array is the most reliable
// way to clear all data stored within the session memory for this request.
$_SESSION = array();

// --- 3. Destroy the session ---
// This function removes the session data from the server storage and cleans up
// the session ID, effectively logging the user out completely.
// Note: This does not unset any global variables associated with the session, 
// nor does it unset the session cookie.
session_destroy();

// --- 4. Redirect to the homepage ---
// After the session is destroyed, redirect the user back to the main
// public landing page. The exit() call ensures no further code is executed
// and the redirect header is sent immediately.
header('Location: /');
exit();
?>