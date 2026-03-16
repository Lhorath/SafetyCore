<?php
/**
 * Analytics & Metrics Dashboard - pages/metrics.php
 *
 * Provides a high-level overview of EHS compliance, pulling data from
 * equipment inventory, pre-shift checklists, and the training matrix.
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */

if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href = '/login';</script>";
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];

// Strictly limit metrics to management roles
if (!in_array($userRole, $managementRoles)) {
    echo "<script>window.location.href = '/dashboard';</script>";
    exit();
}

$userId = (int)$_SESSION['user']['id'];

// Safely derive Company ID
$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    $compSql = "SELECT s.company_id FROM user_stores us JOIN stores s ON us.store_id = s.id WHERE us.user_id = ? LIMIT 1";
    $compStmt = $conn->prepare($compSql);
    $compStmt->bind_param("i", $userId);
    $compStmt->execute();
    $res = $compStmt->get_result()->fetch_assoc();
    $companyId = $res ? $res['company_id'] : 1; 
    $compStmt->close();
}

// ==========================================================
// 1. DATA GATHERING (Safely querying the new modules)
// ==========================================================

// A. Equipment Status Distribution
$eqStatusData = ['Active' => 0, 'Maintenance' => 0, 'Out of Service' => 0];
$eqSql = "SELECT status, COUNT(id) as total FROM equipment WHERE company_id = ? GROUP BY status";
if ($stmt = $conn->prepare($eqSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $eqStatusData[$row['status']] = (int)$row['total'];
    }
    $stmt->close();
}

// B. Pre-Shift Checklist Outcomes (All-time or Last 30 Days)
$preShiftData = ['Safe' => 0, 'Unsafe' => 0];
$psSql = "SELECT overall_status, COUNT(id) as total FROM checklist_submissions WHERE company_id = ? GROUP BY overall_status";
if ($stmt = $conn->prepare($psSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $preShiftData[$row['overall_status']] = (int)$row['total'];
    }
    $stmt->close();
}

// C. Training Matrix Compliance (Valid vs Expired)
$trainingData = ['Valid' => 0, 'Expired' => 0];
$trSql = "
    SELECT 
        SUM(CASE WHEN ut.expiry_date >= CURDATE() OR ut.expiry_date IS NULL THEN 1 ELSE 0 END) as valid_count,
        SUM(CASE WHEN ut.expiry_date < CURDATE() THEN 1 ELSE 0 END) as expired_count
    FROM user_training_records ut
    JOIN users u ON ut.user_id = u.id
    LEFT JOIN user_stores us ON u.id = us.user_id
    LEFT JOIN stores s ON us.store_id = s.id
    WHERE s.company_id = ? OR u.is_platform_admin = 1
";
if ($stmt = $conn->prepare($trSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $trainingData['Valid'] = (int)$res['valid_count'];
        $trainingData['Expired'] = (int)$res['expired_count'];
    }
    $stmt->close();
}

// D. Totals for KPI Cards
$totalEquipment = array_sum($eqStatusData);
$totalPreShifts = array_sum($preShiftData);
$totalOOS = $eqStatusData['Out of Service'] ?? 0;
$totalExpiredCerts = $trainingData['Expired'] ?? 0;

