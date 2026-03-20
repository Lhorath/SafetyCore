<?php
/**
 * User Dashboard - pages/dashboard.php
 *
 * Clean, permission-driven dashboard where all module links are rendered from
 * role_module_permissions via includes/permissions.php.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @version   Version 11.0.0 (sentry ohs launch)
 */

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

require_once __DIR__ . '/../includes/permissions.php';

$user = $_SESSION['user'];
$userId = (int)$user['id'];
$userRole = $user['role_name'] ?? 'Employee';

$modulesByArea = get_user_modules($conn);
$employeeModules = $modulesByArea[AREA_EMPLOYEE] ?? [];
$companyAdminModules = $modulesByArea[AREA_COMPANY_ADMIN] ?? [];
$platformAdminModules = $modulesByArea[AREA_PLATFORM_ADMIN] ?? [];

$totalAccessibleModules = count($employeeModules) + count($companyAdminModules) + count($platformAdminModules);

$myHazardCount = 0;
$myOpenFlhaCount = 0;

$hazardSql = "SELECT COUNT(*) AS total FROM reports WHERE reporter_user_id = ?";
if ($stmt = $conn->prepare($hazardSql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $myHazardCount = (int)$row['total'];
    }
    $stmt->close();
}

$flhaSql = "SELECT COUNT(*) AS total FROM flha_records WHERE creator_user_id = ? AND status = 'Open'";
if ($stmt = $conn->prepare($flhaSql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $myOpenFlhaCount = (int)$row['total'];
    }
    $stmt->close();
}

$moduleDirectory = [
    [
        'name' => 'Report a Hazard',
        'description' => 'Submit hazards and near misses from the field.',
        'route' => '/hazard-report',
        'icon_class' => 'fa-exclamation-triangle',
        'module_key' => 'hazard_report',
    ],
    [
        'name' => 'Report an Incident',
        'description' => 'Capture injuries, incidents, and property damage.',
        'route' => '/incident-report',
        'icon_class' => 'fa-ambulance',
        'module_key' => 'incident_report',
    ],
    [
        'name' => 'FLHA Hub',
        'description' => 'Start and manage daily FLHAs.',
        'route' => '/flha-list',
        'icon_class' => 'fa-clipboard-check',
        'module_key' => 'daily_flha',
    ],
    [
        'name' => 'My Reports',
        'description' => 'Review your submitted reports and statuses.',
        'route' => '/my-reports',
        'icon_class' => 'fa-folder-open',
        'module_key' => 'my_history',
    ],
    [
        'name' => 'Meetings & Talks',
        'description' => 'Run toolbox talks and safety meetings.',
        'route' => '/meetings-list',
        'icon_class' => 'fa-users',
        'module_key' => 'meetings_talks',
    ],
    [
        'name' => 'Executive Metrics',
        'description' => 'Track executive KPIs, risk signals, and safety trends.',
        'route' => '/metrics',
        'icon_class' => 'fa-chart-line',
        'module_key' => 'metrics_stats',
    ],
    [
        'name' => 'Hazard Reviews',
        'description' => 'Review and action location-based hazards.',
        'route' => '/store-reports',
        'icon_class' => 'fa-store',
        'module_key' => 'location_hazards',
    ],
    [
        'name' => 'Incident Reviews',
        'description' => 'Classify and investigate incident records.',
        'route' => '/store-incidents',
        'icon_class' => 'fa-file-medical-alt',
        'module_key' => 'manage_incidents',
    ],
    [
        'name' => 'Training Matrix',
        'description' => 'Track certifications, expiry dates, and compliance.',
        'route' => '/training-matrix',
        'icon_class' => 'fa-certificate',
    ],
    [
        'name' => 'Equipment Management',
        'description' => 'Manage assets, checks, and equipment status.',
        'route' => '/equipment-management',
        'icon_class' => 'fa-truck-pickup',
    ],
    [
        'name' => 'Checklist Builder',
        'description' => 'Build dynamic pre-shift checklist templates.',
        'route' => '/checklist-builder',
        'icon_class' => 'fa-list-check',
    ],
    [
        'name' => 'Pre-Shift Checklist',
        'description' => 'Complete pre-shift equipment inspections.',
        'route' => '/preshift-checklist',
        'icon_class' => 'fa-clipboard-list',
    ],
    [
        'name' => 'Company Admin',
        'description' => 'Manage users and company structure.',
        'route' => '/company-admin?view=users',
        'icon_class' => 'fa-users-cog',
        'any_module_keys' => ['company_users', 'company_structure'],
    ],
    [
        'name' => 'Platform Admin',
        'description' => 'Access system-level administration controls.',
        'route' => '/admin',
        'icon_class' => 'fa-cogs',
        'module_key' => 'platform_admin',
    ],
    [
        'name' => 'My Profile',
        'description' => 'Update account details and preferences.',
        'route' => '/profile',
        'icon_class' => 'fa-user-cog',
        'module_key' => 'my_profile',
    ],
];

