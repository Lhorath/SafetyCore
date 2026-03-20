<?php
/**
 * Analytics & Metrics Dashboard - pages/metrics.php
 *
 * Executive-level safety overview for management users.
 *
 * @package   Sentry OHS
 * @version   Version 11.0.0 (sentry ohs launch)
 */

if (!isset($_SESSION['user'])) {
    echo "<script>window.location.href = '/login';</script>";
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];

if (!in_array($userRole, $managementRoles, true)) {
    echo "<script>window.location.href = '/dashboard';</script>";
    exit();
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
$companyId = (int)($_SESSION['user']['company_id'] ?? 0);

if ($companyId <= 0) {
    $companySql = "
        SELECT COALESCE(s.company_id, js.company_id) AS company_id
        FROM users u
        LEFT JOIN user_stores us ON us.user_id = u.id
        LEFT JOIN stores s ON s.id = us.store_id
        LEFT JOIN user_job_sites ujs ON ujs.user_id = u.id
        LEFT JOIN job_sites js ON js.id = ujs.job_site_id
        WHERE u.id = ?
        LIMIT 1
    ";
    if ($companyStmt = $conn->prepare($companySql)) {
        $companyStmt->bind_param("i", $userId);
        $companyStmt->execute();
        $companyRow = $companyStmt->get_result()->fetch_assoc();
        $companyId = (int)($companyRow['company_id'] ?? 0);
        $companyStmt->close();
    }
}

if ($companyId <= 0) {
    echo "<script>window.location.href = '/dashboard';</script>";
    exit();
}

// Equipment status distribution
$eqStatusData = ['Active' => 0, 'Maintenance' => 0, 'Out of Service' => 0];
$eqSql = "SELECT status, COUNT(id) AS total FROM equipment WHERE company_id = ? GROUP BY status";
if ($stmt = $conn->prepare($eqSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $statusKey = $row['status'] ?? '';
        if (array_key_exists($statusKey, $eqStatusData)) {
            $eqStatusData[$statusKey] = (int)$row['total'];
        }
    }
    $stmt->close();
}

// Pre-shift outcomes
$preShiftData = ['Safe' => 0, 'Unsafe' => 0];
$psSql = "SELECT overall_status, COUNT(id) AS total FROM checklist_submissions WHERE company_id = ? GROUP BY overall_status";
if ($stmt = $conn->prepare($psSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $statusKey = $row['overall_status'] ?? '';
        if (array_key_exists($statusKey, $preShiftData)) {
            $preShiftData[$statusKey] = (int)$row['total'];
        }
    }
    $stmt->close();
}

// Training compliance
$trainingData = ['Valid' => 0, 'Expired' => 0];
$trSql = "
    SELECT
        SUM(CASE WHEN ut.expiry_date >= CURDATE() OR ut.expiry_date IS NULL THEN 1 ELSE 0 END) AS valid_count,
        SUM(CASE WHEN ut.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired_count
    FROM user_training_records ut
    JOIN users u ON ut.user_id = u.id
    LEFT JOIN user_stores us ON u.id = us.user_id
    LEFT JOIN stores s ON us.store_id = s.id
    LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
    LEFT JOIN job_sites js ON ujs.job_site_id = js.id
    WHERE s.company_id = ? OR js.company_id = ?
";
if ($stmt = $conn->prepare($trSql)) {
    $stmt->bind_param("ii", $companyId, $companyId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $trainingData['Valid'] = (int)($res['valid_count'] ?? 0);
        $trainingData['Expired'] = (int)($res['expired_count'] ?? 0);
    }
    $stmt->close();
}

// Hazard report health
$hazardStats = ['total' => 0, 'open' => 0, 'under_review' => 0, 'high_risk' => 0];
$hazardSql = "
    SELECT
        COUNT(r.id) AS total,
        SUM(CASE WHEN r.status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN r.status = 'Under Review' THEN 1 ELSE 0 END) AS under_review_count,
        SUM(CASE WHEN r.risk_level = 3 THEN 1 ELSE 0 END) AS high_risk_count
    FROM reports r
    LEFT JOIN stores s ON r.store_id = s.id
    LEFT JOIN job_sites js ON r.job_site_id = js.id
    WHERE s.company_id = ? OR js.company_id = ?
";
if ($stmt = $conn->prepare($hazardSql)) {
    $stmt->bind_param("ii", $companyId, $companyId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $hazardStats['total'] = (int)($res['total'] ?? 0);
        $hazardStats['open'] = (int)($res['open_count'] ?? 0);
        $hazardStats['under_review'] = (int)($res['under_review_count'] ?? 0);
        $hazardStats['high_risk'] = (int)($res['high_risk_count'] ?? 0);
    }
    $stmt->close();
}

// Incident health
$incidentStats = ['total' => 0, 'open' => 0, 'recordable' => 0, 'lost_time' => 0];
$incidentSql = "
    SELECT
        COUNT(id) AS total,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count,
        SUM(CASE WHEN is_recordable = 1 THEN 1 ELSE 0 END) AS recordable_count,
        SUM(CASE WHEN is_lost_time = 1 THEN 1 ELSE 0 END) AS lost_time_count
    FROM incidents
    WHERE company_id = ?
";
if ($stmt = $conn->prepare($incidentSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $incidentStats['total'] = (int)($res['total'] ?? 0);
        $incidentStats['open'] = (int)($res['open_count'] ?? 0);
        $incidentStats['recordable'] = (int)($res['recordable_count'] ?? 0);
        $incidentStats['lost_time'] = (int)($res['lost_time_count'] ?? 0);
    }
    $stmt->close();
}

// FLHA activity
$flhaStats = ['total' => 0, 'open' => 0];
$flhaSql = "
    SELECT
        COUNT(id) AS total,
        SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) AS open_count
    FROM flha_records
    WHERE company_id = ?
";
if ($stmt = $conn->prepare($flhaSql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $flhaStats['total'] = (int)($res['total'] ?? 0);
        $flhaStats['open'] = (int)($res['open_count'] ?? 0);
    }
    $stmt->close();
}

$totalEquipment = array_sum($eqStatusData);
$totalPreShifts = array_sum($preShiftData);
$totalTraining = $trainingData['Valid'] + $trainingData['Expired'];

$equipmentActiveRate = $totalEquipment > 0 ? round((($eqStatusData['Active'] ?? 0) / $totalEquipment) * 100, 1) : 0;
$unsafeRate = $totalPreShifts > 0 ? round((($preShiftData['Unsafe'] ?? 0) / $totalPreShifts) * 100, 1) : 0;
$trainingValidRate = $totalTraining > 0 ? round(($trainingData['Valid'] / $totalTraining) * 100, 1) : 0;

// Build a 6-month activity window
$monthBuckets = [];
for ($i = 5; $i >= 0; $i--) {
    $dt = new DateTime("first day of -{$i} month");
    $monthBuckets[$dt->format('Y-m')] = [
        'label' => $dt->format('M Y'),
        'checklists' => 0,
        'hazards' => 0,
        'incidents' => 0,
        'flha' => 0,
    ];
}

$activityQueries = [
    'checklists' => [
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
         FROM checklist_submissions
         WHERE company_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY ym",
        "i",
        [$companyId],
    ],
    'hazards' => [
        "SELECT DATE_FORMAT(r.created_at, '%Y-%m') AS ym, COUNT(*) AS total
         FROM reports r
         LEFT JOIN stores s ON r.store_id = s.id
         LEFT JOIN job_sites js ON r.job_site_id = js.id
         WHERE (s.company_id = ? OR js.company_id = ?)
           AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY ym",
        "ii",
        [$companyId, $companyId],
    ],
    'incidents' => [
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
         FROM incidents
         WHERE company_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY ym",
        "i",
        [$companyId],
    ],
    'flha' => [
        "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
         FROM flha_records
         WHERE company_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
         GROUP BY ym",
        "i",
        [$companyId],
    ],
];

foreach ($activityQueries as $seriesName => $config) {
    [$sql, $types, $params] = $config;
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $ym = (string)($row['ym'] ?? '');
            if (isset($monthBuckets[$ym])) {
                $monthBuckets[$ym][$seriesName] = (int)($row['total'] ?? 0);
            }
        }
        $stmt->close();
    }
}

