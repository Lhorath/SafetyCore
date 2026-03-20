<?php
/**
 * Login Processing Script - includes/login_process.php
 *
 * Handles authentication via Company Code + Email + Password.
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-07 — Added last_name to the user SELECT query and to the session array.
 *           api/hazard_reporting.php close_report builds the audit trail name
 *           from $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'];
 *           the missing last_name caused a broken audit string on every report closure.
 *   F-02 — CSRF token is initialised here (csrf_token() call after
 *           session_regenerate_id) so the token is fresh for the post-login
 *           session. The token is consumed and re-verified on each form submit
 *           via csrf_verify_or_die() / csrf_check().
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

session_start();
require_once 'db.php';
require_once 'csrf.php';

// ── Constants ─────────────────────────────────────────────────────────────────
const RATE_LIMIT_WINDOW_MINUTES  = 15;
const RATE_LIMIT_MAX_ATTEMPTS    = 8;
const RATE_LIMIT_LOCKOUT_MINUTES = 30;

// ── Only accept POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit();
}

// ── CSRF check ────────────────────────────────────────────────────────────────
if (!csrf_valid()) {
    header('Location: /login?error=1');
    exit();
}

// ── 1. Collect and validate raw inputs ────────────────────────────────────────
$rawCode  = $_POST['company_code'] ?? '';
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$companyCode = preg_replace('/\D/', '', $rawCode);

if (strlen($companyCode) !== 4 || empty($email) || empty($password)) {
    header('Location: /login?error=1');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /login?error=1');
    exit();
}

// ── 2. Rate limiting (DB-backed) ──────────────────────────────────────────────
$ipAddress  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$identifier = hash('sha256', $companyCode . strtolower($email));

// mysqli bind_param() passes arguments by reference — PHP constants cannot be
// passed by reference, so copy them into variables first.
$windowMinutes = RATE_LIMIT_WINDOW_MINUTES;
$maxAttempts   = RATE_LIMIT_MAX_ATTEMPTS;

$cleanStmt = $conn->prepare(
    "DELETE FROM login_attempts
     WHERE identifier = ? AND attempted_at < NOW() - INTERVAL ? MINUTE"
);
$cleanStmt->bind_param("si", $identifier, $windowMinutes);
$cleanStmt->execute();
$cleanStmt->close();

$countStmt = $conn->prepare(
    "SELECT COUNT(*) as attempts
     FROM login_attempts
     WHERE identifier = ?
       AND attempted_at >= NOW() - INTERVAL ? MINUTE"
);
$countStmt->bind_param("si", $identifier, $windowMinutes);
$countStmt->execute();
$attemptCount = $countStmt->get_result()->fetch_assoc()['attempts'] ?? 0;
$countStmt->close();

if ($attemptCount >= $maxAttempts) {
    header('Location: /login?error=locked');
    exit();
}

// ── Helper: record failed attempt and redirect ─────────────────────────────────
function fail_login(mysqli $conn, string $identifier, string $ipAddress): never {
    $ins = $conn->prepare(
        "INSERT INTO login_attempts (ip_address, identifier) VALUES (?, ?)"
    );
    $ins->bind_param("ss", $ipAddress, $identifier);
    $ins->execute();
    $ins->close();
    header('Location: /login?error=1');
    exit();
}

// ── 3. Resolve company from code ──────────────────────────────────────────────
$companyStmt = $conn->prepare(
    "SELECT id, company_name, company_type, is_system, is_active
     FROM companies
     WHERE company_code = ?
     LIMIT 1"
);
$companyStmt->bind_param("s", $companyCode);
$companyStmt->execute();
$company = $companyStmt->get_result()->fetch_assoc();
$companyStmt->close();

// ── 4. User lookup ────────────────────────────────────────────────────────────
// F-07 FIX: include last_name in the SELECT so it can be stored in the session.
$userStmt = $conn->prepare(
    "SELECT u.id, u.first_name, u.last_name, u.email, u.password, u.employee_position,
            u.employee_code, u.status, u.employment_type, u.department, u.phone_number,
            u.hire_date, u.last_login_at, u.password_changed_at, u.mfa_enabled,
            u.preferred_language, u.timezone,
            r.role_name
     FROM users u
     JOIN roles r ON u.role_id = r.id
     WHERE u.email = ?
     LIMIT 1"
);
$userStmt->bind_param("s", $email);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

// ── 5. Timing-safe credential verification ────────────────────────────────────
$DUMMY_HASH    = '$2y$12$invaliddummyhashfortimingequalisation000000000000000000';
$hashToVerify  = $user ? $user['password'] : $DUMMY_HASH;
$passwordValid = password_verify($password, $hashToVerify);

if (!$company || !$company['is_active']) {
    fail_login($conn, $identifier, $ipAddress);
}

if (!$user || !$passwordValid) {
    fail_login($conn, $identifier, $ipAddress);
}

if (($user['status'] ?? 'active') !== 'active') {
    fail_login($conn, $identifier, $ipAddress);
}

// ── 6. Multi-tenant access check ──────────────────────────────────────────────
$companyId     = (int)$company['id'];
$accessGranted = false;

if ($user['role_name'] === 'Admin' && (bool)$company['is_system']) {
    $accessGranted = true;
} elseif ($user['role_name'] === 'Admin') {
    $accessGranted = true;
} else {
    if ($company['company_type'] === 'job_based') {
        $accessSql = "SELECT 1
                      FROM user_job_sites ujs
                      JOIN job_sites js ON ujs.job_site_id = js.id
                      WHERE ujs.user_id = ? AND js.company_id = ?
                      LIMIT 1";
    } else {
        $accessSql = "SELECT 1
                      FROM user_stores us
                      JOIN stores s ON us.store_id = s.id
                      WHERE us.user_id = ? AND s.company_id = ?
                      LIMIT 1";
    }

    $accessStmt = $conn->prepare($accessSql);
    $accessStmt->bind_param("ii", $user['id'], $companyId);
    $accessStmt->execute();
    $accessGranted = ($accessStmt->get_result()->fetch_assoc() !== null);
    $accessStmt->close();
}

if (!$accessGranted) {
    fail_login($conn, $identifier, $ipAddress);
}

// ── 7. Fetch primary location for session context ─────────────────────────────
$primaryLocationId = 0;
$locationKey       = ($company['company_type'] === 'job_based') ? 'job_site_id' : 'store_id';

if ($user['role_name'] !== 'Admin') {
    if ($company['company_type'] === 'job_based') {
        $locSql = "SELECT ujs.job_site_id AS location_id
                   FROM user_job_sites ujs
                   JOIN job_sites js ON ujs.job_site_id = js.id
                   WHERE ujs.user_id = ? AND js.company_id = ?
                   LIMIT 1";
    } else {
        $locSql = "SELECT us.store_id AS location_id
                   FROM user_stores us
                   JOIN stores s ON us.store_id = s.id
                   WHERE us.user_id = ? AND s.company_id = ?
                   LIMIT 1";
    }

    $locStmt = $conn->prepare($locSql);
    $locStmt->bind_param("ii", $user['id'], $companyId);
    $locStmt->execute();
    $locRow            = $locStmt->get_result()->fetch_assoc();
    $primaryLocationId = $locRow['location_id'] ?? 0;
    $locStmt->close();
}

// ── 8. Clear rate-limit records on successful login ───────────────────────────
$clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
$clearStmt->bind_param("s", $identifier);
$clearStmt->execute();
$clearStmt->close();

// ── 9. Build session ──────────────────────────────────────────────────────────
// session_regenerate_id invalidates the old session ID to prevent fixation.
// csrf_token() is then called to seed a fresh token into the new session.
session_regenerate_id(true);

$_SESSION['user'] = [
    'id'           => (int)$user['id'],
    'first_name'   => $user['first_name'],
    'last_name'    => $user['last_name'],      // F-07 FIX: was missing, broke audit trail
    'email'        => $user['email'],
    'employee_position' => $user['employee_position'],
    'employee_code' => $user['employee_code'],
    'status' => $user['status'],
    'employment_type' => $user['employment_type'],
    'department' => $user['department'],
    'phone_number' => $user['phone_number'],
    'hire_date' => $user['hire_date'],
    'last_login_at' => $user['last_login_at'],
    'password_changed_at' => $user['password_changed_at'],
    'mfa_enabled' => (int)($user['mfa_enabled'] ?? 0),
    'preferred_language' => $user['preferred_language'],
    'timezone' => $user['timezone'],
    'role_name'    => $user['role_name'],
    'company_id'   => $companyId,
    'company_name' => $company['company_name'],
    'company_type' => $company['company_type'],
    'is_system'    => (bool)$company['is_system'],
    'location_key' => $locationKey,
    'store_id'     => $primaryLocationId,      // kept for backward compat
];

$lastLoginStmt = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
if ($lastLoginStmt) {
    $lastLoginStmt->bind_param("i", $user['id']);
    $lastLoginStmt->execute();
    $lastLoginStmt->close();
}

// Seed a fresh CSRF token for the new session (F-02)
csrf_token();

header('Location: /');
exit();