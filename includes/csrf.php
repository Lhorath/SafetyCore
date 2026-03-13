<?php
/**
 * CSRF Token Helper - includes/csrf.php
 *
 * Provides token generation and validation for all forms and AJAX POST
 * endpoints. Addresses audit finding F-02.
 *
 * Usage — HTML forms:
 * require_once 'includes/csrf.php';
 * csrf_field();                    // outputs hidden <input>
 *
 * Usage — API endpoints (JSON POST):
 * require_once '../includes/csrf.php';
 * csrf_verify_or_die();            // halts with 403 JSON on failure
 *
 * Usage — AJAX (JavaScript):
 * Fetch the token from the meta tag rendered by csrf_meta_tag(), then
 * include it as the X-CSRF-Token request header or in the JSON body as
 * "_csrf_token".
 *
 * Token lifecycle:
 * - One token per session, regenerated on login (session_regenerate_id
 * already called in login_process.php).
 * - Token is stored in $_SESSION['csrf_token'] and verified server-side.
 * - Accepted via POST field "_csrf_token" OR header "X-CSRF-Token".
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   9.0.0 (NorthPoint Beta 09)
 */

// ── Token generation ──────────────────────────────────────────────────────────

/**
 * Returns the current session CSRF token, creating one if it doesn't exist.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ── Output helpers ────────────────────────────────────────────────────────────

/**
 * Echoes a hidden form field containing the CSRF token.
 * Drop inside every <form> that POSTs to the application.
 */
function csrf_field(): void {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    echo "<input type=\"hidden\" name=\"_csrf_token\" value=\"{$token}\">";
}

/**
 * Echoes a <meta> tag for use by JavaScript fetch/XHR calls.
 * Place in <head> via header.php.
 *
 * const token = document.querySelector('meta[name="csrf-token"]').content;
 * fetch('/api/...', { headers: { 'X-CSRF-Token': token }, ... });
 */
function csrf_meta_tag(): void {
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    echo "<meta name=\"csrf-token\" content=\"{$token}\">";
}

// ── Validation ────────────────────────────────────────────────────────────────

/**
 * Validates the incoming CSRF token from POST field or X-CSRF-Token header.
 *
 * @return bool  True on valid token, false otherwise.
 */
function csrf_valid(): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    // Accept token from POST field or from JSON body (read via header check)
    $incoming = $_POST['_csrf_token']
        ?? getallheaders()['X-CSRF-Token']
        ?? getallheaders()['x-csrf-token']
        ?? '';

    return hash_equals($_SESSION['csrf_token'], $incoming);
}

/**
 * Validates CSRF for standard form pages.
 * On failure, sets $errorMessage and returns false so the page can
 * re-render the form with an error banner rather than dying hard.
 *
 * @param  string &$errorMessage  Reference to the page's error string.
 * @return bool
 */
function csrf_check(string &$errorMessage): bool {
    if (!csrf_valid()) {
        $errorMessage = 'Your session has expired or the request was invalid. Please try again.';
        return false;
    }
    return true;
}

/**
 * Validates CSRF for API endpoints that return JSON.
 * Terminates execution with HTTP 403 and a JSON error body on failure.
 */
function csrf_verify_or_die(): void {
    if (!csrf_valid()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid or missing.']);
        exit();
    }
}

// ── Compatibility / Alias functions (Added for new Modules) ────────────────────

/**
 * Generates a secure CSRF token and stores it in the user's session.
 * Alias for csrf_token() to support the Training Matrix module.
 *
 * @return string The 64-character hex CSRF token
 */
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token(): string {
        return csrf_token();
    }
}

/**
 * Validates a submitted CSRF token against the one stored in the session.
 * Used by the Training Matrix API for manual validation matching.
 *
 * @param string $token The token submitted via POST/Fetch
 * @return bool True if valid, False if invalid or missing
 */
if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token(string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>