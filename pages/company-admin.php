<?php
/**
 * Company Administration Panel - pages/company-admin.php
 *
 * A dedicated controller for company-level administrative actions.
 * This is SEPARATE from pages/admin.php (platform admin) and allows
 * Company Admins, Owners, Managers, and Safety Managers to:
 *
 *   - Manage users within their own company (create, edit, view)
 *   - Configure company structure (branches / job sites)
 *
 * Security model:
 *   - Access requires is_company_admin() from permissions.php.
 *   - Platform Admin role is NOT required.
 *   - All DB operations are scoped to the session company_id.
 *   - Role assignment is restricted to COMPANY_ASSIGNABLE_ROLES —
 *     the 'Admin' platform role can never be assigned here.
 *   - Privilege escalation guard: role_is_company_assignable() is
 *     called server-side on every role assignment POST.
 *
 * Views (via ?view= GET param, whitelisted):
 *   users     — list, create, and quick-edit users in this company
 *   structure — manage branches / job sites (delegates to manage-company.php logic)
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// ── Auth & access gate ────────────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/company_context.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/user_profile_fields.php';

if (!is_company_admin()) {
    header('Location: /');
    exit();
}

$user      = $_SESSION['user'];
$companyId = (int)($user['company_id'] ?? 0);
$userRole  = $user['role_name'] ?? '';

// ── View routing ──────────────────────────────────────────────────────────────
$view = $_GET['view'] ?? 'overview';
$allowedViews = ['overview', 'users', 'add-user', 'edit-user', 'structure'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'overview';
}

$companyCtx  = get_company_context($conn, $companyId);
$isJobBased  = ($companyCtx['type'] === 'job_based');
$allowedRoles = get_allowed_roles_for_company($conn);

$successMessage = '';
$errorMessage   = '';

$searchTerm = trim((string)($_GET['q'] ?? ''));
$filterRoleId = filter_input(INPUT_GET, 'role_id', FILTER_VALIDATE_INT) ?: null;
$filterLocationId = filter_input(INPUT_GET, 'location_id', FILTER_VALIDATE_INT) ?: null;
$filterStatus = strtolower(trim((string)($_GET['status'] ?? '')));
$sortKey = trim((string)($_GET['sort'] ?? 'name'));
$sortDir = strtolower(trim((string)($_GET['dir'] ?? 'asc')));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 25);
$allowedPerPage = [10, 25, 50, 100];
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 25;
}
$allowedStatuses = ['active', 'inactive', 'suspended', 'terminated'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}
if ($sortDir !== 'desc') {
    $sortDir = 'asc';
}

/**
 * Bind a dynamic list of params to a mysqli prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @param string      $types
 * @param array       $params
 * @return void
 */
