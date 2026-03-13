<?php
/**
 * Dynamic Checklist API - api/checklists.php
 */
session_start();
header('Content-Type: application/json');

require_once '../includes/db.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$userId = (int)$_SESSION['user']['id'];
$userRole = $_SESSION['user']['role_name'] ?? '';

// Derive Company ID
$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    $compStmt = $conn->prepare("SELECT s.company_id FROM user_stores us JOIN stores s ON us.store_id = s.id WHERE us.user_id = ? LIMIT 1");
    $compStmt->bind_param("i", $userId);
    $compStmt->execute();
    $res = $compStmt->get_result()->fetch_assoc();
    $companyId = $res ? $res['company_id'] : 1; 
    $compStmt->close();
}

$isManager = in_array($userRole, ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager']);

switch ($action) {
    
    // --- BUILDER ENDPOINTS (Managers Only) ---
    case 'save_template':
        if (!$isManager) exit(json_encode(['success'=>false, 'message'=>'Unauthorized']));
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) exit(json_encode(['success'=>false, 'message'=>'Invalid CSRF']));
        
        $name = trim($data['name']);
        $desc = trim($data['description'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO checklist_templates (company_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $companyId, $name, $desc);
        if ($stmt->execute()) echo json_encode(['success'=>true, 'template_id'=>$conn->insert_id]);
        else echo json_encode(['success'=>false]);
        $stmt->close();
        break;

    case 'save_item':
        if (!$isManager) exit(json_encode(['success'=>false]));
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) exit(json_encode(['success'=>false]));

        $templateId = (int)$data['template_id'];
        $label = trim($data['label']);
        $type = $data['field_type'];
        
        $stmt = $conn->prepare("INSERT INTO checklist_items (template_id, label, field_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $templateId, $label, $type);
        if ($stmt->execute()) echo json_encode(['success'=>true]);
        else echo json_encode(['success'=>false]);
        $stmt->close();
        break;

    // --- OPERATOR ENDPOINTS ---
    case 'get_form':
        $equipId = (int)($_GET['equipment_id'] ?? 0);
        // Find assigned template
        $stmt = $conn->prepare("SELECT checklist_template_id FROM equipment WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $equipId, $companyId);
        $stmt->execute();
        $eqRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$eqRes || !$eqRes['checklist_template_id']) {
            echo json_encode(['success'=>false, 'message'=>'No checklist assigned to this equipment.']);
            exit();
        }

        $templateId = $eqRes['checklist_template_id'];
        $stmt = $conn->prepare("SELECT id, label, field_type, is_required FROM checklist_items WHERE template_id = ? ORDER BY order_index ASC, id ASC");
        $stmt->bind_param("i", $templateId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode(['success'=>true, 'template_id'=>$templateId, 'items'=>$items]);
        break;

    case 'submit_checklist':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) exit(json_encode(['success'=>false, 'message'=>'Invalid CSRF']));

        $equipId = (int)$data['equipment_id'];
        $templateId = (int)$data['template_id'];
        $responses = $data['responses'] ?? [];
        $comments = $data['general_comments'] ?? '';
        $shiftDate = date('Y-m-d');
        
        // Validation loop to determine Safe/Unsafe
        $isSafe = true;
        foreach ($responses as $r) {
            $val = $r['value'] ?? '';
            if ($val === 'Fail' || $val === 'No') {
                $isSafe = false;
            }
        }
        $status = $isSafe ? 'Safe' : 'Unsafe';

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO checklist_submissions (company_id, user_id, equipment_id, template_id, shift_date, overall_status, general_comments) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiisss", $companyId, $userId, $equipId, $templateId, $shiftDate, $status, $comments);
            $stmt->execute();
            $subId = $conn->insert_id;
            $stmt->close();

            $rStmt = $conn->prepare("INSERT INTO checklist_responses (submission_id, item_id, response_value, notes) VALUES (?, ?, ?, ?)");
            foreach ($responses as $r) {
                $iId = (int)$r['item_id'];
                $val = $r['value'] ?? '';
                $note = $r['notes'] ?? '';
                $rStmt->bind_param("iiss", $subId, $iId, $val, $note);
                $rStmt->execute();
            }
            $rStmt->close();

            if (!$isSafe) {
                $conn->query("UPDATE equipment SET status = 'Out of Service' WHERE id = $equipId");
            }

            $conn->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success'=>false, 'message'=>'Transaction failed.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>