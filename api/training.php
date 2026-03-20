<?php
/**
 * Training Matrix API - api/training.php
 *
 * Handles backend processing for employee certifications and training categories.
 * Enforces RBAC and CSRF protection.
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
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
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

        // 3. Batch-fetch all records to avoid N+1 per-user queries.
        $userIds = array_values(array_map(static fn($u) => (int)$u['id'], $users));
        $recordsByUser = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $recSql = "SELECT user_id, category_id, issue_date, expiry_date, certificate_number
                       FROM user_training_records
                       WHERE user_id IN ($placeholders)";
            $recStmt = $conn->prepare($recSql);
            $types = str_repeat('i', count($userIds));
            $recStmt->bind_param($types, ...$userIds);
            $recStmt->execute();
            $allRecords = $recStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $recStmt->close();

            foreach ($allRecords as $r) {
                $uid = (int)$r['user_id'];
                $cat = (int)$r['category_id'];
                if (!isset($recordsByUser[$uid])) {
                    $recordsByUser[$uid] = [];
                }
                $recordsByUser[$uid][$cat] = [
                    'category_id' => $cat,
                    'issue_date' => $r['issue_date'],
                    'expiry_date' => $r['expiry_date'],
                    'certificate_number' => $r['certificate_number'],
                ];
            }
        }

        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $u['records'] = $recordsByUser[$uid] ?? [];
            $matrix['users'][] = $u;
        }

        echo json_encode(['success' => true, 'data' => $matrix]);
        break;

    /**
     * Add a new custom training category
     */
    case 'add_category':
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Access denied.']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit();
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
        if (!$isManager) { echo json_encode(['success' => false, 'message' => 'Access denied.']); exit(); }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!validate_csrf_token($data['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']); exit();
        }

        $empId = (int)$data['user_id'];
        $catId = (int)$data['category_id'];
        $issueDate = $data['issue_date'];
        $certNum = trim($data['certificate_number'] ?? '');
        
        if (!$empId || !$catId || empty($issueDate)) {
            echo json_encode(['success' => false, 'message' => 'Employee, Category, and Issue Date are required.']); break;
        }

        // Enforce tenant ownership on both employee and category before writing.
        $empScopeSql = "SELECT 1
                        FROM users u
                        WHERE u.id = ?
                          AND (
                                EXISTS (
                                    SELECT 1
                                    FROM user_stores us
                                    JOIN stores s ON us.store_id = s.id
                                    WHERE us.user_id = u.id AND s.company_id = ?
                                )
                                OR EXISTS (
                                    SELECT 1
                                    FROM user_job_sites ujs
                                    JOIN job_sites js ON ujs.job_site_id = js.id
                                    WHERE ujs.user_id = u.id AND js.company_id = ?
                                )
                              )
                        LIMIT 1";
        $empScopeStmt = $conn->prepare($empScopeSql);
        $empScopeStmt->bind_param("iii", $empId, $companyId, $companyId);
        $empScopeStmt->execute();
        $employeeInScope = $empScopeStmt->get_result()->fetch_assoc() !== null;
        $empScopeStmt->close();

        if (!$employeeInScope) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

        // Calculate expiry date from a category that is also tenant-scoped.
        $expiryDate = null;
        $catSql = "SELECT validity_months FROM training_categories WHERE id = ? AND company_id = ?";
        $cStmt = $conn->prepare($catSql);
        $cStmt->bind_param("ii", $catId, $companyId);
        $cStmt->execute();
        $catRes = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if (!$catRes) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied.']);
            break;
        }

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