<?php
/**
 * Meetings & Talks API - api/meetings.php
 * Handles backend processing for safety meetings and talks, including meeting creation and attendee management.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 09)
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

$action     = $_GET['action'] ?? '';
$companyId  = (int)$_SESSION['user']['company_id'];
$hostUserId = (int)$_SESSION['user']['id'];
$userRole   = $_SESSION['user']['role_name'] ?? '';

$allowedRoles = [
    'Admin',
    'Manager',
    'Safety Manager',
    'Safety Leader',
    'Owner / CEO',
    'Co-manager',
    'JHSC Member',   // F-17
    'JHSC Leader',   // F-17
];

if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

switch ($action) {

    // ── get_store_employees ───────────────────────────────────────────────────
    case 'get_store_employees':
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

        if ($storeId > 0) {
            // F-09 FIX: AND s.company_id = ? prevents fetching employees from
            // a store that belongs to a different tenant.
            $sql = "SELECT u.id, u.first_name, u.last_name, u.employee_position
                    FROM users u
                    JOIN user_stores us ON u.id = us.user_id
                    JOIN stores s      ON us.store_id = s.id
                    WHERE us.store_id = ?
                      AND s.company_id = ?
                    ORDER BY u.first_name ASC, u.last_name ASC";

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
            echo json_encode(['success' => false, 'message' => 'Invalid Store ID provided.']);
        }
        break;

    // ── save_meeting ──────────────────────────────────────────────────────────
    case 'save_meeting':
        // F-02: CSRF required on all state-changing calls
        csrf_verify_or_die();

        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Invalid or empty data payload.']);
            break;
        }

        $storeId   = isset($data['store_id']) ? (int)$data['store_id'] : 0;
        $topic     = trim($data['topic']    ?? '');
        $date      = !empty($data['meeting_date']) ? $data['meeting_date'] : date('Y-m-d');
        $comments  = trim($data['comments'] ?? '');
        $attendees = (isset($data['attendees']) && is_array($data['attendees'])) ? $data['attendees'] : [];

        if ($storeId <= 0 || empty($topic) || empty($attendees)) {
            echo json_encode(['success' => false, 'message' => 'Store location, Meeting Topic, and at least one Attendee are required.']);
            break;
        }

        // F-09 FIX: verify the supplied store_id belongs to this company before
        // inserting. An attacker cannot assign a meeting to another tenant's store.
        $ownerCheck = $conn->prepare(
            "SELECT 1 FROM stores WHERE id = ? AND company_id = ? LIMIT 1"
        );
        $ownerCheck->bind_param("ii", $storeId, $companyId);
        $ownerCheck->execute();
        $storeOwned = $ownerCheck->get_result()->fetch_assoc() !== null;
        $ownerCheck->close();

        if (!$storeOwned) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied to that store location.']);
            break;
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare(
                "INSERT INTO meetings (company_id, store_id, host_user_id, topic, meeting_date, comments)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiisss", $companyId, $storeId, $hostUserId, $topic, $date, $comments);
            $stmt->execute();
            $meetingId = $stmt->insert_id;
            $stmt->close();

            $attStmt = $conn->prepare("INSERT INTO meeting_attendees (meeting_id, user_id) VALUES (?, ?)");
            foreach ($attendees as $uid) {
                $uid = (int)$uid;
                if ($uid > 0) {
                    $attStmt->bind_param("ii", $meetingId, $uid);
                    $attStmt->execute();
                }
            }
            $attStmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'meeting_id' => $meetingId]);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Meeting Save Transaction Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'A database error occurred while saving the meeting.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid API action requested.']);
        break;
}

if (isset($conn)) $conn->close();
?>
