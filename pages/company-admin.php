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

if (!is_company_admin()) {
    header('Location: /');
    exit();
}

$user      = $_SESSION['user'];
$companyId = (int)($user['company_id'] ?? 0);
$userRole  = $user['role_name'] ?? '';

// ── View routing ──────────────────────────────────────────────────────────────
$view = $_GET['view'] ?? 'users';
$allowedViews = ['users', 'add-user', 'edit-user', 'structure'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'users';
}

$companyCtx  = get_company_context($conn, $companyId);
$isJobBased  = ($companyCtx['type'] === 'job_based');
$allowedRoles = get_allowed_roles_for_company($conn);

$successMessage = '';
$errorMessage   = '';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check($errorMessage)) {
        // ── A: Create new user ────────────────────────────────────────────────
        if ($view === 'add-user') {
            $firstName  = trim($_POST['first_name'] ?? '');
            $lastName   = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $position   = trim($_POST['employee_position'] ?? '');
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
            } else {
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
                            "INSERT INTO users (first_name, last_name, email, password, employee_position, role_id)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->bind_param("sssssi", $firstName, $lastName, $email, $hashedPassword, $position, $roleId);
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
            $roleId     = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
            $newPassword = $_POST['new_password'] ?? '';

            if (!$editUserId || empty($firstName) || empty($lastName) || empty($email) || !$roleId) {
                $errorMessage = "Please fill out all required fields.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errorMessage = "Invalid email address format.";
            } elseif (!role_is_company_assignable($conn, $roleId)) {
                $errorMessage = "The selected role cannot be assigned at the company level.";
            } else {
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
                                "UPDATE users SET first_name=?, last_name=?, email=?, employee_position=?, role_id=?, password=? WHERE id=?"
                            );
                            $stmt->bind_param("ssssisi", $firstName, $lastName, $email, $position, $roleId, $hashed, $editUserId);
                        }
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE users SET first_name=?, last_name=?, email=?, employee_position=?, role_id=? WHERE id=?"
                        );
                        $stmt->bind_param("ssssii", $firstName, $lastName, $email, $position, $roleId, $editUserId);
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
if ($view === 'users') {
    $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position,
                   r.role_name,
                   GROUP_CONCAT(DISTINCT s.store_name SEPARATOR ', ') AS locations
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN user_stores us ON u.id = us.user_id
            LEFT JOIN stores s ON us.store_id = s.id AND s.company_id = ?
            WHERE s.company_id = ?
            GROUP BY u.id
            ORDER BY u.last_name, u.first_name";

    if ($isJobBased) {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position,
                       r.role_name,
                       GROUP_CONCAT(DISTINCT js.job_name SEPARATOR ', ') AS locations
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
                LEFT JOIN job_sites js ON ujs.job_site_id = js.id AND js.company_id = ?
                WHERE js.company_id = ?
                GROUP BY u.id
                ORDER BY u.last_name, u.first_name";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $companyId, $companyId);
    $stmt->execute();
    $companyUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Single user for edit view
$editUser = null;
if ($view === 'edit-user') {
    $editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($editId) {
        if ($isJobBased) {
            $stmt = $conn->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position, u.role_id
                 FROM users u
                 JOIN user_job_sites ujs ON u.id = ujs.user_id
                 JOIN job_sites js ON ujs.job_site_id = js.id
                 WHERE u.id = ? AND js.company_id = ?
                 LIMIT 1"
            );
        } else {
            $stmt = $conn->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.employee_position, u.role_id
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
?>

<div class="flex flex-col md:flex-row gap-6">

    <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
    <aside class="w-full md:w-64 shrink-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-6">

            <!-- Sidebar header -->
            <div class="p-4 bg-purple-700 text-white">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-building text-purple-200"></i>
                    <h3 class="font-bold text-sm uppercase tracking-wider">Company Admin</h3>
                </div>
                <p class="text-xs text-purple-200 truncate"><?php echo htmlspecialchars($companyCtx['company_name']); ?></p>
            </div>

            <nav class="p-2 space-y-1">
                <!-- Users section -->
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 pt-3 pb-1">User Management</p>

                <a href="/company-admin?view=users"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'users') ? 'bg-purple-700 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-users w-6 text-center mr-2"></i> All Users
                </a>

                <a href="/company-admin?view=add-user"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'add-user') ? 'bg-purple-700 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas fa-user-plus w-6 text-center mr-2"></i> Add New User
                </a>

                <!-- Company structure section -->
                <div class="border-t border-gray-100 my-2"></div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-4 pt-1 pb-1">Company</p>

                <a href="/company-admin?view=structure"
                   class="flex items-center px-4 py-3 rounded-lg transition font-medium
                          <?php echo ($view === 'structure') ? 'bg-purple-700 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 hover:text-primary'; ?>">
                    <i class="fas <?php echo htmlspecialchars($companyCtx['location_icon']); ?> w-6 text-center mr-2"></i>
                    <?php echo $isJobBased ? 'Job Sites' : 'Branches'; ?>
                </a>

                <!-- Back to Dashboard -->
                <div class="border-t border-gray-100 my-2"></div>
                <a href="/dashboard" class="flex items-center px-4 py-3 rounded-lg text-gray-500 hover:bg-gray-100 text-sm transition">
                    <i class="fas fa-arrow-left w-6 text-center mr-2"></i> Back to Dashboard
                </a>
            </nav>
        </div>
    </aside>

    <!-- ── Main content ─────────────────────────────────────────────────────── -->
    <main class="flex-1 min-w-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 md:p-8">

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

            // ── VIEW: Users list ───────────────────────────────────────────
            if ($view === 'users'):
            ?>

            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">Users</h2>
                    <p class="text-sm text-gray-500 mt-1">All users within your company workspace.</p>
                </div>
                <a href="/company-admin?view=add-user" class="btn bg-purple-600 text-white hover:bg-purple-700 flex items-center gap-2 shadow">
                    <i class="fas fa-user-plus"></i> Add User
                </a>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 text-xs uppercase tracking-wider font-bold">
                            <tr>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Email</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4"><?php echo $isJobBased ? 'Job Site(s)' : 'Location(s)'; ?></th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($companyUsers)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">
                                    No users found. <a href="/company-admin?view=add-user" class="text-purple-600 font-semibold hover:underline">Add the first user.</a>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($companyUsers as $u): ?>
                                    <tr class="hover:bg-purple-50 transition group">
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
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
                                               class="text-purple-600 hover:text-purple-800 font-bold text-xs uppercase tracking-wide border border-purple-200 rounded px-3 py-1.5 hover:bg-purple-50 transition">
                                                <i class="fas fa-pencil-alt mr-1"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 text-xs text-gray-500">
                    Showing <strong><?php echo count($companyUsers); ?></strong> user(s) in your company
                </div>
            </div>

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
