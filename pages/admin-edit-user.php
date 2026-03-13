<?php
/**
 * Admin Edit User Page - pages/admin-edit-user.php
 * This page allows platform and company administrators to edit user profiles, including their personal information, role, and location assignment (store or job site).
 * 
 * Company membership is verified by checking user_stores OR user_job_sites
 * depending on the company_type, matching the same dual-path logic used in
 * company-admin.php from Beta 08.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

require_once 'includes/permissions.php';
require_once 'includes/csrf.php';

// ── 1. Auth check ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

// Only platform admins or company admins can reach this page
if (!is_platform_admin() && !is_company_admin()) {
    header('Location: /');
    exit();
}

$companyId   = (int)$_SESSION['user']['company_id'];
$companyType = $_SESSION['user']['company_type'] ?? 'store_based';

// ── Helper: verify a user belongs to this company ─────────────────────────────
function user_belongs_to_company(mysqli $conn, int $editUserId, int $companyId, string $companyType): bool {
    // Platform admins (in system company) can edit anyone
    if (is_platform_admin()) return true;

    if ($companyType === 'job_based') {
        $sql = "SELECT 1
                FROM user_job_sites ujs
                JOIN job_sites js ON ujs.job_site_id = js.id
                WHERE ujs.user_id = ? AND js.company_id = ?
                LIMIT 1";
    } else {
        $sql = "SELECT 1
                FROM user_stores us
                JOIN stores s ON us.store_id = s.id
                WHERE us.user_id = ? AND s.company_id = ?
                LIMIT 1";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $editUserId, $companyId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    return $found;
}

// ── 2. Input Validation ───────────────────────────────────────────────────────
$editUserId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$editUserId) {
    header('Location: /admin?view=manage-users');
    exit();
}

// F-10 FIX: verify ownership before doing anything with the record
if (!user_belongs_to_company($conn, $editUserId, $companyId, $companyType)) {
    header('Location: /admin?view=manage-users');
    exit();
}

$successMessage = '';
$errorMessage   = '';

// ── 3. Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // F-02: CSRF
    if (!csrf_check($errorMessage)) {
        goto fetch_data;
    }

    $firstName   = trim($_POST['first_name']        ?? '');
    $lastName    = trim($_POST['last_name']         ?? '');
    $email       = trim($_POST['email']             ?? '');
    $position    = trim($_POST['employee_position'] ?? '');
    $storeId     = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
    $roleId      = filter_input(INPUT_POST, 'role_id',  FILTER_VALIDATE_INT);
    $newPassword = $_POST['new_password'] ?? '';

    // Re-verify ownership on POST (the GET check above only covers the initial page load)
    if (!user_belongs_to_company($conn, $editUserId, $companyId, $companyType)) {
        header('Location: /admin?view=manage-users');
        exit();
    }

    // F-10 FIX: only allow roles that a company admin is permitted to assign
    if ($roleId && !role_is_company_assignable($conn, $roleId)) {
        $errorMessage = "The selected role cannot be assigned by a company administrator.";
        goto fetch_data;
    }

    if (empty($firstName) || empty($lastName) || empty($email) || !$roleId) {
        $errorMessage = "First Name, Last Name, Email, and Role are required fields.";
    } else {
        $conn->begin_transaction();

        try {
            // Update user record
            $params = [$firstName, $lastName, $email, $position, $roleId];
            $types  = 'ssssi';
            $sql    = "UPDATE users SET first_name = ?, last_name = ?, email = ?, employee_position = ?, role_id = ?";

            if (!empty($newPassword)) {
                $sql     .= ", password = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
                $types   .= 's';
            }

            // F-10 FIX: scope WHERE to prevent updating another tenant's user
            $sql     .= " WHERE id = ?";
            $params[] = $editUserId;
            $types   .= 'i';

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->close();

            // Update location assignment (store or job site)
            if ($companyType === 'job_based') {
                $jobSiteId = filter_input(INPUT_POST, 'job_site_id', FILTER_VALIDATE_INT);
                if ($jobSiteId) {
                    $conn->prepare("DELETE FROM user_job_sites WHERE user_id = ?")
                         ->bind_param("i", $editUserId);
                    // run delete
                    $del = $conn->prepare("DELETE FROM user_job_sites WHERE user_id = ?");
                    $del->bind_param("i", $editUserId);
                    $del->execute();
                    $del->close();

                    $ins = $conn->prepare("INSERT INTO user_job_sites (user_id, job_site_id) VALUES (?, ?)");
                    $ins->bind_param("ii", $editUserId, $jobSiteId);
                    $ins->execute();
                    $ins->close();
                }
            } else {
                if ($storeId) {
                    $del = $conn->prepare("DELETE FROM user_stores WHERE user_id = ?");
                    $del->bind_param("i", $editUserId);
                    $del->execute();
                    $del->close();

                    $ins = $conn->prepare("INSERT INTO user_stores (user_id, store_id) VALUES (?, ?)");
                    $ins->bind_param("ii", $editUserId, $storeId);
                    $ins->execute();
                    $ins->close();
                }
            }

            $conn->commit();
            $successMessage = "User profile updated successfully!";

        } catch (Exception $e) {
            $conn->rollback();
            $errorMessage = ($conn->errno === 1062)
                ? "Error: This email address is already in use by another account."
                : "An error occurred while updating the user.";
        }
    }
}

// ── 4. Fetch data for form ────────────────────────────────────────────────────
fetch_data:

// F-10 FIX: scope user fetch to this company (prevents cross-tenant data exposure)
if (is_platform_admin()) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $editUserId);
} else {
    // Confirm company membership inside the query itself
    if ($companyType === 'job_based') {
        $stmt = $conn->prepare(
            "SELECT u.* FROM users u
             WHERE u.id = ?
               AND EXISTS (
                   SELECT 1 FROM user_job_sites ujs
                   JOIN job_sites js ON ujs.job_site_id = js.id
                   WHERE ujs.user_id = u.id AND js.company_id = ?
               )"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT u.* FROM users u
             WHERE u.id = ?
               AND EXISTS (
                   SELECT 1 FROM user_stores us
                   JOIN stores s ON us.store_id = s.id
                   WHERE us.user_id = u.id AND s.company_id = ?
               )"
        );
    }
    $stmt->bind_param("ii", $editUserId, $companyId);
}

$stmt->execute();
$userToEdit = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userToEdit) {
    header('Location: /admin?view=manage-users');
    exit();
}

// Current location assignment
$currentLocationId = 0;
if ($companyType === 'job_based') {
    $locStmt = $conn->prepare("SELECT job_site_id FROM user_job_sites WHERE user_id = ? LIMIT 1");
    $locStmt->bind_param("i", $editUserId);
    $locStmt->execute();
    $currentLocationId = $locStmt->get_result()->fetch_assoc()['job_site_id'] ?? 0;
    $locStmt->close();
} else {
    $locStmt = $conn->prepare("SELECT store_id FROM user_stores WHERE user_id = ? LIMIT 1");
    $locStmt->bind_param("i", $editUserId);
    $locStmt->execute();
    $currentLocationId = $locStmt->get_result()->fetch_assoc()['store_id'] ?? 0;
    $locStmt->close();
}

// Locations dropdown (scoped to company)
$locations = [];
if ($companyType === 'job_based') {
    $locSql   = "SELECT id, site_name AS location_name FROM job_sites WHERE company_id = ? AND is_active = 1 ORDER BY site_name ASC";
} else {
    $locSql   = "SELECT id, store_name AS location_name FROM stores WHERE company_id = ? ORDER BY store_name ASC";
}
$locStmt = $conn->prepare($locSql);
$locStmt->bind_param("i", $companyId);
$locStmt->execute();
$locations = $locStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$locStmt->close();

// F-10 FIX: use get_allowed_roles_for_company() — excludes platform Admin role
$roles = get_allowed_roles_for_company($conn);

$locationLabel    = ($companyType === 'job_based') ? 'Job Site'    : 'Store';
$locationField    = ($companyType === 'job_based') ? 'job_site_id' : 'store_id';
$locationPlaceholder = ($companyType === 'job_based') ? '-- Select Job Site --' : '-- Select Store --';
?>

<div class="max-w-4xl mx-auto">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-primary">
            Edit User: <span class="text-secondary"><?php echo htmlspecialchars($userToEdit['first_name'] . ' ' . $userToEdit['last_name']); ?></span>
        </h2>
        <a href="/admin?view=manage-users" class="text-gray-500 hover:text-primary transition flex items-center text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back to List
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <div><p class="font-bold">Success</p><p><?php echo htmlspecialchars($successMessage); ?></p></div>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <div><p class="font-bold">Error</p><p><?php echo htmlspecialchars($errorMessage); ?></p></div>
        </div>
    <?php endif; ?>

    <form action="/admin-edit-user?id=<?php echo $editUserId; ?>" method="POST" class="space-y-8">

        <!-- F-02: CSRF token -->
        <?php csrf_field(); ?>

        <!-- Profile Information -->
        <div class="card">
            <h3 class="text-xl font-bold text-primary mb-6 border-b border-gray-100 pb-2">Profile Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['first_name']); ?>" required>
                </div>
                <div>
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['last_name']); ?>" required>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['email']); ?>" required>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Employee Position</label>
                    <input type="text" name="employee_position" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['employee_position'] ?? ''); ?>"
                           placeholder="e.g. Sales Associate">
                </div>
            </div>
        </div>

        <!-- Assignment -->
        <div class="card">
            <h3 class="text-xl font-bold text-primary mb-6 border-b border-gray-100 pb-2">Assignment</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label"><?php echo $locationLabel; ?></label>
                    <div class="relative">
                        <select name="<?php echo $locationField; ?>" class="form-input appearance-none cursor-pointer">
                            <option value=""><?php echo $locationPlaceholder; ?></option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>"
                                    <?php echo ($currentLocationId == $loc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['location_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Assigning a new location will replace the current assignment.</p>
                </div>
                <div>
                    <label class="form-label">User Role</label>
                    <div class="relative">
                        <select name="role_id" class="form-input appearance-none cursor-pointer" required>
                            <option value="">-- Select Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    <?php echo ($userToEdit['role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                    <?php if (!is_platform_admin()): ?>
                        <p class="text-xs text-gray-400 mt-2">Platform administrator roles are not shown.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Password Reset -->
        <div class="card border-l-4 border-l-secondary">
            <h3 class="text-xl font-bold text-primary mb-2">Reset Password</h3>
            <p class="text-sm text-gray-500 mb-6">Enter a new password to reset it for this user. Leave blank to keep the current password.</p>
            <div>
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-input"
                       autocomplete="new-password" placeholder="Leave blank to keep current password">
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-4 pt-4 pb-12">
            <a href="/admin?view=manage-users" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all">
                Save Changes
            </button>
        </div>

    </form>
</div>
