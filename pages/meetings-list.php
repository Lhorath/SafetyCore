<?php
/**
 * Meetings & Talks Dashboard - pages/meetings-list.php
 *
 * A restricted management view that displays a historical log of all safety 
 * meetings, toolbox talks, and tailgate meetings hosted across the company.
 * It provides visibility into training topics, branch locations, host details, 
 * and verified employee attendance counts for compliance auditing.
 *
 * Updates in Beta 05:
 * - Initial creation of the Meetings & Talks module.
 * - Implemented optimized SQL subqueries for accurate attendance counts.
 * - Integrated with the max-w-7xl global layout structure.
 * - Added responsive table design with hover states and empty-state handling.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   5.0.0 (NorthPoint Beta 05)
 */

// --- 1. Security & Access Control ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';

// Define roles authorized to view safety meetings
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
    // Silently redirect unauthorized users to the primary dashboard
    header('Location: /dashboard');
    exit();
}

$companyId = $_SESSION['user']['company_id'];

// --- 2. Data Fetching ---
$meetings = [];

// Fetch all meetings for the company, including host details and a dynamic count of attendees
$sql = "SELECT m.id, 
               m.topic, 
               m.meeting_date, 
               s.store_name, 
               CONCAT(u.first_name, ' ', u.last_name) as host_name,
               (SELECT COUNT(*) FROM meeting_attendees ma WHERE ma.meeting_id = m.id) as attendee_count
        FROM meetings m
        JOIN stores s ON m.store_id = s.id
        JOIN users u ON m.host_user_id = u.id
        WHERE m.company_id = ?
        ORDER BY m.meeting_date DESC, m.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $meetings[] = $row;
    }
    $stmt->close();
}
?>

<div class="max-w-7xl mx-auto py-8">
    
    <!-- Page Header & Actions -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b-2 border-primary pb-4 gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-users-class text-secondary mr-3"></i> Meetings &amp; Safety Talks
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Review past toolbox talks, safety topics, and track team attendance.</p>
        </div>
        <a href="/host-meeting" class="btn btn-primary shadow-lg flex items-center transform hover:-translate-y-0.5 transition-all">
            <i class="fas fa-plus mr-2"></i> Host a Meeting
        </a>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm whitespace-nowrap md:whitespace-normal">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase text-xs font-bold tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Meeting Topic</th>
                        <th class="px-6 py-4">Branch / Location</th>
                        <th class="px-6 py-4">Hosted By</th>
                        <th class="px-6 py-4 text-center">Verified Attendance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    
                    <!-- Empty State -->
                    <?php if (empty($meetings)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
                                <i class="fas fa-clipboard-list text-gray-300 text-5xl mb-4 block"></i>
                                <span class="text-gray-500 font-medium block text-lg">No safety meetings have been logged yet.</span>
                                <span class="text-gray-400 text-sm block mt-1">Host your first toolbox talk to start building compliance records.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        
                        <!-- Data Rows -->
                        <?php foreach ($meetings as $m): ?>
                            <tr class="hover:bg-blue-50 transition-colors duration-200 group">
                                
                                <!-- Date Column -->
                                <td class="px-6 py-4 align-middle">
                                    <span class="font-medium text-gray-600 flex items-center">
                                        <i class="far fa-calendar-alt text-gray-400 mr-2"></i>
                                        <?php echo date('M d, Y', strtotime($m['meeting_date'])); ?>
                                    </span>
                                </td>
                                
                                <!-- Topic Column -->
                                <td class="px-6 py-4 align-middle">
                                    <span class="font-bold text-primary text-base group-hover:text-secondary transition-colors block line-clamp-2 max-w-sm">
                                        <?php echo htmlspecialchars($m['topic']); ?>
                                    </span>
                                </td>
                                
                                <!-- Location Column -->
                                <td class="px-6 py-4 align-middle">
                                    <span class="text-gray-700 font-medium flex items-center">
                                        <i class="fas fa-map-marker-alt text-gray-400 mr-2 text-xs"></i>
                                        <?php echo htmlspecialchars($m['store_name']); ?>
                                    </span>
                                </td>
                                
                                <!-- Host Column -->
                                <td class="px-6 py-4 align-middle">
                                    <span class="text-gray-500 text-sm flex items-center">
                                        <div class="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-[10px] font-bold mr-2">
                                            <?php echo substr(htmlspecialchars($m['host_name']), 0, 1); ?>
                                        </div>
                                        <?php echo htmlspecialchars($m['host_name']); ?>
                                    </span>
                                </td>
                                
                                <!-- Attendance Column -->
                                <td class="px-6 py-4 text-center align-middle">
                                    <span class="inline-flex items-center justify-center bg-blue-100 text-secondary font-bold px-3 py-1.5 rounded-full text-xs shadow-sm border border-blue-200">
                                        <i class="fas fa-users mr-1.5 opacity-75"></i>
                                        <?php echo (int)$m['attendee_count']; ?> User<?php echo ($m['attendee_count'] == 1) ? '' : 's'; ?>
                                    </span>
                                </td>
                                
                            </tr>
                        <?php endforeach; ?>
                        
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>