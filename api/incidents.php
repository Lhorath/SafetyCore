<?php
/**
 * Incident Management API - api/incidents.php
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-09 — get_details and update_review now scope all queries by the calling
 *           user's company_id (via the incidents.company_id column or a JOIN
 *           through stores). A user from Tenant A can no longer retrieve or
 *           update an incident belonging to Tenant B by guessing its ID.
 *   F-02 — csrf_verify_or_die() added to all state-changing actions (POST).
 *   F-17 — 'JHSC Leader' added alongside 'JHSC Member' in $allowedRoles
 *           (matches the new DB role added in Beta 09).
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$action    = $_GET['action'] ?? '';
$userId    = (int)$_SESSION['user']['id'];
$companyId = (int)$_SESSION['user']['company_id'];   // tenant scope for all queries
$userRole  = $_SESSION['user']['role_name'] ?? '';

$allowedRoles = [
    'Admin',
    'Manager',
    'Safety Manager',
    'Safety Leader',
    'Owner / CEO',
    'Co-manager',
    'JHSC Member',   // F-17: was 'JHSC Leader' in original code — wrong name
    'JHSC Leader',   // F-17: new role added in Beta 09
];

$isManager = in_array($userRole, $allowedRoles);

switch ($action) {

    // ── get_store_incidents ───────────────────────────────────────────────────
    case 'get_store_incidents':
        if (!$isManager) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit();
        }

        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

        if ($storeId > 0) {
            // F-09: scope store to calling user's company_id via JOIN
            $sql = "SELECT i.id, i.incident_type, i.incident_date, i.status,
                           i.is_recordable, i.is_lost_time
                    FROM incidents i
                    JOIN stores s ON i.store_id = s.id
                    WHERE i.store_id = ?
                      AND s.company_id = ?
                    ORDER BY i.incident_date DESC";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $storeId, $companyId);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database query failed.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Store ID.']);
        }
        break;

    // ── get_details ───────────────────────────────────────────────────────────
    case 'get_details':
        if (!$isManager) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit();
        }

        $incidentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($incidentId > 0) {
            // F-09 FIX: AND s.company_id = ? prevents cross-tenant IDOR.
            // If the incident belongs to another company, zero rows are returned
            // and the caller receives a "not found" response — not a data leak.
            $sql = "SELECT i.*,
                           s.store_name,
                           CONCAT(u.first_name,  ' ', u.last_name)  AS reporter_name,
                           CONCAT(ru.first_name, ' ', ru.last_name) AS reviewer_name
                    FROM incidents i
                    JOIN stores s    ON i.store_id          = s.id
                    JOIN users  u    ON i.reporter_user_id  = u.id
                    LEFT JOIN users ru ON i.reviewed_by_user_id = ru.id
                    WHERE i.id = ?
                      AND s.company_id = ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $incidentId, $companyId);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();

                if ($data) {
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Incident not found.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database query failed.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Incident ID.']);
        }
        break;

    // ── update_review ─────────────────────────────────────────────────────────
    case 'update_review':
        if (!$isManager) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit();
        }

        // F-02: CSRF required on all state-changing API calls
        csrf_verify_or_die();

        $input = json_decode(file_get_contents('php://input'), true);

        $incidentId   = isset($input['incident_id']) ? (int)$input['incident_id'] : 0;
        $status       = $input['status'] ?? 'Open';
        $isRecordable = !empty($input['is_recordable']) ? 1 : 0;
        $isLostTime   = !empty($input['is_lost_time'])  ? 1 : 0;
        $notes        = trim($input['manager_review_notes'] ?? '');

        $validStatuses = ['Open', 'Under Review', 'Closed'];
        if (!in_array($status, $validStatuses)) $status = 'Open';

        if ($incidentId > 0) {
            // F-09 FIX: subquery ensures the incident belongs to the calling tenant.
            // UPDATE will affect 0 rows (and report failure) if company_id mismatches.
            $sql = "UPDATE incidents i
                    JOIN stores s ON i.store_id = s.id
                    SET i.status                = ?,
                        i.is_recordable         = ?,
                        i.is_lost_time          = ?,
                        i.manager_review_notes  = ?,
                        i.reviewed_by_user_id   = ?
                    WHERE i.id      = ?
                      AND s.company_id = ?";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("siisiii",
                    $status, $isRecordable, $isLostTime, $notes,
                    $userId, $incidentId, $companyId
                );
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Incident not found or access denied.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['success' => false, 'message' => 'Database query failed.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid Incident ID provided.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid API action requested.']);
        break;
}

if (isset($conn)) $conn->close();
?>
