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

function render_module_card(array $module): void {
    $name = htmlspecialchars($module['module_name'] ?? 'Module');
    $desc = htmlspecialchars($module['description'] ?? 'Open this module.');
    $route = htmlspecialchars($module['route'] ?? '/dashboard');
    $label = htmlspecialchars($module['btn_label'] ?? 'Open');
    $icon = htmlspecialchars($module['icon_class'] ?? 'fa-circle');
    ?>
    <article class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all flex flex-col">
        <div class="dashboard-shield-icon mb-4">
            <i class="fas <?php echo $icon; ?>"></i>
        </div>
        <h3 class="text-lg font-bold text-primary mb-2"><?php echo $name; ?></h3>
        <p class="text-sm text-slate-500 leading-relaxed mb-5 flex-grow"><?php echo $desc; ?></p>
        <a href="<?php echo $route; ?>" class="btn btn-primary w-full !px-4 !py-2 text-sm"><?php echo $label; ?></a>
    </article>
    <?php
}
?>

<style>
    .dashboard-shield-icon {
        width: 3rem;
        height: 3rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-image: url('/style/images/shield.png');
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
        color: #ffffff;
        font-size: 1.15rem;
        line-height: 1;
    }

    .dashboard-shield-icon i {
        display: inline-block;
        transform: scale(0.9);
        transform-origin: center;
    }

</style>

<div class="max-w-7xl mx-auto py-8 space-y-8">
    <section class="bg-gradient-to-r from-primary via-slate-900 to-primary rounded-2xl p-8 text-white shadow-lg">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div>
                <p class="uppercase tracking-widest text-[11px] text-blue-200 font-bold mb-2">User Dashboard</p>
                <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-2">
                    Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>
                </h1>
                <p class="text-slate-300">
                    <?php echo htmlspecialchars($user['employee_position'] ?? 'Employee'); ?> 
                    <span class="mx-2 text-slate-500">|</span>
                    <span class="font-semibold text-blue-200"><?php echo htmlspecialchars($userRole); ?></span>
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto">
                <div class="bg-white/10 border border-white/10 rounded-xl p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-blue-200 font-bold mb-1">My Reports</p>
                    <p class="text-2xl font-extrabold"><?php echo $myHazardCount; ?></p>
                </div>
                <div class="bg-white/10 border border-white/10 rounded-xl p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-blue-200 font-bold mb-1">Open FLHAs</p>
                    <p class="text-2xl font-extrabold"><?php echo $myOpenFlhaCount; ?></p>
                </div>
                <div class="bg-white/10 border border-white/10 rounded-xl p-4 min-w-[160px]">
                    <p class="text-xs uppercase tracking-widest text-blue-200 font-bold mb-1">Available Tools</p>
                    <p class="text-2xl font-extrabold"><?php echo $totalAccessibleModules; ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-primary">Quick Actions</h2>
            <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Permission Aware</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <?php if (can_access_module($conn, 'hazard_report')): ?>
                <a href="/hazard-report" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-exclamation-triangle mr-2"></i>Report Hazard</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'incident_report')): ?>
                <a href="/incident-report" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-ambulance mr-2"></i>Report Incident</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'daily_flha')): ?>
                <a href="/flha-form" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-clipboard-check mr-2"></i>Start FLHA</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'meetings_talks')): ?>
                <a href="/host-meeting" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-users mr-2"></i>Host Meeting</a>
            <?php endif; ?>
            <?php if (can_access_module($conn, 'metrics_stats')): ?>
                <a href="/metrics" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-chart-pie mr-2"></i>View Metrics</a>
            <?php endif; ?>
            <a href="/profile" class="btn btn-secondary !px-4 !py-2 text-sm"><i class="fas fa-user-cog mr-2"></i>My Profile</a>
        </div>
    </section>

    <?php if (!empty($employeeModules)): ?>
        <section>
            <div class="flex items-center mb-4">
                <h2 class="text-lg font-bold text-primary">My Workspace</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($employeeModules as $module): ?>
                    <?php render_module_card($module); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($companyAdminModules)): ?>
        <section>
            <div class="flex items-center mb-4">
                <h2 class="text-lg font-bold text-primary">Management Workspace</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($companyAdminModules as $module): ?>
                    <?php render_module_card($module); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($platformAdminModules)): ?>
        <section>
            <div class="flex items-center mb-4">
                <h2 class="text-lg font-bold text-primary">Platform Administration</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($platformAdminModules as $module): ?>
                    <?php render_module_card($module); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>