$visibleDirectoryModules = [];
foreach ($moduleDirectory as $directoryModule) {
    $showModule = true;

    if (!empty($directoryModule['module_key'])) {
        $showModule = can_access_module($conn, $directoryModule['module_key']);
    }

    if (!empty($directoryModule['any_module_keys']) && is_array($directoryModule['any_module_keys'])) {
        $showModule = false;
        foreach ($directoryModule['any_module_keys'] as $moduleKey) {
            if (can_access_module($conn, $moduleKey)) {
                $showModule = true;
                break;
            }
        }
    }

    if ($showModule) {
        $visibleDirectoryModules[] = $directoryModule;
    }
}

?>

<div class="max-w-7xl mx-auto py-5 md:py-8 space-y-6 md:space-y-8 dashboard-shell app-page">
    <section class="dashboard-hero-card p-5 md:p-8 text-slate-900">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <p class="dashboard-section-label mb-2">Safety Operations Dashboard</p>
                <h1 class="text-2xl md:text-4xl font-extrabold tracking-tight mb-2 text-slate-900">
                    Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>
                </h1>
                <p class="text-slate-600">
                    <?php echo htmlspecialchars($user['employee_position'] ?? 'Employee'); ?> 
                    <span class="mx-2 text-slate-400 hidden sm:inline">|</span>
                    <span class="font-semibold text-primary"><?php echo htmlspecialchars($userRole); ?></span>
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                <div class="dashboard-kpi-tile p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-bold mb-1">My Reports</p>
                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $myHazardCount; ?></p>
                </div>
                <div class="dashboard-kpi-tile p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-bold mb-1">Open FLHAs</p>
                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $myOpenFlhaCount; ?></p>
                </div>
                <div class="dashboard-kpi-tile p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-slate-500 font-bold mb-1">Available Tools</p>
                    <p class="text-2xl font-extrabold text-slate-900"><?php echo $totalAccessibleModules; ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-hero-card p-4 md:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h2 class="text-lg font-bold text-slate-900">Quick Actions</h2>
            <p class="dashboard-section-label">Essentials</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <?php if (can_access_module($conn, 'hazard_report')): ?>
                <a href="/hazard-report" class="dashboard-quick-link !px-4 !py-2.5 text-sm w-full"><i class="fas fa-exclamation-triangle"></i>Report Hazard</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'incident_report')): ?>
                <a href="/incident-report" class="dashboard-quick-link !px-4 !py-2.5 text-sm w-full"><i class="fas fa-ambulance"></i>Report Incident</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'daily_flha')): ?>
                <a href="/flha-form" class="dashboard-quick-link !px-4 !py-2.5 text-sm w-full"><i class="fas fa-clipboard-check"></i>Start FLHA</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($visibleDirectoryModules)): ?>
        <section class="dashboard-hero-card p-5 md:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="text-lg font-bold text-slate-900">All Modules</h2>
                <p class="dashboard-section-label">Single Navigation Hub</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4">
                <?php foreach ($visibleDirectoryModules as $module): ?>
                    <a href="<?php echo htmlspecialchars($module['route']); ?>" class="dashboard-hub-card p-4 flex gap-3 items-start">
                        <span class="dashboard-shield-icon shrink-0">
                            <i class="fas <?php echo htmlspecialchars($module['icon_class']); ?>"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-slate-900"><?php echo htmlspecialchars($module['name']); ?></span>
                            <span class="block mt-1 text-xs text-slate-500 leading-relaxed"><?php echo htmlspecialchars($module['description']); ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>