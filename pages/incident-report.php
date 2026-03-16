<?php
/**
 * Incident & Accident Report Form - pages/incident-report.php
 *
 * Provides a universal access form for all authenticated users to log injuries, 
 * property damage, and severe near misses. Data submitted here feeds directly 
 * into the management-only `store-incidents` dashboard for OSHA/WCB classification.
 *
 * Updates in Beta 05:
 * - Initial creation of the Incident Reporting module.
 * - Implemented secure POST handling with prepared statements.
 * - Styled with the new Beta 05 Tailwind UI component library (accent-red).
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

$successMessage = '';
$errorMessage = '';
$companyId = $_SESSION['user']['company_id'];
$reporterUserId = $_SESSION['user']['id'];

// --- 2. Fetch Available Locations ---
// Only fetch stores the user is explicitly assigned to
$stores = [];
$sql = "SELECT s.id, s.store_name, s.store_number 
        FROM stores s 
        JOIN user_stores us ON s.id = us.store_id 
        WHERE us.user_id = ? 
        ORDER BY s.store_name ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $reporterUserId);
    $stmt->execute();
    $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// --- 3. Process Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate inputs
    $storeId          = filter_input(INPUT_POST, 'store_id', FILTER_VALIDATE_INT);
    $incidentType     = trim($_POST['incident_type'] ?? '');
    $incidentDate     = trim($_POST['incident_date'] ?? '');
    $incidentTime     = trim($_POST['incident_time'] ?? '');
    $locationDetails  = trim($_POST['location_details'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $immediateActions = trim($_POST['immediate_actions'] ?? '');

    // Combine date and time for MySQL DATETIME format
    $datetimeString = $incidentDate . ' ' . $incidentTime . ':00';

    // Basic Validation
    if (!$storeId || empty($incidentType) || empty($incidentDate) || empty($incidentTime) || empty($description)) {
        $errorMessage = "Please fill out all required fields marked with an asterisk (*).";
    } else {
        // Insert into the database
        $insertSql = "INSERT INTO incidents (company_id, store_id, reporter_user_id, incident_type, incident_date, location_details, description, immediate_actions) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                      
        if ($insStmt = $conn->prepare($insertSql)) {
            $insStmt->bind_param("iiisssss", 
                $companyId, 
                $storeId, 
                $reporterUserId, 
                $incidentType, 
                $datetimeString, 
                $locationDetails, 
                $description, 
                $immediateActions
            );
            
            if ($insStmt->execute()) {
                $successMessage = "Incident report submitted successfully. Management has been notified and will review the details.";
            } else {
                $errorMessage = "A database error occurred while submitting your report. Please try again or contact support.";
            }
            $insStmt->close();
        } else {
            $errorMessage = "Failed to prepare the database statement.";
        }
    }
}
?>

<div class="max-w-3xl mx-auto py-8">
    
    <!-- Page Header -->
    <div class="mb-8 border-b-2 border-accent-red pb-4">
        <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
            <div class="w-10 h-10 rounded-full bg-red-100 text-accent-red flex items-center justify-center mr-3 text-lg"><i class="fas fa-ambulance"></i></div>
            Report an Incident
        </h2>
        <p class="text-base text-gray-500 mt-2 font-medium">Log workplace injuries, severe near misses, or property damage immediately.</p>
    </div>

    <!-- Alert Messaging -->
    <?php if ($successMessage): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 p-6 mb-8 rounded-xl shadow-sm flex items-start animate-fade-in-up">
            <i class="fas fa-check-circle mt-0.5 mr-3 text-xl text-green-500 flex-shrink-0"></i>
            <div>
                <h4 class="font-bold text-lg mb-1">Report Submitted</h4>
                <p class="text-sm font-medium"><?php echo htmlspecialchars($successMessage); ?></p>
                <div class="mt-4">
                    <a href="/dashboard" class="btn bg-green-600 text-white hover:bg-green-700 !px-4 !py-2 text-sm shadow-sm transition-colors">Return to Dashboard</a>
                </div>
            </div>
        </div>
        <?php 
            // Hide the form on success to prevent accidental duplicate submissions
            echo '</div>'; 
            return; 
        ?>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 p-4 mb-8 rounded-xl shadow-sm flex items-center animate-fade-in-up">
            <i class="fas fa-exclamation-triangle mr-3 text-xl text-accent-red flex-shrink-0"></i>
            <span class="text-sm font-bold"><?php echo htmlspecialchars($errorMessage); ?></span>
        </div>
    <?php endif; ?>

    <!-- Educational Callout -->
    <div class="bg-blue-50 border-l-4 border-secondary p-5 mb-8 rounded-r-xl shadow-inner">
        <h4 class="font-bold text-primary mb-1 flex items-center"><i class="fas fa-info-circle text-secondary mr-2"></i> When should I use this form?</h4>
        <p class="text-sm text-gray-700 leading-relaxed">
            Use this form <strong>after</strong> an event has occurred that resulted in personal injury (to an employee or customer) or significant property damage. If you are reporting a proactive risk that has <em>not</em> caused harm yet, please use the <a href="/hazard-report" class="text-secondary font-bold hover:underline">Hazard Report</a> instead.
        </p>
    </div>

    <!-- Incident Submission Form -->
    <form action="/incident-report" method="POST" class="space-y-6">
        <div class="card p-6 md:p-8 space-y-8 shadow-md border border-gray-200 rounded-xl">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Location Selection -->
                <div class="md:col-span-2">
                    <label for="store_id" class="form-label">Branch / Location <span class="text-accent-red">*</span></label>
                    <select id="store_id" name="store_id" required class="form-input cursor-pointer shadow-sm bg-gray-50 focus:bg-white transition-colors">
                        <option value="" disabled selected>-- Select the Location of the Incident --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>">
                                <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date & Time -->
                <div>
                    <label for="incident_date" class="form-label">Date of Incident <span class="text-accent-red">*</span></label>
                    <input type="date" id="incident_date" name="incident_date" required class="form-input shadow-sm" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label for="incident_time" class="form-label">Time of Incident <span class="text-accent-red">*</span></label>
                    <input type="time" id="incident_time" name="incident_time" required class="form-input shadow-sm" value="<?php echo date('H:i'); ?>">
                </div>

                <!-- Incident Type -->
                <div class="md:col-span-2">
                    <label for="incident_type" class="form-label">Type of Incident <span class="text-accent-red">*</span></label>
                    <select id="incident_type" name="incident_type" required class="form-input cursor-pointer shadow-sm bg-gray-50 focus:bg-white transition-colors">
                        <option value="" disabled selected>-- Select Classification --</option>
                        <option value="Employee Injury">Employee Injury / Medical Event</option>
                        <option value="Customer Injury">Customer / Client Injury</option>
                        <option value="Property Damage">Property / Equipment Damage</option>
                        <option value="Near Miss">Severe Near Miss</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <!-- Specific Location Details -->
                <div class="md:col-span-2">
                    <label for="location_details" class="form-label flex justify-between">
                        <span>Specific Location Details <span class="text-accent-red">*</span></span>
                        <span class="text-xs font-normal text-gray-400 normal-case">e.g., Aisle 4, Loading Dock B</span>
                    </label>
                    <input type="text" id="location_details" name="location_details" required class="form-input shadow-sm" placeholder="Where exactly did the incident occur?">
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label for="description" class="form-label">Detailed Description of Incident <span class="text-accent-red">*</span></label>
                    <textarea id="description" name="description" required class="form-input shadow-sm min-h-[140px]" placeholder="Explain exactly what happened, who was involved, what tasks were being performed, and the outcome..."></textarea>
                </div>

                <!-- Immediate Actions -->
                <div class="md:col-span-2">
                    <label for="immediate_actions" class="form-label flex justify-between">
                        <span>Immediate Actions Taken <span class="text-accent-red">*</span></span>
                    </label>
                    <textarea id="immediate_actions" name="immediate_actions" required class="form-input shadow-sm min-h-[100px] border-l-4 border-l-accent-red" placeholder="e.g., First aid applied by Supervisor, area cordoned off, emergency services contacted..."></textarea>
                </div>
                
            </div>
        </div>

        <!-- Action Bar -->
        <div class="flex flex-col sm:flex-row justify-between items-center mt-8 pt-4 gap-4">
            <a href="/dashboard" class="text-sm font-bold text-gray-500 hover:text-primary transition-colors order-2 sm:order-1">
                <i class="fas fa-arrow-left mr-2"></i> Cancel &amp; Return
            </a>
            <button type="submit" class="btn btn-accent text-lg px-10 shadow-xl transform hover:-translate-y-1 transition-all w-full sm:w-auto order-1 sm:order-2 flex justify-center items-center">
                Submit Incident Report <i class="fas fa-paper-plane ml-3"></i>
            </button>
        </div>
    </form>
</div>