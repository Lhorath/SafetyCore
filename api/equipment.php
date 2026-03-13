<?php
/**
 * Equipment Management API - api/equipment.php
 *
 * Handles backend processing for equipment inventory and pre-use inspections.
 * Enforces RBAC and CSRF protection.
 *
 * @package   NorthPoint360
 */

session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/csrf.php';

// Security Check
if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$action = $_GET['action'] ?? '';
$userId = (int)$_SESSION['user']['id'];
$userRole = $_SESSION['user']['role_name'] ?? '';

// Safely derive the user's Company ID based on their store assignments
$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    $compSql = "SELECT s.company_id FROM user_stores us JOIN stores s ON us.store_id = s.id WHERE us.user_id = ? LIMIT 1";
    $compStmt = $conn->prepare($compSql);
    $compStmt->bind_param("i", $userId);
    $compStmt->execute();
    $res = $compStmt->get_result()->fetch_assoc();
    $companyId = $res ? $res['company_id'] : 1; 
    $compStmt->close();
}

$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];
$isManager = in_array($userRole, $managementRoles);

switch ($action) {
    
    /**
     * Fetch all equipment for the company, including last inspection date
     */
    case 'get_inventory':
        // Everyone can view inventory to log inspections
        $sql = "
            SELECT e.id, e.name, e.category, e.serial_number, e.status, e.next_inspection_date,
                   (SELECT MAX(inspection_date) FROM equipment_inspections WHERE equipment_id = e.id) as last_inspection
            FROM equipment e
            WHERE e.company_id = ?
            ORDER BY e.status ASC, e.name ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $inventory]);
        break;

    /**
     * Add new equipment to inventory (Managers Only)
     */
    case 'add_equipment':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit();
        }

        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? 'Other');
        $serial = trim($data['serial_number'] ?? '');
        $status = trim($data['status'] ?? 'Active');
        $nextInsp = !empty($data['next_inspection_date']) ? $data['next_inspection_date'] : null;

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Equipment name is required.']); break;
        }

        $sql = "INSERT INTO equipment (company_id, name, category, serial_number, status, next_inspection_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssss", $companyId, $name, $category, $serial, $status, $nextInsp);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        $stmt->close();
        break;

    /**
     * Log a pre-use inspection (Available to all workers)
     */
    case 'log_inspection':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit();
        }

        $equipId = (int)$data['equipment_id'];
        $result = trim($data['result'] ?? '');
        $comments = trim($data['comments'] ?? '');
        $inspDate = date('Y-m-d H:i:s');

        if (!$equipId || empty($result)) {
            echo json_encode(['success' => false, 'message' => 'Missing required inspection fields.']); break;
        }

        // 1. Insert the inspection record
        $sql = "INSERT INTO equipment_inspections (equipment_id, user_id, inspection_date, result, comments) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisss", $equipId, $userId, $inspDate, $result, $comments);
        
        if ($stmt->execute()) {
            // 2. Automatically update equipment status if it failed
            if ($result === 'Fail' || $result === 'Needs Repair') {
                $autoStatus = 'Out of Service';
                $updSql = "UPDATE equipment SET status = ? WHERE id = ?";
                $updStmt = $conn->prepare($updSql);
                $updStmt->bind_param("si", $autoStatus, $equipId);
                $updStmt->execute();
                $updStmt->close();
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>