<?php
/**
 * Admin View: Add User - pages/admin-views/add-user.php
 *
 * This partial view file contains the HTML form for creating a new user within the system.
 * It is included by the main Admin Controller (pages/admin.php) and relies on variables
 * ($stores, $roles, $successMessage, $errorMessage) defined in the parent scope.
 *
 * Features:
 * - Tailwind CSS styling consistent with NorthPoint 360 branding.
 * - Password field for initial credential setup (added in Revision 11).
 * - Dropdowns for Store and Role assignment.
 * - Responsive Card layout.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>

<div class="max-w-4xl">
    
    <!-- Section Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
            Add New User
        </h2>
    </div>
        
    <!-- Feedback Messages (Passed from Parent Controller) -->
    <?php if (!empty($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-check-circle mr-3 text-lg"></i>
            <div>
                <p class="font-bold">Success</p>
                <p><?php echo $successMessage; // Outputting raw to allow HTML tags like <strong> for password display ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-lg"></i>
            <div>
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Add User Form -->
    <!-- Action points back to the main admin controller with the current view parameter -->
    <form action="/admin?view=add-user" method="POST" class="space-y-8">
        
        <!-- Card 1: Personal Information -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required class="form-input" placeholder="John">
                </div>
                <!-- Last Name -->
                <div>
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required class="form-input" placeholder="Doe">
                </div>
                <!-- Email -->
                <div class="md:col-span-2">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" required class="form-input" placeholder="john.doe@northpoint360.ca" autocomplete="new-email">
                </div>
                <!-- Position -->
                <div class="md:col-span-2">
                    <label class="form-label" for="employee_position">Employee Position</label>
                    <input type="text" id="employee_position" name="employee_position" class="form-input" placeholder="e.g., Sales Associate">
                </div>
                
                <!-- Password (Required for creation) -->
                <div class="md:col-span-2">
                    <label class="form-label" for="password">Initial Password</label>
                    <input type="password" id="password" name="password" required class="form-input" placeholder="Create a strong password" autocomplete="new-password">
                    <p class="text-xs text-gray-500 mt-1">Users can change this later in their profile settings.</p>
                </div>
            </div>
        </div>

        <!-- Card 2: Assignment & Role -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">Assignment & Role</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Store Selection -->
                <div>
                    <label class="form-label" for="store_id">Primary Store</label>
                    <div class="relative">
                        <select id="store_id" name="store_id" required class="form-input appearance-none cursor-pointer">
                            <option value="">-- Select a Store --</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['store_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Custom Arrow Icon -->
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
                <!-- Role Selection -->
                <div>
                    <label class="form-label" for="role_id">User Role</label>
                    <div class="relative">
                        <select id="role_id" name="role_id" required class="form-input appearance-none cursor-pointer">
                            <option value="">-- Select a Role --</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Custom Arrow Icon -->
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all flex items-center">
                <i class="fas fa-user-plus mr-2"></i> Create User
            </button>
        </div>
        
    </form>
</div>