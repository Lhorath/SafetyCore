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
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   6.0.0 (NorthPoint Beta 06)
 */

require_once __DIR__ . '/../../includes/company_context.php';

$companyId  = $_SESSION['user']['company_id'] ?? 1;
$companyCtx = get_company_context($conn, $companyId);
$isJobBased = ($companyCtx['type'] === 'job_based');
$isSystem   = $companyCtx['is_system'];

// ─── POST: Handle Add Location ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

$locations = $companyCtx['locations'];
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
                Platform administration — managing the NorthPoint 360 system company.
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

        <form action="/admin?view=manage-company" method="POST">

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
        <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
            <h3 class="font-bold text-gray-500 uppercase text-xs tracking-wider">
                <?php echo $isJobBased ? 'Active Job Sites' : 'Current Locations'; ?>
            </h3>
            <span class="text-xs bg-gray-200 text-gray-600 py-1 px-2 rounded-full font-bold">
                <?php echo count($locations); ?> Total
            </span>
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
                        <?php else: ?>
                            <th class="px-6 py-4">Location Name</th>
                            <th class="px-6 py-4">Number</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">City</th>
                        <?php endif; ?>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500 italic">
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
                                <?php endif; ?>
                                <td class="px-6 py-4 text-right">
                                    <?php
                                    $staffParam = $isJobBased
                                        ? "filter_job_site={$loc['id']}"
                                        : "filter_store={$loc['id']}";
                                    ?>
                                    <a href="/admin?view=manage-users&<?php echo $staffParam; ?>"
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
