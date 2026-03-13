<?php
/**
 * Consolidated Hazard Reporting API - api/hazard_reporting.php
 *
 * This centralized API endpoint handles all asynchronous data requests for the 
 * NorthPoint 360 application. It processes AJAX requests from the frontend 
 * (Hazard Form, Dashboards, Report Viewers, Metrics) and returns JSON.
 *
 * Updates in Beta 04:
 * - Added `get_advanced_metrics` to support the new Statistics & Metrics dashboard.
 * - Added `close_report` to allow leadership to append resolution notes and close reports.
 * - Updated `get_report_details` to return a `can_close` permission flag.
 * - Updated `get_store_reports` to return report status for UI badges.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   4.4.0 (NorthPoint Beta 04)
 */

// --- 1. Initialization ---

session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

$action = $_GET['action'] ?? '';

// --- 2. Action Routing ---

switch ($action) {

    /**
     * Action: get_employees
     * Purpose: Fetch employees assigned to a specific store (supports multi-tenant user_stores).
     */
    case 'get_employees':
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $data = [];
        
        if ($storeId > 0) {
            $sql = "SELECT u.id, u.first_name, u.last_name 
                    FROM users u
                    JOIN user_stores us ON u.id = us.user_id
                    WHERE us.store_id = ? 
                    ORDER BY u.first_name ASC";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $storeId);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
        echo json_encode($data);
        break;

    /**
     * Action: get_locations
     * Purpose: Fetch active hazard locations for a specific store.
     */
    case 'get_locations':
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $data = [];
        
        if ($storeId > 0) {
            $sql = "SELECT id, location_name FROM hazard_locations WHERE store_id = ? AND is_active = 1 ORDER BY location_name ASC";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $storeId);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
        echo json_encode($data);
        break;

    /**
     * Action: add_location
     * Purpose: Create a new custom hazard location dynamically from the frontend modal.
     */
    case 'add_location':
        if (!isset($_SESSION['user']['id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $storeId = isset($input['store_id']) ? (int)$input['store_id'] : 0;
        $locationName = isset($input['location_name']) ? trim($input['location_name']) : '';
        $response = ['success' => false, 'message' => 'Invalid input.'];

        if ($storeId > 0 && !empty($locationName)) {
            // Use ON DUPLICATE KEY UPDATE to prevent errors if the location already exists
            $sql = "INSERT INTO hazard_locations (store_id, location_name) VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("is", $storeId, $locationName);
                if ($stmt->execute()) {
                    $newId = $stmt->insert_id;
                    if ($newId > 0) {
                        $response = ['success' => true, 'id' => $newId, 'location_name' => $locationName];
                    } else {
                        // Fallback if LAST_INSERT_ID fails on duplicate
                        $findStmt = $conn->prepare("SELECT id FROM hazard_locations WHERE store_id = ? AND location_name = ?");
                        $findStmt->bind_param("is", $storeId, $locationName);
                        $findStmt->execute();
                        $existingResult = $findStmt->get_result()->fetch_assoc();
                        
                        if ($existingResult) {
                            $response = ['success' => true, 'id' => $existingResult['id'], 'location_name' => $locationName, 'existed' => true];
                        }
                        $findStmt->close();
                    }
                } else {
                    $response['message'] = 'Database execution error.';
                }
                $stmt->close();
            }
        }
        echo json_encode($response);
        break;

    /**
     * Action: get_supervisors
     * Purpose: Fetch leadership/management assigned to a specific store for notifications.
     */
    case 'get_supervisors':
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $data = [];
        
        if ($storeId > 0) {
            $sql = "SELECT u.id, u.first_name, u.last_name 
                    FROM users u
                    JOIN user_stores us ON u.id = us.user_id
                    JOIN roles r ON u.role_id = r.id
                    WHERE us.store_id = ? 
                    AND r.role_name IN ('Manager', 'Co-manager', 'Safety Leader', 'Safety Manager', 'Owner / CEO')
                    ORDER BY u.first_name ASC";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $storeId);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
        echo json_encode($data);
        break;

    /**
     * Action: get_report_details
     * Purpose: Fetch full details and media files for a specific report in the Master-Detail viewer.
     */
    case 'get_report_details':
        if (!isset($_SESSION['user']['id'])) { 
            http_response_code(403); 
            echo json_encode(['success' => false, 'message' => 'Authentication required.']); 
            exit(); 
        }
        
        $reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $userId = $_SESSION['user']['id'];
        $userRole = $_SESSION['user']['role_name'];
        $response = ['success' => false, 'message' => 'Report not found or access denied.'];

        if ($reportId > 0) {
            $sql = "SELECT 
                        r.*, 
                        s.store_name, 
                        hl.location_name, 
                        CONCAT(u.first_name, ' ', u.last_name) as reporter_name 
                    FROM reports r 
                    JOIN stores s ON r.store_id = s.id 
                    JOIN hazard_locations hl ON r.hazard_location_id = hl.id 
                    JOIN users u ON r.reporter_user_id = u.id 
                    WHERE r.id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $reportData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $allowedViewRoles = ['Admin', 'Manager', 'JHSC Leader', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager'];
            $allowedCloseRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager'];

            if ($reportData && ($reportData['reporter_user_id'] == $userId || in_array($userRole, $allowedViewRoles))) {
                
                // Add capability flag so JS knows whether to render the 'Close' button
                $reportData['can_close'] = in_array($userRole, $allowedCloseRoles) && $reportData['status'] !== 'Closed';

                // Fetch Media Files
                $fileStmt = $conn->prepare("SELECT file_path, file_type FROM report_files WHERE report_id = ?");
                $fileStmt->bind_param("i", $reportId);
                $fileStmt->execute();
                $reportData['files'] = $fileStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $fileStmt->close();
                
                $response = ['success' => true, 'data' => $reportData];
            } else {
                $response['message'] = 'Access denied to this report.';
            }
        }
        echo json_encode($response);
        break;

    /**
     * Action: get_store_stats
     * Purpose: Fetch quick high-level KPIs for the Dashboard summary bar.
     */
    case 'get_store_stats':
        if (!isset($_SESSION['user']['id'])) { 
            http_response_code(403); 
            exit(); 
        }
        
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        
        if ($storeId > 0) {
            $stats = [];
            
            // Monthly Total
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reports WHERE store_id = ? AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
            $stmt->bind_param("i", $storeId);
            $stmt->execute();
            $stats['reports_this_month'] = $stmt->get_result()->fetch_assoc()['count'];
            $stmt->close();

            // Risk Tally
            $stmt = $conn->prepare("SELECT risk_level, COUNT(*) as count FROM reports WHERE store_id = ? GROUP BY risk_level");
            $stmt->bind_param("i", $storeId);
            $stmt->execute();
            $riskCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            $stats['risk_counts'] = ['1' => 0, '2' => 0, '3' => 0];
            foreach ($riskCounts as $row) { 
                $stats['risk_counts'][$row['risk_level']] = $row['count']; 
            }
            
            echo json_encode(['success' => true, 'data' => $stats]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid store ID.']);
        }
        break;

    /**
     * Action: get_store_reports
     * Purpose: Fetch simplified list of reports for the left-pane list view.
     */
    case 'get_store_reports':
        if (!isset($_SESSION['user']['id'])) { 
            http_response_code(403); 
            exit(); 
        }

        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

        if ($storeId > 0) {
            $sql = "SELECT r.id, r.risk_level, r.created_at, r.status, hl.location_name as hazard_location_name 
                    FROM reports r 
                    JOIN hazard_locations hl ON r.hazard_location_id = hl.id 
                    WHERE r.store_id = ? 
                    ORDER BY r.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $storeId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid store ID.']);
        }
        break;

    /**
     * Action: close_report
     * Purpose: Close an active report, appending resolution comments to the audit trail.
     */
    case 'close_report':
        if (!isset($_SESSION['user']['id'])) { 
            http_response_code(403); 
            echo json_encode(['success' => false, 'message' => 'Authentication required.']); 
            exit(); 
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $reportId = isset($input['report_id']) ? (int)$input['report_id'] : 0;
        $comments = isset($input['resolution_comments']) ? trim($input['resolution_comments']) : '';
        
        $userRole = $_SESSION['user']['role_name'];
        $userName = $_SESSION['user']['first_name'] . ' ' . $_SESSION['user']['last_name'];
        $allowedCloseRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager'];

        if (!in_array($userRole, $allowedCloseRoles)) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to close reports.']);
            break;
        }

        if ($reportId > 0 && !empty($comments)) {
            // Fetch existing comments to prevent overwriting previous notes
            $stmt = $conn->prepare("SELECT additional_comments FROM reports WHERE id = ?");
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc()['additional_comments'] ?? '';
            $stmt->close();

            // Create timestamped audit log block
            $dateStr = date('F j, Y \a\t g:i A');
            $newCommentBlock = "\n\n=== HAZARD RESOLVED ON {$dateStr} BY " . strtoupper($userName) . " ===\n" . $comments;
            $updatedComments = trim($existing . $newCommentBlock);

            // Execute Status & Comment Update
            $upStmt = $conn->prepare("UPDATE reports SET status = 'Closed', additional_comments = ? WHERE id = ?");
            $upStmt->bind_param("si", $updatedComments, $reportId);
            
            if ($upStmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to close report. Database error.']);
            }
            $upStmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Report ID and resolution comments are required.']);
        }
        break;

    /**
     * Action: get_advanced_metrics
     * Purpose: Aggregate deep statistics for the Metrics Dashboard (Beta 04) based on Store and Month.
     */
    case 'get_advanced_metrics':
        if (!isset($_SESSION['user']['id'])) { 
            http_response_code(403); 
            exit(); 
        }
        
        $storeId = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;
        $month = isset($_GET['month']) && !empty($_GET['month']) ? $_GET['month'] : date('Y-m');

        if ($storeId > 0) {
            $metrics = [
                'total' => 0,
                'risk_levels' => ['1' => 0, '2' => 0, '3' => 0],
                'locations' => [],
                'hazard_types' => [],
                'lockout_count' => 0,
                'status_counts' => ['Open' => 0, 'Under Review' => 0, 'Closed' => 0]
            ];

            // Fetch reports matching the store and exact month string (YYYY-MM)
            $sql = "SELECT r.*, hl.location_name 
                    FROM reports r 
                    JOIN hazard_locations hl ON r.hazard_location_id = hl.id 
                    WHERE r.store_id = ? AND DATE_FORMAT(r.created_at, '%Y-%m') = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $storeId, $month);
            $stmt->execute();
            $result = $stmt->get_result();

            // Iterate and tally all parameters
            while ($row = $result->fetch_assoc()) {
                $metrics['total']++;
                
                // Track Risk Levels
                if(isset($metrics['risk_levels'][$row['risk_level']])) {
                    $metrics['risk_levels'][$row['risk_level']]++;
                }
                
                // Track Statuses
                if(isset($metrics['status_counts'][$row['status']])) {
                    $metrics['status_counts'][$row['status']]++;
                }
                
                // Track Lockouts
                if ($row['equipment_locked_out']) {
                    $metrics['lockout_count']++;
                }

                // Track & Group Hazard Types
                $type = ucfirst($row['hazard_type']);
                if (!isset($metrics['hazard_types'][$type])) $metrics['hazard_types'][$type] = 0;
                $metrics['hazard_types'][$type]++;

                // Track & Group Locations
                $loc = $row['location_name'];
                if (!isset($metrics['locations'][$loc])) $metrics['locations'][$loc] = 0;
                $metrics['locations'][$loc]++;
            }
            $stmt->close();

            // Sort Locations and Types descending for cleaner visual charts
            arsort($metrics['locations']);
            arsort($metrics['hazard_types']);

            echo json_encode(['success' => true, 'data' => $metrics]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid store ID.']);
        }
        break;

    /**
     * Default Fallback
     */
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown or invalid API action specified.']);
        break;
}

// Ensure the connection is closed after execution
if (isset($conn)) {
    $conn->close();
}
?>