<?php
/**
 * Admin View: Manage Company - pages/admin-views/manage-company.php
 *
 * Supports both Multi-Location and Job-Based company structures.
 * - Multi-Location: lists and adds permanent stores/branches.
 * - Job-Based: lists and adds temporary job sites.
 *
 * The view is fully driven by $companyCtx (from get_company_context()).
 * No hardcoded "store" references remain — all labels are dynamic.
 *
 * Beta 06 Changes:
 * - Reads company_type from session to branch UI and form logic.
 * - Adds job site creation form for job-based companies.
 * - Audit F-08 fix: all location queries scoped to company_id.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

require_once __DIR__ . '/../../includes/company_context.php';

$companyId  = $_SESSION['user']['company_id'] ?? 1;
$companyCtx = get_company_context($conn, $companyId);
$isJobBased = ($companyCtx['type'] === 'job_based');
$isSystem   = $companyCtx['is_system'];
$adminBaseRoute = $adminBaseRoute ?? '/admin';
// company-admin uses `view=structure`, platform admin uses `view=manage-company`.
$manageViewParam = (isset($view) && $view === 'structure') ? 'structure' : 'manage-company';
$assignmentFilter = strtolower(trim((string)($_GET['assignment'] ?? 'all')));
if (!in_array($assignmentFilter, ['all', 'unassigned', 'assigned'], true)) {
    $assignmentFilter = 'all';
}
$editLocationId = filter_input(INPUT_GET, 'edit_location', FILTER_VALIDATE_INT) ?: 0;
$editLocation = null;

// ─── POST: Handle Add Location ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'add-location'));
    if ($action === 'update-location-fields') {
        $locationId = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
        if (!$locationId || !validate_location_ownership($conn, (int)$locationId, (int)$companyId, $companyCtx['type'])) {
            $errorMessage = "Invalid location selected for update.";
        } else {
            if ($isJobBased) {
                $jobNumber = trim((string)($_POST['job_number'] ?? ''));
                $jobName = trim((string)($_POST['job_name'] ?? ''));
                $clientName = trim((string)($_POST['client_name'] ?? ''));
                $siteAddress = trim((string)($_POST['site_address'] ?? ''));
                $city = trim((string)($_POST['city'] ?? ''));
                $provinceState = trim((string)($_POST['province_state'] ?? ''));
                $status = trim((string)($_POST['status'] ?? 'Active'));
                $startDate = trim((string)($_POST['start_date'] ?? ''));
                $endDate = trim((string)($_POST['end_date'] ?? ''));
                $supervisorUserId = filter_input(INPUT_POST, 'supervisor_user_id', FILTER_VALIDATE_INT);
                if ($supervisorUserId === false) {
                    $supervisorUserId = null;
                }

                $allowedJobStatuses = ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled'];
                if ($jobNumber === '' || $jobName === '') {
                    $errorMessage = "Job Number and Job Name are required.";
                } elseif (!in_array($status, $allowedJobStatuses, true)) {
                    $errorMessage = "Invalid job status selected.";
                } elseif ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                    $errorMessage = "Start date format is invalid.";
                } elseif ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                    $errorMessage = "End date format is invalid.";
                } elseif ($startDate !== '' && $endDate !== '' && $endDate < $startDate) {
                    $errorMessage = "End date cannot be earlier than start date.";
                } else {
                    if ($supervisorUserId !== null) {
                        $candStmt = $conn->prepare(
                            "SELECT u.id
                             FROM users u
                             JOIN user_job_sites ujs ON u.id = ujs.user_id
                             JOIN job_sites js ON ujs.job_site_id = js.id
                             WHERE u.id = ? AND js.company_id = ?
                             LIMIT 1"
                        );
                        $candStmt->bind_param("ii", $supervisorUserId, $companyId);
                        $candStmt->execute();
                        $candidateValid = $candStmt->get_result()->fetch_assoc() !== null;
                        $candStmt->close();
                        if (!$candidateValid) {
                            $errorMessage = "Selected supervisor is not part of this company.";
                        }
                    }
                }

                if (empty($errorMessage)) {
                    $clientName = ($clientName === '') ? null : $clientName;
                    $siteAddress = ($siteAddress === '') ? null : $siteAddress;
                    $city = ($city === '') ? null : $city;
                    $provinceState = ($provinceState === '') ? null : $provinceState;
                    $startDate = ($startDate === '') ? null : $startDate;
                    $endDate = ($endDate === '') ? null : $endDate;

                    $stmt = $conn->prepare(
                        "UPDATE job_sites
                         SET job_number = ?, job_name = ?, client_name = ?, site_address = ?, city = ?, province_state = ?,
                             status = ?, start_date = ?, end_date = ?, supervisor_user_id = ?
                         WHERE id = ? AND company_id = ?"
                    );
                    $stmt->bind_param(
                        "sssssssssiii",
                        $jobNumber, $jobName, $clientName, $siteAddress, $city, $provinceState,
                        $status, $startDate, $endDate, $supervisorUserId, $locationId, $companyId
                    );

                    if ($stmt->execute()) {
                        $successMessage = "Job site updated successfully.";
                        $companyCtx = get_company_context($conn, $companyId);
                        $editLocationId = 0;
                    } else {
                        $errorMessage = ($conn->errno === 1062)
                            ? "A job site with that number already exists."
                            : "Database error. Could not update the job site.";
                    }
                    $stmt->close();
                }
            } else {
                $storeName = trim((string)($_POST['store_name'] ?? ''));
                $storeNumber = trim((string)($_POST['store_number'] ?? ''));
                $locationType = trim((string)($_POST['location_type'] ?? 'store'));
                $address = trim((string)($_POST['address'] ?? ''));
                $city = trim((string)($_POST['city'] ?? ''));
                $provinceState = trim((string)($_POST['province_state'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $managerUserId = filter_input(INPUT_POST, 'manager_user_id', FILTER_VALIDATE_INT);
                $jhscLeaderUserId = filter_input(INPUT_POST, 'jhsc_leader_user_id', FILTER_VALIDATE_INT);
                if ($managerUserId === false) {
                    $managerUserId = null;
                }
                if ($jhscLeaderUserId === false) {
                    $jhscLeaderUserId = null;
                }

                $allowedLocationTypes = ['store', 'office', 'warehouse', 'facility', 'other'];
                if ($storeName === '' || $storeNumber === '') {
                    $errorMessage = "Location Name and Number are required.";
                } elseif (!in_array($locationType, $allowedLocationTypes, true)) {
                    $errorMessage = "Invalid location type selected.";
                } else {
                    if ($managerUserId !== null) {
                        $candStmt = $conn->prepare(
                            "SELECT u.id
                             FROM users u
                             JOIN user_stores us ON u.id = us.user_id
                             JOIN stores s ON us.store_id = s.id
                             WHERE u.id = ? AND s.company_id = ?
                             LIMIT 1"
                        );
                        $candStmt->bind_param("ii", $managerUserId, $companyId);
                        $candStmt->execute();
                        $candidateValid = $candStmt->get_result()->fetch_assoc() !== null;
                        $candStmt->close();
                        if (!$candidateValid) {
                            $errorMessage = "Selected manager is not part of this company.";
                        }
                    }

                    if (empty($errorMessage) && $jhscLeaderUserId !== null) {
                        $candStmt = $conn->prepare(
                            "SELECT u.id
                             FROM users u
                             JOIN user_stores us ON u.id = us.user_id
                             JOIN stores s ON us.store_id = s.id
                             WHERE u.id = ? AND s.company_id = ?
                             LIMIT 1"
                        );
                        $candStmt->bind_param("ii", $jhscLeaderUserId, $companyId);
                        $candStmt->execute();
                        $candidateValid = $candStmt->get_result()->fetch_assoc() !== null;
                        $candStmt->close();
                        if (!$candidateValid) {
                            $errorMessage = "Selected JHSC leader is not part of this company.";
                        }
                    }
                }

                if (empty($errorMessage)) {
                    $address = ($address === '') ? null : $address;
                    $city = ($city === '') ? null : $city;
                    $provinceState = ($provinceState === '') ? null : $provinceState;

                    $stmt = $conn->prepare(
                        "UPDATE stores
                         SET store_name = ?, store_number = ?, location_type = ?, address = ?, city = ?, province_state = ?,
                             is_active = ?, manager_user_id = ?, jhsc_leader_user_id = ?
                         WHERE id = ? AND company_id = ?"
                    );
                    $stmt->bind_param(
                        "ssssssiiiii",
                        $storeName, $storeNumber, $locationType, $address, $city, $provinceState,
                        $isActive, $managerUserId, $jhscLeaderUserId, $locationId, $companyId
                    );

                    if ($stmt->execute()) {
                        $successMessage = "Location updated successfully.";
                        $companyCtx = get_company_context($conn, $companyId);
                        $editLocationId = 0;
                    } else {
                        $errorMessage = ($conn->errno === 1062)
                            ? "A location with that number already exists."
                            : "Database error. Could not update the location.";
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($action === 'assign-location-manager') {
        $locationId = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT);
        $managerUserId = filter_input(INPUT_POST, 'manager_user_id', FILTER_VALIDATE_INT);
        if ($managerUserId === false) {
            $managerUserId = null;
        }

        if (!$locationId || !validate_location_ownership($conn, (int)$locationId, (int)$companyId, $companyCtx['type'])) {
            $errorMessage = "Invalid location selected.";
        } else {
            if ($managerUserId !== null) {
                if ($isJobBased) {
                    $candStmt = $conn->prepare(
                        "SELECT u.id
                         FROM users u
                         JOIN user_job_sites ujs ON u.id = ujs.user_id
                         JOIN job_sites js ON ujs.job_site_id = js.id
                         WHERE u.id = ? AND js.company_id = ?
                         LIMIT 1"
                    );
                } else {
                    $candStmt = $conn->prepare(
                        "SELECT u.id
                         FROM users u
                         JOIN user_stores us ON u.id = us.user_id
                         JOIN stores s ON us.store_id = s.id
                         WHERE u.id = ? AND s.company_id = ?
                         LIMIT 1"
                    );
                }
                $candStmt->bind_param("ii", $managerUserId, $companyId);
                $candStmt->execute();
                $candidateValid = $candStmt->get_result()->fetch_assoc() !== null;
                $candStmt->close();

                if (!$candidateValid) {
                    $errorMessage = "Selected manager is not part of this company.";
                }
            }

            if (empty($errorMessage)) {
                if ($isJobBased) {
                    if ($managerUserId === null) {
                        $setStmt = $conn->prepare(
                            "UPDATE job_sites
                             SET supervisor_user_id = NULL
                             WHERE id = ? AND company_id = ?"
                        );
                        $setStmt->bind_param("ii", $locationId, $companyId);
                    } else {
                        $setStmt = $conn->prepare(
                            "UPDATE job_sites
                             SET supervisor_user_id = ?
                             WHERE id = ? AND company_id = ?"
                        );
                        $setStmt->bind_param("iii", $managerUserId, $locationId, $companyId);
                    }
                } else {
                    if ($managerUserId === null) {
                        $setStmt = $conn->prepare(
                            "UPDATE stores
                             SET manager_user_id = NULL
                             WHERE id = ? AND company_id = ?"
                        );
                        $setStmt->bind_param("ii", $locationId, $companyId);
                    } else {
                        $setStmt = $conn->prepare(
                            "UPDATE stores
                             SET manager_user_id = ?
                             WHERE id = ? AND company_id = ?"
                        );
                        $setStmt->bind_param("iii", $managerUserId, $locationId, $companyId);
                    }
                }

                if ($setStmt->execute()) {
                    $successMessage = $isJobBased
                        ? "Site supervisor updated successfully."
                        : "Location manager updated successfully.";
                    $companyCtx = get_company_context($conn, $companyId);
                } else {
                    $errorMessage = "Database error. Could not update manager assignment.";
                }
                $setStmt->close();
            }
        }
    } else {

    if ($isJobBased) {
        // Job-Based: Add Job Site
        $jobNumber   = trim($_POST['job_number'] ?? '');
        $jobName     = trim($_POST['job_name'] ?? '');
        $clientName  = trim($_POST['client_name'] ?? '');
        $siteAddress = trim($_POST['site_address'] ?? '');
        $city        = trim($_POST['city'] ?? '');
        $startDate   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;

        if (empty($jobNumber) || empty($jobName)) {
            $errorMessage = "Job Number and Job Name are required.";
        } else {
            $sql  = "INSERT INTO job_sites
                        (company_id, job_number, job_name, client_name, site_address, city, start_date, created_by_user_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "issssssi",
                $companyId, $jobNumber, $jobName, $clientName,
                $siteAddress, $city, $startDate,
                $_SESSION['user']['id']
            );

            if ($stmt->execute()) {
                $successMessage = "Job site '{$jobName}' added successfully.";
                // Refresh context after insert
                $companyCtx = get_company_context($conn, $companyId);
            } else {
                $errorMessage = ($conn->errno === 1062)
                    ? "Error: A job site with this number already exists."
                    : "Database error. Could not add job site.";
            }
            $stmt->close();
        }

    } else {
        // Multi-Location: Add Store (existing logic, now scoped + audit-clean)
        $storeName   = trim($_POST['store_name'] ?? '');
        $storeNumber = trim($_POST['store_number'] ?? '');
        $locType     = trim($_POST['location_type'] ?? 'store');
        $city        = trim($_POST['city'] ?? '');

        if (empty($storeName) || empty($storeNumber)) {
            $errorMessage = "Store Name and Store Number are required.";
        } else {
            $sql  = "INSERT INTO stores (company_id, store_name, store_number, location_type, city)
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issss", $companyId, $storeName, $storeNumber, $locType, $city);

            if ($stmt->execute()) {
                $successMessage = "Location '{$storeName}' added successfully.";
                $companyCtx = get_company_context($conn, $companyId);
            } else {
                $errorMessage = ($conn->errno === 1062)
                    ? "Error: A location with this number already exists."
                    : "Database error. Could not add location.";
            }
            $stmt->close();
        }
    }
    }
}

$locations = $companyCtx['locations'];
$managerCandidates = [];
if ($isJobBased) {
    $mgrStmt = $conn->prepare(
        "SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position
         FROM users u
         JOIN roles r ON u.role_id = r.id
         JOIN user_job_sites ujs ON u.id = ujs.user_id
         JOIN job_sites js ON ujs.job_site_id = js.id
         WHERE js.company_id = ?
           AND r.role_name IN ('Manager', 'Safety Manager', 'Site Supervisor', 'Company Admin', 'Owner / CEO', 'Co-manager')
         ORDER BY u.last_name, u.first_name"
    );
} else {
    $mgrStmt = $conn->prepare(
        "SELECT DISTINCT u.id, u.first_name, u.last_name, u.employee_position
         FROM users u
         JOIN roles r ON u.role_id = r.id
         JOIN user_stores us ON u.id = us.user_id
         JOIN stores s ON us.store_id = s.id
         WHERE s.company_id = ?
           AND r.role_name IN ('Manager', 'Safety Manager', 'Site Supervisor', 'Company Admin', 'Owner / CEO', 'Co-manager')
         ORDER BY u.last_name, u.first_name"
    );
}
if ($mgrStmt) {
    $mgrStmt->bind_param("i", $companyId);
    $mgrStmt->execute();
    $managerCandidates = $mgrStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mgrStmt->close();
}

if ($editLocationId > 0) {
    if (!validate_location_ownership($conn, $editLocationId, (int)$companyId, $companyCtx['type'])) {
        $errorMessage = "Edit target is invalid for your company.";
        $editLocationId = 0;
    } else {
        if ($isJobBased) {
            $editStmt = $conn->prepare(
                "SELECT id, job_number, job_name, client_name, site_address, city, province_state, status, start_date, end_date, supervisor_user_id
                 FROM job_sites
                 WHERE id = ? AND company_id = ?
                 LIMIT 1"
            );
        } else {
            $editStmt = $conn->prepare(
                "SELECT id, store_name, store_number, location_type, address, city, province_state, is_active, manager_user_id, jhsc_leader_user_id
                 FROM stores
                 WHERE id = ? AND company_id = ?
                 LIMIT 1"
            );
        }
        $editStmt->bind_param("ii", $editLocationId, $companyId);
        $editStmt->execute();
        $editLocation = $editStmt->get_result()->fetch_assoc() ?: null;
        $editStmt->close();
        if (!$editLocation) {
            $errorMessage = "Unable to load the location for editing.";
            $editLocationId = 0;
        }
    }
}

if ($assignmentFilter !== 'all') {
    $locations = array_values(array_filter(
        $locations,
        static function (array $loc) use ($isJobBased, $assignmentFilter): bool {
            $assignedId = (int)($isJobBased
                ? ($loc['supervisor_user_id'] ?? 0)
                : ($loc['manager_user_id'] ?? 0));
            if ($assignmentFilter === 'unassigned') {
                return $assignedId <= 0;
            }
            return $assignedId > 0;
        }
    ));
}
?>

<div class="max-w-6xl">

    <!-- View Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-1">
            <i class="fas <?php echo htmlspecialchars($companyCtx['location_icon']); ?> text-secondary text-2xl"></i>
            <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2 inline-block">
                Manage Company
            </h2>
        </div>
        <p class="text-sm text-gray-500 mt-1">
            <?php if ($isSystem): ?>
                Platform administration — managing the Sentry OHS system company.
            <?php elseif ($isJobBased): ?>
                <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded-full">
                    <i class="fas fa-hard-hat"></i> Job-Based Company
                </span>
                — configure temporary job sites and assign crews.
            <?php else: ?>
                <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-full">
                    <i class="fas fa-building"></i> Multi-Location Company
                </span>
                — manage permanent branches, stores, and facilities.
            <?php endif; ?>
        </p>
    </div>

    <!-- Feedback Messages -->
    <?php if (!empty($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($editLocation): ?>
        <div class="card mb-10 border-l-4 border-l-primary">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="text-xl font-bold text-primary">
                    Edit <?php echo $isJobBased ? 'Job Site' : 'Location'; ?>
                </h3>
                <a href="<?php echo htmlspecialchars($adminBaseRoute); ?>?<?php echo htmlspecialchars(http_build_query(['view' => $manageViewParam, 'assignment' => $assignmentFilter])); ?>"
                   class="btn btn-secondary !text-xs !px-3 !py-1.5">Cancel Edit</a>
            </div>

            <form action="<?php echo htmlspecialchars($adminBaseRoute); ?>?<?php echo htmlspecialchars(http_build_query(['view' => $manageViewParam, 'assignment' => $assignmentFilter, 'edit_location' => $editLocationId])); ?>" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="update-location-fields">
                <input type="hidden" name="location_id" value="<?php echo (int)$editLocation['id']; ?>">

                <?php if ($isJobBased): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="form-label" for="edit_job_number">Job / Project Number *</label>
                            <input id="edit_job_number" type="text" name="job_number" required class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['job_number'] ?? ''); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="edit_job_name">Project Name *</label>
                            <input id="edit_job_name" type="text" name="job_name" required class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['job_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_client_name">Client / Owner</label>
                            <input id="edit_client_name" type="text" name="client_name" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['client_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_city">City / Municipality</label>
                            <input id="edit_city" type="text" name="city" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['city'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_province_state">Province / State</label>
                            <input id="edit_province_state" type="text" name="province_state" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['province_state'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_status">Status</label>
                            <select id="edit_status" name="status" class="form-input cursor-pointer">
                                <?php $jobStatuses = ['Planning', 'Active', 'On Hold', 'Completed', 'Cancelled']; ?>
                                <?php foreach ($jobStatuses as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo (($editLocation['status'] ?? 'Active') === $st) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($st); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="edit_start_date">Start Date</label>
                            <input id="edit_start_date" type="date" name="start_date" class="form-input"
                                   value="<?php echo htmlspecialchars((string)($editLocation['start_date'] ?? '')); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_end_date">End Date</label>
                            <input id="edit_end_date" type="date" name="end_date" class="form-input"
                                   value="<?php echo htmlspecialchars((string)($editLocation['end_date'] ?? '')); ?>">
                        </div>
                        <div class="md:col-span-3">
                            <label class="form-label" for="edit_site_address">Site Address</label>
                            <input id="edit_site_address" type="text" name="site_address" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['site_address'] ?? ''); ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="edit_supervisor_user_id">Site Supervisor</label>
                            <select id="edit_supervisor_user_id" name="supervisor_user_id" class="form-input cursor-pointer">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($managerCandidates as $mgr): ?>
                                    <?php $mgrId = (int)$mgr['id']; ?>
                                    <option value="<?php echo $mgrId; ?>" <?php echo ($mgrId === (int)($editLocation['supervisor_user_id'] ?? 0)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name'] . (!empty($mgr['employee_position']) ? ' - ' . $mgr['employee_position'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="form-label" for="edit_store_name">Location Name *</label>
                            <input id="edit_store_name" type="text" name="store_name" required class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['store_name'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_store_number">Number / Code *</label>
                            <input id="edit_store_number" type="text" name="store_number" required class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['store_number'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_location_type">Type</label>
                            <select id="edit_location_type" name="location_type" class="form-input cursor-pointer">
                                <?php $storeTypes = ['store', 'office', 'warehouse', 'facility', 'other']; ?>
                                <?php foreach ($storeTypes as $tp): ?>
                                    <option value="<?php echo htmlspecialchars($tp); ?>" <?php echo (($editLocation['location_type'] ?? 'store') === $tp) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($tp)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="edit_address">Address</label>
                            <input id="edit_address" type="text" name="address" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['address'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_city_store">City</label>
                            <input id="edit_city_store" type="text" name="city" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['city'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_province_state_store">Province / State</label>
                            <input id="edit_province_state_store" type="text" name="province_state" class="form-input"
                                   value="<?php echo htmlspecialchars($editLocation['province_state'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="form-label" for="edit_manager_user_id">Manager</label>
                            <select id="edit_manager_user_id" name="manager_user_id" class="form-input cursor-pointer">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($managerCandidates as $mgr): ?>
                                    <?php $mgrId = (int)$mgr['id']; ?>
                                    <option value="<?php echo $mgrId; ?>" <?php echo ($mgrId === (int)($editLocation['manager_user_id'] ?? 0)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name'] . (!empty($mgr['employee_position']) ? ' - ' . $mgr['employee_position'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="edit_jhsc_leader_user_id">JHSC Leader</label>
                            <select id="edit_jhsc_leader_user_id" name="jhsc_leader_user_id" class="form-input cursor-pointer">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($managerCandidates as $mgr): ?>
                                    <?php $mgrId = (int)$mgr['id']; ?>
                                    <option value="<?php echo $mgrId; ?>" <?php echo ($mgrId === (int)($editLocation['jhsc_leader_user_id'] ?? 0)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name'] . (!empty($mgr['employee_position']) ? ' - ' . $mgr['employee_position'] : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-center mt-7">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300"
                                    <?php echo ((int)($editLocation['is_active'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                Active location
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- ── ADD LOCATION FORM ────────────────────────────────────────────── -->
    <div class="card mb-10 border-l-4 border-l-secondary">
        <div class="flex items-center mb-4">
            <div class="bg-blue-50 p-2 rounded-lg mr-3">
                <i class="fas <?php echo htmlspecialchars($companyCtx['location_icon']); ?> text-primary text-xl"></i>
            </div>
            <h3 class="text-xl font-bold text-primary">
                <?php echo htmlspecialchars($companyCtx['add_location_label']); ?>
            </h3>
        </div>

        <form action="<?php echo htmlspecialchars($adminBaseRoute); ?>?<?php echo htmlspecialchars(http_build_query(['view' => $manageViewParam, 'assignment' => $assignmentFilter])); ?>" method="POST">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="add-location">

            <?php if ($isJobBased): ?>
            <!-- ── JOB SITE FORM ── -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="form-label" for="job_number">Job / Project Number *</label>
                    <input type="text" id="job_number" name="job_number" required
                           class="form-input" placeholder="e.g., J-2026-014">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label" for="job_name">Project Name *</label>
                    <input type="text" id="job_name" name="job_name" required
                           class="form-input" placeholder="e.g., Highway 16 Overpass Rehabilitation">
                </div>
                <div>
                    <label class="form-label" for="client_name">Client / Owner</label>
                    <input type="text" id="client_name" name="client_name"
                           class="form-input" placeholder="e.g., Ministry of Transportation">
                </div>
                <div>
                    <label class="form-label" for="city">City / Municipality</label>
                    <input type="text" id="city" name="city"
                           class="form-input" placeholder="e.g., Prince George, BC">
                </div>
                <div>
                    <label class="form-label" for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input">
                </div>
                <div class="md:col-span-3">
                    <label class="form-label" for="site_address">Site Address</label>
                    <input type="text" id="site_address" name="site_address"
                           class="form-input" placeholder="Street address or GPS coordinates">
                </div>
                <div class="md:col-span-3 flex justify-end">
                    <button type="submit" class="btn btn-secondary shadow-md">
                        <i class="fas fa-plus mr-2"></i> Add Job Site
                    </button>
                </div>
            </div>

            <?php else: ?>
            <!-- ── STORE / BRANCH FORM ── -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <div>
                    <label class="form-label" for="store_name">Location Name *</label>
                    <input type="text" id="store_name" name="store_name" required
                           class="form-input" placeholder="e.g., Downtown Location">
                </div>
                <div>
                    <label class="form-label" for="store_number">Number / Code *</label>
                    <input type="text" id="store_number" name="store_number" required
                           class="form-input" placeholder="e.g., 5001-A">
                </div>
                <div>
                    <label class="form-label" for="location_type">Type</label>
                    <select id="location_type" name="location_type" class="form-input cursor-pointer">
                        <option value="store">Store</option>
                        <option value="office">Office</option>
                        <option value="warehouse">Warehouse</option>
                        <option value="facility">Facility</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-secondary w-full shadow-md">
                        <i class="fas fa-plus mr-2"></i> Add Location
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </form>
    </div>

    <!-- ── LOCATIONS TABLE ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 bg-gray-50 border-b border-gray-200 flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <h3 class="font-bold text-gray-500 uppercase text-xs tracking-wider">
                    <?php echo $isJobBased ? 'Active Job Sites' : 'Current Locations'; ?>
                </h3>
                <span class="text-xs bg-gray-200 text-gray-600 py-1 px-2 rounded-full font-bold">
                    <?php echo count($locations); ?> Showing
                </span>
            </div>
            <form method="GET" action="<?php echo htmlspecialchars($adminBaseRoute); ?>" class="flex items-center gap-2">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($manageViewParam); ?>">
                <label for="assignment" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Assignment</label>
                <select id="assignment" name="assignment" onchange="this.form.submit()" class="form-input !py-1.5 !px-2 text-xs w-36 cursor-pointer">
                    <option value="all" <?php echo ($assignmentFilter === 'all') ? 'selected' : ''; ?>>All</option>
                    <option value="unassigned" <?php echo ($assignmentFilter === 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                    <option value="assigned" <?php echo ($assignmentFilter === 'assigned') ? 'selected' : ''; ?>>Assigned</option>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white border-b border-gray-100 text-gray-400 font-bold uppercase text-xs tracking-wider">
                    <tr>
                        <?php if ($isJobBased): ?>
                            <th class="px-6 py-4">Job #</th>
                            <th class="px-6 py-4">Project Name</th>
                            <th class="px-6 py-4">Client</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Site Supervisor</th>
                        <?php else: ?>
                            <th class="px-6 py-4">Location Name</th>
                            <th class="px-6 py-4">Number</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">City</th>
                            <th class="px-6 py-4">Manager</th>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500 italic">
                                No <?php echo $isJobBased ? 'job sites' : 'locations'; ?> found.
                                Use the form above to add one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $loc): ?>
                            <tr class="hover:bg-blue-50 transition duration-150">
                                <?php if ($isJobBased): ?>
                                    <td class="px-6 py-4 font-mono text-gray-600">
                                        <?php echo htmlspecialchars($loc['job_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-primary">
                                        <?php echo htmlspecialchars($loc['job_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">
                                        <?php echo htmlspecialchars($loc['client_name'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusColors = [
                                            'Active'    => 'bg-green-100 text-green-800',
                                            'Planning'  => 'bg-blue-100 text-blue-800',
                                            'On Hold'   => 'bg-yellow-100 text-yellow-800',
                                            'Completed' => 'bg-gray-100 text-gray-600',
                                            'Cancelled' => 'bg-red-100 text-red-700',
                                        ];
                                        $sc = $statusColors[$loc['status']] ?? 'bg-gray-100 text-gray-600';
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $sc; ?>">
                                            <?php echo htmlspecialchars($loc['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-xs">
                                        <?php echo htmlspecialchars(trim((string)($loc['supervisor_name'] ?? '')) ?: 'Unassigned'); ?>
                                    </td>
                                <?php else: ?>
                                    <td class="px-6 py-4 font-bold text-primary">
                                        <?php echo htmlspecialchars($loc['store_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 font-mono text-gray-600">
                                        <?php echo htmlspecialchars($loc['store_number']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 capitalize">
                                        <?php echo htmlspecialchars($loc['location_type'] ?? 'store'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <?php echo htmlspecialchars($loc['city'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 text-xs">
                                        <?php echo htmlspecialchars(trim((string)($loc['manager_name'] ?? '')) ?: 'Unassigned'); ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <?php
                                    $staffParam = $isJobBased
                                        ? "filter_job_site={$loc['id']}"
                                        : "filter_store={$loc['id']}";
                                    $editUrl = $adminBaseRoute . '?' . http_build_query([
                                        'view' => $manageViewParam,
                                        'assignment' => $assignmentFilter,
                                        'edit_location' => (int)$loc['id']
                                    ]);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($editUrl); ?>"
                                       class="text-primary hover:text-secondary font-bold transition text-xs uppercase tracking-wide border border-blue-200 rounded-lg px-3 py-2 hover:bg-blue-50 inline-flex items-center mr-2">
                                        <i class="fas fa-pen mr-2"></i> Edit
                                    </a>
                                    <a href="<?php echo htmlspecialchars($adminBaseRoute); ?>?view=manage-users&<?php echo $staffParam; ?>"
                                       class="text-gray-500 hover:text-secondary font-bold transition text-xs uppercase tracking-wide border border-gray-200 rounded-lg px-3 py-2 hover:bg-white hover:border-secondary hover:shadow-sm inline-flex items-center">
                                        <i class="fas fa-users mr-2"></i> Staff
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
