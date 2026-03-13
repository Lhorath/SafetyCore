<?php
/**
 * Store Hazard Reports Dashboard - pages/store-reports.php
 *
 * This page serves as an interactive management dashboard for authorized users 
 * (Admins, Managers, Safety Leaders) to view, filter, inspect, and close 
 * safety hazard reports for a specific store location.
 *
 * Updates in Beta 04:
 * - Expanded main container to max-w-7xl for improved dual-pane viewing.
 * - Solidified the Close Report modal structure and UX flow.
 * - Confirmed RBAC permissions list for management actions.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   2.5.0 (NorthPoint Beta 04)
 */

// --- 1. Security & Access Control ---

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';
// Expanded list of leadership/supervisory roles allowed to view and manage this dashboard
$allowedRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];

if (!in_array($userRole, $allowedRoles)) {
    header('Location: /');
    exit();
}

// --- 2. Initial Data Fetch ---
$companyId = $_SESSION['user']['company_id'] ?? 1;
$stores = [];
$sql = "SELECT id, store_name, store_number FROM stores WHERE company_id = ? ORDER BY store_name ASC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
    $stmt->close();
}
?>

<!-- Close Report Modal (Hidden by default) -->
<div id="closeReportModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-3">
            <h2 class="text-xl font-bold text-accent-red flex items-center">
                <i class="fas fa-clipboard-check mr-2"></i> Close Hazard Report
            </h2>
            <span class="close-modal-btn cursor-pointer text-gray-400 text-2xl font-bold hover:text-accent-red transition">&times;</span>
        </div>
        
        <p class="text-sm text-gray-500 mb-6 bg-red-50 p-3 rounded border border-red-100">
            You are about to close this hazard report. Please provide details on how the hazard was remedied. <strong>This action cannot be undone.</strong>
        </p>
        
        <input type="hidden" id="closeReportId">
        
        <div class="mb-6">
            <label for="resolutionComments" class="form-label">Resolution Actions Taken</label>
            <textarea id="resolutionComments" class="form-input min-h-[120px]" placeholder="e.g., The spill was cleaned up, the source of the leak was repaired, and safety signs were updated." required></textarea>
        </div>
        
        <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="btn btn-secondary close-modal-btn px-6 py-2 shadow-sm">Cancel</button>
            <button type="button" id="confirmCloseBtn" class="btn btn-accent px-6 py-2 shadow-md hover:-translate-y-0.5 transform transition-all flex items-center">
                Confirm Closure <i class="fas fa-check ml-2"></i>
            </button>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto">
    
    <!-- Page Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
            Store Hazard Reports
        </h2>
    </div>

    <!-- Store Selector Prompt -->
    <div class="bg-white p-8 rounded-xl shadow-sm border border-gray-200 text-center mb-10 max-w-2xl mx-auto">
        <label for="storeSelector" class="block text-lg font-bold text-accent-gray mb-4">
            Select a branch to view its hazard reports:
        </label>
        <div class="relative max-w-md mx-auto">
            <select id="storeSelector" class="form-input w-full text-center font-medium cursor-pointer appearance-none bg-gray-50 hover:bg-white transition-colors">
                <option value="">-- Choose a Location --</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo $store['id']; ?>">
                        <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                <i class="fas fa-chevron-down text-sm"></i>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content (Hidden until store selected) -->
    <div id="dashboardContent" style="display: none;">
        
        <!-- Stats Panel (Top Full Width) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="card flex flex-col items-center justify-center py-6 shadow-sm border border-gray-100">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Reports This Month</h4>
                <p id="statMonthCount" class="text-3xl font-bold text-primary">0</p>
            </div>
            <div class="card flex flex-col items-center justify-center py-6 border-b-4 border-secondary shadow-sm">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Risk Level 1</h4>
                <p id="statRisk1" class="text-3xl font-bold text-secondary">0</p>
            </div>
            <div class="card flex flex-col items-center justify-center py-6 border-b-4 border-orange-500 shadow-sm">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Risk Level 2</h4>
                <p id="statRisk2" class="text-3xl font-bold text-orange-500">0</p>
            </div>
            <div class="card flex flex-col items-center justify-center py-6 border-b-4 border-accent-red shadow-sm">
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Risk Level 3</h4>
                <p id="statRisk3" class="text-3xl font-bold text-accent-red">0</p>
            </div>
        </div>

        <!-- Dual Pane Layout -->
        <div class="flex flex-col lg:flex-row gap-6 items-start">
            
            <!-- LEFT PANE: Filters & List (1/3 Width) -->
            <div class="w-full lg:w-1/3 flex flex-col gap-4">
                
                <!-- Filters -->
                <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 grid grid-cols-2 lg:grid-cols-1 gap-3">
                    <div>
                        <label for="riskFilter" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Risk Level</label>
                        <select id="riskFilter" class="form-input py-2 text-sm bg-gray-50 cursor-pointer">
                            <option value="">All Levels</option>
                            <option value="1">Level 1 - Minor</option>
                            <option value="2">Level 2 - Severe</option>
                            <option value="3">Level 3 - Critical</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="dateStartFilter" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">From</label>
                            <input type="date" id="dateStartFilter" class="form-input py-2 px-2 text-xs bg-gray-50">
                        </div>
                        <div>
                            <label for="dateEndFilter" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">To</label>
                            <input type="date" id="dateEndFilter" class="form-input py-2 px-2 text-xs bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Report List Container -->
                <div id="reportListContainer" class="report-list-selectable overflow-y-auto max-h-[700px] custom-scrollbar pr-2 space-y-3 pb-4">
                    <!-- Javascript populates list here -->
                </div>
            </div>

            <!-- RIGHT PANE: Report Viewer (2/3 Width) -->
            <div class="w-full lg:w-2/3 lg:sticky lg:top-6">
                <div id="reportViewer" class="card min-h-[500px] flex flex-col items-center justify-center text-gray-400 ring-2 ring-transparent transition-all">
                    <i class="fas fa-hand-pointer text-5xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium text-gray-500">Select a report from the list to view details</p>
                    <p class="text-sm mt-2 opacity-75">Clicking a report will load the full information here.</p>
                </div>
            </div>

        </div>
    </div>
</div>