$trendLabels = [];
$trendChecklists = [];
$trendHazards = [];
$trendIncidents = [];
$trendFlha = [];
foreach ($monthBuckets as $bucket) {
    $trendLabels[] = $bucket['label'];
    $trendChecklists[] = $bucket['checklists'];
    $trendHazards[] = $bucket['hazards'];
    $trendIncidents[] = $bucket['incidents'];
    $trendFlha[] = $bucket['flha'];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto py-6 md:py-8 px-4 sm:px-6 lg:px-8 metrics-shell app-page">
    <section class="metrics-hero p-5 md:p-7 mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500 mb-2">Safety Intelligence</p>
                <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 flex items-center gap-3">
                    <i class="fas fa-chart-pie text-secondary"></i>
                    Executive Metrics
                </h2>
                <p class="text-sm md:text-base text-slate-600 mt-2">
                    A consolidated view of asset health, front-line reporting, and workforce readiness.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    <i class="fas fa-calendar-alt mr-1.5"></i> Last 6 months trend
                </span>
                <button onclick="window.print()" class="btn btn-secondary !px-4 !py-2 text-sm font-semibold">
                    <i class="fas fa-print mr-2"></i> Export Report
                </button>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Total Assets</p>
            <p class="text-3xl font-extrabold text-slate-900"><?php echo number_format($totalEquipment); ?></p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($equipmentActiveRate, 1); ?>% currently active</p>
        </article>
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Open Hazards</p>
            <p class="text-3xl font-extrabold <?php echo $hazardStats['open'] > 0 ? 'text-orange-600' : 'text-slate-900'; ?>">
                <?php echo number_format($hazardStats['open']); ?>
            </p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($hazardStats['high_risk']); ?> high-risk reports</p>
        </article>
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Open Incidents</p>
            <p class="text-3xl font-extrabold <?php echo $incidentStats['open'] > 0 ? 'text-red-600' : 'text-slate-900'; ?>">
                <?php echo number_format($incidentStats['open']); ?>
            </p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($incidentStats['recordable']); ?> recordable incidents</p>
        </article>
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Unsafe Pre-Shift Rate</p>
            <p class="text-3xl font-extrabold <?php echo $unsafeRate > 0 ? 'text-amber-600' : 'text-slate-900'; ?>">
                <?php echo number_format($unsafeRate, 1); ?>%
            </p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($totalPreShifts); ?> inspections submitted</p>
        </article>
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Training Valid Rate</p>
            <p class="text-3xl font-extrabold <?php echo $trainingData['Expired'] > 0 ? 'text-indigo-700' : 'text-slate-900'; ?>">
                <?php echo number_format($trainingValidRate, 1); ?>%
            </p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($trainingData['Expired']); ?> expired certifications</p>
        </article>
        <article class="metrics-kpi-card p-5">
            <p class="text-xs uppercase tracking-wider font-bold text-slate-500 mb-1">Open FLHAs</p>
            <p class="text-3xl font-extrabold <?php echo $flhaStats['open'] > 0 ? 'text-blue-700' : 'text-slate-900'; ?>">
                <?php echo number_format($flhaStats['open']); ?>
            </p>
            <p class="text-xs mt-2 text-slate-500"><?php echo number_format($flhaStats['total']); ?> total FLHA records</p>
        </article>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-5">
        <article class="metrics-chart-card p-5 md:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-bold text-slate-900"><i class="fas fa-truck-pickup text-slate-400 mr-2"></i>Equipment Health</h3>
                <span class="text-xs font-semibold text-slate-500"><?php echo number_format($totalEquipment); ?> assets</span>
            </div>
            <div class="relative h-72">
                <?php if ($totalEquipment === 0): ?>
                    <p class="text-sm text-slate-400 mt-20 text-center">No equipment records available.</p>
                <?php else: ?>
                    <canvas id="eqChart"></canvas>
                <?php endif; ?>
            </div>
        </article>

        <article class="metrics-chart-card p-5 md:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-bold text-slate-900"><i class="fas fa-clipboard-check text-slate-400 mr-2"></i>Pre-Shift Outcomes</h3>
                <span class="text-xs font-semibold text-slate-500"><?php echo number_format($totalPreShifts); ?> checks</span>
            </div>
            <div class="relative h-72">
                <?php if ($totalPreShifts === 0): ?>
                    <p class="text-sm text-slate-400 mt-20 text-center">No checklist submissions available.</p>
                <?php else: ?>
                    <canvas id="psChart"></canvas>
                <?php endif; ?>
            </div>
        </article>

        <article class="metrics-chart-card p-5 md:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-bold text-slate-900"><i class="fas fa-certificate text-slate-400 mr-2"></i>Training Compliance</h3>
                <span class="text-xs font-semibold text-slate-500"><?php echo number_format($totalTraining); ?> records</span>
            </div>
            <div class="relative h-72">
                <?php if ($totalTraining === 0): ?>
                    <p class="text-sm text-slate-400 mt-20 text-center">No training records available.</p>
                <?php else: ?>
                    <canvas id="trChart"></canvas>
                <?php endif; ?>
            </div>
        </article>

        <article class="metrics-chart-card p-5 md:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-lg font-bold text-slate-900"><i class="fas fa-chart-line text-slate-400 mr-2"></i>Monthly Activity Trend</h3>
                <span class="text-xs font-semibold text-slate-500">Hazards, incidents, checks, FLHAs</span>
            </div>
            <div class="relative h-72">
                <canvas id="activityTrendChart"></canvas>
            </div>
        </article>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const commonDoughnutOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 16,
                    boxWidth: 12,
                    font: { family: "'Inter', sans-serif", weight: '600' }
                }
            }
        },
        cutout: '68%'
    };

    <?php if ($totalEquipment > 0): ?>
    new Chart(document.getElementById('eqChart'), {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Maintenance', 'Out of Service'],
            datasets: [{
                data: [
                    <?php echo (int)($eqStatusData['Active'] ?? 0); ?>,
                    <?php echo (int)($eqStatusData['Maintenance'] ?? 0); ?>,
                    <?php echo (int)($eqStatusData['Out of Service'] ?? 0); ?>
                ],
                backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                borderWidth: 0
            }]
        },
        options: commonDoughnutOptions
    });
    <?php endif; ?>

    <?php if ($totalPreShifts > 0): ?>
    new Chart(document.getElementById('psChart'), {
        type: 'doughnut',
        data: {
            labels: ['Safe', 'Unsafe'],
            datasets: [{
                data: [
                    <?php echo (int)($preShiftData['Safe'] ?? 0); ?>,
                    <?php echo (int)($preShiftData['Unsafe'] ?? 0); ?>
                ],
                backgroundColor: ['#2563EB', '#F59E0B'],
                borderWidth: 0
            }]
        },
        options: commonDoughnutOptions
    });
    <?php endif; ?>

    <?php if ($totalTraining > 0): ?>
    new Chart(document.getElementById('trChart'), {
        type: 'doughnut',
        data: {
            labels: ['Valid', 'Expired'],
            datasets: [{
                data: [
                    <?php echo (int)($trainingData['Valid'] ?? 0); ?>,
                    <?php echo (int)($trainingData['Expired'] ?? 0); ?>
                ],
                backgroundColor: ['#6366F1', '#FB923C'],
                borderWidth: 0
            }]
        },
        options: commonDoughnutOptions
    });
    <?php endif; ?>

    new Chart(document.getElementById('activityTrendChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [
                {
                    label: 'Pre-Shift Checks',
                    data: <?php echo json_encode($trendChecklists); ?>,
                    backgroundColor: '#2563EB'
                },
                {
                    label: 'Hazard Reports',
                    data: <?php echo json_encode($trendHazards); ?>,
                    backgroundColor: '#F59E0B'
                },
                {
                    label: 'Incidents',
                    data: <?php echo json_encode($trendIncidents); ?>,
                    backgroundColor: '#EF4444'
                },
                {
                    label: 'FLHAs',
                    data: <?php echo json_encode($trendFlha); ?>,
                    backgroundColor: '#14B8A6'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            borderRadius: 6,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 14,
                        boxWidth: 10,
                        font: { family: "'Inter', sans-serif", weight: '600' }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#475569' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#475569' },
                    grid: { color: 'rgba(148, 163, 184, 0.2)' }
                }
            }
        }
    });
});
</script>