<?php
/**
 * Admin Control Panel - pages/admin.php
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// ── 1. Security & Access Control ─────────────────────────────────────────────

if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href = '/login';</script>";
    exit();
}

// Load permissions helper so child views (like manage-users.php) can use its functions
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/user_profile_fields.php';

// Platform admin panel must only be accessible to true platform admins.
if (!is_platform_admin()) {
    echo "<script>window.location.href = '/dashboard';</script>";
    exit();
}

// ── 2. Admin View Routing ─────────────────────────────────────────────────────

$adminView = $_GET['view'] ?? 'manage-users';

$allowedAdminViews = ['add-user', 'manage-users', 'manage-company'];

if (!in_array($adminView, $allowedAdminViews)) {
    $adminView = 'manage-users';
}

// ── 3. Handle Form Submissions ────────────────────────────────────────────────

$companyId = (int)($_SESSION['user']['company_id'] ?? 1);
$successMessage = '';
$errorMessage   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF validation
    if (!csrf_check($errorMessage)) {
        // Fall through — $errorMessage is set, form re-renders with error banner
    } else {

        // ── CASE A: Add New User ───────────────────────────────────────────
        if ($adminView === 'add-user') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');
            $email     = trim($_POST['email'] ?? '');
            $position  = trim($_POST['employee_position'] ?? '');
            $employeeCode = upf_nullable_string($_POST['employee_code'] ?? null, 50);
            $status = strtolower(trim($_POST['status'] ?? 'active'));
            $employmentType = upf_nullable_string($_POST['employment_type'] ?? null, 20);
            $department = upf_nullable_string($_POST['department'] ?? null, 100);
            $phoneNumber = upf_nullable_string($_POST['phone_number'] ?? null, 30);
            $hireDate = upf_nullable_string($_POST['hire_date'] ?? null, 20);
            $preferredLanguage = upf_nullable_string($_POST['preferred_language'] ?? null, 10);
            $timezone = upf_nullable_string($_POST['timezone'] ?? null, 50);
            $supervisorUserId = filter_input(INPUT_POST, 'supervisor_user_id', FILTER_VALIDATE_INT) ?: null;
            $password  = $_POST['password'] ?? '';
            $storeId   = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
            $roleId    = filter_input(INPUT_POST, 'role_id',  FILTER_VALIDATE_INT);

            if (empty($firstName) || empty($lastName) || empty($email) || !$storeId || !$roleId || empty($password)) {
                $errorMessage = "Please fill out all required fields, including password.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email address format.";
            } elseif (strlen($password) < 8) {
                $errorMessage = "Password must be at least 8 characters.";
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
                $errorMessage = "Selected supervisor is not in this company.";
            } else {
                if ($hireDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
                    $hireDate = null;
                }
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $conn->begin_transaction();
                try {
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
                    $newUserId = $stmt->insert_id;
                    $stmt->close();

                    $stmtStore = $conn->prepare("INSERT INTO user_stores (user_id, store_id) VALUES (?, ?)");
                    $stmtStore->bind_param("ii", $newUserId, $storeId);
                    $stmtStore->execute();
                    $stmtStore->close();

                    $conn->commit();
                    $successMessage = "User '" . htmlspecialchars($firstName . ' ' . $lastName) . "' created successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $errorMessage = ($conn->errno === 1062)
                        ? "Error: An account with this email address already exists."
                        : "System Error: " . $e->getMessage();
                }
            }
        }

        // ── CASE B: Add New Store ──────────────────────────────────────────
        if ($adminView === 'manage-company') {
            $storeName   = trim($_POST['store_name']   ?? '');
            $storeNumber = trim($_POST['store_number'] ?? '');
            $companyId   = $_SESSION['user']['company_id'] ?? 1;

            if (empty($storeName) || empty($storeNumber)) {
                $errorMessage = "Store Name and Store Number are required.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO stores (company_id, store_name, store_number) VALUES (?, ?, ?)"
                );
                $stmt->bind_param("iss", $companyId, $storeName, $storeNumber);

                if ($stmt->execute()) {
                    $successMessage = "Store '" . htmlspecialchars($storeName) . "' added successfully.";
                } else {
                    $errorMessage = ($conn->errno === 1062)
                        ? "Error: A store with this number already exists for this company."
                        : "Database Error: Could not add store.";
                }
                $stmt->close();
            }
        }

    } // end csrf check
}

// ── 4. Global Data Fetching ───────────────────────────────────────────────────

$stores = [];
$stmtS  = $conn->prepare("SELECT id, store_name FROM stores WHERE company_id = ? ORDER BY store_name ASC");
$stmtS->bind_param("i", $companyId);
$stmtS->execute();
$stores = $stmtS->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtS->close();

$roles = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name ASC")->fetch_all(MYSQLI_ASSOC);
$companyType = $_SESSION['user']['company_type'] ?? 'multi_location';
$supervisors = upf_get_supervisor_candidates_by_type($conn, (int)$companyId, $companyType);
?>

<div class="flex flex-col md:flex-row gap-6">

    <!-- Sidebar -->
    <aside class="w-full md:w-64 shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-bold text-primary uppercase text-xs tracking-wider">Platform Admin</h3>
            </div>
            <nav class="p-2 space-y-1">
                <a href="/admin?view=manage-users"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium <?php echo ($adminView === 'manage-users') ? 'bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-users w-6 text-center mr-2"></i> Manage Users
                </a>
                <a href="/admin?view=add-user"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium <?php echo ($adminView === 'add-user') ? 'bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-user-plus w-6 text-center mr-2"></i> Add New User
                </a>
                <div class="border-t border-gray-100 my-2"></div>
                <a href="/admin?view=manage-company"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium <?php echo ($adminView === 'manage-company') ? 'bg-primary text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-building w-6 text-center mr-2"></i> Manage Company
                </a>
            </nav>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 min-w-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">
            <?php
                $viewPath = __DIR__ . "/admin-views/{$adminView}.php";
                if (file_exists($viewPath)) {
                    require_once $viewPath;
                } else {
                    echo "<div class='bg-red-100 border-l-4 border-accent-red text-red-700 p-4 rounded shadow-sm'>
                            <p class='font-bold'>System Error</p>
                            <p>The requested admin view (<strong>{$adminView}.php</strong>) could not be found.</p>
                          </div>";
                }
            ?>
        </div>
    </main>

</div>