<?php
/**
 * FLHA List Dashboard - pages/flha-list.php
 *
 * This view serves as the user's personal hub for Field Level Hazard Assessments.
 * It displays a historical list of all FLHAs created by the logged-in user, 
 * showing their current status (Open/Closed) and providing quick actions to 
 * start new assessments or close out active shifts.
 *
 * Updates in Beta 05:
 * - Initial creation of the FLHA List dashboard.
 * - Integrated with the max-w-7xl global layout structure.
 * - Implemented status-aware action buttons (Close Out vs. Completed Timestamp).
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// --- 1. Security & Authentication ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userId = $_SESSION['user']['id'];

// --- 2. Data Fetching ---
$records = [];

// Fetch all FLHA records created by the current user, ordered newest first
$sql = "SELECT id, task_location, work_to_be_done, status, created_at, closed_at 
        FROM flha_records 
        WHERE creator_user_id = ? 
        ORDER BY created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();
}
?>

<div class="max-w-7xl mx-auto">
    
    <!-- Page Header & Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b-2 border-primary pb-4 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-primary flex items-center">
                <i class="fas fa-clipboard-check text-green-600 mr-3"></i> Field Level Hazard Assessments
            </h2>
            <p class="text-sm text-gray-500 mt-1">Manage your daily site assessments and mandatory safety workflows.</p>
        </div>
        <a href="/flha-form" class="btn btn-primary shadow-lg flex items-center transform hover:-translate-y-0.5 transition-all">
            <i class="fas fa-plus mr-2"></i> Start New FLHA
        </a>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm whitespace-nowrap md:whitespace-normal">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">ID &amp; Date</th>
                        <th class="px-6 py-4">Location &amp; Work Scope</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    
                    <!-- Empty State -->
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-16 text-center">
                                <i class="fas fa-folder-open text-gray-300 text-5xl mb-4 block"></i>
                                <span class="text-gray-500 font-medium block text-lg">No FLHA records found.</span>
                                <span class="text-gray-400 text-sm block mt-1">Start your first daily assessment before beginning work.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        
                        <!-- Data Rows -->
                        <?php foreach ($records as $r): ?>
                            <tr class="hover:bg-blue-50 transition-colors duration-200 group">
                                
                                <!-- ID & Date Column -->
                                <td class="px-6 py-4 align-top">
                                    <span class="font-bold text-primary block text-base group-hover:text-secondary transition-colors">
                                        #FLHA-<?php echo htmlspecialchars($r['id']); ?>
                                    </span>
                                    <span class="text-xs text-gray-500 flex items-center mt-1">
                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                    </span>
                                </td>
                                
                                <!-- Location & Scope Column -->
                                <td class="px-6 py-4 align-top">
                                    <span class="font-bold text-gray-800 block text-base">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-1 text-xs"></i> 
                                        <?php echo htmlspecialchars($r['task_location']); ?>
                                    </span>
                                    <span class="text-sm text-gray-600 block mt-1 line-clamp-2 max-w-md">
                                        <?php echo htmlspecialchars($r['work_to_be_done']); ?>
                                    </span>
                                </td>
                                
                                <!-- Status Badge Column -->
                                <td class="px-6 py-4 align-top">
                                    <?php if ($r['status'] === 'Open'): ?>
                                        <span class="inline-flex items-center bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-bold shadow-sm border border-orange-200">
                                            <span class="w-2 h-2 rounded-full bg-orange-500 mr-2 animate-pulse"></span> Active / Open
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-bold shadow-sm border border-green-200">
                                            <i class="fas fa-check-circle mr-1.5"></i> Closed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Action Buttons Column -->
                                <td class="px-6 py-4 text-right align-top">
                                    <?php if ($r['status'] === 'Open'): ?>
                                        <a href="/flha-close?id=<?php echo urlencode($r['id']); ?>" class="btn btn-accent !px-4 !py-2 text-xs shadow-sm whitespace-nowrap inline-flex items-center">
                                            Close Out Shift <i class="fas fa-lock ml-2"></i>
                                        </a>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-400 font-bold bg-gray-50 inline-block px-3 py-2 rounded border border-gray-100 whitespace-nowrap">
                                            <i class="fas fa-lock text-gray-300 mr-1"></i> 
                                            Completed <br>
                                            <span class="text-[10px] font-normal"><?php echo date('M d, Y g:i A', strtotime($r['closed_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                        
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>