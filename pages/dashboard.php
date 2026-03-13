<?php
/**
 * User & Management Dashboard - pages/dashboard.php
 *
 * This view serves as the central hub post-login. It displays personal safety
 * statistics, provides quick access to core operational forms (Hazards, FLHAs),
 * and conditionally renders advanced management tools based on user roles.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @version   1.0.0
 */

// --- 1. Security & Authentication ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$user = $_SESSION['user'];
$userId = (int)$user['id'];
$userRole = $user['role_name'] ?? '';

// Define which roles get access to the "Management" section of the dashboard
$managementRoles = [
    'Admin', 
    'Manager', 
    'Safety Manager', 
    'Safety Leader', 
    'Owner / CEO', 
    'Co-manager', 
    'JHSC Leader'
];

$isManagement = in_array($userRole, $managementRoles);

// --- 2. Live Statistics Fetching ---
$myHazardCount = 0;
$myOpenFlhaCount = 0;

// Fetch total hazard reports submitted by this user
$hazardSql = "SELECT COUNT(*) as total FROM reports WHERE user_id = ?";
if ($stmt = $conn->prepare($hazardSql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $myHazardCount = $row['total'];
    }
    $stmt->close();
}

// Fetch active/open Field Level Hazard Assessments (FLHAs) for this user
$flhaSql = "SELECT COUNT(*) as total FROM flha_records WHERE creator_user_id = ? AND status = 'Open'";
if ($stmt = $conn->prepare($flhaSql)) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $myOpenFlhaCount = $row['total'];
    }
    $stmt->close();
}
?>

