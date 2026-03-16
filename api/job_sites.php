<?php
/**
 * Job Sites API - api/job_sites.php
 *
 * Handles AJAX requests for Job-Based company location management.
 * Mirrors the store/location pattern in hazard_reporting.php but
 * operates on the job_sites and user_job_sites tables.
 *
 * Actions:
 *   get_job_sites        — fetch active job sites for a company
 *   get_site_employees   — fetch users assigned to a specific job site
 *   add_job_site         — create a new job site (managers/admins)
 *   update_site_status   — change job site lifecycle status
 *
 * Security:
 *   - Auth check at file top (Audit F-03 pattern)
 *   - All queries scoped to session company_id (Audit F-04 pattern)
 *   - Prepared statements throughout
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/company_context.php';

// ── Auth gate (Audit F-03 fix applied to all actions) ─────────────────────
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$action    = $_GET['action'] ?? '';
$companyId = $_SESSION['user']['company_id'];
$userId    = $_SESSION['user']['id'];
$userRole  = $_SESSION['user']['role_name'] ?? '';

// Verify this company is actually job-based
if ($_SESSION['user']['company_type'] !== 'job_based') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This endpoint is only available for job-based companies.']);
    exit();
}

$managerRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager'];
$isManager    = in_array($userRole, $managerRoles);

switch ($action) {

    // ── Get all active job sites for this company ──────────────────────────
    case 'get_job_sites':
        $sql = "SELECT id, job_number, job_name, client_name, status, city
                FROM job_sites
                WHERE company_id = ? AND status IN ('Planning', 'Active')
                ORDER BY job_name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    // ── Get employees assigned to a specific job site ──────────────────────
    case 'get_site_employees':
        $jobSiteId = isset($_GET['job_site_id']) ? (int)$_GET['job_site_id'] : 0;

        if ($jobSiteId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid job site ID.']);
            break;
        }

        // Audit F-04 pattern: scope to company_id before fetching
        if (!validate_location_ownership($conn, $jobSiteId, $companyId, 'job_based')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

        $employees = get_location_employees($conn, $jobSiteId, 'job_based');
        echo json_encode(['success' => true, 'data' => $employees]);
        break;

    // ── Add a new job site ─────────────────────────────────────────────────
    case 'add_job_site':
        if (!$isManager) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
            break;
        }

        $jobNumber   = trim($input['job_number'] ?? '');
        $jobName     = trim($input['job_name'] ?? '');
        $clientName  = trim($input['client_name'] ?? '');
        $siteAddress = trim($input['site_address'] ?? '');
        $city        = trim($input['city'] ?? '');
        $startDate   = !empty($input['start_date']) ? $input['start_date'] : null;

        if (empty($jobNumber) || empty($jobName)) {
            echo json_encode(['success' => false, 'message' => 'Job number and job name are required.']);
            break;
        }

        $sql  = "INSERT INTO job_sites
                    (company_id, job_number, job_name, client_name, site_address, city, start_date, created_by_user_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issssssi",
            $companyId, $jobNumber, $jobName,
            $clientName, $siteAddress, $city,
            $startDate, $userId
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'job_name' => $jobName]);
        } else {
            $msg = ($conn->errno === 1062)
                ? 'A job site with this number already exists.'
                : 'Database error.';
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        $stmt->close();
        break;

    // ── Update job site lifecycle status ──────────────────────────────────
    case 'update_site_status':
        if (!$isManager) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

        $input     = json_decode(file_get_contents('php://input'), true);
        $jobSiteId = isset($input['job_site_id']) ? (int)$input['job_site_id'] : 0;
        $newStatus = $input['status'] ?? '';

        $validStatuses = ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'];
        if ($jobSiteId <= 0 || !in_array($newStatus, $validStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid job site ID or status.']);
            break;
        }

        // Scope check — ensure job site belongs to this company
        if (!validate_location_ownership($conn, $jobSiteId, $companyId, 'job_based')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

        $stmt = $conn->prepare(
            "UPDATE job_sites SET status = ? WHERE id = ? AND company_id = ?"
        );
        $stmt->bind_param("sii", $newStatus, $jobSiteId, $companyId);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed or no change made.']);
        }
        $stmt->close();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

if (isset($conn)) $conn->close();
