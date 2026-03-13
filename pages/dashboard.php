<?php
/**
 * Main User Dashboard - pages/dashboard.php
 *
 * Beta 08 Redesign:
 * - Module tiles are now fully DB-driven via the modules + role_module_permissions
 *   tables. No role names are hardcoded in this file.
 * - Three distinct sections:
 *     1. My Tools          — employee-level modules (all users)
 *     2. Company Administration — management modules (management roles)
 *     3. Platform Admin    — system-level modules (platform Admin only, system co.)
 * - Platform Admin section is ONLY visible when is_system = true in session
 *   (double-gated in get_user_modules() in permissions.php).
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   8.0.0 (NorthPoint Beta 08)
 */

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

require_once __DIR__ . '/../includes/permissions.php';

$user      = $_SESSION['user'];
$firstName = htmlspecialchars($user['first_name']);
$userRole  = htmlspecialchars($user['role_name'] ?? 'User');
$companyName = htmlspecialchars($user['company_name'] ?? '');

// Fetch modules grouped by area (DB-driven, cached for this request)
$modules = get_user_modules($conn);

$employeeModules  = $modules['employee']       ?? [];
$companyModules   = $modules['company_admin']  ?? [];
$platformModules  = $modules['platform_admin'] ?? [];

$showCompanyAdmin = !empty($companyModules);
$showPlatformAdmin = !empty($platformModules) && is_platform_admin();

/**
 * Renders a single module tile.
 * Accepts a row from the modules table.
 */
function render_module_tile(array $mod): void {
    $name     = htmlspecialchars($mod['module_name']);
    $desc     = htmlspecialchars($mod['description'] ?? '');
    $iconCls  = htmlspecialchars($mod['icon_class']);
    $iconBg   = htmlspecialchars($mod['icon_bg']);
    $iconClr  = htmlspecialchars($mod['icon_color']);
    $btnCls   = htmlspecialchars($mod['btn_class']);
    $btnLabel = htmlspecialchars($mod['btn_label']);
    $route    = htmlspecialchars($mod['route']);
    echo <<<HTML
    <div class="card bg-white border border-gray-100 hover:shadow-xl transition duration-300 flex flex-col items-center text-center h-full p-8 group">
        <div class="h-16 w-16 {$iconBg} {$iconClr} rounded-2xl flex items-center justify-center text-3xl mb-6 mx-auto group-hover:scale-110 transition-transform duration-300">
            <i class="fas {$iconCls}"></i>
        </div>
        <h3 class="text-xl font-bold text-primary mb-2">{$name}</h3>
        <p class="text-sm text-gray-500 mb-6 flex-grow">{$desc}</p>
        <a href="{$route}" class="btn {$btnCls} w-full group-hover:-translate-y-0.5 transform transition-all shadow-md">{$btnLabel}</a>
    </div>
    HTML;
}
?>