<div class="max-w-7xl mx-auto py-8">
    
    <!-- Welcome Header & Stats -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-10 gap-6 border-b-2 border-primary pb-6">
        <div>
            <h1 class="text-3xl lg:text-4xl font-extrabold text-primary mb-2 tracking-tight">
                Welcome back, <span class="text-secondary"><?php echo htmlspecialchars($user['first_name']); ?></span>!
            </h1>
            <p class="text-gray-500 font-medium text-lg flex items-center">
                <i class="fas fa-id-badge mr-2 opacity-75"></i> <?php echo htmlspecialchars($user['employee_position'] ?? 'Employee'); ?>
                <span class="mx-3 text-gray-300">|</span> 
                <span class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-sm font-bold border border-gray-200">
                    <?php echo htmlspecialchars($userRole); ?>
                </span>
            </p>
        </div>
        
        <!-- Personal Quick Stats -->
        <div class="flex gap-4 w-full lg:w-auto">
            <div class="bg-white border border-gray-200 p-4 rounded-xl shadow-sm flex items-center flex-1 lg:flex-none">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-secondary flex items-center justify-center text-xl mr-4">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div>
                    <span class="block text-2xl font-black text-primary leading-none"><?php echo $myHazardCount; ?></span>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">My Reports</span>
                </div>
            </div>
            
            <a href="/flha-list" class="bg-white border <?php echo $myOpenFlhaCount > 0 ? 'border-orange-300 shadow-md ring-2 ring-orange-50' : 'border-gray-200 shadow-sm'; ?> p-4 rounded-xl flex items-center flex-1 lg:flex-none hover:shadow-md transition-shadow group cursor-pointer">
                <div class="w-12 h-12 rounded-full <?php echo $myOpenFlhaCount > 0 ? 'bg-orange-100 text-orange-500 animate-pulse' : 'bg-green-50 text-green-500'; ?> flex items-center justify-center text-xl mr-4 group-hover:scale-110 transition-transform">
                    <i class="fas <?php echo $myOpenFlhaCount > 0 ? 'fa-hard-hat' : 'fa-check-circle'; ?>"></i>
                </div>
                <div>
                    <span class="block text-2xl font-black text-primary leading-none"><?php echo $myOpenFlhaCount; ?></span>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Active FLHAs</span>
                </div>
            </a>
        </div>
    </div>

    <!-- ==========================================
         FIELD WORKER TOOLS (Visible to Everyone)
         ========================================== -->
    <div class="mb-12">
        <h2 class="text-lg font-bold text-gray-400 uppercase tracking-widest mb-6 flex items-center">
            <i class="fas fa-toolbox mr-3 text-gray-300"></i> Field & Operational Tools
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- TILE: Field Level Hazard Assessment -->
            <div class="card bg-white border border-gray-100 hover:shadow-xl hover:border-blue-200 transition-all duration-300 flex flex-col items-center text-center p-8 group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-blue-50 rounded-bl-full -z-10 transition-transform group-hover:scale-150"></div>
                <div class="h-16 w-16 bg-blue-100 text-secondary rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mb-2">Daily FLHA</h3>
                <p class="text-sm text-gray-500 mb-6 flex-grow leading-relaxed">Start or close out your daily Field Level Hazard Assessments.</p>
                <div class="flex gap-2 w-full">
                    <a href="/flha-form" class="btn btn-primary flex-1 shadow-sm font-bold !px-2"><i class="fas fa-plus mr-1"></i> Start</a>
                    <a href="/flha-list" class="btn btn-secondary flex-1 shadow-sm font-bold !px-2"><i class="fas fa-list mr-1"></i> View</a>
                </div>
            </div>

            <!-- TILE: Report Hazard -->
            <div class="card bg-white border border-gray-100 hover:shadow-xl hover:border-orange-200 transition-all duration-300 flex flex-col items-center text-center p-8 group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-orange-50 rounded-bl-full -z-10 transition-transform group-hover:scale-150"></div>
                <div class="h-16 w-16 bg-orange-100 text-orange-500 rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mb-2">Report a Hazard</h3>
                <p class="text-sm text-gray-500 mb-6 flex-grow leading-relaxed">Log unsafe conditions, property damage, or near misses.</p>
                <div class="flex gap-2 w-full">
                    <a href="/hazard-report" class="btn bg-orange-500 text-white hover:bg-orange-600 flex-1 shadow-sm font-bold !px-2"><i class="fas fa-flag mr-1"></i> Report</a>
                    <a href="/my-reports" class="btn btn-secondary flex-1 shadow-sm font-bold !px-2"><i class="fas fa-history mr-1"></i> History</a>
                </div>
            </div>
            
            <!-- TILE: Incident Report -->
            <div class="card bg-white border border-gray-100 hover:shadow-xl hover:border-red-200 transition-all duration-300 flex flex-col items-center text-center p-8 group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-red-50 rounded-bl-full -z-10 transition-transform group-hover:scale-150"></div>
                <div class="h-16 w-16 bg-red-100 text-accent-red rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                    <i class="fas fa-ambulance"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mb-2">Log an Incident</h3>
                <p class="text-sm text-gray-500 mb-6 flex-grow leading-relaxed">Report actual injuries or major property damage immediately.</p>
                <a href="/incident-report" class="btn bg-accent-red text-white hover:bg-red-700 w-full group-hover:-translate-y-0.5 transform transition-all shadow-sm font-bold"><i class="fas fa-notes-medical mr-2"></i> File Report</a>
            </div>

            <!-- TILE: My Profile -->
            <div class="card bg-white border border-gray-100 hover:shadow-xl hover:border-gray-300 transition-all duration-300 flex flex-col items-center text-center p-8 group relative overflow-hidden">
                <div class="absolute top-0 right-0 w-16 h-16 bg-gray-50 rounded-bl-full -z-10 transition-transform group-hover:scale-150"></div>
                <div class="h-16 w-16 bg-gray-100 text-gray-600 rounded-2xl flex items-center justify-center text-3xl mb-5 shadow-sm group-hover:-translate-y-1 transition-transform duration-300">
                    <i class="fas fa-user-circle"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mb-2">My Profile</h3>
                <p class="text-sm text-gray-500 mb-6 flex-grow leading-relaxed">Update your contact info, change passwords, and manage settings.</p>
                <a href="/profile" class="btn btn-secondary w-full group-hover:-translate-y-0.5 transform transition-all shadow-sm font-bold"><i class="fas fa-cog mr-2"></i> Settings</a>
            </div>
        </div>
    </div>

    <!-- ==========================================
         MANAGEMENT TOOLS (RBAC Restricted)
         ========================================== -->
    <?php if ($isManagement): ?>
    <div class="bg-slate-50 border border-slate-200 p-8 rounded-2xl shadow-inner relative overflow-hidden">
        <!-- Decorative Management Background Icon -->
        <i class="fas fa-shield-alt absolute -right-10 -bottom-10 text-9xl text-slate-200 opacity-50 transform -rotate-12 pointer-events-none"></i>
        
        <h2 class="text-lg font-bold text-slate-500 uppercase tracking-widest mb-6 flex items-center relative z-10">
            <i class="fas fa-lock mr-3 text-slate-400"></i> Management &amp; Admin Hub
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 relative z-10">
            
            <!-- TILE: Branch Hazard Review -->
            <div class="card bg-white border border-slate-200 hover:border-secondary hover:shadow-xl transition duration-300 flex flex-col items-center text-center p-6 group">
                <div class="h-14 w-14 bg-blue-50 text-secondary rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-search-location"></i>
                </div>
                <h3 class="text-lg font-bold text-primary mb-2">Review Hazards</h3>
                <p class="text-xs text-gray-500 mb-5 flex-grow">Investigate and close out hazards reported by your branch.</p>
                <a href="/store-reports" class="btn btn-primary w-full shadow-sm text-sm py-2">Open Review</a>
            </div>

            <!-- TILE: Recordable Incidents -->
            <div class="card bg-white border border-slate-200 hover:border-accent-red hover:shadow-xl transition duration-300 flex flex-col items-center text-center p-6 group">
                <div class="h-14 w-14 bg-red-50 text-accent-red rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-file-medical-alt"></i>
                </div>
                <h3 class="text-lg font-bold text-primary mb-2">Compliance Log</h3>
                <p class="text-xs text-gray-500 mb-5 flex-grow">Classify OSHA/WCB recordable incidents &amp; lost time.</p>
                <a href="/store-incidents" class="btn bg-red-100 text-red-800 hover:bg-red-200 w-full shadow-sm font-bold text-sm py-2 border border-red-200">View Log</a>
            </div>

            <!-- TILE: Safety Meetings -->
            <div class="card bg-white border border-slate-200 hover:border-teal-400 hover:shadow-xl transition duration-300 flex flex-col items-center text-center p-6 group">
                <div class="h-14 w-14 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-users-class"></i>
                </div>
                <h3 class="text-lg font-bold text-primary mb-2">Safety Meetings</h3>
                <p class="text-xs text-gray-500 mb-5 flex-grow">Host toolbox talks and track verified team attendance.</p>
                <a href="/meetings-list" class="btn bg-teal-100 text-teal-800 hover:bg-teal-200 w-full shadow-sm font-bold text-sm py-2 border border-teal-200">Host / View</a>
            </div>

            <!-- TILE: Training Matrix [NEW] -->
            <div class="card bg-white border border-slate-200 hover:border-purple-400 hover:shadow-xl transition duration-300 flex flex-col items-center text-center p-6 group">
                <div class="h-14 w-14 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="text-lg font-bold text-primary mb-2">Training Matrix</h3>
                <p class="text-xs text-gray-500 mb-5 flex-grow">Track certs, manage expiry dates, and ensure compliance.</p>
                <a href="/training-matrix" class="btn bg-purple-100 text-purple-800 hover:bg-purple-200 w-full shadow-sm font-bold text-sm py-2 border border-purple-200">Open Matrix</a>
            </div>
            
        </div>
        
        <!-- Deep Admin Links (Only for highly privileged roles) -->
        <?php if (in_array($userRole, ['Admin', 'Owner / CEO'])): ?>
            <div class="mt-8 pt-6 border-t border-slate-200 flex justify-end relative z-10">
                <a href="/admin" class="text-sm font-bold text-slate-500 hover:text-primary transition-colors flex items-center bg-white px-4 py-2 rounded-lg border border-slate-300 shadow-sm">
                    <i class="fas fa-cogs mr-2 text-secondary"></i> Advanced System Admin
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>