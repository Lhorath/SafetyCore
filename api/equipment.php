<?php
/**
 * Equipment Management API - api/equipment.php
 * 
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>
<?php

session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

$action = $_GET['action'] ?? '';
$userId = (int)$_SESSION['user']['id'];
$userRole = $_SESSION['user']['role_name'] ?? '';

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
    
    case 'get_inventory':
        // Modified to include checklist_template_id and template_name
        $sql = "
            SELECT e.id, e.name, e.category, e.serial_number, e.status, e.next_inspection_date, e.checklist_template_id,
                   ct.name as template_name,
                   (SELECT MAX(inspection_date) FROM equipment_inspections WHERE equipment_id = e.id AND inspection_type != 'Maintenance') as last_inspection
            FROM equipment e
            LEFT JOIN checklist_templates ct ON e.checklist_template_id = ct.id
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

    case 'get_daily_logs':
        $today = date('Y-m-d');
        $sql = "
            SELECT ei.id, e.name as equipment_name, u.first_name, u.last_name, ei.inspection_date, ei.result, ei.comments
            FROM equipment_inspections ei
            JOIN equipment e ON ei.equipment_id = e.id
            JOIN users u ON ei.user_id = u.id
            WHERE e.company_id = ? AND DATE(ei.inspection_date) = ? AND ei.inspection_type = 'Pre-Shift'
            ORDER BY ei.inspection_date DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $companyId, $today);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $logs]);
        break;

    // --- NEW: Fetch available checklist templates ---
    case 'get_templates':
        if (!$isManager) { echo json_encode(['success' => false]); exit(); }
        $sql = "SELECT id, name FROM checklist_templates WHERE company_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $templates]);
        break;

    // --- NEW: Assign template to equipment ---
    case 'assign_template':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF']); exit(); }

        $equipId = (int)$data['equipment_id'];
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;

        $sql = "UPDATE equipment SET checklist_template_id = ? WHERE id = ? AND company_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $templateId, $equipId, $companyId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error.']);
        }
        $stmt->close();
        break;

    case 'add_equipment':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit(); }

        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? 'Other');
        $serial = trim($data['serial_number'] ?? '');
        $status = trim($data['status'] ?? 'Active');
        $nextInsp = !empty($data['next_inspection_date']) ? $data['next_inspection_date'] : null;
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;

        if (empty($name)) { echo json_encode(['success' => false, 'message' => 'Equipment name is required.']); break; }

        $sql = "INSERT INTO equipment (company_id, name, category, serial_number, status, next_inspection_date, checklist_template_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssi", $companyId, $name, $category, $serial, $status, $nextInsp, $templateId);
        if ($stmt->execute()) { echo json_encode(['success' => true]); } else { echo json_encode(['success' => false, 'message' => 'Database error.']); }
        $stmt->close();
        break;

    case 'log_inspection':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit(); }

        $equipId = (int)$data['equipment_id'];
        $inspType = trim($data['inspection_type'] ?? 'Pre-Shift');
        $result = trim($data['result'] ?? '');
        $comments = trim($data['comments'] ?? '');
        $inspDate = date('Y-m-d H:i:s');

        if (!$equipId || empty($result)) { echo json_encode(['success' => false, 'message' => 'Missing fields.']); break; }

        $sql = "INSERT INTO equipment_inspections (equipment_id, user_id, inspection_type, inspection_date, result, comments) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iissss", $equipId, $userId, $inspType, $inspDate, $result, $comments);
        
        if ($stmt->execute()) {
            if ($result === 'Fail' || $result === 'Needs Repair') {
                $autoStatus = 'Out of Service';
                $updSql = "UPDATE equipment SET status = ? WHERE id = ?";
                $updStmt = $conn->prepare($updSql);
                $updStmt->bind_param("si", $autoStatus, $equipId);
                $updStmt->execute();
                $updStmt->close();
            } elseif ($result === 'Pass' && $inspType === 'Maintenance') {
                $autoStatus = 'Active';
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