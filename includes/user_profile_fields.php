<?php
/**
 * User profile field constants and validators.
 *
 * @package Sentry OHS
 */

const USER_STATUS_VALUES = ['active', 'inactive', 'suspended', 'terminated'];
const USER_EMPLOYMENT_TYPE_VALUES = ['full_time', 'part_time', 'contractor', 'temporary'];

/**
 * Trim a scalar value and return null when empty.
 */
function upf_nullable_string($value, int $maxLen = 255): ?string {
    if (!is_scalar($value)) {
        return null;
    }
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }
    if (strlen($v) > $maxLen) {
        $v = substr($v, 0, $maxLen);
    }
    return $v;
}

/**
 * Validate phone number in a broad international-safe pattern.
 */
function upf_valid_phone(?string $phone): bool {
    if ($phone === null || $phone === '') {
        return true;
    }
    return preg_match('/^\+?[0-9\-\(\)\s\.]{7,30}$/', $phone) === 1;
}

/**
 * Validate language tag (simple format: en or en-CA).
 */
function upf_valid_language(?string $lang): bool {
    if ($lang === null || $lang === '') {
        return true;
    }
    return preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $lang) === 1;
}

/**
 * Validate timezone against PHP timezone registry.
 */
function upf_valid_timezone(?string $tz): bool {
    if ($tz === null || $tz === '') {
        return true;
    }
    return in_array($tz, DateTimeZone::listIdentifiers(), true);
}

/**
 * Validate user status enum.
 */
function upf_valid_status(?string $status): bool {
    if ($status === null || $status === '') {
        return true;
    }
    return in_array($status, USER_STATUS_VALUES, true);
}

/**
 * Validate employment type enum.
 */
function upf_valid_employment_type(?string $type): bool {
    if ($type === null || $type === '') {
        return true;
    }
    return in_array($type, USER_EMPLOYMENT_TYPE_VALUES, true);
}

/**
 * Ensure supervisor belongs to the same company scope.
 */
function upf_supervisor_in_company(mysqli $conn, ?int $supervisorId, int $companyId): bool {
    if (!$supervisorId) {
        return true;
    }

    $sql = "SELECT 1
            FROM users u
            WHERE u.id = ?
              AND (
                    EXISTS (
                        SELECT 1
                        FROM user_stores us
                        JOIN stores s ON us.store_id = s.id
                        WHERE us.user_id = u.id AND s.company_id = ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM user_job_sites ujs
                        JOIN job_sites js ON ujs.job_site_id = js.id
                        WHERE ujs.user_id = u.id AND js.company_id = ?
                    )
              )
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iii", $supervisorId, $companyId, $companyId);
    $stmt->execute();
    $ok = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    return $ok;
}

/**
 * Fetch supervisor candidates in a company.
 */
function upf_get_supervisor_candidates(mysqli $conn, int $companyId): array {
    $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position
            FROM users u
            LEFT JOIN user_stores us ON u.id = us.user_id
            LEFT JOIN stores s ON us.store_id = s.id
            LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
            LEFT JOIN job_sites js ON ujs.job_site_id = js.id
            WHERE s.company_id = ? OR js.company_id = ?
            ORDER BY u.first_name ASC, u.last_name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ii", $companyId, $companyId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * Fetch supervisor candidates with location IDs for client-side filtering.
 *
 * Returns rows with:
 *   id, first_name, last_name, employee_position, location_ids_csv
 */
function upf_get_supervisor_candidates_by_type(mysqli $conn, int $companyId, string $companyType): array {
    if ($companyType === 'job_based') {
        $sql = "SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.employee_position,
                    GROUP_CONCAT(DISTINCT ujs.job_site_id ORDER BY ujs.job_site_id SEPARATOR ',') AS location_ids_csv
                FROM users u
                JOIN user_job_sites ujs ON u.id = ujs.user_id
                JOIN job_sites js ON ujs.job_site_id = js.id
                WHERE js.company_id = ?
                GROUP BY u.id
                ORDER BY u.first_name ASC, u.last_name ASC";
    } else {
        $sql = "SELECT
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.employee_position,
                    GROUP_CONCAT(DISTINCT us.store_id ORDER BY us.store_id SEPARATOR ',') AS location_ids_csv
                FROM users u
                JOIN user_stores us ON u.id = us.user_id
                JOIN stores s ON us.store_id = s.id
                WHERE s.company_id = ?
                GROUP BY u.id
                ORDER BY u.first_name ASC, u.last_name ASC";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

