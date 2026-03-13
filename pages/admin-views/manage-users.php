<?php
/**
 * Admin View: Manage Users - pages/admin-views/manage-users.php
 *
 * This file is only reachable after admin.php's is_platform_admin() gate,
 * so we can safely reference platform-admin context here.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

$loggedInUserRole = $_SESSION['user']['role_name'];

// Filter inputs
$filterStoreId   = filter_input(INPUT_GET, 'filter_store',   FILTER_VALIDATE_INT);
$filterCompanyId = filter_input(INPUT_GET, 'filter_company', FILTER_VALIDATE_INT); // F-22

$users = [];

// ── Build query ───────────────────────────────────────────────────────────────
$sql = "SELECT
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            r.role_name,
            GROUP_CONCAT(DISTINCT s.store_name SEPARATOR ', ') AS store_names,
            c.company_name
        FROM users u
        JOIN roles r     ON u.role_id    = r.id
        LEFT JOIN user_stores us ON u.id = us.user_id
        LEFT JOIN stores s       ON us.store_id = s.id
        LEFT JOIN companies c    ON s.company_id = c.id";

$whereClauses = [];
$params       = [];
$types        = '';

// Managers: scope to their own store
if ($loggedInUserRole === 'Manager') {
    $userStoreId    = (int)$_SESSION['user']['store_id'];
    $whereClauses[] = "u.id IN (SELECT user_id FROM user_stores WHERE store_id = ?)";
    $params[]       = $userStoreId;
    $types         .= 'i';
}
// Platform admins: optional store filter (legacy) or new company filter
elseif ($filterCompanyId) {
    // F-22: company-level scope — all stores belonging to this company
    $whereClauses[] = "s.company_id = ?";
    $params[]       = $filterCompanyId;
    $types         .= 'i';
} elseif ($filterStoreId) {
    $whereClauses[] = "u.id IN (SELECT user_id FROM user_stores WHERE store_id = ?)";
    $params[]       = $filterStoreId;
    $types         .= 'i';
}

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(' AND ', $whereClauses);
}

$sql .= " GROUP BY u.id ORDER BY u.last_name ASC, u.first_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// ── F-22: Fetch company list for filter dropdown ──────────────────────────────
$companies = [];
if (is_platform_admin()) {
    $compResult = $conn->query("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name ASC");
    $companies  = $compResult->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="max-w-6xl">

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
                Manage Users
                <?php if ($filterStoreId): ?>
                    <span class="text-gray-400 text-base font-normal ml-2">(Filtered by Store)</span>
                <?php elseif ($filterCompanyId): ?>
                    <span class="text-gray-400 text-base font-normal ml-2">(Filtered by Company)</span>
                <?php elseif (is_platform_admin()): ?>
                    <span class="text-amber-500 text-sm font-semibold ml-2">— All Tenants</span>
                <?php endif; ?>
            </h2>
            <p class="text-sm text-gray-500 mt-2">View and manage employee accounts and permissions.</p>
        </div>

        <?php if ($filterStoreId || $filterCompanyId): ?>
            <a href="/admin?view=manage-users" class="btn btn-secondary text-sm px-4 py-2 flex items-center">
                <i class="fas fa-times mr-2"></i> Clear Filter
            </a>
        <?php endif; ?>
    </div>

    <!-- F-22: Company filter (platform admins only) -->
    <?php if (is_platform_admin() && !empty($companies)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2 text-amber-700 font-semibold text-sm">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Platform View — All Tenant Data Visible</span>
        </div>
        <form method="GET" action="/admin" class="flex items-center gap-2 ml-auto">
            <input type="hidden" name="view" value="manage-users">
            <label for="filter_company" class="text-sm font-medium text-gray-700 whitespace-nowrap">Filter by Company:</label>
            <select name="filter_company" id="filter_company" onchange="this.form.submit()"
                    class="form-input !py-1.5 !px-3 text-sm cursor-pointer w-48">
                <option value="">— All Companies —</option>
                <?php foreach ($companies as $co): ?>
                    <option value="<?php echo $co['id']; ?>"
                        <?php echo ($filterCompanyId == $co['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($co['company_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-4">Name</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Role</th>
                        <th class="px-6 py-4">Assigned Store(s)</th>
                        <?php if (is_platform_admin() && !$filterCompanyId): ?>
                            <th class="px-6 py-4">Company</th>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="fas fa-users-slash text-4xl text-gray-300 mb-3"></i>
                                    <p>No users found matching the current criteria.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-blue-50 transition duration-150 ease-in-out group">
                                <td class="px-6 py-4 font-bold text-gray-900 whitespace-nowrap">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"
                                       class="hover:text-primary hover:underline">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $badgeColor = match($user['role_name']) {
                                            'Admin', 'Owner / CEO'            => 'bg-purple-100 text-purple-800',
                                            'Manager', 'Safety Manager',
                                            'Company Admin'                   => 'bg-blue-100 text-blue-800',
                                            'Safety Leader', 'JHSC Member',
                                            'JHSC Leader', 'Site Supervisor'  => 'bg-green-100 text-green-800',
                                            default                           => 'bg-gray-100 text-gray-800'
                                        };
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $badgeColor; ?> shadow-sm">
                                        <?php echo htmlspecialchars($user['role_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500 max-w-xs truncate">
                                    <?php if ($user['store_names']): ?>
                                        <span title="<?php echo htmlspecialchars($user['store_names']); ?>">
                                            <?php echo htmlspecialchars($user['store_names']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_platform_admin() && !$filterCompanyId): ?>
                                    <td class="px-6 py-4 text-gray-500 text-xs">
                                        <?php echo htmlspecialchars($user['company_name'] ?? '—'); ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <a href="/admin-edit-user?id=<?php echo $user['id']; ?>"
                                       class="text-gray-400 hover:text-secondary font-bold transition duration-200 text-xs uppercase tracking-wide flex justify-end items-center gap-1 group-hover:text-primary">
                                        <i class="fas fa-pencil-alt"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 text-xs text-gray-500 font-medium flex justify-between items-center">
            <span>Showing <strong><?php echo count($users); ?></strong> user(s)</span>
            <?php if ($loggedInUserRole === 'Manager'): ?>
                <span class="text-gray-400 italic flex items-center">
                    <i class="fas fa-lock mr-1 text-xs"></i> Restricted to your location
                </span>
            <?php elseif (is_platform_admin() && !$filterCompanyId): ?>
                <span class="text-amber-600 italic flex items-center font-semibold">
                    <i class="fas fa-eye mr-1 text-xs"></i> Showing all tenants — use filter above to scope
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
