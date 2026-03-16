<?php
/**
 * Training Matrix API - api/training.php
 *
 * Handles backend processing for employee certifications and training categories.
 * Enforces RBAC and CSRF protection.
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
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
$userId = $_SESSION['user']['id'];
$userRole = $_SESSION['user']['role_name'] ?? '';

// Safely derive the user's Company ID based on their store assignments
$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    $compSql = "SELECT s.company_id FROM user_stores us JOIN stores s ON us.store_id = s.id WHERE us.user_id = ? LIMIT 1";
    $compStmt = $conn->prepare($compSql);
    $compStmt->bind_param("i", $userId);
    $compStmt->execute();
    $res = $compStmt->get_result()->fetch_assoc();
    $companyId = $res ? $res['company_id'] : 1; // Fallback to 1
    $compStmt->close();
}

$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];
$isManager = in_array($userRole, $managementRoles);

switch ($action) {
    
    /**
     * Fetch the complete training matrix data structure
     */
    case 'get_matrix':
        if (!$isManager) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }

        $matrix = ['categories' => [], 'users' => []];

        // 1. Fetch Categories
        $catSql = "SELECT id, name, validity_months FROM training_categories WHERE company_id = ? ORDER BY name ASC";
        $stmt = $conn->prepare($catSql);
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $matrix['categories'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 2. Fetch Users & their Records (Correctly joining through Stores/Job Sites)
        $userSql = "
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position 
            FROM users u
            LEFT JOIN user_stores us ON u.id = us.user_id
            LEFT JOIN stores s ON us.store_id = s.id
            LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
            LEFT JOIN job_sites js ON ujs.job_site_id = js.id
            WHERE s.company_id = ? OR js.company_id = ? OR u.is_platform_admin = 1
            ORDER BY u.first_name ASC
        ";
        $stmt = $conn->prepare($userSql);
        $stmt->bind_param("ii", $companyId, $companyId);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 3. Map records to users
        foreach ($users as $u) {
            $u['records'] = [];
            
            $recSql = "SELECT category_id, issue_date, expiry_date, certificate_number FROM user_training_records WHERE user_id = ?";
            $recStmt = $conn->prepare($recSql);
            $recStmt->bind_param("i", $u['id']);
            $recStmt->execute();
            $records = $recStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Map by category_id for easy O(1) frontend lookup
            foreach ($records as $r) {
                $u['records'][$r['category_id']] = $r;
            }
            $recStmt->close();
            
            $matrix['users'][] = $u;
        }

        echo json_encode(['success' => true, 'data' => $matrix]);
        break;

    /**
     * Add a new custom training category
     */
    case 'add_category':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit();
        }

        $name = trim($data['name'] ?? '');
        $validity = (int)($data['validity_months'] ?? 0);

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name is required.']); break;
        }

        $sql = "INSERT INTO training_categories (company_id, name, validity_months) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $companyId, $name, $validity);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add category.']);
        }
        $stmt->close();
        break;

    /**
     * Log a user's training record (Upsert to prevent duplicates)
     */
    case 'log_training':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit();
        }

        $empId = (int)$data['user_id'];
        $catId = (int)$data['category_id'];
        $issueDate = $data['issue_date'];
        $certNum = trim($data['certificate_number'] ?? '');
        
        if (!$empId || !$catId || empty($issueDate)) {
            echo json_encode(['success' => false, 'message' => 'Employee, Category, and Issue Date are required.']); break;
        }

        // Calculate Expiry Date based on category validity
        $expiryDate = null;
        $catSql = "SELECT validity_months FROM training_categories WHERE id = ?";
        $cStmt = $conn->prepare($catSql);
        $cStmt->bind_param("i", $catId);
        $cStmt->execute();
        $catRes = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if ($catRes && $catRes['validity_months'] > 0) {
            $months = (int)$catRes['validity_months'];
            $expiryDate = date('Y-m-d', strtotime("+$months months", strtotime($issueDate)));
        }

        // Insert or Update existing record
        $sql = "INSERT INTO user_training_records (user_id, category_id, issue_date, expiry_date, certificate_number, logged_by_user_id) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                issue_date = VALUES(issue_date), expiry_date = VALUES(expiry_date), certificate_number = VALUES(certificate_number), logged_by_user_id = VALUES(logged_by_user_id)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisssi", $empId, $catId, $issueDate, $expiryDate, $certNum, $userId);
        
        if ($stmt->execute()) {
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