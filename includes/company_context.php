<?php
/**
 * Company Context Helper - includes/company_context.php
 *
 * Provides a centralized, reusable function for resolving the current
 * company's type and returning the appropriate location context (stores
 * or job sites) for use across all pages and API endpoints.
 *
 * This avoids scattering company-type logic throughout the codebase —
 * any page that needs to know "what kind of locations does this company use"
 * simply calls get_company_context().
 *
 * Usage:
 *   $ctx = get_company_context($conn, $_SESSION['user']['company_id']);
 *   // $ctx['type']         => 'multi_location' | 'job_based'
 *   // $ctx['is_system']    => bool
 *   // $ctx['locations']    => array of location rows
 *   // $ctx['location_key'] => 'store_id' | 'job_site_id'  (for FK references)
 *   // $ctx['location_label'] => 'Branch / Location' | 'Job Site'
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

/**
 * Resolves the company type and returns a unified context array.
 *
 * @param  mysqli $conn       Active database connection
 * @param  int    $companyId  The company ID from the session
 * @return array              Context array (see docblock above)
 */
function get_company_context(mysqli $conn, int $companyId): array {

    // Sensible fallback defaults
    $ctx = [
        'company_id'     => $companyId,
        'company_name'   => '',
        'type'           => 'multi_location',
        'is_system'      => false,
        'locations'      => [],
        'location_key'   => 'store_id',
        'location_label' => 'Branch / Location',
        'location_icon'  => 'fa-building',
        'add_location_label' => 'Add Branch',
    ];

    // Fetch company record
    $stmt = $conn->prepare(
        "SELECT company_name, company_type, is_system
         FROM companies
         WHERE id = ? AND is_active = 1
         LIMIT 1"
    );
    if (!$stmt) return $ctx;

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$company) return $ctx;

    $ctx['company_name'] = $company['company_name'];
    $ctx['type']         = $company['company_type'];
    $ctx['is_system']    = (bool)$company['is_system'];

    // Resolve location metadata based on company type
    if ($company['company_type'] === 'job_based') {
        $ctx['location_key']        = 'job_site_id';
        $ctx['location_label']      = 'Job Site';
        $ctx['location_icon']       = 'fa-hard-hat';
        $ctx['add_location_label']  = 'Add Job Site';

        // Fetch active job sites for this company
        $lStmt = $conn->prepare(
            "SELECT js.id, js.job_number, js.job_name, js.client_name, js.status, js.site_address, js.city,
                    js.supervisor_user_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS supervisor_name
             FROM job_sites js
             LEFT JOIN users u ON js.supervisor_user_id = u.id
             WHERE js.company_id = ? AND js.status IN ('Planning', 'Active')
             ORDER BY js.job_name ASC"
        );
        if ($lStmt) {
            $lStmt->bind_param("i", $companyId);
            $lStmt->execute();
            $ctx['locations'] = $lStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $lStmt->close();
        }
    } else {
        // multi_location — fetch stores
        $lStmt = $conn->prepare(
            "SELECT s.id, s.store_name, s.store_number, s.location_type, s.city,
                    s.manager_user_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS manager_name
             FROM stores s
             LEFT JOIN users u ON s.manager_user_id = u.id
             WHERE s.company_id = ? AND s.is_active = 1
             ORDER BY s.store_name ASC"
        );
        if ($lStmt) {
            $lStmt->bind_param("i", $companyId);
            $lStmt->execute();
            $ctx['locations'] = $lStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $lStmt->close();
        }
    }

    return $ctx;
}

/**
 * Returns a display-friendly label for a location row, regardless of type.
 *
 * Multi-location: "Downtown Location (5001-A)"
 * Job-based:      "Highway 16 Expansion (J-2026-001)"
 *
 * @param  array  $location  A row from stores or job_sites
 * @param  string $type      'multi_location' | 'job_based'
 * @return string
 */
function format_location_label(array $location, string $type): string {
    if ($type === 'job_based') {
        $label = $location['job_name'] ?? 'Unknown Job';
        $sub   = $location['job_number'] ?? '';
    } else {
        $label = $location['store_name'] ?? 'Unknown Store';
        $sub   = $location['store_number'] ?? '';
    }
    return $sub ? "{$label} ({$sub})" : $label;
}

/**
 * Validates that a given location ID belongs to the expected company.
 * Used by API endpoints to prevent cross-tenant IDOR (audit finding F-04).
 *
 * @param  mysqli $conn
 * @param  int    $locationId
 * @param  int    $companyId
 * @param  string $type       'multi_location' | 'job_based'
 * @return bool
 */
function validate_location_ownership(mysqli $conn, int $locationId, int $companyId, string $type): bool {
    if ($type === 'job_based') {
        $stmt = $conn->prepare(
            "SELECT id FROM job_sites WHERE id = ? AND company_id = ? LIMIT 1"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT id FROM stores WHERE id = ? AND company_id = ? LIMIT 1"
        );
    }

    if (!$stmt) return false;

    $stmt->bind_param("ii", $locationId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result !== null;
}

/**
 * Returns the users assigned to a location, regardless of type.
 * Consolidates the user_stores / user_job_sites junction table lookups.
 *
 * @param  mysqli $conn
 * @param  int    $locationId
 * @param  string $type
 * @return array  Array of user rows (id, first_name, last_name, employee_position)
 */
function get_location_employees(mysqli $conn, int $locationId, string $type): array {
    if ($type === 'job_based') {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_position
                FROM users u
                JOIN user_job_sites ujs ON u.id = ujs.user_id
                WHERE ujs.job_site_id = ?
                ORDER BY u.first_name ASC, u.last_name ASC";
    } else {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_position
                FROM users u
                JOIN user_stores us ON u.id = us.user_id
                WHERE us.store_id = ?
                ORDER BY u.first_name ASC, u.last_name ASC";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];

    $stmt->bind_param("i", $locationId);
    $stmt->execute();
    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $employees;
}
