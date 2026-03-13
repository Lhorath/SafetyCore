<?php
/**
 * Permissions Helper - includes/permissions.php
 *
 * Centralises all role/module permission lookups for the dashboard and
 * access-control gates throughout the application.
 *
 * Functions:
 *   get_user_modules()          — Returns the modules the current user can see,
 *                                 grouped by area, fetched from DB.
 *   can_access_module()         — Quick boolean check for a specific module_key.
 *   is_platform_admin()         — True only for role=Admin in the system company.
 *   is_company_admin()          — True for any management-level role in a tenant.
 *   get_allowed_roles_for_company() — Roles a Company Admin is permitted to assign
 *                                     (excludes platform-level roles).
 *
 * Design notes:
 *   - Results are cached in $GLOBALS for the request lifetime to avoid
 *     repeated identical queries within a single page load.
 *   - All queries are prepared statements scoped to role_id (never role_name)
 *     to be consistent with the DB-driven permission model.
 *   - The is_platform_admin() gate is intentionally strict: company_id MUST
 *     equal the system company (id=1) AND role must be Admin. A client company
 *     can never accidentally grant platform access by naming a role "Admin".
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   8.0.0 (NorthPoint Beta 08)
 */

require_once __DIR__ . '/db.php';

// ── Tier constants ────────────────────────────────────────────────────────────
const AREA_EMPLOYEE      = 'employee';
const AREA_COMPANY_ADMIN = 'company_admin';
const AREA_PLATFORM_ADMIN = 'platform_admin';

// ── Role name constants ───────────────────────────────────────────────────────
const ROLE_PLATFORM_ADMIN  = 'Admin';
const ROLE_OWNER_CEO       = 'Owner / CEO';
const ROLE_COMPANY_ADMIN   = 'Company Admin';
const ROLE_SAFETY_MANAGER  = 'Safety Manager';
const ROLE_MANAGER         = 'Manager';
const ROLE_CO_MANAGER      = 'Co-manager';
const ROLE_SAFETY_LEADER   = 'Safety Leader';
const ROLE_JHSC_MEMBER     = 'JHSC Member';
const ROLE_SITE_SUPERVISOR = 'Site Supervisor';

// Roles that are permitted to assign to users by a Company Admin
// (Platform Admin is deliberately excluded — only the system can grant that)
const COMPANY_ASSIGNABLE_ROLES = [
    ROLE_OWNER_CEO,
    ROLE_COMPANY_ADMIN,
    ROLE_SAFETY_MANAGER,
    ROLE_MANAGER,
    ROLE_CO_MANAGER,
    ROLE_SAFETY_LEADER,
    ROLE_JHSC_MEMBER,
    ROLE_SITE_SUPERVISOR,
    'Full Time Employee',
    'Part Time Employee',
    'Equipment Operator',
];

/**
 * Returns true if the current session user is a platform-level administrator.
 * Requires BOTH: role = 'Admin' AND company_id = 1 (system company).
 *
 * @return bool
 */
function is_platform_admin(): bool {
    if (!isset($_SESSION['user'])) return false;
    return (
        ($_SESSION['user']['role_name'] ?? '') === ROLE_PLATFORM_ADMIN &&
        (bool)($_SESSION['user']['is_system'] ?? false)
    );
}

/**
 * Returns true if the current user has company-administration privileges
 * (can manage users, structure, incidents etc. within their own company).
 * Platform Admin implicitly also has company admin access.
 *
 * @return bool
 */
function is_company_admin(): bool {
    if (!isset($_SESSION['user'])) return false;
    static $companyAdminRoles = [
        ROLE_PLATFORM_ADMIN,
        ROLE_OWNER_CEO,
        ROLE_COMPANY_ADMIN,
        ROLE_SAFETY_MANAGER,
        ROLE_MANAGER,
        ROLE_CO_MANAGER,
        ROLE_SAFETY_LEADER,
        ROLE_JHSC_MEMBER,
        ROLE_SITE_SUPERVISOR,
    ];
    return in_array($_SESSION['user']['role_name'] ?? '', $companyAdminRoles, true);
}

