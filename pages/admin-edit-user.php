<?php
/**
 * Admin Edit User Page - pages/admin-edit-user.php
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-10 — Added company_id scope to both the GET fetch and the POST update.
 *           Previously the page accepted any ?id= and would expose or modify
 *           users belonging to other tenants.
 *           - GET fetch: WHERE u.id = ? AND u.id IN (users belonging to this company)
 *           - POST update: WHERE id = ? AND id IN (same company check)
 *           - Roles dropdown: now uses get_allowed_roles_for_company() which
 *             excludes platform-level roles from the picker.
 *   F-02 — CSRF token verified on POST submission.
 *
 * Company membership is verified by checking user_stores OR user_job_sites
 * depending on the company_type, matching the same dual-path logic used in
 * company-admin.php from Beta 08.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/user_profile_fields.php';

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
    $employeeCode = upf_nullable_string($_POST['employee_code'] ?? null, 50);
    $status      = strtolower(trim($_POST['status'] ?? 'active'));
    $employmentType = upf_nullable_string($_POST['employment_type'] ?? null, 20);
    $department  = upf_nullable_string($_POST['department'] ?? null, 100);
    $phoneNumber = upf_nullable_string($_POST['phone_number'] ?? null, 30);
    $hireDate    = upf_nullable_string($_POST['hire_date'] ?? null, 20);
    $preferredLanguage = upf_nullable_string($_POST['preferred_language'] ?? null, 10);
    $timezone    = upf_nullable_string($_POST['timezone'] ?? null, 50);
    $supervisorUserId = filter_input(INPUT_POST, 'supervisor_user_id', FILTER_VALIDATE_INT) ?: null;
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
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email address format.";
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
        $errorMessage = "Selected supervisor is not in this company.";
    } else {
        if ($hireDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
            $hireDate = null;
        }
        $conn->begin_transaction();

        try {
            // Update user record
            $params = [
                $firstName, $lastName, $email, $position, $roleId,
                $employeeCode, $status, $employmentType, $department, $phoneNumber,
                $hireDate, $preferredLanguage, $timezone, $supervisorUserId
            ];
            $types  = 'ssssissssssssi';
            $sql    = "UPDATE users
                       SET first_name = ?, last_name = ?, email = ?, employee_position = ?, role_id = ?,
                           employee_code = ?, status = ?, employment_type = ?, department = ?, phone_number = ?,
                           hire_date = ?, preferred_language = ?, timezone = ?, supervisor_user_id = ?";

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 8) {
                    $errorMessage = "New password must be at least 8 characters.";
                    throw new Exception('Password policy validation failed.');
                }
                $sql     .= ", password = ?, password_changed_at = NOW()";
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
            if (empty($errorMessage)) {
                $errorMessage = ($conn->errno === 1062)
                    ? "Error: This email address is already in use by another account."
                    : "An error occurred while updating the user.";
            }
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
    $locSql   = "SELECT id, job_name AS location_name
                 FROM job_sites
                 WHERE company_id = ? AND status IN ('Planning', 'Active')
                 ORDER BY job_name ASC";
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
$supervisors = upf_get_supervisor_candidates_by_type($conn, (int)$companyId, $companyType);
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
                <div>
                    <label class="form-label">Employee Code</label>
                    <input type="text" name="employee_code" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['employee_code'] ?? ''); ?>"
                           placeholder="e.g. EMP-1023">
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input appearance-none cursor-pointer">
                        <option value="active" <?php echo (($userToEdit['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (($userToEdit['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo (($userToEdit['status'] ?? '') === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        <option value="terminated" <?php echo (($userToEdit['status'] ?? '') === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Employment Type</label>
                    <select name="employment_type" class="form-input appearance-none cursor-pointer">
                        <option value="">-- Select --</option>
                        <option value="full_time" <?php echo (($userToEdit['employment_type'] ?? '') === 'full_time') ? 'selected' : ''; ?>>Full Time</option>
                        <option value="part_time" <?php echo (($userToEdit['employment_type'] ?? '') === 'part_time') ? 'selected' : ''; ?>>Part Time</option>
                        <option value="contractor" <?php echo (($userToEdit['employment_type'] ?? '') === 'contractor') ? 'selected' : ''; ?>>Contractor</option>
                        <option value="temporary" <?php echo (($userToEdit['employment_type'] ?? '') === 'temporary') ? 'selected' : ''; ?>>Temporary</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['department'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone_number" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['phone_number'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label">Hire Date</label>
                    <input type="date" name="hire_date" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['hire_date'] ?? ''); ?>">
                </div>
                <div>
                    <label class="form-label">Preferred Language</label>
                    <input type="text" name="preferred_language" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['preferred_language'] ?? ''); ?>" placeholder="en or en-CA">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Timezone</label>
                    <input type="text" name="timezone" class="form-input"
                           value="<?php echo htmlspecialchars($userToEdit['timezone'] ?? ''); ?>" placeholder="America/Edmonton">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Supervisor</label>
                    <select name="supervisor_user_id" class="form-input appearance-none cursor-pointer">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($supervisors as $sup): ?>
                            <?php if ((int)$sup['id'] === (int)$editUserId) continue; ?>
                            <option value="<?php echo (int)$sup['id']; ?>" data-location-ids="<?php echo htmlspecialchars($sup['location_ids_csv'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" <?php echo ((int)($userToEdit['supervisor_user_id'] ?? 0) === (int)$sup['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name'] . (!empty($sup['employee_position']) ? ' - ' . $sup['employee_position'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Filtered by selected location.</p>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const locationSelect = document.querySelector('select[name="<?php echo $locationField; ?>"]');
    const supervisorSelect = document.querySelector('select[name="supervisor_user_id"]');
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
