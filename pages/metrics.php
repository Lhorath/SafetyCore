<?php
/**
 * Statistics and Metrics Dashboard - pages/metrics.php
 *
 * Provides a comprehensive, read-only analytical view of hazard reports
 * for a specific store and month. Accessible only to leadership and management roles.
 * Displays aggregated data including risk breakdowns, resolution rates, 
 * top hazard locations, and hazard types.
 *
 * Updates in Beta 04:
 * - New feature introduced.
 * - Utilizes max-w-7xl container to match the expanded global dashboard layout.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// --- 1. Security & Access Control ---

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';

// Whitelist of roles permitted to view the analytics dashboard
$allowedRoles = [
    'Admin', 
    'Manager', 
    'Safety Manager', 
    'Safety Leader', 
    'Owner / CEO', 
    'Co-manager', 
    'JHSC Leader'
];

if (!in_array($userRole, $allowedRoles)) {
    // Unauthorized users are silently redirected back to the root/dashboard
    header('Location: /');
    exit();
}

// --- 2. Initial Data Fetch ---

// Fetch stores associated with the user's company to populate the filter dropdown
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

// Default filter state: Current Year and Month
$currentMonth = date('Y-m');
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Page Header -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-primary border-b-2 border-indigo-500 pb-2 inline-block">
                Hazard Reports Metrics
            </h2>
            <p class="text-sm text-gray-500 mt-2">Analyze safety trends, risk distributions, and resolution performance.</p>
        </div>
        <div class="bg-indigo-50 text-indigo-700 px-4 py-2 rounded-lg border border-indigo-100 font-medium text-sm shadow-sm flex items-center">
            <i class="fas fa-shield-alt mr-2"></i> Executive Dashboard
        </div>
    </div>

    <!-- Filter Controls Panel -->
    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Store Selection -->
            <div>
                <label for="metricsStoreSelect" class="form-label text-indigo-900">Select Branch</label>
                <div class="relative">
                    <select id="metricsStoreSelect" class="form-input w-full cursor-pointer appearance-none bg-indigo-50 border-indigo-200 hover:bg-indigo-100 transition-colors focus:ring-indigo-500">
                        <option value="">-- Choose a Location --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>">
                                <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-indigo-500">
                        <i class="fas fa-chevron-down text-sm"></i>
                    </div>
                </div>
            </div>
            
            <!-- Month Selection -->
            <div>
                <label for="metricsMonthSelect" class="form-label text-indigo-900">Select Month</label>
                <input type="month" id="metricsMonthSelect" class="form-input bg-indigo-50 border-indigo-200 cursor-pointer hover:bg-indigo-100 transition-colors focus:ring-indigo-500" value="<?php echo $currentMonth; ?>">
            </div>
        </div>
    </div>

    <!-- Placeholder (Visible before a store is selected) -->
    <div id="metricsPlaceholder" class="card min-h-[400px] flex flex-col items-center justify-center text-gray-400 bg-gray-50/50 border-dashed border-2">
        <i class="fas fa-chart-bar text-6xl mb-4 opacity-30 text-indigo-500"></i>
        <p class="text-xl font-medium">Select a branch to view metrics</p>
        <p class="text-sm mt-2 opacity-75">Data will be aggregated automatically based on the selected location and timeframe.</p>
    </div>

    <!-- Main Metrics Content Container (Populated dynamically via JS in footer.php) -->
    <div id="metricsContent" style="display: none;" class="space-y-6">
        
        <!-- Row 1: High Level KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Total Reports -->
            <div class="card bg-primary text-white text-center py-6 border-b-4 border-indigo-400 shadow-md relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-10 text-6xl group-hover:scale-110 transition-transform"><i class="fas fa-file-alt"></i></div>
                <p class="text-xs uppercase font-bold tracking-wider text-indigo-200 mb-1 relative z-10">Total Reports</p>
                <h3 id="mTotal" class="text-4xl font-extrabold relative z-10">0</h3>
            </div>
            
            <!-- Closed Reports -->
            <div class="card text-center py-6 border-b-4 border-green-500 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 text-green-500 text-6xl group-hover:scale-110 transition-transform"><i class="fas fa-check-circle"></i></div>
                <p class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1 relative z-10">Closed Reports</p>
                <h3 id="mClosed" class="text-4xl font-extrabold text-green-600 relative z-10">0</h3>
            </div>
            
            <!-- Open/Under Review -->
            <div class="card text-center py-6 border-b-4 border-orange-500 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 text-orange-500 text-6xl group-hover:scale-110 transition-transform"><i class="fas fa-clock"></i></div>
                <p class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1 relative z-10">Open / Review</p>
                <h3 id="mOpen" class="text-4xl font-extrabold text-orange-500 relative z-10">0</h3>
            </div>
            
            <!-- Equipment Lockouts -->
            <div class="card text-center py-6 border-b-4 border-accent-red shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 text-accent-red text-6xl group-hover:scale-110 transition-transform"><i class="fas fa-lock"></i></div>
                <p class="text-xs uppercase font-bold tracking-wider text-gray-400 mb-1 relative z-10">Equip. Lockouts</p>
                <h3 id="mLockout" class="text-4xl font-extrabold text-accent-red relative z-10">0</h3>
            </div>
        </div>

        <!-- Row 2: Deep Dives (Bar Charts & Breakdowns) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Risk Level Breakdown -->
            <div class="card md:col-span-1 shadow-sm">
                <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 flex items-center">
                    <i class="fas fa-exclamation-triangle text-secondary mr-2"></i> Reports by Risk
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between items-center bg-gray-50 p-3 rounded border border-gray-100 hover:shadow-sm transition">
                        <span class="font-bold text-secondary text-sm">1 - Potentially Dangerous</span>
                        <span id="mRisk1" class="text-lg font-bold bg-white px-3 py-1 rounded shadow-sm border border-gray-200">0</span>
                    </div>
                    <div class="flex justify-between items-center bg-orange-50 p-3 rounded border border-orange-100 hover:shadow-sm transition">
                        <span class="font-bold text-orange-600 text-sm">2 - Severe Risk</span>
                        <span id="mRisk2" class="text-lg font-bold bg-white px-3 py-1 rounded shadow-sm border border-orange-200 text-orange-600">0</span>
                    </div>
                    <div class="flex justify-between items-center bg-red-50 p-3 rounded border border-red-100 hover:shadow-sm transition">
                        <span class="font-bold text-accent-red text-sm">3 - Near Miss</span>
                        <span id="mRisk3" class="text-lg font-bold bg-white px-3 py-1 rounded shadow-sm border border-red-200 text-accent-red">0</span>
                    </div>
                </div>
            </div>

            <!-- Top Locations (Dynamic Bars) -->
            <div class="card md:col-span-1 shadow-sm">
                <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 flex items-center">
                    <i class="fas fa-map-marker-alt text-indigo-500 mr-2"></i> Top Locations
                </h3>
                <!-- Container populated dynamically by JS renderBars() -->
                <div id="mLocations" class="space-y-2 overflow-y-auto max-h-[300px] pr-2 custom-scrollbar">
                    <div class="text-center text-sm text-gray-400 py-8"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</div>
                </div>
            </div>

            <!-- Hazard Types (Dynamic Bars) -->
            <div class="card md:col-span-1 shadow-sm">
                <h3 class="font-bold text-gray-700 border-b pb-2 mb-4 flex items-center">
                    <i class="fas fa-tags text-indigo-500 mr-2"></i> Hazard Types
                </h3>
                <!-- Container populated dynamically by JS renderBars() -->
                <div id="mHazardTypes" class="space-y-2 overflow-y-auto max-h-[300px] pr-2 custom-scrollbar">
                    <div class="text-center text-sm text-gray-400 py-8"><i class="fas fa-spinner fa-spin mr-2"></i>Loading data...</div>
                </div>
            </div>

        </div>
    </div>
</div>