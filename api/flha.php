<?php
/**
 * FLHA API Controller - api/flha.php
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-16 — get_company_workers was acknowledged in a code comment as returning
 *           all users from all tenants. Fixed by joining through user_stores /
 *           user_job_sites to scope results to the calling user's company.
 *           The approach adapts to both store-based and job-based company types.
 *   F-02 — csrf_verify_or_die() added to save_open and close_out (write operations).
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

$action      = $_GET['action'] ?? '';
$companyId   = (int)$_SESSION['user']['company_id'];
$userId      = (int)$_SESSION['user']['id'];
$companyType = $_SESSION['user']['company_type'] ?? 'store_based';

switch ($action) {

    // ── get_company_workers ───────────────────────────────────────────────────
    case 'get_company_workers':
        // F-16 FIX: scope to the calling user's company by joining through the
        // appropriate location table based on company_type.
        // Both paths exclude the current user (cannot add yourself as co-worker).

        if ($companyType === 'job_based') {
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position
                    FROM users u
                    JOIN user_job_sites ujs ON u.id = ujs.user_id
                    JOIN job_sites js       ON ujs.job_site_id = js.id
                    WHERE js.company_id = ?
                      AND u.id != ?
                    ORDER BY u.first_name ASC";
        } else {
            $sql = "SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position
                    FROM users u
                    JOIN user_stores us ON u.id = us.user_id
                    JOIN stores s       ON us.store_id = s.id
                    WHERE s.company_id = ?
                      AND u.id != ?
                    ORDER BY u.first_name ASC";
        }

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $companyId, $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $res]);
        $stmt->close();
        break;

    // ── save_open ─────────────────────────────────────────────────────────────
    case 'save_open':
        // F-02: CSRF required on write operations
        csrf_verify_or_die();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid data payload.']);
            break;
        }

        $conn->begin_transaction();

        try {
            $sql = "INSERT INTO flha_records
                        (company_id, creator_user_id, work_to_be_done, task_location,
                         emergency_location, permit_number, warning_ribbon_required,
                         working_alone, working_alone_desc, employer_supplied_ppe)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iissssiiis",
                $companyId,
                $userId,
                $data['work_to_be_done'],
                $data['task_location'],
                $data['emergency_location'],
                $data['permit_number'],
                $data['warning_ribbon_required'],
                $data['working_alone'],
                $data['working_alone_desc'],
                $data['employer_supplied_ppe']
            );
            $stmt->execute();
            $flhaId = $stmt->insert_id;
            $stmt->close();

            $checkStmt = $conn->prepare(
                "INSERT INTO flha_checklists (flha_id, type, category, item_name) VALUES (?, ?, ?, ?)"
            );

            if (!empty($data['hazards'])) {
                $type = 'hazard';
                foreach ($data['hazards'] as $cat => $items) {
                    foreach ($items as $item) {
                        $checkStmt->bind_param("isss", $flhaId, $type, $cat, $item);
                        $checkStmt->execute();
                    }
                }
            }

            if (!empty($data['ppe'])) {
                $type = 'ppe';
                $cat  = 'General';
                foreach ($data['ppe'] as $item) {
                    $checkStmt->bind_param("isss", $flhaId, $type, $cat, $item);
                    $checkStmt->execute();
                }
            }
            $checkStmt->close();

            if (!empty($data['other_workers'])) {
                $workStmt = $conn->prepare("INSERT INTO flha_workers (flha_id, user_id) VALUES (?, ?)");
                foreach ($data['other_workers'] as $wId) {
                    $workerId = (int)$wId;
                    $workStmt->bind_param("ii", $flhaId, $workerId);
                    $workStmt->execute();
                }
                $workStmt->close();
            }

            if (!empty($data['tasks'])) {
                $taskStmt = $conn->prepare(
                    "INSERT INTO flha_tasks (flha_id, task_description, associated_hazards, mitigation_plan)
                     VALUES (?, ?, ?, ?)"
                );
                foreach ($data['tasks'] as $task) {
                    $taskStmt->bind_param("isss",
                        $flhaId, $task['desc'], $task['hazards'], $task['mitigation']
                    );
                    $taskStmt->execute();
                }
                $taskStmt->close();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'id' => $flhaId]);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("FLHA Save Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during transaction. Please contact support.']);
        }
        break;

    // ── close_out ─────────────────────────────────────────────────────────────
    case 'close_out':
        // F-02: CSRF required on write operations
        csrf_verify_or_die();

        $data   = json_decode(file_get_contents('php://input'), true);
        $flhaId = isset($data['flha_id']) ? (int)$data['flha_id'] : 0;

        if ($flhaId > 0) {
            $sql = "UPDATE flha_records SET
                        status                = 'Closed',
                        close_permits_closed  = ?,
                        close_area_cleaned    = ?,
                        close_hazards_remain  = ?,
                        close_hazards_desc    = ?,
                        close_incidents       = ?,
                        close_incidents_desc  = ?,
                        closed_at             = NOW()
                    WHERE id = ?
                      AND creator_user_id = ?
                      AND company_id      = ?";

            $stmt = $conn->prepare($sql);

            $permitsClosed = isset($data['permits_closed']) ? (int)$data['permits_closed'] : 0;
            $areaCleaned   = isset($data['area_cleaned'])   ? (int)$data['area_cleaned']   : 0;
            $hazardsRemain = isset($data['hazards_remain']) ? (int)$data['hazards_remain'] : 0;
            $hazardsDesc   = trim($data['hazards_desc']     ?? '');
            $incidents     = isset($data['incidents'])      ? (int)$data['incidents']      : 0;
            $incidentsDesc = trim($data['incidents_desc']   ?? '');

            $stmt->bind_param("iiisisiii",
                $permitsClosed, $areaCleaned, $hazardsRemain, $hazardsDesc,
                $incidents, $incidentsDesc, $flhaId, $userId, $companyId
            );

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Record not found or you do not have permission to close it.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to execute close-out update.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid FLHA ID provided.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}

if (isset($conn)) $conn->close();
?>
