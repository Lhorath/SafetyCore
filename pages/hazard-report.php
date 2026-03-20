<?php
/**
 * Hazard Report Form - pages/hazard-report.php
 *
 * This file handles the display and processing of the hazard reporting interface.
 * It features a multi-section form with dynamic dropdowns (powered by the API),
 * complex file upload validation, and secure database transactions.
 *
 * Features:
 * - Tailwind CSS styling (Cards, Grid, Modern Inputs).
 * - Server-side validation and file handling.
 * - Transaction-based database insertion for data integrity.
 * - Integration with JavaScript in footer.php for dynamic store/location logic.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// --- 1. Security Check ---
// Redirect to the login page if no user is logged in.
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

// Initialize variables for user feedback
$successMessage = '';
$errorMessage = '';
$uploadedFilePaths = []; // Track files for cleanup if DB insert fails

$companyId = (int)($_SESSION['user']['company_id'] ?? 0);
$userId = (int)($_SESSION['user']['id'] ?? 0);
$userRole = $_SESSION['user']['role_name'] ?? '';
$isManager = in_array($userRole, ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager']);

// --- 2. Initial Data Fetch ---
// Fetch stores scoped by company; non-managers only see stores they are assigned to.
$stores = [];
if ($companyId) {
    if ($isManager) {
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
    } else {
        $sql = "SELECT s.id, s.store_name, s.store_number
                FROM stores s
                INNER JOIN user_stores us ON s.id = us.store_id AND us.user_id = ?
                WHERE s.company_id = ?
                ORDER BY s.store_name ASC";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $userId, $companyId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $stores[] = $row;
            }
            $stmt->close();
        }
    }
}
$allowedStoreIds = array_column($stores, 'id');

// --- 3. Form Processing (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($errorMessage)) {
        // $errorMessage set by csrf_check; do not process form
    } else {
    // --- Server-Side Configuration ---
    // These limits are checked here but depend on php.ini (upload_max_filesize, post_max_size)
    define('MAX_PHOTO_SIZE', 2 * 1024 * 1024); // 2 MB
    define('MAX_PHOTO_COUNT', 5);
    define('MAX_VIDEO_SIZE', 200 * 1024 * 1024); // 200 MB
    define('MAX_VIDEO_COUNT', 2);
    $allowedPhotoExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $allowedPhotoMimes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedVideoExtensions = ['mp4', 'mov', 'wmv'];
    $allowedVideoMimes = ['video/mp4', 'video/quicktime', 'video/x-ms-wmv'];
    
    // Upload Directories (Relative to the index.php router)
    define('PHOTO_UPLOAD_DIR', 'reports/uploads/photos/'); 
    define('VIDEO_UPLOAD_DIR', 'reports/uploads/videos/');

    // Ensure upload directories exist
    if (!is_dir(PHOTO_UPLOAD_DIR)) mkdir(PHOTO_UPLOAD_DIR, 0755, true);
    if (!is_dir(VIDEO_UPLOAD_DIR)) mkdir(VIDEO_UPLOAD_DIR, 0755, true);
    
    // Start a database transaction
    // This ensures that we don't save a partial report if file uploads fail, or vice versa.
    $conn->begin_transaction();
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    
    try {
        // --- A. Process Photo Uploads ---
        $photoFilesToInsert = [];
        if (isset($_FILES['photoUpload']) && !empty($_FILES['photoUpload']['name'][0])) {
            $photos = $_FILES['photoUpload'];
            
            if (count($photos['name']) > MAX_PHOTO_COUNT) {
                throw new Exception('You can upload a maximum of ' . MAX_PHOTO_COUNT . ' photos.');
            }

            for ($i = 0; $i < count($photos['name']); $i++) {
                if ($photos['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($photos['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error uploading photo: ' . $photos['name'][$i]);
                }
                if ($photos['size'][$i] > MAX_PHOTO_SIZE) {
                    throw new Exception('Photo "' . htmlspecialchars($photos['name'][$i]) . '" exceeds the 2MB size limit.');
                }
                
                $fileExtension = strtolower(pathinfo($photos['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedPhotoExtensions, true)) {
                    throw new Exception('Photo "' . htmlspecialchars($photos['name'][$i]) . '" has an unsupported file extension.');
                }
                $detectedMime = $finfo ? finfo_file($finfo, $photos['tmp_name'][$i]) : '';
                if (!in_array($detectedMime, $allowedPhotoMimes, true)) {
                    throw new Exception('Photo "' . htmlspecialchars($photos['name'][$i]) . '" has an invalid file type.');
                }
                $uniqueName = uniqid('photo_', true) . '.' . $fileExtension;
                $targetPath = PHOTO_UPLOAD_DIR . $uniqueName;
                
                if (!move_uploaded_file($photos['tmp_name'][$i], $targetPath)) {
                    throw new Exception('Failed to move uploaded photo to server.');
                }
                
                $uploadedFilePaths[] = $targetPath; // Add to cleanup list on error
                $photoFilesToInsert[] = ['path' => $targetPath, 'size' => $photos['size'][$i]];
            }
        }

        // --- B. Process Video Uploads ---
        $videoFilesToInsert = [];
        if (isset($_FILES['videoUpload']) && !empty($_FILES['videoUpload']['name'][0])) {
            $videos = $_FILES['videoUpload'];
            
            if (count($videos['name']) > MAX_VIDEO_COUNT) {
                throw new Exception('You can upload a maximum of ' . MAX_VIDEO_COUNT . ' videos.');
            }

            for ($i = 0; $i < count($videos['name']); $i++) {
                if ($videos['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                if ($videos['error'][$i] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error uploading video: ' . $videos['name'][$i]);
                }
                if ($videos['size'][$i] > MAX_VIDEO_SIZE) {
                    throw new Exception('Video "' . htmlspecialchars($videos['name'][$i]) . '" exceeds the 200MB size limit.');
                }

                $fileExtension = strtolower(pathinfo($videos['name'][$i], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, $allowedVideoExtensions, true)) {
                    throw new Exception('Video "' . htmlspecialchars($videos['name'][$i]) . '" has an unsupported file extension.');
                }
                $detectedMime = $finfo ? finfo_file($finfo, $videos['tmp_name'][$i]) : '';
                if (!in_array($detectedMime, $allowedVideoMimes, true)) {
                    throw new Exception('Video "' . htmlspecialchars($videos['name'][$i]) . '" has an invalid file type.');
                }
                $uniqueName = uniqid('video_', true) . '.' . $fileExtension;
                $targetPath = VIDEO_UPLOAD_DIR . $uniqueName;
                
                if (!move_uploaded_file($videos['tmp_name'][$i], $targetPath)) {
                    throw new Exception('Failed to move uploaded video to server.');
                }
                
                $uploadedFilePaths[] = $targetPath; // Add to cleanup list on error
                $videoFilesToInsert[] = ['path' => $targetPath, 'size' => $videos['size'][$i]];
            }
        }

        // --- C. Process Text Data & Sanitization ---
        $storeId = filter_input(INPUT_POST, 'store', FILTER_VALIDATE_INT);
        if (!$storeId || !in_array($storeId, $allowedStoreIds)) {
            throw new Exception('Invalid store selected. Please choose a location you have access to.');
        }
        $reporterUserId = $_SESSION['user']['id']; // Securely use session ID
        
        $reportDate = ($_POST['reporterDate'] ?? '') . ' ' . ($_POST['reporterTime'] ?? '');
        $hazardLocationId = filter_input(INPUT_POST, 'hazardLocation', FILTER_VALIDATE_INT);
        $riskLevel = filter_input(INPUT_POST, 'riskLevel', FILTER_VALIDATE_INT);
        $hazardObservedAt = ($_POST['hazardDate'] ?? '') . ' ' . ($_POST['hazardTime'] ?? '');
        $hazardType = trim($_POST['hazardType'] ?? '');
        $hazardDescription = trim($_POST['hazardDescription'] ?? '');
        $potentialConsequences = trim($_POST['potentialConsequences'] ?? '');
        
        // Radio Buttons (Yes/No logic)
        $actionTaken = (isset($_POST['immediateActionTaken']) && $_POST['immediateActionTaken'] === 'yes') ? 1 : 0;
        $actionDescription = trim($_POST['actionDescription'] ?? '');
        $equipmentLockedOut = (isset($_POST['equipmentLockedOut']) && $_POST['equipmentLockedOut'] === 'yes') ? 1 : 0;
        $lockoutKeyHolder = $equipmentLockedOut ? trim($_POST['keyHolderName'] ?? '') : null;
        
        $notifiedUserId = filter_input(INPUT_POST, 'whoNotified', FILTER_VALIDATE_INT) ?: null;
        $additionalComments = trim($_POST['additionalComments'] ?? '');
        // Validate notified_user_id is an allowed supervisor for the selected store (when provided)
        if ($notifiedUserId !== null && $notifiedUserId > 0) {
            $supSql = "SELECT 1 FROM users u
                       INNER JOIN user_stores us ON u.id = us.user_id AND us.store_id = ?
                       INNER JOIN roles r ON u.role_id = r.id
                       INNER JOIN stores s ON s.id = us.store_id AND s.company_id = ?
                       WHERE u.id = ? AND r.role_name IN ('Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Co-manager', 'Owner / CEO')
                       LIMIT 1";
            $supStmt = $conn->prepare($supSql);
            if ($supStmt) {
                $supStmt->bind_param("iii", $storeId, $companyId, $notifiedUserId);
                $supStmt->execute();
                if (!$supStmt->get_result()->fetch_assoc()) {
                    $supStmt->close();
                    throw new Exception('Invalid person selected for "Who was notified". Please choose from the list for this location.');
                }
                $supStmt->close();
            }
        }
        
        // --- D. Insert Main Report Record ---
        $sql = "INSERT INTO reports (reporter_user_id, store_id, report_date, hazard_location_id, risk_level, hazard_observed_at, hazard_type, hazard_description, potential_consequences, action_taken, action_description, equipment_locked_out, lockout_key_holder, notified_user_id, additional_comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Database prepare error: " . $conn->error);
        
        $stmt->bind_param("iisiisssisisis", $reporterUserId, $storeId, $reportDate, $hazardLocationId, $riskLevel, $hazardObservedAt, $hazardType, $hazardDescription, $potentialConsequences, $actionTaken, $actionDescription, $equipmentLockedOut, $lockoutKeyHolder, $notifiedUserId, $additionalComments);
        
        if (!$stmt->execute()) throw new Exception("Database execute error: " . $stmt->error);
        
        $reportId = $conn->insert_id;
        $stmt->close();

        // --- E. Insert File Records ---
        if (!empty($photoFilesToInsert) || !empty($videoFilesToInsert)) {
            $fileSql = "INSERT INTO report_files (report_id, file_path, file_type, file_size) VALUES (?, ?, ?, ?)";
            $fileStmt = $conn->prepare($fileSql);

            // Save Photos
            foreach($photoFilesToInsert as $file) {
                $fileType = 'photo';
                $fileStmt->bind_param("issi", $reportId, $file['path'], $fileType, $file['size']);
                $fileStmt->execute();
            }
            // Save Videos
            foreach($videoFilesToInsert as $file) {
                $fileType = 'video';
                $fileStmt->bind_param("issi", $reportId, $file['path'], $fileType, $file['size']);
                $fileStmt->execute();
            }
            $fileStmt->close();
        }
        
        // --- F. Commit Transaction ---
        $conn->commit();
        if ($finfo) {
            finfo_close($finfo);
        }
        $successMessage = "Report submitted successfully! Thank you for your contribution to safety.";

    } catch (Exception $e) {
        if ($finfo) {
            finfo_close($finfo);
        }
        // Rollback DB changes if anything failed
        $conn->rollback();
        error_log('Hazard report submission failed: ' . $e->getMessage());
        $errorMessage = "An error occurred while submitting the report. Please try again or contact support.";
        
        // Cleanup uploaded files to prevent orphans
        foreach ($uploadedFilePaths as $path) {
            if (file_exists($path)) unlink($path);
        }
    }
    }
}
?>

<!-- Modal for adding a new location (Tailwind Styled) -->
<!-- The 'hidden' class is toggled by the JavaScript in footer.php -->
<div id="addLocationModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">Add New Hazard Location</h2>
            <span class="modal-close-btn cursor-pointer text-gray-500 text-2xl font-bold hover:text-accent-red transition">&times;</span>
        </div>
        <div class="mb-6">
            <label for="newLocationName" class="form-label">Location Name</label>
            <input type="text" id="newLocationName" class="form-input" placeholder="e.g., Lumber Aisle 4 - Rack B">
        </div>
        <button id="saveNewLocationBtn" class="btn btn-primary w-full">Save Location</button>
    </div>
</div>

<div class="max-w-4xl mx-auto">
    
    <!-- Page Header -->
    <div class="mb-8">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
            New Hazard Report
        </h2>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-check-circle mr-3 text-xl"></i>
            <div>
                <p class="font-bold">Success</p>
                <p><?php echo htmlspecialchars($successMessage); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-3 text-xl"></i>
            <div>
                <p class="font-bold">Error</p>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Form -->
    <form action="/hazard-report" method="POST" enctype="multipart/form-data" class="space-y-8">
        <?php csrf_field(); ?>
        
        <!-- Section 1: Reporter Information -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">1. Reporter Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="storeSelect" class="form-label">Store Location</label>
                    <select id="storeSelect" name="store" required class="form-input cursor-pointer">
                        <option value="">-- Select a Store --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Read-only reporter field (Session User) -->
                <div class="md:col-span-2">
                    <label class="form-label">Reported By</label>
                    <input type="text" value="<?php echo htmlspecialchars($_SESSION['user']['first_name']); ?> (You)" class="form-input bg-gray-100 text-gray-500 cursor-not-allowed" readonly>
                </div>

                <div>
                    <label for="reporterDate" class="form-label">Date Reported</label>
                    <input type="date" id="reporterDate" name="reporterDate" required class="form-input">
                </div>
                <div>
                    <label for="reporterTime" class="form-label">Time Reported</label>
                    <input type="time" id="reporterTime" name="reporterTime" required class="form-input">
                </div>
            </div>
        </div>

        <!-- Section 2: Hazard Details -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">2. Hazard Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Dynamic Location Select + Add New Button -->
                <div class="md:col-span-2">
                    <label for="hazardLocationSelect" class="form-label">Location of Hazard</label>
                    <div class="flex gap-2">
                        <select id="hazardLocationSelect" name="hazardLocation" required disabled class="form-input flex-grow bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 transition-colors">
                            <option value="">-- Select a store first --</option>
                        </select>
                        <button type="button" id="addNewLocationBtn" disabled class="btn bg-gray-400 text-white cursor-not-allowed whitespace-nowrap px-4 py-2 text-sm shadow-sm transition-colors">
                            <i class="fas fa-plus mr-1"></i> Add New
                        </button>
                    </div>
                </div>
                
                <div class="md:col-span-2">
                    <label for="riskLevelSelect" class="form-label">Level of Risk</label>
                    <select id="riskLevelSelect" name="riskLevel" required class="form-input cursor-pointer">
                        <option value="">-- Select Risk Level --</option>
                        <option value="1">1 - Potentially Dangerous</option>
                        <option value="2">2 - Severe Risk</option>
                        <option value="3">3 - Near Miss</option>
                    </select>
                </div>

                <div>
                    <label for="hazardDate" class="form-label">Date Observed</label>
                    <input type="date" id="hazardDate" name="hazardDate" required class="form-input">
                </div>
                <div>
                    <label for="hazardTime" class="form-label">Time Observed</label>
                    <input type="time" id="hazardTime" name="hazardTime" required class="form-input">
                </div>

                <div class="md:col-span-2">
                    <label for="hazardType" class="form-label">Type of Hazard</label>
                    <select id="hazardType" name="hazardType" required class="form-input cursor-pointer">
                        <option value="">Select One</option>
                        <option value="physical">Physical (trip hazard, falling object)</option>
                        <option value="chemical">Chemical (spill, improper storage)</option>
                        <option value="ergonomic">Ergonomic (lifting, repetitive motion)</option>
                        <option value="biological">Biological (pest, mold)</option>
                        <option value="electrical">Electrical (faulty wiring)</option>
                        <option value="mechanical">Mechanical (unguarded machinery)</option>
                        <option value="psychological">Psychological (stress, bullying)</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="hazardDescription" class="form-label">Description</label>
                    <textarea id="hazardDescription" name="hazardDescription" required class="form-input min-h-[120px]" placeholder="Describe the hazard in detail..."></textarea>
                </div>
                <div class="md:col-span-2">
                    <label for="potentialConsequences" class="form-label">Potential Consequences</label>
                    <textarea id="potentialConsequences" name="potentialConsequences" class="form-input min-h-[80px]" placeholder="What could happen if this isn't fixed?"></textarea>
                </div>
            </div>
        </div>

        <!-- Section 3: Immediate Actions -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">3. Immediate Actions Taken</h3>
            <div class="space-y-6">
                
                <!-- Action Taken Radios -->
                <div>
                    <label class="form-label mb-2">Was immediate action taken?</label>
                    <div class="flex gap-6" id="actionTakenRadioGroup">
                        <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition w-full md:w-auto">
                            <input type="radio" name="immediateActionTaken" value="yes" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary">
                            <span class="ml-2 text-gray-700 font-medium">Yes</span>
                        </label>
                        <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition w-full md:w-auto">
                            <input type="radio" name="immediateActionTaken" value="no" checked class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary">
                            <span class="ml-2 text-gray-700 font-medium">No</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label id="actionDescriptionLabel" for="actionDescription" class="form-label">If No, why not?</label>
                    <textarea id="actionDescription" name="actionDescription" required class="form-input min-h-[80px]"></textarea>
                </div>

                <!-- Lockout Radios -->
                <div>
                    <label class="form-label mb-2">Was a piece of equipment Locked Out?</label>
                    <div class="flex gap-6" id="lockoutRadioGroup">
                        <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition w-full md:w-auto">
                            <input type="radio" name="equipmentLockedOut" value="yes" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary">
                            <span class="ml-2 text-gray-700 font-medium">Yes</span>
                        </label>
                        <label class="flex items-center cursor-pointer p-3 border rounded-lg hover:bg-gray-50 transition w-full md:w-auto">
                            <input type="radio" name="equipmentLockedOut" value="no" checked class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary">
                            <span class="ml-2 text-gray-700 font-medium">No</span>
                        </label>
                    </div>
                </div>

                <!-- Conditional Key Holder Input -->
                <div id="keyHolderGroup" class="hazard-key-holder-hidden">
                    <label for="keyHolderName" class="form-label">Who holds the key?</label>
                    <input type="text" id="keyHolderName" name="keyHolderName" class="form-input">
                </div>

                <!-- Supervisor Notification Dropdown -->
                <div>
                    <label for="whoNotifiedSelect" class="form-label">Who was notified immediately?</label>
                    <select id="whoNotifiedSelect" name="whoNotified" disabled class="form-input bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 transition-colors">
                        <option value="">-- Select a store first --</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Section 4: Supporting Information -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">4. Supporting Information</h3>
            <div class="space-y-6">
                <div>
                    <label for="photoUpload" class="form-label flex items-center">
                        <i class="fas fa-camera mr-2 text-primary"></i> Upload Photos
                    </label>
                    <span class="text-xs text-gray-500 block mb-2">Max 5 photos, 2MB each (JPG, PNG)</span>
                    <input type="file" id="photoUpload" name="photoUpload[]" accept="image/jpeg, image/png, image/gif" multiple 
                           class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-opacity-90 cursor-pointer">
                </div>
                <div>
                    <label for="videoUpload" class="form-label flex items-center">
                        <i class="fas fa-video mr-2 text-primary"></i> Upload Videos
                    </label>
                    <span class="text-xs text-gray-500 block mb-2">Max 2 videos, 200MB each (MP4, MOV)</span>
                    <input type="file" id="videoUpload" name="videoUpload[]" accept="video/mp4, video/quicktime, video/x-ms-wmv" multiple 
                           class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-secondary file:text-white hover:file:bg-opacity-90 cursor-pointer">
                </div>
                <div>
                    <label for="additionalComments" class="form-label">Additional Comments</label>
                    <textarea id="additionalComments" name="additionalComments" class="form-input min-h-[80px]" placeholder="Any other details?"></textarea>
                </div>
            </div>
        </div>

        <!-- Submission -->
        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" class="btn btn-primary text-lg px-10 shadow-lg transform hover:-translate-y-1 flex items-center">
                Submit Hazard Report <i class="fas fa-paper-plane ml-3"></i>
            </button>
        </div>
    </form>
</div>