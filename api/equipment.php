<?php
/**
 * Equipment Management API - api/equipment.php
 *
 * @package   NorthPoint360
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
        $sql = "
            SELECT e.id, e.name, e.category, e.serial_number, e.status, e.next_inspection_date, e.checklist_template_id,
                   ct.name as template_name,
                   (SELECT MAX(created_at) FROM checklist_submissions WHERE equipment_id = e.id) as last_inspection
            FROM equipment e
            LEFT JOIN checklist_templates ct ON e.checklist_template_id = ct.id
            WHERE e.company_id = ?
            ORDER BY e.status ASC, e.name ASC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $inventory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $inventory]);
        break;

    case 'get_daily_logs':
        $today = date('Y-m-d');
        // Pull Today's Dynamic Checklists directly to the Hub
        $sql = "
            SELECT cs.id, e.name as equipment_name, u.first_name, u.last_name, cs.created_at as inspection_date, cs.overall_status as result, cs.general_comments as comments
            FROM checklist_submissions cs
            JOIN equipment e ON cs.equipment_id = e.id
            JOIN users u ON cs.user_id = u.id
            WHERE cs.company_id = ? AND cs.shift_date = ?
            ORDER BY cs.created_at DESC
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("is", $companyId, $today);
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $logs]);
        break;

    case 'get_templates':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Access denied.']); exit(); }
        $sql = "SELECT id, name FROM checklist_templates WHERE company_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $templates]);
        break;

    case 'assign_template':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Access denied.']); exit(); }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit(); }

        $equipId = (int)$data['equipment_id'];
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;

        $sql = "UPDATE equipment SET checklist_template_id = ? WHERE id = ? AND company_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("iii", $templateId, $equipId, $companyId);
        if ($stmt->execute()) { echo json_encode(['success' => true]); } else { echo json_encode(['success' => false, 'message' => 'Database error.']); }
        $stmt->close();
        break;

    case 'add_equipment':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Access denied.']); exit(); }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit(); }

        $name = trim($data['name'] ?? '');
        $category = trim($data['category'] ?? 'Other');
        $serial = trim($data['serial_number'] ?? '');
        $status = trim($data['status'] ?? 'Active');
        $nextInsp = !empty($data['next_inspection_date']) ? $data['next_inspection_date'] : null;
        $templateId = !empty($data['template_id']) ? (int)$data['template_id'] : null;

        if (empty($name)) { echo json_encode(['success' => false, 'message' => 'Equipment name is required.']); break; }

        $sql = "INSERT INTO equipment (company_id, name, category, serial_number, status, next_inspection_date, checklist_template_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("isssssi", $companyId, $name, $category, $serial, $status, $nextInsp, $templateId);
        if ($stmt->execute()) { echo json_encode(['success' => true]); } else { echo json_encode(['success' => false, 'message' => 'Database error.']); }
        $stmt->close();
        break;

    case 'log_inspection':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) { echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit(); }

        $equipId = (int)$data['equipment_id'];
        $inspType = trim($data['inspection_type'] ?? 'Maintenance');
        $result = trim($data['result'] ?? '');
        $comments = trim($data['comments'] ?? '');
        $inspDate = date('Y-m-d H:i:s');

        if (!$equipId || empty($result)) { echo json_encode(['success' => false, 'message' => 'Missing fields.']); break; }

        $sql = "INSERT INTO equipment_inspections (equipment_id, user_id, inspection_type, inspection_date, result, comments) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]); exit(); }
        
        $stmt->bind_param("iissss", $equipId, $userId, $inspType, $inspDate, $result, $comments);
        
        if ($stmt->execute()) {
            if ($result === 'Pass') {
                $autoStatus = 'Active';
                $conn->query("UPDATE equipment SET status = 'Active' WHERE id = $equipId");
            } elseif ($result === 'Fail') {
                $conn->query("UPDATE equipment SET status = 'Out of Service' WHERE id = $equipId");
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