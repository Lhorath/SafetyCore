<?php
/**
 * My Reports Page - pages/my-reports.php
 *
 * This page displays a historical list of hazard reports submitted by the 
 * currently logged-in user. It features a side-by-side master-detail layout,
 * allowing users to quickly filter, sort, and review their submissions.
 *
 * Updates in Beta 04:
 * - Container expanded to max-w-7xl to match the new global layout constraints.
 * - Refined status ribbon and risk badge UI for consistency with the Store Reports dashboard.
 * - Ensured strict compatibility with the updated footer.php JS injector for "Edit Details".
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   2.4.0 (NorthPoint Beta 04)
 */

// --- 1. Security & Authentication ---

// Ensure the user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userId = $_SESSION['user']['id'];

// --- 2. Fetch Available Months for Filter ---
// Queries the database for distinct months where the user has submitted a report
$months = [];
$monthSql = "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') as value, DATE_FORMAT(created_at, '%M %Y') as label 
             FROM reports 
             WHERE reporter_user_id = ? 
             ORDER BY value DESC";

if ($stmtM = $conn->prepare($monthSql)) {
    $stmtM->bind_param("i", $userId);
    $stmtM->execute();
    $resM = $stmtM->get_result();
    while ($row = $resM->fetch_assoc()) {
        $months[] = $row;
    }
    $stmtM->close();
}

// --- 3. Data Fetching & Sorting/Filtering ---
$reports = [];
$sortOrder = $_GET['sort'] ?? 'newest';
$selectedMonth = $_GET['month'] ?? '';

// Base SQL query
$sql = "SELECT 
            r.id, 
            r.risk_level, 
            r.created_at, 
            r.status,
            hl.location_name as hazard_location_name
        FROM reports r
        JOIN hazard_locations hl ON r.hazard_location_id = hl.id
        WHERE r.reporter_user_id = ?";

$params = [$userId];
$types = "i";

// Apply Month Filter if selected
if (!empty($selectedMonth)) {
    $sql .= " AND DATE_FORMAT(r.created_at, '%Y-%m') = ?";
    $params[] = $selectedMonth;
    $types .= "s";
}

// Apply Sorting Logic
switch ($sortOrder) {
    case 'risk_high': 
        $sql .= " ORDER BY r.risk_level DESC, r.created_at DESC"; 
        break;
    case 'risk_low':  
        $sql .= " ORDER BY r.risk_level ASC, r.created_at DESC"; 
        break;
    case 'oldest':    
        $sql .= " ORDER BY r.created_at ASC"; 
        break;
    case 'newest':
    default:          
        $sql .= " ORDER BY r.created_at DESC"; 
        break;
}

// Execute Main Query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
}

/**
 * Helper function to determine Tailwind color classes for Risk Badges
 * @param int $level The risk level (1-3)
 * @return string Tailwind CSS classes
 */
function getRiskBadgeColor($level) {
    switch($level) {
        case 1: return 'bg-secondary text-black';     // Level 1: Yellow/Blue based on brand
        case 2: return 'bg-orange-500 text-white';    // Level 2: Orange
        case 3: return 'bg-accent-red text-white';    // Level 3: Red
        default: return 'bg-gray-500 text-white';
    }
}
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Page Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
            My Submitted Reports
        </h2>
    </div>

    <!-- Dual Pane Layout -->
    <div class="flex flex-col lg:flex-row gap-6 items-start">
        
        <!-- LEFT PANE: Filters & List (1/3 Width) -->
        <div class="w-full lg:w-1/3 flex flex-col gap-4">
            
            <!-- Filter Controls -->
            <form method="GET" action="/my-reports" class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 flex flex-col sm:flex-row lg:flex-col xl:flex-row gap-3">
                
                <!-- Month Selection -->
                <div class="flex-1">
                    <label for="monthFilter" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Month</label>
                    <select name="month" id="monthFilter" onchange="this.form.submit()" class="form-input py-2 text-sm bg-gray-50 cursor-pointer">
                        <option value="">All Time</option>
                        <?php foreach($months as $m): ?>
                            <option value="<?php echo $m['value']; ?>" <?php echo ($selectedMonth === $m['value']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort Order -->
                <div class="flex-1">
                    <label for="sortFilter" class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Sort By</label>
                    <select name="sort" id="sortFilter" onchange="this.form.submit()" class="form-input py-2 text-sm bg-gray-50 cursor-pointer">
                        <option value="newest" <?php echo ($sortOrder === 'newest') ? 'selected' : ''; ?>>Newest</option>
                        <option value="oldest" <?php echo ($sortOrder === 'oldest') ? 'selected' : ''; ?>>Oldest</option>
                        <option value="risk_high" <?php echo ($sortOrder === 'risk_high') ? 'selected' : ''; ?>>Highest Risk</option>
                        <option value="risk_low" <?php echo ($sortOrder === 'risk_low') ? 'selected' : ''; ?>>Lowest Risk</option>
                    </select>
                </div>
            </form>

            <!-- Report List -->
            <!-- Note: class 'report-list-selectable' acts as the listener hook for footer.php -->
            <div class="report-list-selectable overflow-y-auto max-h-[700px] custom-scrollbar pr-2 space-y-3 pb-4">
                <?php if (empty($reports)): ?>
                    <div class="bg-white p-8 rounded-xl text-center border border-dashed border-gray-300">
                        <i class="fas fa-folder-open text-gray-300 text-3xl mb-3"></i>
                        <p class="text-gray-500 text-sm font-medium">No reports match your filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="report-item bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md cursor-pointer transition duration-200 ease-in-out group relative overflow-hidden" 
                             data-report-id="<?php echo $report['id']; ?>">
                            
                            <!-- Status Indicator Ribbon (Visual Cue) -->
                            <?php 
                                $statusColor = match($report['status']) {
                                    'Open' => 'bg-green-500',
                                    'Under Review' => 'bg-orange-500',
                                    default => 'bg-gray-400'
                                };
                            ?>
                            <div class="absolute left-0 top-0 bottom-0 w-1 <?php echo $statusColor; ?>"></div>

                            <div class="pl-2">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-bold text-primary group-hover:text-secondary transition-colors">
                                        #<?php echo htmlspecialchars($report['id']); ?>
                                    </span>
                                    <span class="text-[10px] text-gray-500 flex items-center">
                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                    </span>
                                </div>
                                
                                <div class="text-sm font-medium text-gray-700 truncate group-hover:text-primary transition-colors">
                                    <?php echo htmlspecialchars($report['hazard_location_name']); ?>
                                </div>
                                
                                <div class="mt-2 flex justify-between items-center">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold text-white <?php echo getRiskBadgeColor($report['risk_level']); ?>">
                                        Risk <?php echo htmlspecialchars($report['risk_level']); ?>
                                    </span>
                                    <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                                        <?php echo htmlspecialchars($report['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT PANE: Report Viewer (2/3 Width) -->
        <!-- Content injected here dynamically via AJAX from api/hazard_reporting.php -->
        <div class="w-full lg:w-2/3 lg:sticky lg:top-6">
            <div id="reportViewer" class="card min-h-[500px] flex flex-col items-center justify-center text-gray-400 ring-2 ring-transparent transition-all">
                <i class="fas fa-hand-pointer text-5xl mb-4 opacity-50"></i>
                <p class="text-lg font-medium text-gray-500">Select a report from the list to view details</p>
                <p class="text-sm mt-2 opacity-75">Clicking a report will load the full information here.</p>
            </div>
        </div>

    </div>
</div>