?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    
    <!-- Page Header -->
    <div class="mb-8 border-b-2 border-primary pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-chart-pie text-blue-600 mr-3"></i> Executive Analytics
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Real-time oversight of equipment health, field compliance, and training readiness.</p>
        </div>
        <button onclick="window.print()" class="btn btn-secondary !px-4 !py-2 text-sm shadow-sm flex items-center font-bold">
            <i class="fas fa-print mr-2"></i> Export Report
        </button>
    </div>

    <!-- ==========================================
         TOP KPI CARDS
         ========================================== -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        
        <!-- Card 1: Total Assets -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Assets</p>
                <p class="text-3xl font-black text-slate-800"><?php echo $totalEquipment; ?></p>
            </div>
            <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl">
                <i class="fas fa-boxes"></i>
            </div>
        </div>

        <!-- Card 2: Out of Service -->
        <div class="bg-white rounded-xl shadow-sm border <?php echo $totalOOS > 0 ? 'border-red-200' : 'border-gray-200'; ?> p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Out of Service</p>
                <p class="text-3xl font-black <?php echo $totalOOS > 0 ? 'text-red-600' : 'text-slate-800'; ?>"><?php echo $totalOOS; ?></p>
            </div>
            <div class="w-12 h-12 rounded-full <?php echo $totalOOS > 0 ? 'bg-red-100 text-red-500 animate-pulse' : 'bg-gray-50 text-gray-400'; ?> flex items-center justify-center text-xl">
                <i class="fas fa-ban"></i>
            </div>
        </div>

        <!-- Card 3: Pre-Shift Logs -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Pre-Shift Logs</p>
                <p class="text-3xl font-black text-slate-800"><?php echo $totalPreShifts; ?></p>
            </div>
            <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl">
                <i class="fas fa-clipboard-check"></i>
            </div>
        </div>

        <!-- Card 4: Expired Certs -->
        <div class="bg-white rounded-xl shadow-sm border <?php echo $totalExpiredCerts > 0 ? 'border-orange-200' : 'border-gray-200'; ?> p-6 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Expired Certs</p>
                <p class="text-3xl font-black <?php echo $totalExpiredCerts > 0 ? 'text-orange-500' : 'text-slate-800'; ?>"><?php echo $totalExpiredCerts; ?></p>
            </div>
            <div class="w-12 h-12 rounded-full <?php echo $totalExpiredCerts > 0 ? 'bg-orange-100 text-orange-500' : 'bg-gray-50 text-gray-400'; ?> flex items-center justify-center text-xl">
                <i class="fas fa-user-times"></i>
            </div>
        </div>

    </div>

    <!-- ==========================================
         CHARTS SECTION
         ========================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Chart 1: Equipment Health -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
            <h3 class="text-lg font-bold text-slate-700 mb-4 border-b border-gray-100 pb-2"><i class="fas fa-truck-pickup text-gray-400 mr-2"></i> Equipment Health</h3>
            <div class="flex-grow flex items-center justify-center relative w-full aspect-square max-h-[300px] mx-auto">
                <?php if ($totalEquipment === 0): ?>
                    <p class="text-gray-400 text-sm font-medium">No equipment data available.</p>
                <?php else: ?>
                    <canvas id="eqChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 2: Pre-Shift Safety Ratio -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
            <h3 class="text-lg font-bold text-slate-700 mb-4 border-b border-gray-100 pb-2"><i class="fas fa-check-shield text-gray-400 mr-2"></i> Pre-Shift Outcomes</h3>
            <div class="flex-grow flex items-center justify-center relative w-full aspect-square max-h-[300px] mx-auto">
                <?php if ($totalPreShifts === 0): ?>
                    <p class="text-gray-400 text-sm font-medium">No pre-shift logs available.</p>
                <?php else: ?>
                    <canvas id="psChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chart 3: Training Compliance -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
            <h3 class="text-lg font-bold text-slate-700 mb-4 border-b border-gray-100 pb-2"><i class="fas fa-certificate text-gray-400 mr-2"></i> Training Compliance</h3>
            <div class="flex-grow flex items-center justify-center relative w-full aspect-square max-h-[300px] mx-auto">
                <?php if (($trainingData['Valid'] + $trainingData['Expired']) === 0): ?>
                    <p class="text-gray-400 text-sm font-medium">No training records available.</p>
                <?php else: ?>
                    <canvas id="trChart"></canvas>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- ==========================================
     CHART INITIALIZATION (JS)
     ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Shared Chart.js Options for consistency
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 20, font: { family: "'Inter', sans-serif", weight: 'bold' } } }
        },
        cutout: '65%', // Makes them Doughnut charts
        borderWidth: 0
    };

    // 1. Equipment Chart
    <?php if ($totalEquipment > 0): ?>
    const ctxEq = document.getElementById('eqChart').getContext('2d');
    new Chart(ctxEq, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Maintenance', 'Out of Service'],
            datasets: [{
                data: [
                    <?php echo $eqStatusData['Active'] ?? 0; ?>, 
                    <?php echo $eqStatusData['Maintenance'] ?? 0; ?>, 
                    <?php echo $eqStatusData['Out of Service'] ?? 0; ?>
                ],
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444'], // Tailwind Green-500, Yellow-500, Red-500
                hoverOffset: 4
            }]
        },
        options: commonOptions
    });
    <?php endif; ?>

    // 2. Pre-Shift Outcomes Chart
    <?php if ($totalPreShifts > 0): ?>
    const ctxPs = document.getElementById('psChart').getContext('2d');
    new Chart(ctxPs, {
        type: 'pie', // Using Pie here for variety
        data: {
            labels: ['Safe', 'Unsafe (Flagged)'],
            datasets: [{
                data: [
                    <?php echo $preShiftData['Safe'] ?? 0; ?>, 
                    <?php echo $preShiftData['Unsafe'] ?? 0; ?>
                ],
                backgroundColor: ['#3B82F6', '#EF4444'], // Blue-500, Red-500
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 20, font: { family: "'Inter', sans-serif", weight: 'bold' } } } },
            borderWidth: 0
        }
    });
    <?php endif; ?>

    // 3. Training Compliance Chart
    <?php if (($trainingData['Valid'] + $trainingData['Expired']) > 0): ?>
    const ctxTr = document.getElementById('trChart').getContext('2d');
    new Chart(ctxTr, {
        type: 'doughnut',
        data: {
            labels: ['Valid Certs', 'Expired Certs'],
            datasets: [{
                data: [
                    <?php echo $trainingData['Valid'] ?? 0; ?>, 
                    <?php echo $trainingData['Expired'] ?? 0; ?>
                ],
                backgroundColor: ['#8B5CF6', '#F97316'], // Purple-500, Orange-500
                hoverOffset: 4
            }]
        },
        options: commonOptions
    });
    <?php endif; ?>
});
</script>