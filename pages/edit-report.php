<?php
/**
 * Edit Hazard Report Page - pages/edit-report.php
 *
 * Provides a secure interface for users to update text details and risk 
 * level of an existing report, provided the report is not marked as 'Closed'.
 * Ensures that users can only edit their own reports.
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

// Validate the report ID from the URL parameters
$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect to My Reports if no valid ID is provided
if (!$reportId) {
    header('Location: /my-reports');
    exit();
}

$successMessage = '';
$errorMessage = '';

// --- 2. Fetch Existing Report Data ---
$sql = "SELECT r.*, hl.location_name 
        FROM reports r
        JOIN hazard_locations hl ON r.hazard_location_id = hl.id
        WHERE r.id = ? AND r.reporter_user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $reportId, $userId);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- 3. Authorization & State Check ---
if (!$report) {
    $errorMessage = "Report not found or you do not have permission to edit it.";
} elseif ($report['status'] === 'Closed') {
    $errorMessage = "This report is marked as 'Closed' and can no longer be edited.";
}

// --- 4. Process Form Submission (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errorMessage)) {
    if (!csrf_check($errorMessage)) {
        // $errorMessage set by csrf_check; do not process form
    } else {
    // Sanitize and validate inputs
    $riskLevel = filter_input(INPUT_POST, 'riskLevel', FILTER_VALIDATE_INT);
    $hazardDescription = trim($_POST['hazardDescription'] ?? '');
    $potentialConsequences = trim($_POST['potentialConsequences'] ?? '');
    $actionDescription = trim($_POST['actionDescription'] ?? '');
    
    if (empty($hazardDescription) || empty($actionDescription) || !$riskLevel) {
        $errorMessage = "Please fill in all required fields.";
    } else {
        // Prepare update query. We re-verify the reporter_user_id here for strict security.
        $updateSql = "UPDATE reports SET 
                        risk_level = ?, 
                        hazard_description = ?, 
                        potential_consequences = ?, 
                        action_description = ? 
                      WHERE id = ? AND reporter_user_id = ?";
                      
        $upStmt = $conn->prepare($updateSql);
        $upStmt->bind_param("isssii", $riskLevel, $hazardDescription, $potentialConsequences, $actionDescription, $reportId, $userId);
        
        if ($upStmt->execute()) {
            $successMessage = "Report details updated successfully.";
            
            // Refresh the local data array so the form displays the newly saved values
            $report['risk_level'] = $riskLevel;
            $report['hazard_description'] = $hazardDescription;
            $report['potential_consequences'] = $potentialConsequences;
            $report['action_description'] = $actionDescription;
        } else {
            $errorMessage = "An error occurred while updating the report. Please try again.";
        }
        $upStmt->close();
    }
    }
}
?>

<div class="max-w-3xl mx-auto">
    
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2">
            Edit Report <span class="text-secondary">#<?php echo htmlspecialchars($reportId); ?></span>
        </h2>
        <a href="/my-reports" class="text-gray-500 hover:text-primary transition flex items-center text-sm font-medium bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm hover:shadow">
            <i class="fas fa-arrow-left mr-2"></i> Back to My Reports
        </a>
    </div>

    <!-- Feedback Messaging -->
    <?php if ($successMessage): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg shadow-sm flex items-center animate-fade-in-up">
            <i class="fas fa-check-circle mr-3 text-xl"></i> 
            <div class="font-medium"><?php echo htmlspecialchars($successMessage); ?></div>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded-r-lg shadow-sm flex items-center animate-fade-in-up">
            <i class="fas fa-exclamation-circle mr-3 text-xl"></i> 
            <div class="font-medium"><?php echo htmlspecialchars($errorMessage); ?></div>
        </div>
        <!-- Hide form if fatal error (e.g. Report is Closed or User does not own it) -->
        <?php if (!$report || $report['status'] === 'Closed'): ?>
            <div class="mt-6 text-center">
                <a href="/my-reports" class="btn btn-primary inline-flex items-center shadow-md">
                    <i class="fas fa-undo mr-2"></i> Return to My Reports
                </a>
            </div>
            </div> <!-- Close max-w-3xl container early -->
            <?php exit(); ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Edit Form -->
    <form action="/edit-report?id=<?php echo $reportId; ?>" method="POST" class="space-y-6">
        <?php csrf_field(); ?>
        
        <!-- Read-Only Context Data Panel -->
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600 shadow-inner">
            <div>
                <span class="font-bold block text-gray-400 uppercase tracking-wider text-[10px] mb-1">Location</span>
                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($report['location_name']); ?></span>
            </div>
            <div>
                <span class="font-bold block text-gray-400 uppercase tracking-wider text-[10px] mb-1">Date Logged</span>
                <span class="font-medium text-gray-800"><?php echo date('F j, Y, g:i a', strtotime($report['created_at'])); ?></span>
            </div>
            <div>
                <span class="font-bold block text-gray-400 uppercase tracking-wider text-[10px] mb-1">Status</span>
                <?php 
                    $statusClass = $report['status'] === 'Open' ? 'text-green-600 bg-green-100' : 'text-orange-600 bg-orange-100';
                ?>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($report['status']); ?>
                </span>
            </div>
            <div>
                <span class="font-bold block text-gray-400 uppercase tracking-wider text-[10px] mb-1">Hazard Type</span>
                <span class="font-medium text-gray-800"><?php echo ucfirst(htmlspecialchars($report['hazard_type'])); ?></span>
            </div>
        </div>

        <!-- Editable Fields Panel -->
        <div class="card space-y-6">
            
            <!-- Risk Level Dropdown -->
            <div>
                <label for="riskLevel" class="form-label">Level of Risk</label>
                <select id="riskLevel" name="riskLevel" required class="form-input cursor-pointer">
                    <option value="1" <?php echo ($report['risk_level'] == 1) ? 'selected' : ''; ?>>1 - Potentially Dangerous (Minor)</option>
                    <option value="2" <?php echo ($report['risk_level'] == 2) ? 'selected' : ''; ?>>2 - Severe Risk (Moderate/High)</option>
                    <option value="3" <?php echo ($report['risk_level'] == 3) ? 'selected' : ''; ?>>3 - Near Miss (Critical)</option>
                </select>
            </div>

            <!-- Hazard Description Textarea -->
            <div>
                <label for="hazardDescription" class="form-label">Hazard Description</label>
                <textarea id="hazardDescription" name="hazardDescription" required class="form-input min-h-[120px]" placeholder="Provide a detailed description of the hazard..."><?php echo htmlspecialchars($report['hazard_description']); ?></textarea>
            </div>
            
            <!-- Potential Consequences Textarea -->
            <div>
                <label for="potentialConsequences" class="form-label">Potential Consequences <span class="text-gray-400 font-normal text-xs ml-1">(Optional)</span></label>
                <textarea id="potentialConsequences" name="potentialConsequences" class="form-input min-h-[80px]" placeholder="What could have happened if this was not addressed?"><?php echo htmlspecialchars($report['potential_consequences']); ?></textarea>
            </div>

            <!-- Immediate Action Taken Textarea -->
            <div>
                <label for="actionDescription" class="form-label">Immediate Action Taken</label>
                <textarea id="actionDescription" name="actionDescription" required class="form-input min-h-[100px]" placeholder="Describe what was done immediately to mitigate the risk, or explain why no action was taken..."><?php echo htmlspecialchars($report['action_description']); ?></textarea>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all text-lg px-8">
                Save Changes <i class="fas fa-save ml-2"></i>
            </button>
        </div>
    </form>
</div>