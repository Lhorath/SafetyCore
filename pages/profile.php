<?php
/**
 * User Profile Display Page - pages/profile.php
 *
 * This page displays the currently logged-in user's profile information in a 
 * clean, read-only format. It aggregates data from multiple tables to show 
 * roles and assigned store locations.
 *
 * Features:
 * - Secure session validation.
 * - Multi-Store Support: Uses GROUP_CONCAT to list all assigned branches from the `user_stores` table.
 * - Tailwind CSS styling consistent with the Sentry OHS brand.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// --- 1. Security & Access Control ---

// If a user is not logged in, they cannot view a profile. Redirect to login.
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

// --- 2. Data Fetching ---

// Get the logged-in user's ID from the secure session.
$userId = $_SESSION['user']['id'];
$companyType = $_SESSION['user']['company_type'] ?? 'multi_location';

// Fetch the user's full details.
// Note: Since Revision 10, the 'store_id' column was removed from the 'users' table 
// in favor of the 'user_stores' junction table to support Many-to-Many relationships.
// We use GROUP_CONCAT to aggregate all assigned store names into a single readable string.
$locationLabel = 'Assigned Branch(es)';
$locationIcon = 'fa-store';
if ($companyType === 'job_based') {
    $sql = "SELECT 
                u.first_name, 
                u.last_name, 
                u.email, 
                u.employee_position, 
                u.employee_code,
                u.status,
                u.employment_type,
                u.department,
                u.phone_number,
                u.hire_date,
                u.preferred_language,
                u.timezone,
                r.role_name,
                CONCAT(su.first_name, ' ', su.last_name) AS supervisor_name,
                GROUP_CONCAT(DISTINCT js.job_name SEPARATOR ', ') AS assigned_locations
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN users su ON u.supervisor_user_id = su.id
            LEFT JOIN user_job_sites ujs ON u.id = ujs.user_id
            LEFT JOIN job_sites js ON ujs.job_site_id = js.id
            WHERE u.id = ?
            GROUP BY u.id";
    $locationLabel = 'Assigned Job Site(s)';
    $locationIcon = 'fa-hard-hat';
} else {
    $sql = "SELECT 
                u.first_name, 
                u.last_name, 
                u.email, 
                u.employee_position, 
                u.employee_code,
                u.status,
                u.employment_type,
                u.department,
                u.phone_number,
                u.hire_date,
                u.preferred_language,
                u.timezone,
                r.role_name,
                CONCAT(su.first_name, ' ', su.last_name) AS supervisor_name,
                GROUP_CONCAT(DISTINCT s.store_name SEPARATOR ', ') AS assigned_locations
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN users su ON u.supervisor_user_id = su.id
            LEFT JOIN user_stores us ON u.id = us.user_id
            LEFT JOIN stores s ON us.store_id = s.id
            WHERE u.id = ?
            GROUP BY u.id";
}

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
} else {
    // If the database query fails, log it and show a generic error (or redirect)
    error_log("Profile Query Error: " . $conn->error);
    header('Location: /logout.php');
    exit();
}

// A fallback for the rare case where the user ID in the session is valid but the record is missing.
// This logs the user out to clear the stale session data.
if (!$user) {
    header('Location: /logout.php');
    exit();
}
?>

<div class="max-w-3xl mx-auto">
    
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2">
            Your Profile
        </h2>
        
        <!-- Action Button: Link to the Edit Page -->
        <a href="/profile-edit" class="btn btn-primary text-sm flex items-center shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all">
            <i class="fas fa-pencil-alt mr-2"></i> Edit Profile
        </a>
    </div>

    <!-- Profile Information Card -->
    <!-- Uses the custom 'card' utility class defined in includes/header.php -->
    <div class="card">
        <div class="space-y-6">
            
            <!-- Field: Name -->
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Name</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                </span>
            </div>

            <!-- Field: Email -->
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Email Address</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['email']); ?>
                </span>
            </div>

            <!-- Field: Position -->
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Position</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['employee_position'] ?? 'Not Specified'); ?>
                </span>
            </div>

            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Employee Code</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['employee_code'] ?? 'Not Assigned'); ?>
                </span>
            </div>

            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Employment Details</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['employment_type'] ?? 'Not Specified'); ?>
                    <?php if (!empty($user['department'])): ?>
                        <span class="text-gray-400">|</span> <?php echo htmlspecialchars($user['department']); ?>
                    <?php endif; ?>
                </span>
            </div>

            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Contact</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['phone_number'] ?? 'Not Specified'); ?>
                </span>
            </div>

            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Supervisor</span>
                <span class="text-lg font-medium text-gray-800">
                    <?php echo htmlspecialchars($user['supervisor_name'] ?? 'Unassigned'); ?>
                </span>
            </div>

            <!-- Field: Assigned Location(s) -->
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1"><?php echo htmlspecialchars($locationLabel); ?></span>
                <div class="flex items-center text-gray-800">
                    <i class="fas <?php echo htmlspecialchars($locationIcon); ?> text-secondary mr-2"></i>
                    <span class="text-lg font-medium">
                        <?php echo htmlspecialchars($user['assigned_locations'] ?? 'Unassigned'); ?>
                    </span>
                </div>
            </div>

            <!-- Field: User Role -->
            <div>
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">User Role</span>
                <!-- Badge Style -->
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-primary text-white shadow-sm">
                    <?php echo htmlspecialchars($user['role_name']); ?>
                </span>
                <span class="inline-block ml-2 px-3 py-1 rounded-full text-xs font-bold <?php echo (($user['status'] ?? 'active') === 'active') ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo htmlspecialchars(ucfirst($user['status'] ?? 'active')); ?>
                </span>
            </div>

        </div>
    </div>
</div>