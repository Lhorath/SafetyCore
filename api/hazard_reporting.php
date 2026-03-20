<?php
/**
 * Hazard Reporting API - api/hazard_reporting.php
 *
 * @package   Sentry OHS
 * @version   Version 11.0.0 (sentry ohs launch)
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

// Basic User Info (company scoping for multi-tenant)
$userId = (int)$_SESSION['user']['id'];
$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$userRole = $_SESSION['user']['role_name'] ?? '';
$isManager = in_array($userRole, ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager']);

// Upload Path configuration
$uploadDir = '../reports/uploads/photos/';
$uploadWebPath = '/reports/uploads/photos/';

switch ($action) {

    // 1. Fetch Locations for a specific Store (store must belong to user's company)
    case 'get_locations':
        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId || !$companyId) {
            echo json_encode([]);
            exit;
        }
        $locations = [];
        $sql = "SELECT hl.id, hl.location_name
                FROM hazard_locations hl
                INNER JOIN stores s ON hl.store_id = s.id AND s.company_id = ?
                WHERE hl.store_id = ?
                ORDER BY hl.location_name ASC";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $companyId, $storeId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
            $stmt->close();
        }
        echo json_encode($locations);
        break;

    // 2. Add Custom Location (store must belong to user's company)
    case 'add_location':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            break;
        }
        $storeId = (int)($data['store_id'] ?? 0);
        $locName = trim($data['location_name'] ?? '');

        if (!$storeId || empty($locName) || !$companyId) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            break;
        }
        // Verify store belongs to user's company before inserting
        $chk = $conn->prepare("SELECT 1 FROM stores WHERE id = ? AND company_id = ? LIMIT 1");
        $chk->bind_param("ii", $storeId, $companyId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            $chk->close();
            echo json_encode(['success' => false, 'message' => 'Invalid store.']);
            break;
        }
        $chk->close();

        $sql = "INSERT INTO hazard_locations (store_id, location_name) VALUES (?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("is", $storeId, $locName);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $stmt->insert_id, 'location_name' => htmlspecialchars($locName)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
            $stmt->close();
        }
        break;

    // 3. Fetch Supervisors for a specific Store (store must belong to user's company)
    case 'get_supervisors':
        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId || !$companyId) {
            echo json_encode([]);
            exit;
        }
        $supervisors = [];
        $sql = "
            SELECT u.id, u.first_name, u.last_name
            FROM users u
            JOIN user_stores us ON u.id = us.user_id AND us.store_id = ?
            JOIN roles r ON u.role_id = r.id
            JOIN stores s ON s.id = us.store_id AND s.company_id = ?
            WHERE r.role_name IN ('Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Co-manager', 'Owner / CEO')
            ORDER BY u.first_name ASC
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $storeId, $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $supervisors[] = $row;
            }
            $stmt->close();
        }
        echo json_encode($supervisors);
        break;

    // 4. Fetch Details of a Single Report (scoped by company; reporter or manager access)
    case 'get_report_details':
        $reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$reportId || !$companyId) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }
        $sql = "
            SELECT r.*,
                   l.location_name,
                   u1.first_name, u1.last_name,
                   u2.first_name as supervisor_first_name, u2.last_name as supervisor_last_name
            FROM reports r
            INNER JOIN stores s ON r.store_id = s.id AND s.company_id = ?
            LEFT JOIN hazard_locations l ON r.hazard_location_id = l.id
            LEFT JOIN users u1 ON r.reporter_user_id = u1.id
            LEFT JOIN users u2 ON r.notified_user_id = u2.id
            WHERE r.id = ?
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $companyId, $reportId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!$isManager && (int)$row['reporter_user_id'] !== $userId) {
                    echo json_encode(['success' => false, 'message' => 'Report not found or access denied.']);
                    $stmt->close();
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error while fetching details.']);
        }
        break;

    // Fetch all reports for a specific store (managers only; store must be in user's company)
    case 'get_reports_by_store':
        if (!$isManager) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            exit;
        }
        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId || !$companyId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Store ID']);
            exit;
        }
        $reports = [];
        $sql = "
            SELECT r.id, r.hazard_type, r.status, r.created_at, l.location_name
            FROM reports r
            INNER JOIN stores s ON r.store_id = s.id AND s.company_id = ?
            LEFT JOIN hazard_locations l ON r.hazard_location_id = l.id
            WHERE r.store_id = ?
            ORDER BY r.created_at DESC
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $companyId, $storeId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $reports[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'data' => $reports]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        break;


    // 5. Submit a NEW Hazard Report
    case 'submit_report':
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit;
        }

        // Gather POST data
        $storeId = (int)$_POST['store_id'];
        $locationId = (int)$_POST['location_id'];
        $hazardType = trim($_POST['hazard_type']);
        $description = trim($_POST['hazard_description']);
        $immAction = (isset($_POST['immediateActionTaken']) && $_POST['immediateActionTaken'] === 'yes') ? 1 : 0;
        $actionDesc = trim($_POST['action_description'] ?? '');
        $supNotified = !empty($_POST['supervisor_notified_user_id']) ? (int)$_POST['supervisor_notified_user_id'] : null;
        $eqLocked = (isset($_POST['equipmentLockedOut']) && $_POST['equipmentLockedOut'] === 'yes') ? 1 : 0;
        $keyHolder = trim($_POST['lockout_key_holder'] ?? '');

        // Basic Validation
        if (!$storeId || !$locationId || empty($hazardType) || empty($description) || empty($actionDesc)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
            exit;
        }
        // Ensure store belongs to user's company; non-managers must be assigned to the store
        if (!$companyId) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            exit;
        }
        $storeChk = $conn->prepare("SELECT 1 FROM stores WHERE id = ? AND company_id = ? LIMIT 1");
        $storeChk->bind_param("ii", $storeId, $companyId);
        $storeChk->execute();
        if (!$storeChk->get_result()->fetch_assoc()) {
            $storeChk->close();
            echo json_encode(['success' => false, 'message' => 'Invalid store.']);
            exit;
        }
        $storeChk->close();
        if (!$isManager) {
            $accChk = $conn->prepare("SELECT 1 FROM user_stores WHERE user_id = ? AND store_id = ? LIMIT 1");
            $accChk->bind_param("ii", $userId, $storeId);
            $accChk->execute();
            if (!$accChk->get_result()->fetch_assoc()) {
                $accChk->close();
                echo json_encode(['success' => false, 'message' => 'You do not have access to that store.']);
                exit;
            }
            $accChk->close();
        }
        // Validate supervisor_notified_user_id is an allowed supervisor for the selected store (when provided)
        if ($supNotified !== null && $supNotified > 0) {
            $supChk = $conn->prepare("SELECT 1 FROM users u
                INNER JOIN user_stores us ON u.id = us.user_id AND us.store_id = ?
                INNER JOIN roles r ON u.role_id = r.id
                INNER JOIN stores s ON s.id = us.store_id AND s.company_id = ?
                WHERE u.id = ? AND r.role_name IN ('Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Co-manager', 'Owner / CEO')
                LIMIT 1");
            if ($supChk) {
                $supChk->bind_param("iii", $storeId, $companyId, $supNotified);
                $supChk->execute();
                if (!$supChk->get_result()->fetch_assoc()) {
                    $supChk->close();
                    echo json_encode(['success' => false, 'message' => 'Invalid person selected for who was notified.']);
                    exit;
                }
                $supChk->close();
            }
        }

        if ($eqLocked === 1 && empty($keyHolder)) {
            echo json_encode(['success' => false, 'message' => 'Key holder name is required if equipment is locked out.']);
            exit;
        }

        // File Upload Handling
        $mediaPath = null;
        if (isset($_FILES['hazardMedia']) && $_FILES['hazardMedia']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['hazardMedia']['tmp_name'];
            $fileName = $_FILES['hazardMedia']['name'];
            $fileSize = $_FILES['hazardMedia']['size'];
            $fileType = $_FILES['hazardMedia']['type'];
            
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                if ($fileSize < (5 * 1024 * 1024)) { // 5MB limit
                    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $dest_path = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $mediaPath = $uploadWebPath . $newFileName;
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']); exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF allowed.']); exit;
            }
        }

        // Insert into database using canonical reports-table contract.
        $sql = "INSERT INTO reports (
                    reporter_user_id, store_id, hazard_location_id, hazard_type, hazard_description,
                    action_taken, action_description, equipment_locked_out, lockout_key_holder,
                    notified_user_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "iiissisisi",
                $userId, $storeId, $locationId, $hazardType, $description,
                $immAction, $actionDesc, $eqLocked, $keyHolder,
                $supNotified
            );
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error during submission.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Prepare failed.']);
        }
        break;

    // 6. Manager Edit/Close Report (report must belong to manager's company)
    case 'update_report':
        if (!$isManager) {
            echo json_encode(['success' => false, 'message' => 'Access denied.']); exit;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit;
        }
        $reportId = (int)($data['report_id'] ?? 0);
        $status = $data['status'] ?? '';
        $mgrNotes = trim($data['manager_notes'] ?? '');
        if (!$reportId || !$companyId || !in_array($status, ['Open', 'In Progress', 'Closed'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit;
        }
        $sql = "UPDATE reports r
                INNER JOIN stores s ON r.store_id = s.id AND s.company_id = ?
                SET r.status = ?, r.manager_notes = ?
                WHERE r.id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issi", $companyId, $status, $mgrNotes, $reportId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found or update failed.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>