<div class="max-w-7xl mx-auto">

    <!-- ── Welcome Header ─────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 mb-8 flex flex-col md:flex-row items-center justify-between gap-6 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full transform translate-x-1/2 -translate-y-1/2 opacity-50 pointer-events-none"></div>
        <div class="relative z-10 text-center md:text-left">
            <h1 class="text-3xl font-extrabold text-primary mb-1">Welcome back, <?php echo $firstName; ?>!</h1>
            <?php if ($companyName): ?>
                <p class="text-secondary font-semibold text-sm mb-1">
                    <i class="fas fa-building mr-1 opacity-60"></i><?php echo $companyName; ?>
                </p>
            <?php endif; ?>
            <p class="text-gray-500 text-sm">Your central command for workplace safety and compliance.</p>
        </div>
        <div class="relative z-10 flex flex-col items-center md:items-end gap-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Current Role</span>
            <span class="px-4 py-1.5 bg-blue-100 text-secondary font-bold rounded-full border border-blue-200 shadow-sm inline-block">
                <i class="fas fa-id-badge mr-2 opacity-75"></i><?php echo $userRole; ?>
            </span>
            <?php if (is_platform_admin()): ?>
                <span class="px-3 py-1 bg-slate-800 text-yellow-400 text-xs font-bold rounded-full tracking-wider">
                    <i class="fas fa-shield-alt mr-1"></i> PLATFORM ADMIN
                </span>
            <?php endif; ?>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════════════════
         SECTION 1: MY TOOLS — Employee-level modules
         Visible to all logged-in users regardless of role.
         ══════════════════════════════════════════════════════════════════════ -->
    <?php if (!empty($employeeModules)): ?>
    <div class="mb-10">

        <!-- Section Label -->
        <div class="flex items-center gap-3 mb-5">
            <div class="h-8 w-1 bg-secondary rounded-full"></div>
            <h2 class="text-lg font-extrabold text-primary uppercase tracking-wider">My Tools</h2>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <!-- Tiles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($employeeModules as $mod): ?>
                <?php render_module_tile($mod); ?>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════════════════════════════════
         SECTION 2: COMPANY ADMINISTRATION
         Visible to management-level roles within a company.
         Clearly separated from platform admin. Includes:
           - Manage Incidents, Location Hazards, Meetings & Talks,
             Metrics & Stats, Manage Users, Company Structure
         ══════════════════════════════════════════════════════════════════════ -->
    <?php if ($showCompanyAdmin): ?>
    <div class="mb-10">

        <!-- Section Label + badge -->
        <div class="flex items-center gap-3 mb-5">
            <div class="h-8 w-1 bg-purple-500 rounded-full"></div>
            <h2 class="text-lg font-extrabold text-primary uppercase tracking-wider">Company Administration</h2>
            <span class="text-xs bg-purple-100 text-purple-700 font-bold px-2.5 py-1 rounded-full border border-purple-200">
                <?php echo htmlspecialchars($companyName ?: 'Your Company'); ?>
            </span>
            <div class="flex-1 h-px bg-gray-200"></div>
        </div>

        <!-- Context note for Company Admin role -->
        <?php if (($user['role_name'] ?? '') === 'Company Admin'): ?>
            <div class="bg-purple-50 border border-purple-200 rounded-lg px-5 py-3 mb-5 text-sm text-purple-800 flex items-center gap-2">
                <i class="fas fa-info-circle text-purple-500"></i>
                You have Company Administrator access. You can manage users and company structure for
                <strong><?php echo htmlspecialchars($companyName); ?></strong>.
            </div>
        <?php endif; ?>

        <!-- Tiles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($companyModules as $mod): ?>
                <?php render_module_tile($mod); ?>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endif; ?>


    <!-- ══════════════════════════════════════════════════════════════════════
         SECTION 3: PLATFORM ADMINISTRATION
         Only visible when: role = Admin AND company = system (is_system = true).
         This section is intentionally visually distinct — dark themed —
         to reduce accidental use on production tenant data.
         ══════════════════════════════════════════════════════════════════════ -->
    <?php if ($showPlatformAdmin): ?>
    <div class="mb-10">

        <!-- Section Label -->
        <div class="flex items-center gap-3 mb-5">
            <div class="h-8 w-1 bg-slate-600 rounded-full"></div>
            <h2 class="text-lg font-extrabold text-slate-700 uppercase tracking-wider">Platform Administration</h2>
            <span class="text-xs bg-slate-800 text-yellow-400 font-bold px-2.5 py-1 rounded-full">
                <i class="fas fa-shield-alt mr-1"></i> System Access
            </span>
            <div class="flex-1 h-px bg-gray-300"></div>
        </div>

        <div class="bg-amber-50 border border-amber-300 rounded-lg px-5 py-3 mb-5 text-sm text-amber-800 flex items-center gap-2">
            <i class="fas fa-exclamation-triangle text-amber-500"></i>
            These tools operate at the platform level and affect all tenants. Use with caution.
        </div>

        <!-- Tiles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($platformModules as $mod): ?>
                <?php render_module_tile($mod); ?>
            <?php endforeach; ?>
        </div>

    </div>
    <?php endif; ?>


    <!-- Empty state (no modules — shouldn't happen, but safe fallback) -->
    <?php if (empty($employeeModules) && !$showCompanyAdmin && !$showPlatformAdmin): ?>
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-400">
            <i class="fas fa-cube text-4xl mb-4"></i>
            <p class="font-semibold">No modules are currently available for your role.</p>
            <p class="text-sm mt-1">Contact your administrator if you believe this is an error.</p>
        </div>
    <?php endif; ?>

</div>
