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
 * - Tailwind CSS styling consistent with the NorthPoint 360 brand.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
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

// Fetch the user's full details.
// Note: Since Revision 10, the 'store_id' column was removed from the 'users' table 
// in favor of the 'user_stores' junction table to support Many-to-Many relationships.
// We use GROUP_CONCAT to aggregate all assigned store names into a single readable string.
$sql = "SELECT 
            u.first_name, 
            u.last_name, 
            u.email, 
            u.employee_position, 
            r.role_name,
            GROUP_CONCAT(s.store_name SEPARATOR ', ') as store_names
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN user_stores us ON u.id = us.user_id
        LEFT JOIN stores s ON us.store_id = s.id
        WHERE u.id = ?
        GROUP BY u.id";

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

            <!-- Field: Assigned Stores (Multi-Tenant Support) -->
            <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Assigned Branch(es)</span>
                <div class="flex items-center text-gray-800">
                    <i class="fas fa-store text-secondary mr-2"></i>
                    <span class="text-lg font-medium">
                        <?php echo htmlspecialchars($user['store_names'] ?? 'Unassigned'); ?>
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
            </div>

        </div>
    </div>
</div>