/**
 * Fetches the modules the current user is permitted to see, grouped by area.
 * Result is cached per request to avoid redundant queries.
 *
 * Returns:
 *   [
 *     'employee'       => [ [...module row...], ... ],
 *     'company_admin'  => [ [...module row...], ... ],
 *     'platform_admin' => [ [...module row...], ... ],
 *   ]
 *
 * @param  mysqli $conn
 * @return array
 */
function get_user_modules(mysqli $conn): array {
    if (isset($GLOBALS['_cached_user_modules'])) {
        return $GLOBALS['_cached_user_modules'];
    }

    $result = [
        AREA_EMPLOYEE       => [],
        AREA_COMPANY_ADMIN  => [],
        AREA_PLATFORM_ADMIN => [],
    ];

    if (!isset($_SESSION['user'])) {
        return $result;
    }

    // Get the role_id for the current user
    $roleId = null;
    $roleStmt = $conn->prepare(
        "SELECT r.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1"
    );
    if ($roleStmt) {
        $roleStmt->bind_param("i", $_SESSION['user']['id']);
        $roleStmt->execute();
        $roleId = $roleStmt->get_result()->fetch_assoc()['id'] ?? null;
        $roleStmt->close();
    }

    if (!$roleId) {
        return $result;
    }

    // Fetch all modules this role has permission to view
    $sql = "SELECT m.module_key, m.module_name, m.description,
                   m.icon_class, m.icon_bg, m.icon_color,
                   m.btn_class, m.btn_label, m.route, m.area
            FROM modules m
            JOIN role_module_permissions rmp ON m.id = rmp.module_id
            WHERE rmp.role_id = ?
              AND m.is_active = 1
            ORDER BY m.sort_order ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $result;
    }

    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $area = $row['area'];
        if (isset($result[$area])) {
            $result[$area][] = $row;
        }
    }

    // Platform admin modules are additionally gated on is_system check
    // (double lock — even if the DB permission exists, strip it for non-system users)
    if (!is_platform_admin()) {
        $result[AREA_PLATFORM_ADMIN] = [];
    }

    $GLOBALS['_cached_user_modules'] = $result;
    return $result;
}

/**
 * Quick boolean check: can this user access a specific module?
 *
 * @param  mysqli $conn
 * @param  string $moduleKey  e.g. 'manage_incidents'
 * @return bool
 */
function can_access_module(mysqli $conn, string $moduleKey): bool {
    $modules = get_user_modules($conn);
    foreach ($modules as $areaModules) {
        foreach ($areaModules as $mod) {
            if ($mod['module_key'] === $moduleKey) return true;
        }
    }
    return false;
}

/**
 * Returns the list of role records a Company Admin is allowed to assign
 * when creating or editing users. Platform Admin role is always excluded.
 *
 * @param  mysqli $conn
 * @return array  Array of ['id' => int, 'role_name' => string]
 */
function get_allowed_roles_for_company(mysqli $conn): array {
    $placeholders = implode(',', array_fill(0, count(COMPANY_ASSIGNABLE_ROLES), '?'));
    $types = str_repeat('s', count(COMPANY_ASSIGNABLE_ROLES));

    $stmt = $conn->prepare(
        "SELECT id, role_name FROM roles
         WHERE role_name IN ({$placeholders})
         ORDER BY FIELD(role_name,
            'Owner / CEO','Company Admin','Safety Manager','Manager',
            'Co-manager','Safety Leader','Site Supervisor','JHSC Member',
            'Full Time Employee','Part Time Employee','Equipment Operator')"
    );

    if (!$stmt) return [];

    $stmt->bind_param($types, ...COMPANY_ASSIGNABLE_ROLES);
    $stmt->execute();
    $roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $roles;
}

/**
 * Verifies a user-supplied role_id is within the set a Company Admin can assign.
 * Used in company-admin.php POST handlers to prevent privilege escalation.
 *
 * @param  mysqli $conn
 * @param  int    $roleId
 * @return bool
 */
function role_is_company_assignable(mysqli $conn, int $roleId): bool {
    $stmt = $conn->prepare(
        "SELECT 1 FROM roles WHERE id = ? AND role_name IN (
            'Owner / CEO','Company Admin','Safety Manager','Manager',
            'Co-manager','Safety Leader','Site Supervisor','JHSC Member',
            'Full Time Employee','Part Time Employee','Equipment Operator'
         ) LIMIT 1"
    );
    if (!$stmt) return false;
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    return $found;
}
