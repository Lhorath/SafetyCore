<?php
/**
 * My Reports - pages/my-reports.php
 *
 * @package   Sentry OHS
 * @version   Version 11.0.0 (sentry ohs launch)
 */

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userId = (int)$_SESSION['user']['id'];

// FIX: Changed 'locations' to 'hazard_locations' to match the database schema
$reportsSql = "SELECT r.id, r.hazard_type, r.status, r.created_at, l.location_name
               FROM reports r
               JOIN hazard_locations l ON r.hazard_location_id = l.id
               WHERE r.reporter_user_id = ?
               ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reportsSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="max-w-7xl mx-auto py-8 app-page reports-shell">
    <div class="app-page-header">
        <div class="app-page-heading">
            <h2 class="app-page-title text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-history text-secondary mr-3"></i> My Hazard Reports
            </h2>
            <p class="app-page-subtitle text-base text-gray-500 mt-2 font-medium">Review the status and details of the hazards you have submitted.</p>
        </div>
    </div>

    <div class="reports-surface bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden grid grid-cols-1 lg:grid-cols-3">
        
        <!-- Report List -->
        <div class="lg:col-span-1 border-r border-gray-200 bg-white flex flex-col relative z-10">
            <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="font-bold text-primary text-sm uppercase tracking-wider"><i class="fas fa-list-ul mr-2 text-gray-400"></i> History</h3>
                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-2 py-1 rounded shadow-sm"><?php echo count($reports); ?> Total</span>
            </div>
            <div class="report-list-selectable divide-y divide-gray-100 max-h-[600px] overflow-y-auto custom-scrollbar">
                <?php if (empty($reports)): ?>
                    <div class="p-8 text-center text-gray-400">
                        <i class="fas fa-clipboard-check text-4xl mb-3 opacity-50"></i>
                        <p class="font-medium text-sm">You haven't reported any hazards yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reports as $report): 
                        $badgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
                        if ($report['status'] === 'Open') $badgeClass = 'bg-red-100 text-red-800 border-red-200 animate-pulse';
                        if ($report['status'] === 'In Progress') $badgeClass = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                        if ($report['status'] === 'Closed') $badgeClass = 'bg-green-100 text-green-800 border-green-200';
                    ?>
                        <div class="report-item p-4 hover:bg-slate-50 cursor-pointer transition-colors relative" data-report-id="<?php echo $report['id']; ?>">
                            <div class="absolute left-0 top-0 bottom-0 bg-secondary transition-all w-0"></div>
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-primary"><?php echo htmlspecialchars($report['hazard_type']); ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold shadow-sm border <?php echo $badgeClass; ?>"><?php echo $report['status']; ?></span>
                            </div>
                            <span class="block text-gray-500 text-xs font-medium"><i class="fas fa-map-marker-alt mr-1 text-gray-400"></i> <?php echo htmlspecialchars($report['location_name']); ?></span>
                            <span class="block text-gray-500 text-xs mt-1 font-medium"><i class="fas fa-calendar-alt mr-1 text-gray-400"></i> <?php echo date('M d, Y - h:i A', strtotime($report['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Report Viewer -->
        <div class="report-viewer-pane lg:col-span-2 bg-slate-50 relative overflow-y-auto custom-scrollbar min-h-[500px]">
            <div id="reportViewer" class="report-viewer-pane h-full relative">
                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="fas fa-search text-5xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium">Select a report to view details</p>
                </div>
            </div>
        </div>

    </div>
</div>