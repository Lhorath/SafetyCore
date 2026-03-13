<?php
/**
 * Hazard Reporting API - api/hazard_reporting.php
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

// Basic User Info
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role_name'] ?? '';
$isManager = in_array($userRole, ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager']);

// Upload Path configuration
$uploadDir = '../reports/uploads/photos/';
$uploadWebPath = '/reports/uploads/photos/';

switch ($action) {

    // 1. Fetch Locations for a specific Store
    case 'get_locations':
        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId) {
            echo json_encode([]);
            exit;
        }
        
        $locations = [];
        // Note: Using hazard_locations as requested in previous fixes
        $sql = "SELECT id, location_name FROM hazard_locations WHERE store_id = ? ORDER BY location_name ASC";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $storeId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row;
            }
            $stmt->close();
        }
        echo json_encode($locations);
        break;

    // 2. Add Custom Location
    case 'add_location':
        $data = json_decode(file_get_contents('php://input'), true);
        $storeId = (int)($data['store_id'] ?? 0);
        $locName = trim($data['location_name'] ?? '');

        if (!$storeId || empty($locName)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            break;
        }

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

    // 3. Fetch Supervisors for a specific Store (for Notified Dropdown)
    case 'get_supervisors':
        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId) {
            echo json_encode([]);
            exit;
        }

        $supervisors = [];
        $sql = "
            SELECT u.id, u.first_name, u.last_name 
            FROM users u
            JOIN user_stores us ON u.id = us.user_id
            JOIN roles r ON u.role_id = r.id
            WHERE us.store_id = ? 
            AND r.role_name IN ('Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Co-manager', 'Owner / CEO')
            ORDER BY u.first_name ASC
        ";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $storeId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $supervisors[] = $row;
            }
            $stmt->close();
        }
        echo json_encode($supervisors);
        break;

    // 4. Fetch Details of a Single Report (My Reports & Store Reports viewer)
    case 'get_report_details':
        $reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$reportId) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            exit;
        }

        // Make the query more robust with LEFT JOINs so that missing data doesn't break the fetch
        $sql = "
            SELECT r.*, 
                   l.location_name,
                   u1.first_name, u1.last_name,
                   u2.first_name as supervisor_first_name, u2.last_name as supervisor_last_name
            FROM reports r
            LEFT JOIN hazard_locations l ON r.hazard_location_id = l.id
            LEFT JOIN users u1 ON r.reporter_user_id = u1.id
            LEFT JOIN users u2 ON r.notified_user_id = u2.id 
            WHERE r.id = ?
        ";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Determine if the user has access.
                // Standard users can only view their own reports.
                // Managers can view any report details fetched here.
                if (!$isManager && $row['reporter_user_id'] != $userId) {
                     echo json_encode(['success' => false, 'message' => 'Report not found or access denied.']);
                     $stmt->close();
                     exit;
                }

                // If media path exists, we would normally get it here, but in the current DB schema it's in a separate table.
                // For now, we will handle the single file upload field if it was used in previous versions.
                // If it's in report_files, we would need a separate query. For this fix, we focus on the core data.
                
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Report not found.']);
            }
            $stmt->close();
        } else {
             echo json_encode(['success' => false, 'message' => 'Database error while fetching details.']);
        }
        break;

    // =========================================================================
    // NEW ENDPOINT: Fetch all reports for a specific store (For Manager Review)
    // =========================================================================
    case 'get_reports_by_store':
        if (!$isManager) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }

        $storeId = filter_input(INPUT_GET, 'store_id', FILTER_VALIDATE_INT);
        if (!$storeId) {
            echo json_encode(['success' => false, 'message' => 'Invalid Store ID']);
            exit;
        }

        $reports = [];
        $sql = "
            SELECT r.id, r.hazard_type, r.status, r.created_at, l.location_name
            FROM reports r
            LEFT JOIN hazard_locations l ON r.hazard_location_id = l.id
            WHERE r.store_id = ?
            ORDER BY r.created_at DESC
        ";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $storeId);
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
        $immAction = $_POST['immediateActionTaken'] === 'yes' ? 'yes' : 'no';
        $actionDesc = trim($_POST['action_description'] ?? '');
        $supNotified = !empty($_POST['supervisor_notified_user_id']) ? (int)$_POST['supervisor_notified_user_id'] : null;
        $eqLocked = $_POST['equipmentLockedOut'] === 'yes' ? 'yes' : 'no';
        $keyHolder = trim($_POST['lockout_key_holder'] ?? '');

        // Basic Validation
        if (!$storeId || !$locationId || empty($hazardType) || empty($description) || empty($actionDesc)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields.']);
            exit;
        }

        if ($eqLocked === 'yes' && empty($keyHolder)) {
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

        // Insert into Database
        $sql = "INSERT INTO reports (
                    reporter_user_id, store_id, hazard_location_id, hazard_type, hazard_description, 
                    immediate_action_taken, action_description, equipment_locked_out, lockout_key_holder, 
                    supervisor_notified_user_id, media_path, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "iiissssssis", 
                $userId, $storeId, $locationId, $hazardType, $description, 
                $immAction, $actionDesc, $eqLocked, $keyHolder, 
                $supNotified, $mediaPath
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

    // 6. Manager Edit/Close Report
    case 'update_report':
        if (!$isManager) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit;
        }

        $reportId = (int)$data['report_id'];
        $status = $data['status'];
        $mgrNotes = trim($data['manager_notes']);

        if (!$reportId || !in_array($status, ['Open', 'In Progress', 'Closed'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit;
        }

        $sql = "UPDATE reports SET status = ?, manager_notes = ? WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssi", $status, $mgrNotes, $reportId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed.']);
            }
            $stmt->close();
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}
?>