function company_admin_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || empty($params)) {
        return;
    }
    $refs = [];
    foreach ($params as $k => $value) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check($errorMessage)) {
        // ── A: Create new user ────────────────────────────────────────────────
        if ($view === 'add-user') {
            $firstName  = trim($_POST['first_name'] ?? '');
            $lastName   = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $position   = trim($_POST['employee_position'] ?? '');
            $employeeCode = upf_nullable_string($_POST['employee_code'] ?? null, 50);
            $status = strtolower(trim($_POST['status'] ?? 'active'));
            $employmentType = upf_nullable_string($_POST['employment_type'] ?? null, 20);
            $department = upf_nullable_string($_POST['department'] ?? null, 100);
            $phoneNumber = upf_nullable_string($_POST['phone_number'] ?? null, 30);
            $hireDate = upf_nullable_string($_POST['hire_date'] ?? null, 20);
            $preferredLanguage = upf_nullable_string($_POST['preferred_language'] ?? null, 10);
            $timezone = upf_nullable_string($_POST['timezone'] ?? null, 50);
            $supervisorUserId = filter_input(INPUT_POST, 'supervisor_user_id', FILTER_VALIDATE_INT) ?: null;
            $password   = $_POST['password'] ?? '';
            $roleId     = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
            $locationId = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);

            // Basic validation
            if (empty($firstName) || empty($lastName) || empty($email) || !$roleId || empty($password)) {
                $errorMessage = "Please fill out all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email address format.";
            } elseif (strlen($password) < 8) {
                $errorMessage = "Password must be at least 8 characters.";
            } elseif (!role_is_company_assignable($conn, $roleId)) {
                // Privilege escalation guard — never allow assigning platform Admin
                $errorMessage = "The selected role cannot be assigned at the company level.";
            } elseif (!upf_valid_status($status)) {
                $errorMessage = "Invalid status selected.";
            } elseif (!upf_valid_employment_type($employmentType)) {
                $errorMessage = "Invalid employment type selected.";
            } elseif (!upf_valid_phone($phoneNumber)) {
                $errorMessage = "Invalid phone number format.";
            } elseif (!upf_valid_language($preferredLanguage)) {
                $errorMessage = "Invalid preferred language format.";
            } elseif (!upf_valid_timezone($timezone)) {
                $errorMessage = "Invalid timezone selected.";
            } elseif (!upf_supervisor_in_company($conn, $supervisorUserId, $companyId)) {
                $errorMessage = "Selected supervisor is not in your company.";
            } else {
                if ($hireDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
                    $hireDate = null;
                }
                // Validate location belongs to this company
                $locationValid = false;
                if ($locationId) {
                    $locationValid = validate_location_ownership($conn, $locationId, $companyId, $companyCtx['type']);
                    if (!$locationValid) {
                        $errorMessage = "Invalid location selected.";
                    }
                }

                if (empty($errorMessage)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $conn->begin_transaction();
                    try {
                        // Insert user
                        $stmt = $conn->prepare(
                            "INSERT INTO users (
                                first_name, last_name, email, password, employee_position, role_id,
                                employee_code, status, employment_type, department, phone_number,
                                hire_date, preferred_language, timezone, supervisor_user_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->bind_param(
                            "sssssissssssssi",
                            $firstName, $lastName, $email, $hashedPassword, $position, $roleId,
                            $employeeCode, $status, $employmentType, $department, $phoneNumber,
                            $hireDate, $preferredLanguage, $timezone, $supervisorUserId
                        );
                        $stmt->execute();
                        $newUserId = (int)$stmt->insert_id;
                        $stmt->close();

                        // Assign to location
                        if ($locationId && $locationValid) {
                            if ($isJobBased) {
                                $locStmt = $conn->prepare(
                                    "INSERT INTO user_job_sites (user_id, job_site_id) VALUES (?, ?)"
                                );
                            } else {
                                $locStmt = $conn->prepare(
                                    "INSERT INTO user_stores (user_id, store_id) VALUES (?, ?)"
                                );
                            }
                            $locStmt->bind_param("ii", $newUserId, $locationId);
                            $locStmt->execute();
                            $locStmt->close();
                        }

                        $conn->commit();
                        $successMessage = "User '{$firstName} {$lastName}' created successfully.";
                        $view = 'users'; // redirect view back to user list on success

                    } catch (Exception $e) {
                        $conn->rollback();
                        $errorMessage = ($conn->errno === 1062)
                            ? "An account with this email already exists."
                            : "Database error. Please try again.";
                    }
                }
            }
        }

        // ── B: Edit user basic details + role ─────────────────────────────────
        if ($view === 'edit-user') {
            $editUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $firstName  = trim($_POST['first_name'] ?? '');
            $lastName   = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $position   = trim($_POST['employee_position'] ?? '');
            $employeeCode = upf_nullable_string($_POST['employee_code'] ?? null, 50);
            $status = strtolower(trim($_POST['status'] ?? 'active'));
            $employmentType = upf_nullable_string($_POST['employment_type'] ?? null, 20);
            $department = upf_nullable_string($_POST['department'] ?? null, 100);
            $phoneNumber = upf_nullable_string($_POST['phone_number'] ?? null, 30);
            $hireDate = upf_nullable_string($_POST['hire_date'] ?? null, 20);
            $preferredLanguage = upf_nullable_string($_POST['preferred_language'] ?? null, 10);
            $timezone = upf_nullable_string($_POST['timezone'] ?? null, 50);
            $supervisorUserId = filter_input(INPUT_POST, 'supervisor_user_id', FILTER_VALIDATE_INT) ?: null;
            $roleId     = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
            $newPassword = $_POST['new_password'] ?? '';

            if (!$editUserId || empty($firstName) || empty($lastName) || empty($email) || !$roleId) {
                $errorMessage = "Please fill out all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email address format.";
            } elseif (!role_is_company_assignable($conn, $roleId)) {
                $errorMessage = "The selected role cannot be assigned at the company level.";
            } elseif (!upf_valid_status($status)) {
                $errorMessage = "Invalid status selected.";
            } elseif (!upf_valid_employment_type($employmentType)) {
                $errorMessage = "Invalid employment type selected.";
            } elseif (!upf_valid_phone($phoneNumber)) {
                $errorMessage = "Invalid phone number format.";
            } elseif (!upf_valid_language($preferredLanguage)) {
                $errorMessage = "Invalid preferred language format.";
            } elseif (!upf_valid_timezone($timezone)) {
                $errorMessage = "Invalid timezone selected.";
            } elseif ($supervisorUserId !== null && $supervisorUserId === $editUserId) {
                $errorMessage = "A user cannot be their own supervisor.";
            } elseif (!upf_supervisor_in_company($conn, $supervisorUserId, $companyId)) {
                $errorMessage = "Selected supervisor is not in your company.";
            } else {
                if ($hireDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
                    $hireDate = null;
                }
                // Confirm this user actually belongs to this company (IDOR guard)
                $ownerCheck = $conn->prepare(
                    "SELECT u.id FROM users u
                     JOIN user_stores us ON u.id = us.user_id
                     JOIN stores s ON us.store_id = s.id
                     WHERE u.id = ? AND s.company_id = ?
                     LIMIT 1"
                );
                $ownerCheck->bind_param("ii", $editUserId, $companyId);
                $ownerCheck->execute();
                $ownerValid = $ownerCheck->get_result()->fetch_assoc() !== null;
                $ownerCheck->close();

                if (!$ownerValid && $isJobBased) {
                    $ownerCheck2 = $conn->prepare(
                        "SELECT u.id FROM users u
                         JOIN user_job_sites ujs ON u.id = ujs.user_id
                         JOIN job_sites js ON ujs.job_site_id = js.id
                         WHERE u.id = ? AND js.company_id = ?
                         LIMIT 1"
                    );
                    $ownerCheck2->bind_param("ii", $editUserId, $companyId);
                    $ownerCheck2->execute();
                    $ownerValid = $ownerCheck2->get_result()->fetch_assoc() !== null;
                    $ownerCheck2->close();
                }

                if (!$ownerValid) {
                    $errorMessage = "Access denied. This user does not belong to your company.";
                } else {
                    // Build update — optionally include password
                    if (!empty($newPassword)) {
                        if (strlen($newPassword) < 8) {
                            $errorMessage = "Password must be at least 8 characters.";
                        } else {
                            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                            $stmt = $conn->prepare(
                                "UPDATE users
                                 SET first_name=?, last_name=?, email=?, employee_position=?, role_id=?,
                                     employee_code=?, status=?, employment_type=?, department=?, phone_number=?,
                                     hire_date=?, preferred_language=?, timezone=?,
                                     supervisor_user_id=?, password=?, password_changed_at=NOW()
                                 WHERE id=?"
                            );
                            $stmt->bind_param(
                                "ssssisssssssssisi",
                                $firstName, $lastName, $email, $position, $roleId,
                                $employeeCode, $status, $employmentType, $department, $phoneNumber,
                                $hireDate, $preferredLanguage, $timezone,
                                $supervisorUserId, $hashed, $editUserId
                            );
                        }
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE users
                             SET first_name=?, last_name=?, email=?, employee_position=?, role_id=?,
                                 employee_code=?, status=?, employment_type=?, department=?, phone_number=?,
                                 hire_date=?, preferred_language=?, timezone=?, supervisor_user_id=?
                             WHERE id=?"
                        );
                        $stmt->bind_param(
                            "ssssisssssssssii",
                            $firstName, $lastName, $email, $position, $roleId,
                            $employeeCode, $status, $employmentType, $department, $phoneNumber,
                            $hireDate, $preferredLanguage, $timezone, $supervisorUserId, $editUserId
                        );
                    }

                    if (empty($errorMessage)) {
                        if ($stmt->execute()) {
                            $successMessage = "User updated successfully.";
                        } else {
                            $errorMessage = ($conn->errno === 1062)
                                ? "This email address is already in use."
                                : "Database error. Please try again.";
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// ── Data fetching for views ───────────────────────────────────────────────────

// Users list (scoped to company)
$companyUsers = [];
$totalUsersCount = 0;
$totalPages = 1;
$rowStart = 0;
$rowEnd = 0;
if ($view === 'users') {
    $fromSql = "";
    $whereClauses = [];
    $types = "";
    $params = [];

    if ($isJobBased) {
        $fromSql = " FROM users u
                     JOIN roles r ON u.role_id = r.id
                     LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
                     LEFT JOIN job_sites js ON ujs.job_site_id = js.id";
        $whereClauses[] = "js.company_id = ?";
    } else {
        $fromSql = " FROM users u
                     JOIN roles r ON u.role_id = r.id
                     LEFT JOIN user_stores us ON u.id = us.user_id
                     LEFT JOIN stores s ON us.store_id = s.id";
        $whereClauses[] = "s.company_id = ?";
    }

    $types .= "i";
    $params = [$companyId];

    if ($searchTerm !== '') {
        $whereClauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_position LIKE ?)";
        $like = '%' . $searchTerm . '%';
        $types .= "ssss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($filterRoleId) {
        $whereClauses[] = "u.role_id = ?";
        $types .= "i";
        $params[] = $filterRoleId;
    }

    if ($filterStatus !== '') {
        $whereClauses[] = "u.status = ?";
        $types .= "s";
        $params[] = $filterStatus;
    }

    if ($filterLocationId) {
        if ($isJobBased) {
            $whereClauses[] = "js.id = ?";
        } else {
            $whereClauses[] = "s.id = ?";
        }
        $types .= "i";
        $params[] = $filterLocationId;
    }

    $whereSql = " WHERE " . implode(" AND ", $whereClauses);

    $sortMap = [
        'name' => "u.last_name",
        'email' => "u.email",
        'role' => "r.role_name",
        'status' => "u.status",
        'location' => $isJobBased ? "MIN(js.job_name)" : "MIN(s.store_name)",
    ];
    if (!isset($sortMap[$sortKey])) {
        $sortKey = 'name';
    }
    $sortExpr = $sortMap[$sortKey];
    $secondaryDir = ($sortDir === 'desc') ? 'DESC' : 'ASC';
    $orderSql = " ORDER BY {$sortExpr} " . strtoupper($sortDir) . ", u.first_name {$secondaryDir}";

    $countSql = "SELECT COUNT(DISTINCT u.id) AS total_users" . $fromSql . $whereSql;
    $countStmt = $conn->prepare($countSql);
    company_admin_bind_params($countStmt, $types, $params);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
    $totalUsersCount = (int)($countRow['total_users'] ?? 0);
    $totalPages = max(1, (int)ceil($totalUsersCount / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $selectSql = "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position,
                         u.status, u.department, u.employment_type,
                         r.role_name,
                         " . ($isJobBased
                            ? "GROUP_CONCAT(DISTINCT js.job_name SEPARATOR ', ')"
                            : "GROUP_CONCAT(DISTINCT s.store_name SEPARATOR ', ')")
                            . " AS locations"
                    . $fromSql
                    . $whereSql
                    . " GROUP BY u.id";

    if ((($_GET['export'] ?? '') === 'csv')) {
        $csvSql = $selectSql . $orderSql;
        $csvStmt = $conn->prepare($csvSql);
        company_admin_bind_params($csvStmt, $types, $params);
        $csvStmt->execute();
        $csvUsers = $csvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $csvStmt->close();

        $csvName = 'company-users-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $csvName . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Name', 'Email', 'Role', 'Status', ($isJobBased ? 'Job Sites' : 'Locations')]);
        foreach ($csvUsers as $u) {
            fputcsv($out, [
                trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
                $u['email'] ?? '',
                $u['role_name'] ?? '',
                ucfirst($u['status'] ?? ''),
                $u['locations'] ?? '',
            ]);
        }
        fclose($out);
        exit();
    }

    $listSql = $selectSql . $orderSql . " LIMIT ? OFFSET ?";
    $listTypes = $types . "ii";
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;

    $stmt = $conn->prepare($listSql);
    company_admin_bind_params($stmt, $listTypes, $listParams);
    $stmt->execute();
    $companyUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($totalUsersCount > 0) {
        $rowStart = $offset + 1;
        $rowEnd = min($offset + $perPage, $totalUsersCount);
    }
}

$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'inactive_users' => 0,
    'supervisors' => 0,
    'locations' => count($companyCtx['locations']),
];

$statsSql = "SELECT
                COUNT(DISTINCT u.id) AS total_users,
                SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN u.status <> 'active' THEN 1 ELSE 0 END) AS inactive_users,
                SUM(CASE WHEN r.role_name IN ('Manager', 'Safety Manager', 'Site Supervisor', 'Company Admin', 'Owner / CEO') THEN 1 ELSE 0 END) AS supervisors
            FROM users u
            JOIN roles r ON u.role_id = r.id";
if ($isJobBased) {
    $statsSql .= " JOIN user_job_sites ujs ON u.id = ujs.user_id
                   JOIN job_sites js ON ujs.job_site_id = js.id
                   WHERE js.company_id = ?";
} else {
    $statsSql .= " JOIN user_stores us ON u.id = us.user_id
                   JOIN stores s ON us.store_id = s.id
                   WHERE s.company_id = ?";
}
$statsStmt = $conn->prepare($statsSql);
$statsStmt->bind_param("i", $companyId);
$statsStmt->execute();
$statsRow = $statsStmt->get_result()->fetch_assoc() ?: [];
$statsStmt->close();
$stats['total_users'] = (int)($statsRow['total_users'] ?? 0);
$stats['active_users'] = (int)($statsRow['active_users'] ?? 0);
$stats['inactive_users'] = (int)($statsRow['inactive_users'] ?? 0);
$stats['supervisors'] = (int)($statsRow['supervisors'] ?? 0);

// Single user for edit view
$editUser = null;
if ($view === 'edit-user') {
    $editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($editId) {
        if ($isJobBased) {
            $stmt = $conn->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position, u.role_id
                        ,u.employee_code, u.status, u.employment_type, u.department, u.phone_number, u.hire_date, u.preferred_language, u.timezone, u.supervisor_user_id
                 FROM users u
                 JOIN user_job_sites ujs ON u.id = ujs.user_id
                 JOIN job_sites js ON ujs.job_site_id = js.id
                 WHERE u.id = ? AND js.company_id = ?
                 LIMIT 1"
            );
        } else {
            $stmt = $conn->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position, u.role_id
                        ,u.employee_code, u.status, u.employment_type, u.department, u.phone_number, u.hire_date, u.preferred_language, u.timezone, u.supervisor_user_id
                 FROM users u
                 JOIN user_stores us ON u.id = us.user_id
                 JOIN stores s ON us.store_id = s.id
                 WHERE u.id = ? AND s.company_id = ?
                 LIMIT 1"
            );
        }
        $stmt->bind_param("ii", $editId, $companyId);
        $stmt->execute();
        $editUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$supervisors = upf_get_supervisor_candidates_by_type($conn, (int)$companyId, $companyCtx['type']);
?>

<div class="flex flex-col md:flex-row gap-6 admin-shell">

    <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
    <aside class="w-full md:w-64 shrink-0">
        <div class="admin-sidebar-panel bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">

            <!-- Sidebar header -->
            <div class="p-4 bg-gradient-to-r from-primary to-secondary text-white">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-building text-purple-200"></i>
                    <h3 class="font-bold text-sm uppercase tracking-wider">Company Admin</h3>
                </div>
                <p class="text-xs text-purple-200 truncate"><?php echo htmlspecialchars($companyCtx['company_name']); ?></p>
            </div>

            <nav class="p-2 space-y-1">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 pt-3 pb-1">Overview</p>
                <a href="/company-admin?view=overview"
                   class="admin-nav-link flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'overview') ? 'is-active bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-chart-line w-6 text-center mr-2"></i> Dashboard
                </a>

                <!-- Users section -->
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 pt-3 pb-1">User Management</p>

                <a href="/company-admin?view=users"
                   class="admin-nav-link flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'users') ? 'is-active bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-users w-6 text-center mr-2"></i> All Users
                </a>

                <a href="/company-admin?view=add-user"
                   class="admin-nav-link flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'add-user') ? 'is-active bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-user-plus w-6 text-center mr-2"></i> Add New User
                </a>

                <!-- Company structure section -->
                <div class="border-t border-gray-100 my-2"></div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 pt-1 pb-1">Company</p>

                <a href="/company-admin?view=structure"
                   class="admin-nav-link flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'structure') ? 'is-active bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas <?php echo htmlspecialchars($companyCtx['location_icon']); ?> w-6 text-center mr-2"></i>
                    <?php echo $isJobBased ? 'Job Sites' : 'Branches'; ?>
                </a>

                <!-- Back to Dashboard -->
                <div class="border-t border-gray-100 my-2"></div>
                <a href="/dashboard" class="admin-nav-link flex items-center px-4 py-3 rounded-lg text-gray-500 hover:bg-gray-100 text-sm transition">
                    <i class="fas fa-arrow-left w-6 text-center mr-2"></i> Back to Dashboard
                </a>
            </nav>
        </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────────────────────────── -->
    <main class="flex-1 min-w-0">
        <div class="admin-content-panel bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">

            <!-- Feedback messages -->
            <?php if (!empty($successMessage)): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php

            // ── VIEW: Overview ─────────────────────────────────────────────
            if ($view === 'overview'):
            ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">Company Admin Overview</h2>
                <p class="text-sm text-gray-500 mt-1">Quick health check and shortcuts for your company workspace.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
                <div class="rounded-xl border border-gray-200 p-4 bg-gradient-to-br from-slate-50 to-white">
                    <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-1">Total Users</p>
                    <p class="text-3xl font-extrabold text-primary"><?php echo (int)$stats['total_users']; ?></p>
                </div>
                <div class="rounded-xl border border-green-200 p-4 bg-gradient-to-br from-green-50 to-white">
                    <p class="text-xs uppercase tracking-wide text-green-700 font-semibold mb-1">Active Users</p>
                    <p class="text-3xl font-extrabold text-green-700"><?php echo (int)$stats['active_users']; ?></p>
                </div>
                <div class="rounded-xl border border-amber-200 p-4 bg-gradient-to-br from-amber-50 to-white">
                    <p class="text-xs uppercase tracking-wide text-amber-700 font-semibold mb-1">Non-Active Users</p>
                    <p class="text-3xl font-extrabold text-amber-700"><?php echo (int)$stats['inactive_users']; ?></p>
                </div>
                <div class="rounded-xl border border-blue-200 p-4 bg-gradient-to-br from-blue-50 to-white">
                    <p class="text-xs uppercase tracking-wide text-blue-700 font-semibold mb-1"><?php echo $isJobBased ? 'Job Sites' : 'Locations'; ?></p>
                    <p class="text-3xl font-extrabold text-blue-700"><?php echo (int)$stats['locations']; ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="card">
                    <h3 class="font-bold text-primary mb-3">Quick Actions</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="/company-admin?view=users" class="rounded-lg border border-gray-200 px-4 py-3 hover:border-primary hover:bg-blue-50 transition">
                            <i class="fas fa-users text-primary mr-2"></i> Manage Users
                        </a>
                        <a href="/company-admin?view=add-user" class="rounded-lg border border-gray-200 px-4 py-3 hover:border-primary hover:bg-blue-50 transition">
                            <i class="fas fa-user-plus text-primary mr-2"></i> Add User
                        </a>
                        <a href="/company-admin?view=structure" class="rounded-lg border border-gray-200 px-4 py-3 hover:border-primary hover:bg-blue-50 transition">
                            <i class="fas <?php echo htmlspecialchars($companyCtx['location_icon']); ?> text-primary mr-2"></i>
                            Manage <?php echo $isJobBased ? 'Job Sites' : 'Locations'; ?>
                        </a>
                        <a href="/dashboard" class="rounded-lg border border-gray-200 px-4 py-3 hover:border-primary hover:bg-blue-50 transition">
                            <i class="fas fa-arrow-left text-primary mr-2"></i> Return to Dashboard
                        </a>
                    </div>
                </div>

                <div class="card">
                    <h3 class="font-bold text-primary mb-3">Role Coverage</h3>
                    <p class="text-sm text-gray-600 mb-4">Supervisor-capable roles currently assigned in your company.</p>
                    <div class="inline-flex items-end gap-2">
                        <span class="text-3xl font-extrabold text-primary"><?php echo (int)$stats['supervisors']; ?></span>
                        <span class="text-gray-500 text-sm pb-1">users</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-3">Includes Company Admin, Owner / CEO, Manager, Safety Manager, and Site Supervisor.</p>
                </div>
            </div>

            <?php
            // ── VIEW: Users list ───────────────────────────────────────────
            elseif ($view === 'users'):
                $baseQueryParams = ['view' => 'users'];
                if ($searchTerm !== '') { $baseQueryParams['q'] = $searchTerm; }
                if ($filterRoleId) { $baseQueryParams['role_id'] = $filterRoleId; }
                if ($filterStatus !== '') { $baseQueryParams['status'] = $filterStatus; }
                if ($filterLocationId) { $baseQueryParams['location_id'] = $filterLocationId; }
                if ($perPage !== 25) { $baseQueryParams['per_page'] = $perPage; }
                $sortLink = function (string $key) use ($baseQueryParams, $sortKey, $sortDir): string {
                    $params = $baseQueryParams;
                    $params['sort'] = $key;
                    $params['dir'] = ($sortKey === $key && $sortDir === 'asc') ? 'desc' : 'asc';
                    return '/company-admin?' . http_build_query($params);
                };
                $arrowFor = function (string $key) use ($sortKey, $sortDir): string {
                    if ($sortKey !== $key) {
                        return '';
                    }
                    return $sortDir === 'asc' ? ' <i class="fas fa-sort-up ml-1"></i>' : ' <i class="fas fa-sort-down ml-1"></i>';
                };
            ?>

            <div class="mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">Users</h2>
                    <p class="text-sm text-gray-500 mt-1">All users within your company workspace.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php
                        $exportParams = $baseQueryParams;
                        $exportParams['sort'] = $sortKey;
                        $exportParams['dir'] = $sortDir;
                        $exportParams['export'] = 'csv';
                    ?>
                    <a href="/company-admin?<?php echo htmlspecialchars(http_build_query($exportParams)); ?>"
                       class="btn btn-secondary text-sm flex items-center gap-2">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                    <a href="/company-admin?view=add-user" class="btn bg-primary text-white hover:bg-secondary flex items-center gap-2 shadow">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                </div>
            </div>

            <form method="GET" action="/company-admin" class="card mb-6">
                <input type="hidden" name="view" value="users">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortKey); ?>">
                <input type="hidden" name="dir" value="<?php echo htmlspecialchars($sortDir); ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div class="xl:col-span-2">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" id="q" name="q" class="form-input" placeholder="Name, email, or position..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div>
                        <label class="form-label" for="role_id">Role</label>
                        <select id="role_id" name="role_id" class="form-input cursor-pointer">
                            <option value="">All Roles</option>
                            <?php foreach ($allowedRoles as $role): ?>
                                <option value="<?php echo (int)$role['id']; ?>" <?php echo ($filterRoleId === (int)$role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-input cursor-pointer">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo ($filterStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($filterStatus === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($filterStatus === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                            <option value="terminated" <?php echo ($filterStatus === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="location_id"><?php echo $isJobBased ? 'Job Site' : 'Location'; ?></label>
                        <select id="location_id" name="location_id" class="form-input cursor-pointer">
                            <option value="">All</option>
                            <?php foreach ($companyCtx['locations'] as $loc): ?>
                                <?php
                                $locId = (int)$loc['id'];
                                $locName = $isJobBased
                                    ? ($loc['job_name'] ?? '')
                                    : ($loc['store_name'] ?? '');
                                ?>
                                <option value="<?php echo $locId; ?>" <?php echo ($filterLocationId === $locId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string)$locName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mt-4">
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold uppercase tracking-wide text-gray-400" for="per_page">Rows</label>
                        <select id="per_page" name="per_page" class="form-input cursor-pointer !py-2 !px-3 text-sm w-24">
                            <option value="10" <?php echo ($perPage === 10) ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo ($perPage === 25) ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($perPage === 50) ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($perPage === 100) ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                    <a href="/company-admin?view=users" class="btn btn-secondary text-sm">Reset</a>
                    <button type="submit" class="btn bg-primary text-white hover:bg-secondary text-sm">
                        <i class="fas fa-filter mr-1"></i> Apply Filters
                    </button>
                </div>
            </form>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="saas-table min-w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-6 py-4">
                                    <a href="<?php echo htmlspecialchars($sortLink('name')); ?>" class="inline-flex items-center hover:text-primary">
                                        Name<?php echo $arrowFor('name'); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-4">
                                    <a href="<?php echo htmlspecialchars($sortLink('email')); ?>" class="inline-flex items-center hover:text-primary">
                                        Email<?php echo $arrowFor('email'); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-4">
                                    <a href="<?php echo htmlspecialchars($sortLink('role')); ?>" class="inline-flex items-center hover:text-primary">
                                        Role<?php echo $arrowFor('role'); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-4">
                                    <a href="<?php echo htmlspecialchars($sortLink('location')); ?>" class="inline-flex items-center hover:text-primary">
                                        <?php echo $isJobBased ? 'Job Site(s)' : 'Location(s)'; ?><?php echo $arrowFor('location'); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($companyUsers)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">
                                    No users found. <a href="/company-admin?view=add-user" class="text-primary font-semibold hover:underline">Add the first user.</a>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($companyUsers as $u): ?>
                                    <tr class="hover:bg-blue-50 transition group">
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?php echo (($u['status'] ?? 'active') === 'active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'; ?>">
                                                <?php echo htmlspecialchars(ucfirst($u['status'] ?? 'active')); ?>
                                            </span>
                                            <?php if (!empty($u['department'])): ?>
                                                <span class="block text-[10px] text-gray-500 mt-0.5"><?php echo htmlspecialchars($u['department']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">
                                            <?php echo htmlspecialchars($u['email']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php
                                            $badge = match($u['role_name']) {
                                                'Owner / CEO', 'Admin' => 'bg-purple-100 text-purple-800',
                                                'Company Admin' => 'bg-purple-200 text-purple-900',
                                                'Manager', 'Safety Manager' => 'bg-blue-100 text-blue-800',
                                                'Safety Leader', 'JHSC Member' => 'bg-green-100 text-green-800',
                                                default => 'bg-gray-100 text-gray-700'
                                            };
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $badge; ?>">
                                                <?php echo htmlspecialchars($u['role_name']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 text-xs max-w-xs truncate">
                                            <?php echo htmlspecialchars($u['locations'] ?? '—'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="/company-admin?view=edit-user&id=<?php echo (int)$u['id']; ?>"
                                               class="text-primary hover:text-secondary font-bold text-xs uppercase tracking-wide border border-blue-200 rounded px-3 py-1.5 hover:bg-blue-50 transition">
                                                <i class="fas fa-pencil-alt mr-1"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 text-xs text-gray-500 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <span>
                        Showing <strong><?php echo (int)$rowStart; ?>-<?php echo (int)$rowEnd; ?></strong> of
                        <strong><?php echo (int)$totalUsersCount; ?></strong> user(s)
                    </span>
                    <span>Page <strong><?php echo (int)$page; ?></strong> of <strong><?php echo (int)$totalPages; ?></strong></span>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-xs text-gray-500">
                        Sorted by <strong><?php echo htmlspecialchars(ucfirst($sortKey)); ?></strong> (<?php echo htmlspecialchars(strtoupper($sortDir)); ?>)
                    </div>
                    <div class="flex items-center gap-2">
                        <?php
                            $prevParams = $baseQueryParams;
                            $prevParams['sort'] = $sortKey;
                            $prevParams['dir'] = $sortDir;
                            $prevParams['page'] = max(1, $page - 1);
                            $nextParams = $baseQueryParams;
                            $nextParams['sort'] = $sortKey;
                            $nextParams['dir'] = $sortDir;
                            $nextParams['page'] = min($totalPages, $page + 1);
                        ?>
                        <a href="/company-admin?<?php echo htmlspecialchars(http_build_query($prevParams)); ?>"
                           class="btn btn-secondary text-sm <?php echo ($page <= 1) ? 'pointer-events-none opacity-50' : ''; ?>">
                            <i class="fas fa-chevron-left mr-1"></i> Prev
                        </a>
                        <a href="/company-admin?<?php echo htmlspecialchars(http_build_query($nextParams)); ?>"
                           class="btn btn-secondary text-sm <?php echo ($page >= $totalPages) ? 'pointer-events-none opacity-50' : ''; ?>">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            // ── VIEW: Add user ──────────────────────────────────────────────
            elseif ($view === 'add-user'):
            ?>

            <div class="mb-8">
                <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">Add New User</h2>
                <p class="text-sm text-gray-500 mt-1">Create a new user account within your company.</p>
            </div>

            <form action="/company-admin?view=add-user" method="POST" class="space-y-8 max-w-3xl">
                <?php csrf_field(); ?>

                <!-- Personal info -->
                <div class="card">
                    <h3 class="text-lg font-bold text-gray-700 mb-5 border-b border-gray-100 pb-2">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="form-label" for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required class="form-input" placeholder="Jane">
                        </div>
                        <div>
                            <label class="form-label" for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required class="form-input" placeholder="Smith">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required class="form-input" placeholder="jane.smith@company.com" autocomplete="new-email">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="employee_position">Job Title / Position</label>
                            <input type="text" id="employee_position" name="employee_position" class="form-input" placeholder="e.g., Safety Officer">
                        </div>
                        <div>
                            <label class="form-label" for="employee_code">Employee Code</label>
                            <input type="text" id="employee_code" name="employee_code" class="form-input" placeholder="e.g., EMP-2001">
                        </div>
                        <div>
                            <label class="form-label" for="status">Status</label>
                            <select id="status" name="status" class="form-input cursor-pointer">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="employment_type">Employment Type</label>
                            <select id="employment_type" name="employment_type" class="form-input cursor-pointer">
                                <option value="">-- Select --</option>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contractor">Contractor</option>
                                <option value="temporary">Temporary</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="department">Department</label>
                            <input type="text" id="department" name="department" class="form-input" placeholder="e.g., Operations">
                        </div>
                        <div>
                            <label class="form-label" for="phone_number">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" class="form-input" placeholder="+1 555 123 4567">
                        </div>
                        <div>
                            <label class="form-label" for="hire_date">Hire Date</label>
                            <input type="date" id="hire_date" name="hire_date" class="form-input">
                        </div>
                        <div>
                            <label class="form-label" for="preferred_language">Preferred Language</label>
                            <input type="text" id="preferred_language" name="preferred_language" class="form-input" placeholder="en or en-CA">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="timezone">Timezone</label>
                            <input type="text" id="timezone" name="timezone" class="form-input" placeholder="America/Edmonton">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="supervisor_user_id">Supervisor</label>
                            <select id="supervisor_user_id" name="supervisor_user_id" class="form-input cursor-pointer">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                    <option value="<?php echo (int)$sup['id']; ?>" data-location-ids="<?php echo htmlspecialchars($sup['location_ids_csv'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name'] . (!empty($sup['employee_position']) ? ' - ' . $sup['employee_position'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Filtered by selected location.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="password">Initial Password * <span class="text-gray-400 font-normal">(min. 8 characters)</span></label>
                            <input type="password" id="password" name="password" required class="form-input" autocomplete="new-password" placeholder="Minimum 8 characters">
                        </div>
                    </div>
                </div>

                <!-- Assignment -->
                <div class="card">
                    <h3 class="text-lg font-bold text-gray-700 mb-5 border-b border-gray-100 pb-2">Role & Assignment</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Role selector — only company-assignable roles shown -->
                        <div>
                            <label class="form-label" for="role_id">Role *</label>
                            <select id="role_id" name="role_id" required class="form-input cursor-pointer">
                                <option value="">— Select a role —</option>
                                <?php foreach ($allowedRoles as $role): ?>
                                    <option value="<?php echo (int)$role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">The 'Admin' platform role is not assignable here.</p>
                        </div>
                        <!-- Location selector -->
                        <div>
                            <label class="form-label" for="location_id">
                                Primary <?php echo $isJobBased ? 'Job Site' : 'Branch / Location'; ?>
                            </label>
                            <select id="location_id" name="location_id" class="form-input cursor-pointer">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($companyCtx['locations'] as $loc): ?>
                                    <?php
                                    $locId    = $loc['id'];
                                    $locLabel = $isJobBased
                                        ? htmlspecialchars($loc['job_name'] . ' (' . $loc['job_number'] . ')')
                                        : htmlspecialchars($loc['store_name'] . ' (' . $loc['store_number'] . ')');
                                    ?>
                                    <option value="<?php echo (int)$locId; ?>"><?php echo $locLabel; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pb-8">
                    <a href="/company-admin?view=users" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn bg-purple-600 text-white hover:bg-purple-700 shadow">
                        <i class="fas fa-user-plus mr-2"></i> Create User
                    </button>
                </div>
            </form>

            <?php
            // ── VIEW: Edit user ─────────────────────────────────────────────
            elseif ($view === 'edit-user' && $editUser):
            ?>

            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">Edit User</h2>
                    <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($editUser['first_name'] . ' ' . $editUser['last_name']); ?></p>
                </div>
                <a href="/company-admin?view=users" class="btn btn-secondary text-sm flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Users
                </a>
            </div>

            <form action="/company-admin?view=edit-user" method="POST" class="space-y-8 max-w-3xl">
                <?php csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo (int)$editUser['id']; ?>">

                <div class="card">
                    <h3 class="text-lg font-bold text-gray-700 mb-5 border-b border-gray-100 pb-2">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" required class="form-input" value="<?php echo htmlspecialchars($editUser['first_name']); ?>">
                        </div>
                        <div>
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" required class="form-input" value="<?php echo htmlspecialchars($editUser['last_name']); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" required class="form-input" value="<?php echo htmlspecialchars($editUser['email']); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Job Title / Position</label>
                            <input type="text" name="employee_position" class="form-input" value="<?php echo htmlspecialchars($editUser['employee_position'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Employee Code</label>
                            <input type="text" name="employee_code" class="form-input" value="<?php echo htmlspecialchars($editUser['employee_code'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-input cursor-pointer">
                                <option value="active" <?php echo (($editUser['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (($editUser['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo (($editUser['status'] ?? '') === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                <option value="terminated" <?php echo (($editUser['status'] ?? '') === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Employment Type</label>
                            <select name="employment_type" class="form-input cursor-pointer">
                                <option value="">-- Select --</option>
                                <option value="full_time" <?php echo (($editUser['employment_type'] ?? '') === 'full_time') ? 'selected' : ''; ?>>Full Time</option>
                                <option value="part_time" <?php echo (($editUser['employment_type'] ?? '') === 'part_time') ? 'selected' : ''; ?>>Part Time</option>
                                <option value="contractor" <?php echo (($editUser['employment_type'] ?? '') === 'contractor') ? 'selected' : ''; ?>>Contractor</option>
                                <option value="temporary" <?php echo (($editUser['employment_type'] ?? '') === 'temporary') ? 'selected' : ''; ?>>Temporary</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-input" value="<?php echo htmlspecialchars($editUser['department'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-input" value="<?php echo htmlspecialchars($editUser['phone_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Hire Date</label>
                            <input type="date" name="hire_date" class="form-input" value="<?php echo htmlspecialchars($editUser['hire_date'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label">Preferred Language</label>
                            <input type="text" name="preferred_language" class="form-input" value="<?php echo htmlspecialchars($editUser['preferred_language'] ?? ''); ?>" placeholder="en or en-CA">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Timezone</label>
                            <input type="text" name="timezone" class="form-input" value="<?php echo htmlspecialchars($editUser['timezone'] ?? ''); ?>" placeholder="America/Edmonton">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">Supervisor</label>
                            <select name="supervisor_user_id" class="form-input cursor-pointer">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                    <?php if ((int)$sup['id'] === (int)$editUser['id']) continue; ?>
                                    <option value="<?php echo (int)$sup['id']; ?>" data-location-ids="<?php echo htmlspecialchars($sup['location_ids_csv'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((int)($editUser['supervisor_user_id'] ?? 0) === (int)$sup['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name'] . (!empty($sup['employee_position']) ? ' - ' . $sup['employee_position'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Filtered by selected location.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label">New Password <span class="text-gray-400 font-normal">(leave blank to keep unchanged)</span></label>
                            <input type="password" name="new_password" class="form-input" autocomplete="new-password" placeholder="Min 8 characters">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="text-lg font-bold text-gray-700 mb-5 border-b border-gray-100 pb-2">Role</h3>
                    <div>
                        <label class="form-label">Assigned Role *</label>
                        <select name="role_id" required class="form-input cursor-pointer max-w-xs">
                            <?php foreach ($allowedRoles as $role): ?>
                                <option value="<?php echo (int)$role['id']; ?>"
                                    <?php echo ($role['id'] == $editUser['role_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">The 'Admin' platform role cannot be assigned here.</p>
                    </div>
                </div>

                <div class="flex justify-end gap-4 pb-8">
                    <a href="/company-admin?view=users" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn bg-purple-600 text-white hover:bg-purple-700 shadow">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>

            <?php
            // ── VIEW: Structure (delegates to existing manage-company logic) ─
            elseif ($view === 'structure'):
                // Reuse the Beta 06 manage-company view (already supports dual types)
                $adminView = 'manage-company';
                $adminBaseRoute = '/company-admin';
                require_once __DIR__ . '/admin-views/manage-company.php';

            else:
            ?>
                <div class="text-gray-500 italic p-8 text-center">View not found.</div>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php if ($view === 'add-user'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const locationSelect = document.getElementById('location_id');
    const supervisorSelect = document.getElementById('supervisor_user_id');
    if (!locationSelect || !supervisorSelect) return;
    const noMatchValue = '__no_match__';

    function filterSupervisorsByLocation() {
        const selectedLocationId = locationSelect.value;
        const selectedSupervisor = supervisorSelect.value;
        const options = supervisorSelect.querySelectorAll('option');
        let visibleCount = 0;

        options.forEach(function (opt, idx) {
            if (idx === 0) {
                opt.hidden = false;
                return;
            }
            if (opt.value === noMatchValue) {
                opt.hidden = true;
                return;
            }
            if (!selectedLocationId) {
                opt.hidden = false;
                visibleCount++;
                return;
            }
            const ids = (opt.getAttribute('data-location-ids') || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
            opt.hidden = !ids.includes(selectedLocationId);
            if (!opt.hidden) visibleCount++;
        });

        const selectedOption = supervisorSelect.options[supervisorSelect.selectedIndex];
        if (selectedSupervisor && selectedOption && selectedOption.hidden) {
            supervisorSelect.value = '';
        }

        let noMatchOption = supervisorSelect.querySelector('option[value="' + noMatchValue + '"]');
        if (!noMatchOption) {
            noMatchOption = document.createElement('option');
            noMatchOption.value = noMatchValue;
            noMatchOption.textContent = '-- No matching supervisors for this location --';
            noMatchOption.disabled = true;
            supervisorSelect.appendChild(noMatchOption);
        }
        noMatchOption.hidden = !(selectedLocationId && visibleCount === 0);
    }

    locationSelect.addEventListener('change', filterSupervisorsByLocation);
    filterSupervisorsByLocation();
});
</script>
<?php endif; ?>
